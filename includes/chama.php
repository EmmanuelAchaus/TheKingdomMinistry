<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/finance.php';

function month_start(string $date): string
{
    return (new DateTimeImmutable($date))->modify('first day of this month')->format('Y-m-01');
}

function due_date_for_month(string $month, int $dueDay): string
{
    $first = new DateTimeImmutable(month_start($month));
    $lastDay = (int) $first->format('t');
    return $first->setDate((int) $first->format('Y'), (int) $first->format('m'), min($dueDay, $lastDay))->format('Y-m-d');
}

function contribution_terms_for_month(int $memberId, string $month): ?array
{
    global $pdo;
    $override = $pdo->prepare(
        'SELECT monthly_amount, due_day FROM contribution_overrides
         WHERE member_id = ? AND effective_from <= ?
           AND (effective_to IS NULL OR effective_to >= ?)
         ORDER BY effective_from DESC LIMIT 1'
    );
    $override->execute([$memberId, $month, $month]);
    if ($row = $override->fetch()) {
        return $row;
    }

    $rule = $pdo->prepare(
        'SELECT monthly_amount, due_day FROM contribution_rules
         WHERE effective_from <= ? ORDER BY effective_from DESC LIMIT 1'
    );
    $rule->execute([$month]);
    return $rule->fetch() ?: null;
}

function refresh_contribution_obligation(int $obligationId): void
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT expected_amount, paid_amount, due_date FROM contribution_obligations WHERE id = ?');
    $stmt->execute([$obligationId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }
    $status = (float) $row['paid_amount'] >= (float) $row['expected_amount']
        ? 'completed'
        : ($row['due_date'] < date('Y-m-d') ? 'overdue' : 'upcoming');
    $update = $pdo->prepare('UPDATE contribution_obligations SET status = ? WHERE id = ?');
    $update->execute([$status, $obligationId]);
}

function apply_contribution_credits(int $memberId): void
{
    global $pdo;
    $credits = $pdo->prepare(
        "SELECT ca.id, ca.payment_id, ca.amount
         FROM contribution_allocations ca
         JOIN financial_payments p ON p.id = ca.payment_id
         WHERE p.member_id = ? AND p.status = 'posted'
           AND ca.allocation_type = 'credit' AND ca.amount > 0
         ORDER BY p.paid_on, ca.id"
    );
    $credits->execute([$memberId]);
    $obligations = $pdo->prepare(
        "SELECT id, expected_amount, paid_amount
         FROM contribution_obligations
         WHERE member_id = ? AND paid_amount < expected_amount
         ORDER BY period_month, id FOR UPDATE"
    );
    $obligations->execute([$memberId]);
    $open = $obligations->fetchAll();

    foreach ($credits->fetchAll() as $credit) {
        $remaining = money_to_cents($credit['amount']);
        foreach ($open as &$obligation) {
            $needed = money_to_cents($obligation['expected_amount']) - money_to_cents($obligation['paid_amount']);
            if ($needed <= 0 || $remaining <= 0) {
                continue;
            }
            $applied = min($needed, $remaining);
            $insert = $pdo->prepare(
                "INSERT INTO contribution_allocations (payment_id, obligation_id, amount, allocation_type)
                 VALUES (?, ?, ?, 'obligation')"
            );
            $insert->execute([$credit['payment_id'], $obligation['id'], cents_to_decimal($applied)]);
            $update = $pdo->prepare('UPDATE contribution_obligations SET paid_amount = paid_amount + ? WHERE id = ?');
            $update->execute([cents_to_decimal($applied), $obligation['id']]);
            $obligation['paid_amount'] = cents_to_decimal(money_to_cents($obligation['paid_amount']) + $applied);
            $remaining -= $applied;
            refresh_contribution_obligation((int) $obligation['id']);
        }
        unset($obligation);
        $updateCredit = $pdo->prepare('UPDATE contribution_allocations SET amount = ? WHERE id = ?');
        $updateCredit->execute([cents_to_decimal($remaining), $credit['id']]);
    }
}

