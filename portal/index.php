<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/chama.php';
$user = require_user();
$memberId = (int) $user['member_id'];
$page = (string) ($_GET['page'] ?? 'dashboard');
$allowedPages = ['dashboard', 'contributions', 'payments', 'loans', 'notifications', 'profile', 'security'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}
if ((int) $user['must_change_password'] === 1 && $page !== 'security') {
    flash('warning', 'Change your temporary password before continuing.');
    redirect('portal/index.php?page=security');
}
$titles = [
    'dashboard' => 'Member dashboard', 'contributions' => 'My contributions', 'payments' => 'Payment claims',
    'loans' => 'My loans', 'notifications' => 'Notifications', 'profile' => 'My profile', 'security' => 'Security',
];
$pageTitle = $titles[$page];

sync_member_contributions($memberId);
$loanIds = $pdo->prepare("SELECT id FROM loans WHERE member_id=? AND status IN ('active','overdue','defaulted','fully_repaid')");
$loanIds->execute([$memberId]);
foreach ($loanIds->fetchAll(PDO::FETCH_COLUMN) as $loanId) {
    if (!$pdo->inTransaction()) { $pdo->beginTransaction(); }
    refresh_loan((int) $loanId);
    $pdo->commit();
}

require __DIR__ . '/_header.php';

if ($page === 'dashboard'):
    $contributions = contribution_summary($memberId);
    $loanSummary = $pdo->prepare(
        "SELECT COALESCE(SUM(outstanding_principal+outstanding_interest+outstanding_fees),0) balance,
                SUM(status IN ('approved','active','overdue','defaulted')) active_count
         FROM loans WHERE member_id=?"
    );
    $loanSummary->execute([$memberId]);
    $loanSummary = $loanSummary->fetch();
    $next = $pdo->prepare(
        "SELECT li.due_date,
                (li.principal_due+li.interest_due+li.fee_due+li.penalty_due-li.principal_paid-li.interest_paid-li.fee_paid-li.penalty_paid) amount
         FROM loan_installments li JOIN loans l ON l.id=li.loan_id
         WHERE l.member_id=? AND li.status<>'completed' ORDER BY li.due_date LIMIT 1"
    );
    $next->execute([$memberId]);
    $next = $next->fetch();
    $recent = $pdo->prepare('SELECT * FROM financial_payments WHERE member_id=? ORDER BY created_at DESC LIMIT 6');
    $recent->execute([$memberId]);
    $unread = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE member_id=? AND read_at IS NULL');
    $unread->execute([$memberId]);
?>
<div class="portal-grid cards">
    <section class="portal-card"><span class="stat-label">Total savings</span><strong class="stat-value"><?= kes($contributions['paid']) ?></strong><span class="stat-note">Across all months</span></section>
    <section class="portal-card"><span class="stat-label">Outstanding contributions</span><strong class="stat-value"><?= kes($contributions['outstanding']) ?></strong><span class="stat-note"><?= (int) $contributions['overdue_count'] ?> overdue period(s)</span></section>
    <section class="portal-card"><span class="stat-label">Active loan balance</span><strong class="stat-value"><?= kes($loanSummary['balance']) ?></strong><span class="stat-note"><?= (int) $loanSummary['active_count'] ?> open loan(s)</span></section>
    <section class="portal-card"><span class="stat-label">Next repayment</span><strong class="stat-value"><?= $next ? kes($next['amount']) : '—' ?></strong><span class="stat-note"><?= $next ? date('d M Y', strtotime($next['due_date'])) : 'No repayment scheduled' ?></span></section>
