<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/chama.php';

function integration_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Integration assertion failed: ' . $message);
    }
}

$databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!str_contains(strtolower($databaseName), 'test')) {
    throw new RuntimeException('Integration tests refuse to run unless DB_NAME contains "test".');
}

$tables = [
    'loan_payment_allocations', 'contribution_allocations', 'financial_payments', 'payment_claims',
    'loan_installments', 'loans', 'loan_applications', 'notifications', 'audit_logs',
    'contribution_obligations', 'contribution_overrides', 'contribution_rules', 'members', 'users',
];
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($tables as $table) {
    $pdo->exec("TRUNCATE TABLE {$table}");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$pdo->prepare("INSERT INTO users(phone,password_hash,role,account_status) VALUES(?,?,'admin','active')")
    ->execute(['+254700000001', password_hash('IntegrationPass123!', PASSWORD_DEFAULT)]);
$adminId = (int) $pdo->lastInsertId();
$pdo->prepare(
    "INSERT INTO members(user_id,membership_number,full_name,date_joined,first_contribution_month,membership_status,approved_by,approved_at)
     VALUES(?,?,'Integration Admin',?,?,'Active',?,NOW())"
)->execute([$adminId, 'TEST-0001', date('Y-m-d'), date('Y-m-01'), $adminId]);
$memberId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO contribution_rules(monthly_amount,due_day,effective_from,created_by) VALUES(1000.00,5,?,?)')
    ->execute([date('Y-m-01'), $adminId]);
sync_member_contributions($memberId);
$obligationCount = (int) $pdo->query('SELECT COUNT(*) FROM contribution_obligations')->fetchColumn();
integration_expect($obligationCount === 2, 'current and next-month obligations are generated');

$pdo->beginTransaction();
$paymentId = post_financial_payment([
    'member_id' => $memberId,
    'payment_type' => 'contribution',
    'loan_id' => null,
    'amount' => '1500.00',
    'paid_on' => date('Y-m-d'),
    'payment_method' => 'M-Pesa',
    'reference_number' => 'TEST-CONTRIB-001',
    'source' => 'admin_direct',
], $adminId);
$pdo->commit();
$summary = contribution_summary($memberId);
integration_expect($summary['paid'] === '1500.00', 'contribution is allocated oldest-first');
integration_expect($summary['outstanding'] === '500.00', 'partial upcoming obligation remains outstanding');

$pdo->beginTransaction();
reverse_financial_payment($paymentId, $adminId, 'Integration reversal');
$pdo->commit();
$summary = contribution_summary($memberId);
integration_expect($summary['paid'] === '0.00', 'contribution reversal restores paid balance');

$pdo->prepare(
    "INSERT INTO loans(member_id,loan_number,principal_amount,annual_interest_rate,interest_method,term_months,
      processing_fee,grace_days,late_penalty_rate,outstanding_principal,outstanding_interest,outstanding_fees,status,approved_by)
     VALUES(?,'TEST-LN-001',1200.00,12.0000,'flat',12,50.00,3,5.0000,1200.00,144.00,50.00,'approved',?)"
)->execute([$memberId, $adminId]);
$loanId = (int) $pdo->lastInsertId();
$pdo->beginTransaction();
disburse_loan($loanId, date('Y-m-d'), 'M-Pesa', 'TEST-DISBURSE-001', $adminId);
$pdo->commit();
integration_expect((int) $pdo->query("SELECT COUNT(*) FROM loan_installments WHERE loan_id={$loanId}")->fetchColumn() === 12, 'disbursement creates the full schedule');

$before = (float) $pdo->query("SELECT outstanding_principal+outstanding_interest+outstanding_fees FROM loans WHERE id={$loanId}")->fetchColumn();
$pdo->beginTransaction();
$repaymentId = post_financial_payment([
    'member_id' => $memberId,
    'payment_type' => 'loan_repayment',
    'loan_id' => $loanId,
    'amount' => '100.00',
    'paid_on' => date('Y-m-d'),
    'payment_method' => 'M-Pesa',
    'reference_number' => 'TEST-LOAN-001',
    'source' => 'admin_direct',
], $adminId);
$pdo->commit();
$after = (float) $pdo->query("SELECT outstanding_principal+outstanding_interest+outstanding_fees FROM loans WHERE id={$loanId}")->fetchColumn();
integration_expect(abs(($before - $after) - 100.00) < 0.001, 'loan repayment reduces outstanding total exactly');

$pdo->beginTransaction();
reverse_financial_payment($repaymentId, $adminId, 'Integration reversal');
$pdo->commit();
$restored = (float) $pdo->query("SELECT outstanding_principal+outstanding_interest+outstanding_fees FROM loans WHERE id={$loanId}")->fetchColumn();
integration_expect(abs($before - $restored) < 0.001, 'loan reversal restores outstanding total');

echo "database integration tests passed\n";