function sync_member_contributions(int $memberId, ?string $throughMonth = null): void
{
    global $pdo;
    $member = $pdo->prepare('SELECT first_contribution_month, membership_status FROM members WHERE id = ?');
    $member->execute([$memberId]);
    $row = $member->fetch();
    if (!$row || !$row['first_contribution_month'] || !in_array($row['membership_status'], ['Active', 'Suspended'], true)) {
        return;
    }

    $start = new DateTimeImmutable(month_start($row['first_contribution_month']));
    $end = new DateTimeImmutable(month_start($throughMonth ?: date('Y-m-d', strtotime('first day of next month'))));
    $insert = $pdo->prepare(
        "INSERT IGNORE INTO contribution_obligations
         (member_id, period_month, due_date, expected_amount, status)
         VALUES (?, ?, ?, ?, 'upcoming')"
    );

    for ($month = $start; $month <= $end; $month = $month->modify('+1 month')) {
        $period = $month->format('Y-m-01');
        $terms = contribution_terms_for_month($memberId, $period);
        if (!$terms || (float) $terms['monthly_amount'] <= 0) {
            continue;
        }
        $insert->execute([
            $memberId,
            $period,
            due_date_for_month($period, (int) $terms['due_day']),
            $terms['monthly_amount'],
        ]);
    }

    $ids = $pdo->prepare('SELECT id FROM contribution_obligations WHERE member_id = ?');
    $ids->execute([$memberId]);
    foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $id) {
        refresh_contribution_obligation((int) $id);
    }
    apply_contribution_credits($memberId);
}

function reprice_unpaid_contribution_obligations(int $memberId, string $fromMonth): void
{
    global $pdo;
    $stmt = $pdo->prepare(
        'SELECT id, period_month FROM contribution_obligations
         WHERE member_id = ? AND period_month >= ? AND paid_amount = 0 FOR UPDATE'
    );
    $stmt->execute([$memberId, month_start($fromMonth)]);
    $update = $pdo->prepare('UPDATE contribution_obligations SET expected_amount=?, due_date=? WHERE id=?');
    foreach ($stmt->fetchAll() as $obligation) {
        $terms = contribution_terms_for_month($memberId, $obligation['period_month']);
        if (!$terms) {
            continue;
        }
        $update->execute([
            $terms['monthly_amount'],
            due_date_for_month($obligation['period_month'], (int) $terms['due_day']),
            $obligation['id'],
        ]);
        refresh_contribution_obligation((int) $obligation['id']);
    }
}

function contribution_summary(int $memberId): array
{
    global $pdo;
    sync_member_contributions($memberId);
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(expected_amount),0) expected,
                COALESCE(SUM(paid_amount),0) paid,
                COALESCE(SUM(expected_amount - paid_amount),0) outstanding,
                SUM(status = \'overdue\') overdue_count
         FROM contribution_obligations WHERE member_id = ?'
    );
    $stmt->execute([$memberId]);
    $summary = $stmt->fetch();
    $credit = $pdo->prepare(
        "SELECT COALESCE(SUM(ca.amount),0)
         FROM contribution_allocations ca JOIN financial_payments p ON p.id = ca.payment_id
         WHERE p.member_id = ? AND p.status = 'posted' AND ca.allocation_type = 'credit'"
    );
    $credit->execute([$memberId]);
    $summary['credit'] = $credit->fetchColumn();
    return $summary;
}

function get_loan_product(): array
{
    global $pdo;
    return $pdo->query('SELECT * FROM loan_product WHERE id = 1')->fetch() ?: [];
}

function loan_estimate(array $product, int $principalCents, int $termMonths): array
{
    $basisPoints = (int) round((float) $product['annual_interest_rate'] * 100);
    return build_loan_schedule(
        $principalCents,
        $basisPoints,
        $termMonths,
        $product['interest_method'],
        money_to_cents($product['processing_fee'])
    );
}