</div>
<div class="portal-grid two" style="margin-top:18px">
    <section class="portal-card"><div class="section-head"><h2>Quick actions</h2></div><div class="portal-actions"><a class="portal-btn" href="<?= h(app_url('portal/index.php?page=payments')) ?>">Submit payment</a><a class="portal-btn secondary" href="<?= h(app_url('portal/index.php?page=loans')) ?>">Apply for loan</a><a class="portal-btn secondary" href="<?= h(app_url('portal/index.php?page=contributions')) ?>">View statement</a></div></section>
    <section class="portal-card"><div class="section-head"><h2>Account</h2><span class="badge active"><?= h($user['membership_status']) ?></span></div><p><strong><?= h($user['membership_number']) ?></strong><br><span class="muted">Member since <?= date('d M Y', strtotime($user['date_joined'])) ?></span></p><p><a class="auth-link" href="<?= h(app_url('portal/index.php?page=notifications')) ?>"><?= (int) $unread->fetchColumn() ?> unread notification(s)</a></p></section>
</div>
<section class="portal-card" style="margin-top:18px"><div class="section-head"><h2>Recent transactions</h2><a class="auth-link" href="<?= h(app_url('portal/index.php?page=payments')) ?>">View all</a></div><div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Amount</th><th>Status</th></tr></thead><tbody>
<?php foreach ($recent as $row): ?><tr><td><?= date('d M Y', strtotime($row['paid_on'])) ?></td><td><?= h(str_replace('_', ' ', $row['payment_type'])) ?></td><td><?= h($row['reference_number']) ?></td><td><?= kes($row['amount']) ?></td><td><span class="badge <?= h($row['status']) ?>"><?= h($row['status']) ?></span></td></tr><?php endforeach; ?>
<?php if ($recent->rowCount() === 0): ?><tr><td colspan="5" class="empty-state">No verified transactions yet.</td></tr><?php endif; ?></tbody></table></div></section>
<?php
elseif ($page === 'contributions'):
    $summary = contribution_summary($memberId);
    $obligations = $pdo->prepare('SELECT * FROM contribution_obligations WHERE member_id=? ORDER BY period_month DESC');
    $obligations->execute([$memberId]);
?>
<div class="portal-grid cards">
    <section class="portal-card"><span class="stat-label">Minimum expected</span><strong class="stat-value"><?= kes($summary['expected']) ?></strong></section>
    <section class="portal-card"><span class="stat-label">Total saved</span><strong class="stat-value"><?= kes($summary['paid']) ?></strong></section>
    <section class="portal-card"><span class="stat-label">Outstanding</span><strong class="stat-value"><?= kes($summary['outstanding']) ?></strong></section>
    <section class="portal-card"><span class="stat-label">Completion rate</span><strong class="stat-value"><?= (float) $summary['expected'] > 0 ? number_format(min(100, ((float)$summary['paid']/(float)$summary['expected'])*100), 1).'%' : '100%' ?></strong></section>
</div>
<section class="portal-card" style="margin-top:18px"><div class="section-head"><h2>Monthly savings</h2><a class="portal-btn" href="<?= h(app_url('portal/index.php?page=payments')) ?>">Submit payment</a></div><div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>Month</th><th>Due date</th><th>Minimum</th><th>Saved</th><th>Shortfall</th><th>Status</th></tr></thead><tbody>
<?php foreach ($obligations as $row): $balance=max(0,(float)$row['expected_amount']-(float)$row['paid_amount']); ?><tr><td><?= date('F Y', strtotime($row['period_month'])) ?></td><td><?= date('d M Y', strtotime($row['due_date'])) ?></td><td><?= kes($row['expected_amount']) ?></td><td><?= kes($row['paid_amount']) ?></td><td><?= kes($balance) ?></td><td><span class="badge <?= h($row['status']) ?>"><?= h($row['status']) ?></span></td></tr><?php endforeach; ?>
<?php if ($obligations->rowCount()===0): ?><tr><td colspan="6" class="empty-state">No contribution rule is active yet.</td></tr><?php endif; ?></tbody></table></div></section>
<?php
elseif ($page === 'payments'):
    $loans = $pdo->prepare("SELECT id,loan_number,outstanding_principal+outstanding_interest+outstanding_fees balance FROM loans WHERE member_id=? AND status IN ('active','overdue','defaulted') ORDER BY created_at DESC");
    $loans->execute([$memberId]); $loanRows=$loans->fetchAll();
    $claims=$pdo->prepare('SELECT pc.*,l.loan_number FROM payment_claims pc LEFT JOIN loans l ON l.id=pc.loan_id WHERE pc.member_id=? ORDER BY pc.created_at DESC');$claims->execute([$memberId]);
    $payments=$pdo->prepare('SELECT * FROM financial_payments WHERE member_id=? ORDER BY created_at DESC');$payments->execute([$memberId]);
