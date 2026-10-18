<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/chama.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$action = (string) ($_POST['action'] ?? '');
$redirects = [
    'approve_member'=>'members','reject_member'=>'members','set_member_status'=>'members','reset_password'=>'members',
    'set_member_role'=>'members','save_contribution_rule'=>'contributions','save_contribution_override'=>'contributions',
    'direct_payment'=>'contributions','verify_claim'=>'claims','reject_claim'=>'claims','save_loan_product'=>'loans',
    'approve_loan'=>'loans','reject_loan'=>'loans','disburse_loan'=>'loans','reverse_payment'=>'transactions',
    'create_existing_member'=>'opening','opening_contribution'=>'opening','opening_loan'=>'opening',
];
$target = 'admin/chama/index.php?page=' . ($redirects[$action] ?? 'dashboard');

try {
    verify_csrf();

    if ($action === 'approve_member') {
        $memberId=(int)($_POST['member_id']??0);$pdo->beginTransaction();
        $stmt=$pdo->prepare("SELECT m.*,u.role,u.account_status,u.id user_account_id FROM members m JOIN users u ON u.id=m.user_id WHERE m.id=? FOR UPDATE");$stmt->execute([$memberId]);$member=$stmt->fetch();
        if(!$member || $member['membership_status']!=='Pending' || $member['account_status']!=='pending'){throw new RuntimeException('This membership request is no longer pending.');}
        if((int)$member['user_account_id']===(int)$admin['id']){
            $count=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND account_status='active'")->fetchColumn();
            if($count>1){throw new RuntimeException('An administrator cannot approve their own membership when another administrator is active.');}
            audit('approval.single_admin_override','member',$memberId,['decision'=>'approved']);
        }
        $prefix=$pdo->query("SELECT setting_value FROM app_settings WHERE setting_key='membership_number_prefix'")->fetchColumn()?:'KSSHG';
        $number=$prefix.'-'.str_pad((string)$memberId,4,'0',STR_PAD_LEFT);$joined=date('Y-m-d');$first=date('Y-m-01',strtotime('first day of next month'));
        $pdo->prepare("UPDATE members SET membership_number=?,date_joined=?,first_contribution_month=?,membership_status='Active',approved_by=?,approved_at=NOW(),rejection_reason=NULL WHERE id=?")->execute([$number,$joined,$first,$admin['id'],$memberId]);
        $pdo->prepare("UPDATE users SET account_status='active' WHERE id=?")->execute([$member['user_id']]);
        notify_member($memberId,'membership_approved','Membership approved','Welcome to Kingdom Sons Self Help Group. Your membership number is '.$number.'.',app_url('portal/'));
        audit('membership.approved','member',$memberId,['membership_number'=>$number]);$pdo->commit();
        action_result(true,'Membership approved and account activated.',$target);
    }

    if ($action === 'reject_member') {
        $memberId=(int)($_POST['member_id']??0);$reason=trim((string)($_POST['reason']??''));if($reason===''){throw new InvalidArgumentException('A rejection reason is required.');}
        $pdo->beginTransaction();$stmt=$pdo->prepare("SELECT * FROM members WHERE id=? AND membership_status='Pending' FOR UPDATE");$stmt->execute([$memberId]);$member=$stmt->fetch();if(!$member){throw new RuntimeException('This membership request is no longer pending.');}
        $pdo->prepare("UPDATE members SET rejection_reason=? WHERE id=?")->execute([$reason,$memberId]);$pdo->prepare("UPDATE users SET account_status='rejected' WHERE id=?")->execute([$member['user_id']]);
        audit('membership.rejected','member',$memberId,['reason'=>$reason]);$pdo->commit();action_result(true,'Membership request rejected.',$target);
    }

    if ($action === 'set_member_status') {
        $memberId=(int)($_POST['member_id']??0);$status=(string)($_POST['status']??'');if(!in_array($status,['Active','Suspended','Withdrawn','Deceased'],true)){throw new InvalidArgumentException('Invalid membership status.');}
        $stmt=$pdo->prepare('SELECT m.*,u.role,u.id uid FROM members m JOIN users u ON u.id=m.user_id WHERE m.id=?');$stmt->execute([$memberId]);$member=$stmt->fetch();if(!$member){throw new RuntimeException('Member not found.');}if(!$member['membership_number']){throw new RuntimeException('A rejected or pending application must be approved before its membership status can change.');}
        if($member['role']==='admin' && $status!=='Active'){$count=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND account_status='active'")->fetchColumn();if($count<=1){throw new RuntimeException('The last active administrator cannot be suspended or withdrawn.');}}
        $accountStatus=$status==='Active'?'active':'suspended';$pdo->beginTransaction();$pdo->prepare('UPDATE members SET membership_status=? WHERE id=?')->execute([$status,$memberId]);$pdo->prepare('UPDATE users SET account_status=? WHERE id=?')->execute([$accountStatus,$member['uid']]);audit('membership.status_changed','member',$memberId,['from'=>$member['membership_status'],'to'=>$status]);$pdo->commit();action_result(true,'Membership status updated.',$target);
    }

    if ($action === 'set_member_role') {
        $memberId=(int)($_POST['member_id']??0);$role=(string)($_POST['role']??'');if(!in_array($role,['member','admin'],true)){throw new InvalidArgumentException('Invalid role.');}
        $stmt=$pdo->prepare('SELECT u.id,u.role FROM users u JOIN members m ON m.user_id=u.id WHERE m.id=?');$stmt->execute([$memberId]);$account=$stmt->fetch();if(!$account){throw new RuntimeException('Member not found.');}
        if($account['role']==='admin'&&$role==='member'){$count=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND account_status='active'")->fetchColumn();if($count<=1){throw new RuntimeException('The last active administrator cannot be demoted.');}}
        $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role,$account['id']]);audit('user.role_changed','member',$memberId,['from'=>$account['role'],'to'=>$role]);action_result(true,'Member role updated.',$target);
    }

    if ($action === 'reset_password') {
        $memberId=(int)($_POST['member_id']??0);$password=(string)($_POST['temporary_password']??'');if(strlen($password)<12){throw new InvalidArgumentException('Temporary password must have at least 12 characters.');}
        $stmt=$pdo->prepare('SELECT user_id FROM members WHERE id=?');$stmt->execute([$memberId]);$uid=$stmt->fetchColumn();if(!$uid){throw new RuntimeException('Member not found.');}
        $pdo->prepare('UPDATE users SET password_hash=?,must_change_password=1,failed_login_attempts=0,locked_until=NULL WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$uid]);audit('auth.password_reset','member',$memberId);action_result(true,'Temporary password issued.',$target);
    }

    if ($action === 'save_contribution_rule') {
        $amount=money_to_cents((string)($_POST['monthly_amount']??''));$day=(int)($_POST['due_day']??0);$month=month_start((string)($_POST['effective_from']??''));if($amount<=0||$day<1||$day>31){throw new InvalidArgumentException('Enter a positive amount and a due day from 1 to 31.');}
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO contribution_rules(monthly_amount,due_day,effective_from,created_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE monthly_amount=VALUES(monthly_amount),due_day=VALUES(due_day),created_by=VALUES(created_by)')->execute([cents_to_decimal($amount),$day,$month,$admin['id']]);
        foreach($pdo->query("SELECT id FROM members WHERE membership_status IN ('Active','Suspended')")->fetchAll(PDO::FETCH_COLUMN) as $memberId){sync_member_contributions((int)$memberId);reprice_unpaid_contribution_obligations((int)$memberId,$month);}
        audit('contribution_rule.saved','contribution_rule',null,['amount'=>cents_to_decimal($amount),'due_day'=>$day,'effective_from'=>$month]);$pdo->commit();action_result(true,'Contribution rule saved.',$target);
    }

    if ($action === 'save_contribution_override') {
        $memberId=(int)($_POST['member_id']??0);$amount=money_to_cents((string)($_POST['monthly_amount']??''));$day=(int)($_POST['due_day']??0);$from=month_start((string)($_POST['effective_from']??''));$to=trim((string)($_POST['effective_to']??''));$to=$to?month_start($to):null;if($amount<=0||$day<1||$day>31||($to&&$to<$from)){throw new InvalidArgumentException('Enter valid override terms and dates.');}
        $pdo->beginTransaction();$pdo->prepare('INSERT INTO contribution_overrides(member_id,monthly_amount,due_day,effective_from,effective_to,reason,created_by) VALUES(?,?,?,?,?,?,?)')->execute([$memberId,cents_to_decimal($amount),$day,$from,$to,trim((string)($_POST['reason']??''))?:null,$admin['id']]);audit('contribution_override.created','member',$memberId,['amount'=>cents_to_decimal($amount),'from'=>$from,'to'=>$to]);sync_member_contributions($memberId);reprice_unpaid_contribution_obligations($memberId,$from);$pdo->commit();action_result(true,'Member contribution override saved.',$target);
    }

    if ($action === 'direct_payment' || $action === 'opening_contribution') {
        $opening=$action==='opening_contribution';$memberId=(int)($_POST['member_id']??0);$type=$opening?'contribution':(string)($_POST['payment_type']??'');$loanId=!empty($_POST['loan_id'])?(int)$_POST['loan_id']:null;
        $paidOn=(string)($_POST['paid_on']??'');if(!$paidOn||$paidOn>date('Y-m-d')){throw new InvalidArgumentException('Payment date is invalid.');}
        $pdo->beginTransaction();$paymentId=post_financial_payment(['member_id'=>$memberId,'payment_type'=>$type,'loan_id'=>$loanId,'amount'=>(string)($_POST['amount']??''),'paid_on'=>$paidOn,'payment_method'=>$opening?'Opening Balance':(string)($_POST['payment_method']??''),'reference_number'=>strtoupper(trim((string)($_POST['reference_number']??''))),'source'=>$opening?'opening':'admin_direct'],$admin['id']);audit($opening?'opening.contribution_recorded':'payment.recorded','financial_payment',$paymentId);notify_member($memberId,'payment_recorded','Payment recorded','A '.str_replace('_',' ',$type).' payment of '.kes($_POST['amount']??0).' was recorded.',app_url('portal/index.php?page=payments'));$pdo->commit();action_result(true,$opening?'Opening contribution recorded.':'Payment posted and allocated.',$target);
    }

    if ($action === 'verify_claim') {
        $claimId=(int)($_POST['claim_id']??0);$pdo->beginTransaction();$stmt=$pdo->prepare("SELECT * FROM payment_claims WHERE id=? AND status='pending' FOR UPDATE");$stmt->execute([$claimId]);$claim=$stmt->fetch();if(!$claim){throw new RuntimeException('Claim is missing or already reviewed.');}
        $paymentId=post_financial_payment(['member_id'=>$claim['member_id'],'payment_type'=>$claim['payment_type'],'loan_id'=>$claim['loan_id'],'claim_id'=>$claimId,'amount'=>$claim['amount'],'paid_on'=>$claim['paid_on'],'payment_method'=>$claim['payment_method'],'reference_number'=>$claim['reference_number'],'source'=>'claim'],$admin['id']);$pdo->prepare("UPDATE payment_claims SET status='verified',reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$admin['id'],$claimId]);audit('payment_claim.verified','payment_claim',$claimId,['payment_id'=>$paymentId]);notify_member((int)$claim['member_id'],'payment_verified','Payment verified','Your payment claim for '.kes($claim['amount']).' was verified.',app_url('portal/index.php?page=payments'));$pdo->commit();action_result(true,'Claim verified and payment allocated.',$target);
    }

    if ($action === 'reject_claim') {
        $claimId=(int)($_POST['claim_id']??0);$note=trim((string)($_POST['review_note']??''));if($note===''){throw new InvalidArgumentException('A rejection reason is required.');}$stmt=$pdo->prepare("SELECT * FROM payment_claims WHERE id=? AND status='pending'");$stmt->execute([$claimId]);$claim=$stmt->fetch();if(!$claim){throw new RuntimeException('Claim is missing or already reviewed.');}$pdo->prepare("UPDATE payment_claims SET status='rejected',reviewed_by=?,review_note=?,reviewed_at=NOW() WHERE id=?")->execute([$admin['id'],$note,$claimId]);audit('payment_claim.rejected','payment_claim',$claimId,['reason'=>$note]);notify_member((int)$claim['member_id'],'payment_rejected','Payment claim needs attention','Your payment claim was rejected: '.$note,app_url('portal/index.php?page=payments'));action_result(true,'Claim rejected.',$target);
    }

    if ($action === 'save_loan_product') {
        $min=money_to_cents((string)($_POST['minimum_amount']??''));$max=money_to_cents((string)($_POST['maximum_amount']??''));$minTerm=(int)($_POST['minimum_term_months']??0);$maxTerm=(int)($_POST['maximum_term_months']??0);$rate=(float)($_POST['annual_interest_rate']??-1);$penalty=(float)($_POST['late_penalty_rate']??-1);$completion=(float)($_POST['minimum_contribution_rate']??-1);
        if($min<=0||$max<$min||$minTerm<1||$maxTerm<$minTerm||$rate<0||$penalty<0||$completion<0||$completion>100){throw new InvalidArgumentException('Review the loan limits, terms, rates, and eligibility values.');}
        $method=(string)($_POST['interest_method']??'');if(!in_array($method,['flat','reducing'],true)){throw new InvalidArgumentException('Invalid interest method.');}
        $pdo->prepare('UPDATE loan_product SET name=?,enabled=?,minimum_amount=?,maximum_amount=?,annual_interest_rate=?,interest_method=?,minimum_term_months=?,maximum_term_months=?,processing_fee=?,grace_days=?,late_penalty_rate=?,minimum_active_months=?,minimum_contribution_rate=?,maximum_active_loans=?,updated_by=? WHERE id=1')->execute([trim((string)$_POST['name']),(int)$_POST['enabled'],cents_to_decimal($min),cents_to_decimal($max),number_format($rate,4,'.',''),$method,$minTerm,$maxTerm,cents_to_decimal(money_to_cents((string)($_POST['processing_fee']??'0'))),(int)$_POST['grace_days'],number_format($penalty,4,'.',''),(int)$_POST['minimum_active_months'],number_format($completion,2,'.',''),max(1,(int)$_POST['maximum_active_loans']),$admin['id']]);audit('loan_product.updated','loan_product',1);action_result(true,'Loan product settings saved.',$target);
    }

    if ($action === 'approve_loan') {
        $applicationId=(int)($_POST['application_id']??0);$approved=money_to_cents((string)($_POST['approved_amount']??''));$pdo->beginTransaction();$stmt=$pdo->prepare("SELECT la.*,u.id applicant_user_id FROM loan_applications la JOIN members m ON m.id=la.member_id JOIN users u ON u.id=m.user_id WHERE la.id=? AND la.status IN ('submitted','under_review') FOR UPDATE");$stmt->execute([$applicationId]);$app=$stmt->fetch();if(!$app){throw new RuntimeException('Application is missing or already reviewed.');}$product=get_loan_product();if(!$product){throw new RuntimeException('Configure the loan product first.');}
        if($approved<money_to_cents($product['minimum_amount'])||$approved>money_to_cents($product['maximum_amount'])){throw new InvalidArgumentException('Approved amount is outside configured limits.');}$problems=loan_eligibility((int)$app['member_id'],$product);if($problems){throw new RuntimeException(implode(' ',$problems));}
        $override=false;if((int)$app['applicant_user_id']===(int)$admin['id']){$activeAdmins=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND account_status='active'")->fetchColumn();if($activeAdmins>1){throw new RuntimeException('Another administrator must approve this application.');}$override=true;}
        $estimate=loan_estimate($product,$approved,(int)$app['requested_term_months']);$loanNumber='LN-'.date('Y').'-'.str_pad((string)$applicationId,5,'0',STR_PAD_LEFT);$snapshot=json_encode(['name'=>$product['name'],'annual_interest_rate'=>$product['annual_interest_rate'],'interest_method'=>$product['interest_method'],'processing_fee'=>$product['processing_fee'],'grace_days'=>$product['grace_days'],'late_penalty_rate'=>$product['late_penalty_rate']],JSON_UNESCAPED_SLASHES);
        $pdo->prepare("UPDATE loan_applications SET status='approved',approved_amount=?,product_snapshot=?,estimated_total=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([cents_to_decimal($approved),$snapshot,cents_to_decimal($estimate['total_cents']),$admin['id'],$applicationId]);
        $pdo->prepare("INSERT INTO loans(application_id,member_id,loan_number,principal_amount,annual_interest_rate,interest_method,term_months,processing_fee,grace_days,late_penalty_rate,outstanding_principal,outstanding_interest,outstanding_fees,status,approved_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,'approved',?)")->execute([$applicationId,$app['member_id'],$loanNumber,cents_to_decimal($approved),$product['annual_interest_rate'],$product['interest_method'],$app['requested_term_months'],$product['processing_fee'],$product['grace_days'],$product['late_penalty_rate'],cents_to_decimal($approved),cents_to_decimal($estimate['interest_cents']),cents_to_decimal($estimate['fees_cents']),$admin['id']]);$loanId=(int)$pdo->lastInsertId();audit($override?'approval.single_admin_override':'loan.approved','loan',$loanId,['application_id'=>$applicationId,'amount'=>cents_to_decimal($approved)]);notify_member((int)$app['member_id'],'loan_approved','Loan approved','Your loan application was approved and is awaiting disbursement.',app_url('portal/index.php?page=loans'));$pdo->commit();action_result(true,'Loan approved; record disbursement when funds are released.',$target);
    }

    if ($action === 'reject_loan') {
        $id=(int)($_POST['application_id']??0);$note=trim((string)($_POST['review_note']??''));if($note===''){throw new InvalidArgumentException('A rejection reason is required.');}$stmt=$pdo->prepare("SELECT member_id FROM loan_applications WHERE id=? AND status IN ('submitted','under_review')");$stmt->execute([$id]);$memberId=$stmt->fetchColumn();if(!$memberId){throw new RuntimeException('Application is missing or already reviewed.');}$pdo->prepare("UPDATE loan_applications SET status='rejected',review_notes=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$note,$admin['id'],$id]);audit('loan.rejected','loan_application',$id,['reason'=>$note]);notify_member((int)$memberId,'loan_rejected','Loan application not approved','Your application was not approved: '.$note,app_url('portal/index.php?page=loans'));action_result(true,'Loan application rejected.',$target);
    }

    if ($action === 'disburse_loan') {
        $loanId=(int)($_POST['loan_id']??0);$date=(string)($_POST['disbursed_on']??'');$reference=strtoupper(trim((string)($_POST['reference']??'')));if(!$date||$date>date('Y-m-d')||$reference===''){throw new InvalidArgumentException('Enter a valid disbursement date and reference.');}$pdo->beginTransaction();$member=$pdo->prepare('SELECT member_id FROM loans WHERE id=?');$member->execute([$loanId]);$memberId=(int)$member->fetchColumn();disburse_loan($loanId,$date,(string)($_POST['method']??'Other'),$reference,(int)$admin['id']);$pdo->prepare("UPDATE loan_applications SET status='disbursed' WHERE id=(SELECT application_id FROM loans WHERE id=?)")->execute([$loanId]);audit('loan.disbursed','loan',$loanId,['date'=>$date,'reference'=>$reference]);notify_member($memberId,'loan_disbursed','Loan disbursed','Your approved loan has been marked as disbursed. The repayment schedule is now active.',app_url('portal/index.php?page=loans'));$pdo->commit();action_result(true,'Loan disbursed and repayment schedule created.',$target);
    }

    if ($action === 'reverse_payment') {
        $id=(int)($_POST['payment_id']??0);$reason=trim((string)($_POST['reason']??''));$pdo->beginTransaction();reverse_financial_payment($id,(int)$admin['id'],$reason);audit('payment.reversed','financial_payment',$id,['reason'=>$reason]);$pdo->commit();action_result(true,'Payment reversed; all affected balances were recalculated.',$target);
    }

    if ($action === 'create_existing_member') {
        $name=trim((string)($_POST['full_name']??''));$phone=normalize_phone((string)($_POST['phone']??''));$password=(string)($_POST['temporary_password']??'');$joined=(string)($_POST['date_joined']??'');if($name===''||strlen($phone)<10||strlen($password)<12||!$joined||$joined>date('Y-m-d')){throw new InvalidArgumentException('Enter valid member details and a 12-character temporary password.');}
        $pdo->beginTransaction();$pdo->prepare("INSERT INTO users(phone,password_hash,role,account_status,must_change_password) VALUES(?,?,'member','active',1)")->execute([$phone,password_hash($password,PASSWORD_DEFAULT)]);$uid=(int)$pdo->lastInsertId();$prefix=$pdo->query("SELECT setting_value FROM app_settings WHERE setting_key='membership_number_prefix'")->fetchColumn()?:'KSSHG';$next=(int)$pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM members FOR UPDATE')->fetchColumn();$number=$prefix.'-'.str_pad((string)$next,4,'0',STR_PAD_LEFT);$first=date('Y-m-01',strtotime('first day of next month',strtotime($joined)));$pdo->prepare("INSERT INTO members(user_id,membership_number,full_name,email,date_joined,first_contribution_month,membership_status,remarks,approved_by,approved_at) VALUES(?,?,?,?,?,?,'Active',?,?,NOW())")->execute([$uid,$number,$name,trim((string)($_POST['email']??''))?:null,$joined,$first,trim((string)($_POST['remarks']??''))?:null,$admin['id']]);$memberId=(int)$pdo->lastInsertId();audit('opening.member_created','member',$memberId,['membership_number'=>$number,'joined'=>$joined]);$pdo->commit();action_result(true,'Existing member created as '.$number.'.',$target);
    }

    if ($action === 'opening_loan') {
        $memberId=(int)($_POST['member_id']??0);$principal=money_to_cents((string)($_POST['principal_amount']??''));$rate=(float)($_POST['annual_interest_rate']??-1);$method=(string)($_POST['interest_method']??'');$term=(int)($_POST['term_months']??0);$date=(string)($_POST['disbursed_on']??'');$reference=strtoupper(trim((string)($_POST['reference']??'')));if($principal<=0||$rate<0||!in_array($method,['flat','reducing'],true)||$term<1||!$date||$date>date('Y-m-d')||$reference===''){throw new InvalidArgumentException('Enter complete and valid opening loan terms.');}
        $pdo->beginTransaction();$number='OPEN-LN-'.date('Y').'-'.str_pad((string)((int)$pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM loans FOR UPDATE')->fetchColumn()),5,'0',STR_PAD_LEFT);$estimate=build_loan_schedule($principal,(int)round($rate*100),$term,$method,0);$pdo->prepare("INSERT INTO loans(member_id,loan_number,principal_amount,annual_interest_rate,interest_method,term_months,outstanding_principal,outstanding_interest,outstanding_fees,status,approved_by) VALUES(?,?,?,?,?,?,?,?,'0.00','approved',?)")->execute([$memberId,$number,cents_to_decimal($principal),number_format($rate,4,'.',''),$method,$term,cents_to_decimal($principal),cents_to_decimal($estimate['interest_cents']),$admin['id']]);$loanId=(int)$pdo->lastInsertId();disburse_loan($loanId,$date,'Opening Balance',$reference,(int)$admin['id']);audit('opening.loan_created','loan',$loanId,['reference'=>$reference,'as_of'=>$date]);notify_member($memberId,'opening_loan','Existing loan added','An existing loan balance was added to your account.',app_url('portal/index.php?page=loans'));$pdo->commit();action_result(true,'Opening loan and remaining schedule created.',$target);
    }

    throw new InvalidArgumentException('Unknown action.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('Admin chama action failed: '.$e->getMessage());
    action_result(false,$e->getMessage(),$target);
}