function loan_eligibility(int $memberId, array $product): array
{
    global $pdo;
    $problems = [];
    $member = $pdo->prepare('SELECT date_joined, membership_status FROM members WHERE id = ?');
    $member->execute([$memberId]);
    $row = $member->fetch();
    if (!$row || $row['membership_status'] !== 'Active') {
        $problems[] = 'Membership must be active.';
        return $problems;
    }
    $joined = new DateTimeImmutable($row['date_joined'] ?: date('Y-m-d'));
    $months = (((int) date('Y') - (int) $joined->format('Y')) * 12) + ((int) date('n') - (int) $joined->format('n'));
    if ($months < (int) $product['minimum_active_months']) {
        $problems[] = 'Minimum active membership period has not been reached.';
    }
    $active = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE member_id = ? AND status IN ('approved','active','overdue','defaulted')");
    $active->execute([$memberId]);
    if ((int) $active->fetchColumn() >= (int) $product['maximum_active_loans']) {
        $problems[] = 'Maximum active loan limit has been reached.';
    }
    sync_member_contributions($memberId);
    $completion = $pdo->prepare(
        'SELECT COALESCE(SUM(expected_amount),0) expected, COALESCE(SUM(paid_amount),0) paid
         FROM contribution_obligations WHERE member_id=? AND due_date <= CURDATE()'
    );
    $completion->execute([$memberId]);
    $summary = $completion->fetch();
    $rate = (float) $summary['expected'] > 0 ? ((float) $summary['paid'] / (float) $summary['expected']) * 100 : 100;
    if ($rate + 0.001 < (float) $product['minimum_contribution_rate']) {
        $problems[] = 'Contribution completion rate is below the loan requirement.';
    }
    return $problems;
}

