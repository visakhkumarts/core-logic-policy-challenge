<?php

declare(strict_types=1);

class TenantAccessGuard
{
    public const ALLOWED = 'ALLOWED';
    public const BLOCKED_STATUS = 'BLOCKED_STATUS';
    public const BLOCKED_USAGE = 'BLOCKED_USAGE';

    private array $monthlyCaps;

    private const GRACE_BUFFER_PERCENTAGE = 10;

    public function __construct(array $monthlyCaps)
    {
        $requiredTiers = [
            'STANDARD',
            'PREMIUM',
            'ENTERPRISE'
        ];

        $normalizedCaps = [];

        foreach ($monthlyCaps as $tier => $cap) {
            $normalizedTier = strtoupper(trim((string) $tier));

            if (!in_array($normalizedTier, $requiredTiers, true)) {
                throw new InvalidArgumentException(
                    "Invalid subscription tier: {$tier}"
                );
            }

            if (
                !is_numeric($cap) ||
                !is_finite((float) $cap) ||
                (float) $cap < 0
            ) {
                throw new InvalidArgumentException(
                    "Invalid monthly cap for {$normalizedTier}"
                );
            }

            $normalizedCaps[$normalizedTier] = (float) $cap;
        }

        foreach ($requiredTiers as $tier) {
            if (!array_key_exists($tier, $normalizedCaps)) {
                throw new InvalidArgumentException(
                    "Monthly cap is missing for {$tier}"
                );
            }
        }

        $this->monthlyCaps = $normalizedCaps;
    }

    public function evaluate(
        string $tier,
        float $monthlyUsage,
        string $paymentStatus
    ): string {
        $tier = strtoupper(trim($tier));
        $paymentStatus = strtoupper(trim($paymentStatus));

        // Validate subscription tier.
        if (!array_key_exists($tier, $this->monthlyCaps)) {
            throw new InvalidArgumentException(
                "Invalid subscription tier: {$tier}"
            );
        }

        // Validate payment status.
        $validStatuses = [
            'ACTIVE',
            'OVERDUE',
            'SUSPENDED'
        ];

        if (!in_array($paymentStatus, $validStatuses, true)) {
            throw new InvalidArgumentException(
                "Invalid payment status: {$paymentStatus}"
            );
        }

        // Validate monthly usage.
        if (!is_finite($monthlyUsage) || $monthlyUsage < 0) {
            throw new InvalidArgumentException(
                'Monthly usage must be a non-negative finite number.'
            );
        }

        // Suspended accounts are always blocked.
        if ($paymentStatus === 'SUSPENDED') {
            return self::BLOCKED_STATUS;
        }

        $monthlyCap = $this->monthlyCaps[$tier];

        // Standard accounts cannot execute tasks when overdue.
        if (
            $tier === 'STANDARD' &&
            $paymentStatus === 'OVERDUE'
        ) {
            return self::BLOCKED_STATUS;
        }

        // Premium and Enterprise receive a 10% grace buffer
        // only when their payment status is overdue.
        if (
            in_array($tier, ['PREMIUM', 'ENTERPRISE'], true) &&
            $paymentStatus === 'OVERDUE'
        ) {
            $allowedUsage = $monthlyCap * (
                1 + self::GRACE_BUFFER_PERCENTAGE / 100
            );

            if ($monthlyUsage > $allowedUsage) {
                return self::BLOCKED_USAGE;
            }

            return self::ALLOWED;
        }

        // All remaining accounts must stay within their
        // normal monthly usage cap.
        if ($monthlyUsage > $monthlyCap) {
            return self::BLOCKED_USAGE;
        }

        return self::ALLOWED;
    }
}
