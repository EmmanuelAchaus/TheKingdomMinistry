<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/chama.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$action = (string) ($_POST['action'] ?? '');
$publicActions = ['register', 'login'];
$redirectTarget = in_array($action, $publicActions, true) ? 'portal/login.php' : 'portal/';

try {
    verify_csrf();

    if ($action === 'register') {
        $redirectTarget = 'portal/register.php';
        $name = trim((string) ($_POST['full_name'] ?? ''));
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? '')) ?: null;
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || strlen($name) > 160 || strlen($phone) < 10) {
            throw new InvalidArgumentException('Enter your full name and a valid phone number.');
        }
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        if (strlen($password) < 12 || $password !== ($_POST['password_confirm'] ?? '')) {
            throw new InvalidArgumentException('Passwords must match and contain at least 12 characters.');
        }
        $dateOfBirth = trim((string) ($_POST['date_of_birth'] ?? '')) ?: null;
        if ($dateOfBirth && $dateOfBirth > date('Y-m-d')) {
            throw new InvalidArgumentException('Date of birth cannot be in the future.');
        }
        $gender = trim((string) ($_POST['gender'] ?? '')) ?: null;
        if ($gender && !in_array($gender, ['Male', 'Female', 'Other', 'Prefer not to say'], true)) {
            throw new InvalidArgumentException('Select a valid gender option.');
        }
        $nationalId = trim((string) ($_POST['national_id'] ?? '')) ?: null;

        $pdo->beginTransaction();
        $check = $pdo->prepare('SELECT id FROM users WHERE phone = ?');
        $check->execute([$phone]);
        if ($check->fetchColumn()) {
            throw new RuntimeException('An application or account already uses that phone number.');
        }
        if ($nationalId) {
            $fingerprint = private_fingerprint($nationalId);
            $check = $pdo->prepare('SELECT id FROM members WHERE national_id_lookup = ?');
            $check->execute([$fingerprint]);
            if ($check->fetchColumn()) {
                throw new RuntimeException('An application already exists for that identity document.');
            }
        } else {
            $fingerprint = null;
        }

        $stmt = $pdo->prepare("INSERT INTO users (phone,password_hash,role,account_status) VALUES (?,?,'member','pending')");
        $stmt->execute([$phone, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare(
            "INSERT INTO members
             (user_id,full_name,email,national_id_encrypted,national_id_lookup,date_of_birth,gender,
              residential_address,next_of_kin_name,next_of_kin_phone,next_of_kin_relationship,membership_status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,'Pending')"
        );
        $stmt->execute([
            $userId,
            $name,
            $email,
            private_encrypt($nationalId),
            $fingerprint,
            $dateOfBirth,
            $gender,
            trim((string) ($_POST['residential_address'] ?? '')) ?: null,
            trim((string) ($_POST['next_of_kin_name'] ?? '')) ?: null,
            normalize_phone((string) ($_POST['next_of_kin_phone'] ?? '')) ?: null,
            trim((string) ($_POST['next_of_kin_relationship'] ?? '')) ?: null,
        ]);
        $memberId = (int) $pdo->lastInsertId();
        audit('membership.requested', 'member', $memberId, ['phone' => $phone], null);
        $pdo->commit();
        flash('success', 'Your membership request was submitted. An official will contact you after review.');
        redirect('portal/login.php');
    }

    if ($action === 'login') {
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        $stmt = $pdo->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        $account = $stmt->fetch();
        if (!$account || !password_verify((string) ($_POST['password'] ?? ''), $account['password_hash'])) {
            if ($account) {
                $attempts = (int) $account['failed_login_attempts'] + 1;
                $locked = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
                $pdo->prepare('UPDATE users SET failed_login_attempts=?, locked_until=? WHERE id=?')->execute([$attempts, $locked, $account['id']]);
            }
            throw new RuntimeException('The phone number or password is incorrect.');
        }
        if ($account['locked_until'] && $account['locked_until'] > date('Y-m-d H:i:s')) {
            throw new RuntimeException('This account is temporarily locked. Try again in 15 minutes.');
        }
        if ($account['account_status'] === 'pending') {
            throw new RuntimeException('Your application is still awaiting approval.');
        }
        if ($account['account_status'] === 'rejected') {
            throw new RuntimeException('This membership application was not approved. Please contact an official.');
        }
        if ($account['account_status'] !== 'active') {
            throw new RuntimeException('This account is not active. Please contact an official.');
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $account['id'];
        $_SESSION['admin_logged_in'] = $account['role'] === 'admin';
        $_SESSION['admin_id'] = (int) $account['id'];
        $_SESSION['admin_username'] = $phone;
        unset($_SESSION['csrf_token']);
        $pdo->prepare('UPDATE users SET failed_login_attempts=0,locked_until=NULL,last_login_at=NOW() WHERE id=?')->execute([$account['id']]);
        audit('auth.login', 'user', (int) $account['id']);
        redirect('portal/');
    }

    $user = require_user();
    $memberId = (int) $user['member_id'];

    if ((int) $user['must_change_password'] === 1 && $action !== 'change_password') {
        throw new RuntimeException('Change your temporary password before using the portal.');
    }

    if ($action === 'change_password') {
        $redirectTarget = 'portal/index.php?page=security';
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify($current, $user['password_hash'])) {
            throw new RuntimeException('Your current password is incorrect.');
        }
        if (strlen($new) < 12 || $new !== ($_POST['new_password_confirm'] ?? '')) {
            throw new InvalidArgumentException('New passwords must match and contain at least 12 characters.');
        }
        $pdo->prepare('UPDATE users SET password_hash=?,must_change_password=0 WHERE id=?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        audit('auth.password_changed', 'user', (int) $user['id']);
        action_result(true, 'Your password was updated.', $redirectTarget);
    }

    if ($action === 'submit_payment_claim') {
        $redirectTarget = 'portal/index.php?page=payments';
        $type = (string) ($_POST['payment_type'] ?? '');
        $method = (string) ($_POST['payment_method'] ?? '');
        $amount = money_to_cents((string) ($_POST['amount'] ?? ''));
        $paidOn = (string) ($_POST['paid_on'] ?? '');
        $reference = strtoupper(trim((string) ($_POST['reference_number'] ?? '')));
        $loanId = !empty($_POST['loan_id']) ? (int) $_POST['loan_id'] : null;
        if (!in_array($type, ['contribution', 'loan_repayment'], true) || !in_array($method, ['Cash', 'M-Pesa', 'Bank Transfer', 'Other'], true)) {
            throw new InvalidArgumentException('Choose a valid payment type and method.');
        }
        if ($amount <= 0 || !$paidOn || $paidOn > date('Y-m-d') || $reference === '') {
            throw new InvalidArgumentException('Enter a valid amount, payment date, and transaction reference.');
        }
        if ($type === 'loan_repayment') {
            $loan = $pdo->prepare("SELECT id FROM loans WHERE id=? AND member_id=? AND status IN ('active','overdue','defaulted')");
            $loan->execute([$loanId, $memberId]);
            if (!$loan->fetchColumn()) {
                throw new RuntimeException('Select one of your active loans.');
            }
        } else {
            $loanId = null;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO payment_claims
             (member_id,payment_type,loan_id,amount,paid_on,payment_method,reference_number,note)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([$memberId, $type, $loanId, cents_to_decimal($amount), $paidOn, $method, $reference, trim((string) ($_POST['note'] ?? '')) ?: null]);
        $claimId = (int) $pdo->lastInsertId();
        audit('payment_claim.submitted', 'payment_claim', $claimId, ['type' => $type, 'amount' => cents_to_decimal($amount)]);
        action_result(true, 'Your payment claim was submitted for verification.', $redirectTarget);
    }

    if ($action === 'submit_loan') {
        $redirectTarget = 'portal/index.php?page=loans';
        $product = get_loan_product();
        if (!$product || !(int) $product['enabled']) {
            throw new RuntimeException('Loan applications are not currently open.');
        }
        $amount = money_to_cents((string) ($_POST['amount'] ?? ''));
        $term = (int) ($_POST['term_months'] ?? 0);
        if ($amount < money_to_cents($product['minimum_amount']) || $amount > money_to_cents($product['maximum_amount'])) {
            throw new InvalidArgumentException('Requested amount is outside the permitted loan range.');
        }
        if ($term < (int) $product['minimum_term_months'] || $term > (int) $product['maximum_term_months']) {
            throw new InvalidArgumentException('Requested repayment period is outside the permitted range.');
        }
        $problems = loan_eligibility($memberId, $product);
        if ($problems) {
            throw new RuntimeException(implode(' ', $problems));
        }
        $estimate = loan_estimate($product, $amount, $term);
        $stmt = $pdo->prepare(
            "INSERT INTO loan_applications
             (member_id,requested_amount,requested_term_months,purpose,status,estimated_total)
             VALUES (?,?,?,?,'submitted',?)"
        );
        $stmt->execute([
            $memberId,
            cents_to_decimal($amount),
            $term,
            trim((string) ($_POST['purpose'] ?? '')) ?: null,
            cents_to_decimal($estimate['total_cents']),
        ]);
        $id = (int) $pdo->lastInsertId();
        audit('loan_application.submitted', 'loan_application', $id, ['amount' => cents_to_decimal($amount), 'term' => $term]);
        notify_member($memberId, 'loan_submitted', 'Loan application received', 'Your loan application is awaiting review.', app_url('portal/index.php?page=loans'));
        action_result(true, 'Your loan application was submitted.', $redirectTarget);
    }

    if ($action === 'read_notification') {
        $redirectTarget = 'portal/index.php?page=notifications';
        $id = (int) ($_POST['notification_id'] ?? 0);
        $pdo->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND member_id=?')->execute([$id, $memberId]);
        action_result(true, 'Notification marked as read.', $redirectTarget);
    }

    throw new InvalidArgumentException('Unknown action.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Portal action failed: ' . $e->getMessage());
    action_result(false, $e->getMessage(), $redirectTarget);
}