?>
<div class="portal-grid two">
<section class="portal-card"><h2>Submit a payment claim</h2><p class="muted">Claims do not affect your balance until an official verifies them.</p><form class="portal-form" method="post" action="<?= h(app_url('portal/action.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="submit_payment_claim">
<div class="form-field"><label for="payment_type">Payment type</label><select id="payment_type" name="payment_type" required><option value="contribution">Monthly contribution</option><option value="loan_repayment">Loan repayment</option></select></div>
<div class="form-field" id="loan-field" hidden><label for="loan_id">Loan</label><select id="loan_id" name="loan_id"><option value="">Select a loan</option><?php foreach($loanRows as $loan): ?><option value="<?= (int)$loan['id'] ?>"><?= h($loan['loan_number']) ?> · <?= kes($loan['balance']) ?></option><?php endforeach; ?></select></div>
<div class="form-grid"><div class="form-field"><label for="amount">Amount (KES)</label><input id="amount" name="amount" inputmode="decimal" required></div><div class="form-field"><label for="paid_on">Date paid</label><input id="paid_on" name="paid_on" type="date" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required></div><div class="form-field"><label for="payment_method">Method</label><select id="payment_method" name="payment_method" required><option>M-Pesa</option><option>Cash</option><option>Bank Transfer</option><option>Other</option></select></div><div class="form-field"><label for="reference_number">Transaction reference</label><input id="reference_number" name="reference_number" maxlength="100" required></div></div>
<div class="form-field"><label for="note">Note</label><textarea id="note" name="note" maxlength="500" rows="3"></textarea></div><button class="portal-btn" type="submit">Send for verification</button></form></section>
<section class="portal-card"><h2>Claim history</h2><div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>Date</th><th>Reference</th><th>Amount</th><th>Status</th></tr></thead><tbody><?php foreach($claims as $claim): ?><tr><td><?= date('d M Y',strtotime($claim['paid_on'])) ?></td><td><?= h($claim['reference_number']) ?></td><td><?= kes($claim['amount']) ?></td><td><span class="badge <?= h($claim['status']) ?>"><?= h($claim['status']) ?></span></td></tr><?php endforeach; ?><?php if($claims->rowCount()===0): ?><tr><td colspan="4" class="empty-state">No payment claims.</td></tr><?php endif; ?></tbody></table></div></section></div>
<section class="portal-card" style="margin-top:18px"><h2>Verified payment history</h2><div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Amount</th><th>Status</th></tr></thead><tbody><?php foreach($payments as $payment): ?><tr><td><?= date('d M Y',strtotime($payment['paid_on'])) ?></td><td><?= h(str_replace('_',' ',$payment['payment_type'])) ?></td><td><?= h($payment['reference_number']) ?></td><td><?= kes($payment['amount']) ?></td><td><span class="badge <?= h($payment['status']) ?>"><?= h($payment['status']) ?></span></td></tr><?php endforeach; ?><?php if($payments->rowCount()===0): ?><tr><td colspan="5" class="empty-state">No verified payments.</td></tr><?php endif; ?></tbody></table></div></section>
<script>const type=document.getElementById('payment_type'),loanField=document.getElementById('loan-field'),loanSelect=document.getElementById('loan_id');function toggleLoan(){const show=type.value==='loan_repayment';loanField.hidden=!show;loanSelect.required=show;}type.addEventListener('change',toggleLoan);toggleLoan();</script>
<?php
elseif ($page === 'loans'):
    $product=get_loan_product();$eligibility=$product?loan_eligibility($memberId,$product):['Loan product is not configured.'];
    $apps=$pdo->prepare('SELECT * FROM loan_applications WHERE member_id=? ORDER BY submitted_at DESC');$apps->execute([$memberId]);
    $memberLoans=$pdo->prepare('SELECT * FROM loans WHERE member_id=? ORDER BY created_at DESC');$memberLoans->execute([$memberId]);$memberLoanRows=$memberLoans->fetchAll();
