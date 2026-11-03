
# Core Logic & Policy Challenge

## 1. Overview

This project implements a tenant access control system for evaluating whether a corporate tenant is allowed to execute high-resource background automation tasks.

Access decisions are based on three factors:

- Subscription tier
- Monthly usage
- Invoice payment status

The implementation uses pure native PHP without frameworks, Composer packages, external databases, or third-party dependencies.

## 2. Project Structure

```text
core-logic-policy-challenge/
├── src/
│   └── TenantAccessGuard.php
├── run_tests.php
└── README.md
```

### File Responsibilities

**TenantAccessGuard.php**
- Contains the tenant access evaluation logic.
- Validates subscription tiers, payment statuses, and monthly usage.
- Applies subscription-specific usage limits.
- Returns one of the three defined access states.

**run_tests.php**
- Contains standalone test cases.
- Validates normal scenarios, boundary conditions, and invalid inputs.
- Runs without PHPUnit or any external testing framework.

## 3. Business Rules

The system supports three subscription tiers:

- Standard
- Premium
- Enterprise

The supported payment statuses are:

- Active
- Overdue
- Suspended

### Standard Tier

- Access is blocked when the invoice status is Overdue.
- Access is blocked when monthly usage exceeds the configured monthly cap.
- Access is allowed when the invoice is Active and usage is within the cap.

### Premium and Enterprise Tiers

- Active accounts must remain within their configured monthly usage cap.
- Overdue accounts receive a 10% grace buffer beyond their configured monthly cap.
- Overdue accounts are allowed when usage is within the 110% limit.
- Access is blocked when usage exceeds the 110% limit.

### Suspended Accounts

- Suspended accounts are blocked immediately, regardless of subscription tier or monthly usage.

## 4. Access Decision States

The system returns one of three explicit states:

| State | Description |
|---|---|
| ALLOWED | Tenant can execute the automation task. |
| BLOCKED_STATUS | Access is blocked because of the payment status. |
| BLOCKED_USAGE | Access is blocked because the applicable usage limit is exceeded. |

### Decision Priority

The evaluation follows this order:

1. Validate the input values.
2. Check whether the account is Suspended.
3. Block Standard accounts with Overdue invoices.
4. Apply the 10% grace buffer to Overdue Premium and Enterprise accounts.
5. Check the normal monthly usage cap for all remaining cases.

## 5. Configuration

Monthly usage caps are passed through the constructor rather than hardcoded into the access evaluation logic.

Example:

```php
$guard = new TenantAccessGuard([
    'STANDARD'   => 1000,
    'PREMIUM'    => 2000,
    'ENTERPRISE' => 5000,
]);
```

The above values are illustrative configuration values only. The assessment does not specify numerical monthly caps.

This approach allows the business to change subscription limits without modifying the core evaluation logic.

## 6. Usage Example

```php
$result = $guard->evaluate(
    'PREMIUM',
    2100,
    'OVERDUE'
);

echo $result;

// Output: ALLOWED
```

In this example:

- Subscription tier: Premium
- Monthly cap: 2000
- Monthly usage: 2100
- Payment status: Overdue
- Grace limit: 2200

Since usage is within the 10% grace buffer, access is allowed.

## 7. Test Coverage

The standalone test suite contains 14 test cases.

| # | Test Scenario | Expected Result |
|---|---|---|
| 1 | Standard account within cap | ALLOWED |
| 2 | Standard account overdue | BLOCKED_STATUS |
| 3 | Standard account exceeds cap | BLOCKED_USAGE |
| 4 | Premium overdue within normal cap | ALLOWED |
| 5 | Premium overdue within grace buffer | ALLOWED |
| 6 | Premium overdue exactly at grace limit | ALLOWED |
| 7 | Premium overdue exceeds grace limit | BLOCKED_USAGE |
| 8 | Premium active exceeds normal cap | BLOCKED_USAGE |
| 9 | Enterprise suspended account | BLOCKED_STATUS |
| 10 | Enterprise overdue within grace buffer | ALLOWED |
| 11 | Enterprise overdue exceeds grace buffer | BLOCKED_USAGE |
| 12 | Standard account exactly at monthly cap | ALLOWED |
| 13 | Invalid subscription tier | InvalidArgumentException |
| 14 | Invalid payment status | InvalidArgumentException |

## 8. Running the Tests

Requirements:
- PHP 8 or later
- No additional dependencies

Run the following command from the project root:

```bash
php run_tests.php
```

Expected successful output:

```text
Total Passed: 14
Total Failed: 0

ALL TESTS COMPLETED SUCCESSFULLY
```

## 9. Design Considerations

- **Separation of concerns:** Access evaluation is isolated in the `TenantAccessGuard` class.
- **Configurable limits:** Monthly caps can be changed without modifying business logic.
- **Input validation:** Invalid subscription tiers, payment statuses, and usage values are rejected.
- **Boundary handling:** Usage exactly at the applicable cap is allowed.
- **Maintainability:** Subscription-specific rules are handled in a single dedicated class.
- **Dependency-free:** The solution uses native PHP only.
