<?php

declare(strict_types=1);

// Kingdom Sons SHG rules. Loans carry a flat one-off 10% charge on the
// principal (not monthly, not annual, not compounded) and fall due as a single
// balloon within six months. Monthly savings have a 300 minimum.
const SHG_LOAN_INTEREST_BP = 1000;          // flat 10% of principal, applied once
const SHG_LOAN_TERM_MONTHS = 6;             // single repayment deadline, in months
const SHG_LOAN_MAX_AMOUNT_CENTS = 2000000;  // KES 20,000.00 ceiling
const SHG_CONTRIB_MIN_CENTS = 30000;        // KES 300.00 monthly minimum

function divide_cents(int $amount, int $parts): array
{
    if ($parts < 1) {
        throw new InvalidArgumentException('Repayment term must be at least one month.');
    }
    $base = intdiv($amount, $parts);
    $remainder = $amount - ($base * $parts);
    $values = array_fill(0, $parts, $base);
    for ($i = 0; $i < $remainder; $i++) {
        $values[$i]++;
    }
    return $values;
}

function rounded_division(int $numerator, int $denominator): int
{
    if ($denominator < 1) {
        throw new InvalidArgumentException('Invalid calculation denominator.');
    }
    return intdiv($numerator + intdiv($denominator, 2), $denominator);
}

/**
 * Build a deterministic, cent-accurate monthly schedule.
 * The reducing method uses equal principal with interest on the opening balance.
 */
function build_loan_schedule(
    int $principalCents,
    int $annualRateBasisPoints,
    int $termMonths,
    string $method,
    int $processingFeeCents = 0
): array {
    if ($principalCents <= 0 || $termMonths < 1 || $annualRateBasisPoints < 0 || $processingFeeCents < 0) {
        throw new InvalidArgumentException('Invalid loan terms.');
    }
    if (!in_array($method, ['flat', 'reducing', 'fixed'], true)) {
        throw new InvalidArgumentException('Interest method must be flat, reducing, or fixed.');
    }

    $principalParts = divide_cents($principalCents, $termMonths);
    $interestParts = [];

    if ($method === 'fixed') {
        // One-off charge: interest = principal × rate, independent of the term.
        $totalInterest = rounded_division($principalCents * $annualRateBasisPoints, 10000);
        $interestParts = divide_cents($totalInterest, $termMonths);
    } elseif ($method === 'flat') {
        $totalInterest = rounded_division(
            $principalCents * $annualRateBasisPoints * $termMonths,
            10000 * 12
        );
        $interestParts = divide_cents($totalInterest, $termMonths);
    } else {
        $outstanding = $principalCents;
        foreach ($principalParts as $principalPart) {
            $interestParts[] = rounded_division($outstanding * $annualRateBasisPoints, 10000 * 12);
            $outstanding -= $principalPart;
        }
    }

    $rows = [];
    $totalInterest = 0;
    foreach ($principalParts as $index => $principalPart) {
        $interest = $interestParts[$index];
        $fee = $index === 0 ? $processingFeeCents : 0;
        $rows[] = [
            'number' => $index + 1,
            'principal_cents' => $principalPart,
            'interest_cents' => $interest,
            'fee_cents' => $fee,
            'total_cents' => $principalPart + $interest + $fee,
        ];
        $totalInterest += $interest;
    }

    return [
        'installments' => $rows,
        'principal_cents' => $principalCents,
        'interest_cents' => $totalInterest,
        'fees_cents' => $processingFeeCents,
        'total_cents' => $principalCents + $totalInterest + $processingFeeCents,
    ];
}