?>
<div class="portal-grid two"><section class="portal-card"><h2>Apply for a loan</h2>
<?php if(!$product || !(int)$product['enabled']): ?><div class="portal-alert warning">Loan applications are currently closed.</div>
<?php elseif($eligibility): ?><div class="portal-alert warning"><?= h(implode(' ',$eligibility)) ?></div>
<?php else: ?><p class="muted"><?= h($product['name']) ?> · Flat 10% interest · repayable within 6 months.</p><form class="portal-form" method="post" action="<?= h(app_url('portal/action.php')) ?>" id="loan-form"><?= csrf_field() ?><input type="hidden" name="action" value="submit_loan"><div class="form-grid"><div class="form-field"><label for="loan-amount">Amount (<?= kes($product['minimum_amount']) ?>–<?= kes($product['maximum_amount']) ?>)</label><input id="loan-amount" name="amount" type="number" step="0.01" min="<?= h($product['minimum_amount']) ?>" max="<?= h($product['maximum_amount']) ?>" required></div></div><div class="form-field"><label for="purpose">Purpose</label><textarea id="purpose" name="purpose" rows="3" maxlength="500"></textarea></div><div class="loan-estimate" id="loan-estimate">Enter an amount to preview total repayment.</div><button class="portal-btn" type="submit">Submit loan application</button></form>
<script>const la=document.getElementById('loan-amount'),le=document.getElementById('loan-estimate');function estimate(){const a=Number(la.value);if(!a){le.textContent='Enter an amount to preview total repayment.';return}const interest=a*0.10,total=a+interest;const due=new Date();due.setMonth(due.getMonth()+6);le.textContent=`Flat interest: KES ${interest.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})} · Total repayable: KES ${total.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})} by ${due.toLocaleDateString(undefined,{year:'numeric',month:'short',day:'numeric'})}`;};la.addEventListener('input',estimate);estimate();</script>
<?php endif; ?></section>
<section class="portal-card"><h2>Applications</h2><div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>Date</th><th>Requested</th><th>Term</th><th>Status</th></tr></thead><tbody><?php foreach($apps as $application): ?><tr><td><?= date('d M Y',strtotime($application['submitted_at'])) ?></td><td><?= kes($application['requested_amount']) ?></td><td><?= (int)$application['requested_term_months'] ?> months</td><td><span class="badge <?= h($application['status']) ?>"><?= h(str_replace('_',' ',$application['status'])) ?></span></td></tr><?php endforeach; ?><?php if($apps->rowCount()===0): ?><tr><td colspan="4" class="empty-state">No loan applications.</td></tr><?php endif; ?></tbody></table></div></section></div>
<?php foreach($memberLoanRows as $loan): $schedule=$pdo->prepare('SELECT * FROM loan_installments WHERE loan_id=? ORDER BY installment_number');$schedule->execute([$loan['id']]); ?><section class="portal-card" style="margin-top:18px"><div class="section-head"><div><h2><?= h($loan['loan_number']) ?></h2><span class="muted">Disbursed <?= $loan['disbursed_on']?date('d M Y',strtotime($loan['disbursed_on'])):'Not yet' ?></span></div><span class="badge <?= h($loan['status']) ?>"><?= h(str_replace('_',' ',$loan['status'])) ?></span></div><div class="portal-grid cards"><div><span class="stat-label">Principal</span><strong class="stat-value"><?= kes($loan['principal_amount']) ?></strong></div><div><span class="stat-label">Balance</span><strong class="stat-value"><?= kes((float)$loan['outstanding_principal']+(float)$loan['outstanding_interest']+(float)$loan['outstanding_fees']) ?></strong></div></div><div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>#</th><th>Due</th><th>Principal</th><th>Interest</th><th>Fees/penalty</th><th>Status</th></tr></thead><tbody><?php foreach($schedule as $inst): ?><tr><td><?= (int)$inst['installment_number'] ?></td><td><?= date('d M Y',strtotime($inst['due_date'])) ?></td><td><?= kes($inst['principal_due']) ?></td><td><?= kes($inst['interest_due']) ?></td><td><?= kes((float)$inst['fee_due']+(float)$inst['penalty_due']) ?></td><td><span class="badge <?= h($inst['status']) ?>"><?= h($inst['status']) ?></span></td></tr><?php endforeach; ?><?php if($schedule->rowCount()===0): ?><tr><td colspan="6" class="empty-state">Schedule will appear after disbursement.</td></tr><?php endif; ?></tbody></table></div></section><?php endforeach; ?>
<?php
elseif ($page === 'notifications'):
    $notifications=$pdo->prepare('SELECT * FROM notifications WHERE member_id=? ORDER BY created_at DESC');$notifications->execute([$memberId]);
