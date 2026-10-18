<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/finance.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$flat = build_loan_schedule(120000, 1200, 12, 'flat', 5000);
expect($flat['principal_cents'] === 120000, 'flat schedule preserves principal');
expect($flat['interest_cents'] === 14400, 'flat interest is principal × annual rate × term');
expect($flat['fees_cents'] === 5000, 'processing fee is included exactly once');
expect($flat['total_cents'] === 139400, 'flat total is exact');
expect(array_sum(array_column($flat['installments'], 'principal_cents')) === 120000, 'flat principal installments reconcile');

$reducing = build_loan_schedule(120000, 1200, 12, 'reducing');
expect($reducing['interest_cents'] === 7800, 'reducing interest uses each opening balance');
expect($reducing['total_cents'] === 127800, 'reducing total is exact');
expect(array_sum(array_column($reducing['installments'], 'principal_cents')) === 120000, 'reducing principal installments reconcile');

$odd = build_loan_schedule(10001, 0, 3, 'flat');
expect(array_sum(array_column($odd['installments'], 'principal_cents')) === 10001, 'cent remainder is preserved');
expect(count($odd['installments']) === 3, 'requested installment count is preserved');

$failed = false;
try {
    build_loan_schedule(0, 1200, 12, 'flat');
} catch (InvalidArgumentException) {
    $failed = true;
}
expect($failed, 'invalid principal is rejected');

echo "finance tests passed\n";
