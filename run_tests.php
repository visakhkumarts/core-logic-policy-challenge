<?php

declare(strict_types=1);

require_once __DIR__ . '/src/TenantAccessGuard.php';

// Example monthly caps for testing.
// These values are configurable and are not specified in the assessment PDF.
$guard = new TenantAccessGuard([
    'STANDARD'   => 1000,
    'PREMIUM'    => 2000,
    'ENTERPRISE' => 5000,
]);

$passed = 0;
$failed = 0;

function runTest(
    string $testName,
    string $expected,
    string $actual
): void {
    global $passed, $failed;

    if ($expected === $actual) {
        echo "[PASS] {$testName}\n";
        $passed++;
    } else {
        echo "[FAIL] {$testName}\n";
        echo "       Expected: {$expected}\n";
        echo "       Actual:   {$actual}\n";
        $failed++;
    }
}

// Test 1: Standard account within cap.
runTest(
    'Standard account within cap',
    TenantAccessGuard::ALLOWED,
    $guard->evaluate('STANDARD', 900, 'ACTIVE')
);

// Test 2: Standard account overdue.
runTest(
    'Standard account overdue',
    TenantAccessGuard::BLOCKED_STATUS,
    $guard->evaluate('STANDARD', 500, 'OVERDUE')
);

// Test 3: Standard account exceeds cap.
runTest(
    'Standard account exceeds monthly cap',
    TenantAccessGuard::BLOCKED_USAGE,
    $guard->evaluate('STANDARD', 1100, 'ACTIVE')
);

// Test 4: Premium overdue within normal cap.
runTest(
    'Premium overdue within normal cap',
    TenantAccessGuard::ALLOWED,
    $guard->evaluate('PREMIUM', 1800, 'OVERDUE')
);

// Test 5: Premium overdue within 10% grace buffer.
runTest(
    'Premium overdue within grace buffer',
    TenantAccessGuard::ALLOWED,
    $guard->evaluate('PREMIUM', 2100, 'OVERDUE')
);

// Test 6: Premium overdue exactly at 110% cap.
runTest(
    'Premium overdue exactly at grace limit',
    TenantAccessGuard::ALLOWED,
    $guard->evaluate('PREMIUM', 2200, 'OVERDUE')
);

// Test 7: Premium overdue exceeds 110% cap.
runTest(
    'Premium overdue exceeds grace limit',
    TenantAccessGuard::BLOCKED_USAGE,
    $guard->evaluate('PREMIUM', 2200.01, 'OVERDUE')
);

// Test 8: Premium active exceeds normal cap.
runTest(
    'Premium active exceeds normal cap',
    TenantAccessGuard::BLOCKED_USAGE,
    $guard->evaluate('PREMIUM', 2100, 'ACTIVE')
);

// Test 9: Enterprise suspended.
runTest(
    'Enterprise suspended account',
    TenantAccessGuard::BLOCKED_STATUS,
    $guard->evaluate('ENTERPRISE', 1000, 'SUSPENDED')
);

// Test 10: Enterprise overdue within grace buffer.
runTest(
    'Enterprise overdue within grace buffer',
    TenantAccessGuard::ALLOWED,
    $guard->evaluate('ENTERPRISE', 5400, 'OVERDUE')
);

// Test 11: Enterprise overdue exceeds grace buffer.
runTest(
    'Enterprise overdue exceeds grace buffer',
    TenantAccessGuard::BLOCKED_USAGE,
    $guard->evaluate('ENTERPRISE', 5500.01, 'OVERDUE')
);

// Test 12: Standard account at exact monthly cap.
runTest(
    'Standard account exactly at monthly cap',
    TenantAccessGuard::ALLOWED,
    $guard->evaluate('STANDARD', 1000, 'ACTIVE')
);

// Test 13: Invalid subscription tier.
try {
    $guard->evaluate('BASIC', 500, 'ACTIVE');
    runTest('Invalid subscription tier', 'EXCEPTION', 'NO EXCEPTION');
} catch (InvalidArgumentException $exception) {
    runTest('Invalid subscription tier', 'EXCEPTION', 'EXCEPTION');
}

// Test 14: Invalid payment status.
try {
    $guard->evaluate('STANDARD', 500, 'PENDING');
    runTest('Invalid payment status', 'EXCEPTION', 'NO EXCEPTION');
} catch (InvalidArgumentException $exception) {
    runTest('Invalid payment status', 'EXCEPTION', 'EXCEPTION');
}

// Test summary.
echo "\n-----------------------------\n";
echo "Total Passed: {$passed}\n";
echo "Total Failed: {$failed}\n";
echo "-----------------------------\n";

if ($failed > 0) {
    exit(1);
}

echo "ALL TESTS COMPLETED SUCCESSFULLY\n";