function refresh_loan(int $loanId): void
{
    global $pdo;
    $loanStmt = $pdo->prepare('SELECT * FROM loans WHERE id = ? FOR UPDATE');
    $loanStmt->execute([$loanId]);
    $loan = $loanStmt->fetch();
    if (!$loan || !in_array($loan['status'], ['active', 'overdue', 'defaulted', 'fully_repaid'], true)) {
        return;
    }

    $graceDays = max(0, (int) $loan['grace_days']);
    $overdue = $pdo->prepare(
        "SELECT * FROM loan_installments
         WHERE loan_id = ? AND DATE_ADD(due_date, INTERVAL {$graceDays} DAY) < CURDATE()
           AND status <> 'completed' FOR UPDATE"
    );
    $overdue->execute([$loanId]);
    foreach ($overdue->fetchAll() as $installment) {
        if (!$installment['penalty_assessed_at'] && (float) $loan['late_penalty_rate'] > 0) {
            $unpaid = max(0, money_to_cents($installment['principal_due']) - money_to_cents($installment['principal_paid']))
                + max(0, money_to_cents($installment['interest_due']) - money_to_cents($installment['interest_paid']));
            $penalty = rounded_division($unpaid * (int) round((float) $loan['late_penalty_rate'] * 100), 10000);
            $assess = $pdo->prepare('UPDATE loan_installments SET penalty_due = ?, penalty_assessed_at = NOW() WHERE id = ?');
            $assess->execute([cents_to_decimal($penalty), $installment['id']]);
        }
        $pdo->prepare("UPDATE loan_installments SET status = 'overdue' WHERE id = ?")->execute([$installment['id']]);
    }

    $pdo->prepare(
        "UPDATE loan_installments
         SET status = CASE
            WHEN principal_paid >= principal_due AND interest_paid >= interest_due
             AND fee_paid >= fee_due AND penalty_paid >= penalty_due THEN 'completed'
            WHEN DATE_ADD(due_date, INTERVAL {$graceDays} DAY) < CURDATE() THEN 'overdue'
            ELSE 'upcoming' END
         WHERE loan_id = ?"
    )->execute([$loanId]);

    $totals = $pdo->prepare(
        'SELECT COALESCE(SUM(principal_due-principal_paid),0) principal,
                COALESCE(SUM(interest_due-interest_paid),0) interest,
                COALESCE(SUM((fee_due-fee_paid)+(penalty_due-penalty_paid)),0) fees,
                SUM(status = \'overdue\') overdue_count
         FROM loan_installments WHERE loan_id = ?'
    );
    $totals->execute([$loanId]);
    $remaining = $totals->fetch();
    $status = ((float) $remaining['principal'] + (float) $remaining['interest'] + (float) $remaining['fees']) <= 0.001
        ? 'fully_repaid'
        : ((int) $remaining['overdue_count'] > 0 ? 'overdue' : 'active');
    $update = $pdo->prepare(
        'UPDATE loans SET outstanding_principal=?, outstanding_interest=?, outstanding_fees=?, status=? WHERE id=?'
    );
    $update->execute([$remaining['principal'], $remaining['interest'], $remaining['fees'], $status, $loanId]);
}

function disburse_loan(int $loanId, string $date, string $method, string $reference, int $actorId): void
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ? AND status = 'approved' FOR UPDATE");
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();
    if (!$loan) {
        throw new RuntimeException('Only approved, undisbursed loans can be disbursed.');
    }
    $schedule = build_loan_schedule(
        money_to_cents($loan['principal_amount']),
        (int) round((float) $loan['annual_interest_rate'] * 100),
        (int) $loan['term_months'],
        $loan['interest_method'],
        money_to_cents($loan['processing_fee'])
    );
    $disbursed = new DateTimeImmutable($date);
    $insert = $pdo->prepare(
        'INSERT INTO loan_installments
         (loan_id, installment_number, due_date, principal_due, interest_due, fee_due)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($schedule['installments'] as $row) {
        $target = $disbursed->modify('+' . $row['number'] . ' month');
        $due = due_date_for_month($target->format('Y-m-01'), (int) $disbursed->format('j'));
        $insert->execute([
            $loanId,
            $row['number'],
            $due,
            cents_to_decimal($row['principal_cents']),
            cents_to_decimal($row['interest_cents']),
            cents_to_decimal($row['fee_cents']),
        ]);
    }
    $maturity = due_date_for_month($disbursed->modify('+' . $loan['term_months'] . ' month')->format('Y-m-01'), (int) $disbursed->format('j'));
    $update = $pdo->prepare(
        "UPDATE loans SET status='active', disbursed_on=?, disbursement_method=?, disbursement_reference=?,
         maturity_date=?, outstanding_interest=?, outstanding_fees=?, disbursed_by=? WHERE id=?"
    );
    $update->execute([
        $date,
        $method,
        $reference,
        $maturity,
        cents_to_decimal($schedule['interest_cents']),
        cents_to_decimal($schedule['fees_cents']),
        $actorId,
        $loanId,
    ]);
}

function post_financial_payment(array $input, int $actorId): int
{
    global $pdo;
    $amountCents = money_to_cents($input['amount']);
    if ($amountCents <= 0) {
        throw new InvalidArgumentException('Payment amount must be greater than zero.');
    }
    $type = $input['payment_type'];
    if (!in_array($type, ['contribution', 'loan_repayment'], true)) {
        throw new InvalidArgumentException('Invalid payment type.');
    }
    $memberId = (int) $input['member_id'];
    $loanId = !empty($input['loan_id']) ? (int) $input['loan_id'] : null;
    $reference = strtoupper(trim((string) ($input['reference_number'] ?? '')));
    $method = (string) ($input['payment_method'] ?? '');
    if ($memberId < 1 || $reference === '' || !in_array($method, ['Cash', 'M-Pesa', 'Bank Transfer', 'Other', 'Opening Balance'], true)) {
        throw new InvalidArgumentException('Select a member, payment method, and transaction reference.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO financial_payments
         (member_id, payment_type, loan_id, claim_id, amount, paid_on, payment_method,
          reference_number, source, recorded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        $memberId,
        $type,
        $loanId,
        $input['claim_id'] ?? null,
        cents_to_decimal($amountCents),
        $input['paid_on'],
        $method,
        $reference,
        $input['source'],
        $actorId,
    ]);
    $paymentId = (int) $pdo->lastInsertId();

    if ($type === 'contribution') {
        sync_member_contributions($memberId);
        $open = $pdo->prepare(
            'SELECT * FROM contribution_obligations
             WHERE member_id = ? AND paid_amount < expected_amount
             ORDER BY period_month, id FOR UPDATE'
        );
        $open->execute([$memberId]);
        $remaining = $amountCents;
        foreach ($open->fetchAll() as $obligation) {
            if ($remaining <= 0) {
                break;
            }
            $needed = money_to_cents($obligation['expected_amount']) - money_to_cents($obligation['paid_amount']);
            $applied = min($needed, $remaining);
            $pdo->prepare(
                "INSERT INTO contribution_allocations (payment_id, obligation_id, amount, allocation_type)
                 VALUES (?, ?, ?, 'obligation')"
            )->execute([$paymentId, $obligation['id'], cents_to_decimal($applied)]);
            $pdo->prepare('UPDATE contribution_obligations SET paid_amount=paid_amount+? WHERE id=?')
                ->execute([cents_to_decimal($applied), $obligation['id']]);
            refresh_contribution_obligation((int) $obligation['id']);
            $remaining -= $applied;
        }
        if ($remaining > 0) {
            $pdo->prepare(
                "INSERT INTO contribution_allocations (payment_id, obligation_id, amount, allocation_type)
                 VALUES (?, NULL, ?, 'credit')"
            )->execute([$paymentId, cents_to_decimal($remaining)]);
        }
    } else {
        if (!$loanId) {
            throw new InvalidArgumentException('Select the loan being repaid.');
        }
        $ownership = $pdo->prepare("SELECT id FROM loans WHERE id=? AND member_id=? AND status IN ('active','overdue','defaulted')");
        $ownership->execute([$loanId, $memberId]);
        if (!$ownership->fetchColumn()) {
            throw new RuntimeException('The selected loan cannot receive repayments.');
        }
        refresh_loan($loanId);
        $rows = $pdo->prepare("SELECT * FROM loan_installments WHERE loan_id=? AND status <> 'completed' ORDER BY due_date, installment_number FOR UPDATE");
        $rows->execute([$loanId]);
        $remaining = $amountCents;
        foreach ($rows->fetchAll() as $installment) {
            foreach (['penalty', 'interest', 'principal', 'fee'] as $component) {
                if ($remaining <= 0) {
                    break 2;
                }
                $dueField = $component . '_due';
                $paidField = $component . '_paid';
                $needed = max(0, money_to_cents($installment[$dueField]) - money_to_cents($installment[$paidField]));
                if ($needed === 0) {
                    continue;
                }
                $applied = min($needed, $remaining);
                $pdo->prepare(
                    'INSERT INTO loan_payment_allocations (payment_id, installment_id, component, amount) VALUES (?, ?, ?, ?)'
                )->execute([$paymentId, $installment['id'], $component, cents_to_decimal($applied)]);
                $pdo->prepare("UPDATE loan_installments SET {$paidField}={$paidField}+? WHERE id=?")
                    ->execute([cents_to_decimal($applied), $installment['id']]);
                $remaining -= $applied;
            }
        }
        if ($remaining > 0) {
            throw new RuntimeException('Payment exceeds the current loan payoff amount.');
        }
        refresh_loan($loanId);
    }

    return $paymentId;
}

function reverse_financial_payment(int $paymentId, int $actorId, string $reason): void
{
    global $pdo;
    if (trim($reason) === '') {
        throw new InvalidArgumentException('A reversal reason is required.');
    }
    $stmt = $pdo->prepare("SELECT * FROM financial_payments WHERE id=? AND status='posted' FOR UPDATE");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
    if (!$payment) {
        throw new RuntimeException('Payment is missing or has already been reversed.');
    }

    if ($payment['payment_type'] === 'contribution') {
        $allocations = $pdo->prepare("SELECT * FROM contribution_allocations WHERE payment_id=? AND allocation_type='obligation'");
        $allocations->execute([$paymentId]);
        foreach ($allocations->fetchAll() as $allocation) {
            $pdo->prepare('UPDATE contribution_obligations SET paid_amount=GREATEST(0,paid_amount-?) WHERE id=?')
                ->execute([$allocation['amount'], $allocation['obligation_id']]);
            refresh_contribution_obligation((int) $allocation['obligation_id']);
        }
    } else {
        $allocations = $pdo->prepare('SELECT * FROM loan_payment_allocations WHERE payment_id=?');
        $allocations->execute([$paymentId]);
        foreach ($allocations->fetchAll() as $allocation) {
            $field = $allocation['component'] . '_paid';
            $pdo->prepare("UPDATE loan_installments SET {$field}=GREATEST(0,{$field}-?) WHERE id=?")
                ->execute([$allocation['amount'], $allocation['installment_id']]);
        }
        refresh_loan((int) $payment['loan_id']);
    }
    $pdo->prepare(
        "UPDATE financial_payments SET status='reversed', reversed_by=?, reversal_reason=?, reversed_at=NOW() WHERE id=?"
    )->execute([$actorId, trim($reason), $paymentId]);
}