?><section class="portal-card"><h2>Account notifications</h2><?php foreach($notifications as $notification): ?><div class="notification <?= $notification['read_at']?'read':'unread' ?>"><span class="notification-dot"></span><div style="flex:1"><strong><?= h($notification['title']) ?></strong><p class="muted" style="margin:4px 0"><?= h($notification['message']) ?></p><small class="muted"><?= date('d M Y, H:i',strtotime($notification['created_at'])) ?></small></div><?php if(!$notification['read_at']): ?><form method="post" action="<?= h(app_url('portal/action.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="read_notification"><input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>"><button class="portal-btn secondary" type="submit">Mark read</button></form><?php endif; ?></div><?php endforeach; ?><?php if($notifications->rowCount()===0): ?><div class="empty-state">You have no notifications.</div><?php endif; ?></section>
<?php
elseif ($page === 'profile'):
?><section class="portal-card"><h2>Membership information</h2><div class="form-grid"><div><span class="stat-label">Full name</span><p><?= h($user['full_name']) ?></p></div><div><span class="stat-label">Membership number</span><p><?= h($user['membership_number']) ?></p></div><div><span class="stat-label">Phone</span><p><?= h($user['phone']) ?></p></div><div><span class="stat-label">Email</span><p><?= h($user['email'] ?: 'Not provided') ?></p></div><div><span class="stat-label">Status</span><p><span class="badge active"><?= h($user['membership_status']) ?></span></p></div><div><span class="stat-label">Date joined</span><p><?= date('d M Y',strtotime($user['date_joined'])) ?></p></div><div class="form-field full"><span class="stat-label">Residential address</span><p><?= h($user['residential_address'] ?: 'Not provided') ?></p></div></div><p class="muted">Contact an official to update protected membership details.</p></section>
<?php
elseif ($page === 'security'):
?><section class="portal-card" style="max-width:640px"><h2>Change password</h2><p class="muted">Use at least 12 characters and avoid reusing a password from another service.</p><form class="portal-form" method="post" action="<?= h(app_url('portal/action.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="change_password"><div class="form-field"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required></div><div class="form-field"><label for="new_password">New password</label><input id="new_password" name="new_password" type="password" minlength="12" autocomplete="new-password" required></div><div class="form-field"><label for="new_password_confirm">Confirm new password</label><input id="new_password_confirm" name="new_password_confirm" type="password" minlength="12" autocomplete="new-password" required></div><button class="portal-btn" type="submit">Update password</button></form></section>
<?php endif; require __DIR__ . '/_footer.php';
