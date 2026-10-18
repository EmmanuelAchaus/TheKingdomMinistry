<?php
require_once dirname(__DIR__) . '/includes/app.php';
$signedIn = current_user();
if ($signedIn && $signedIn['account_status'] === 'active' && ($signedIn['membership_status'] ?? '') === 'Active') {
    redirect('portal/');
}
if ($signedIn) {
    unset($_SESSION['user_id'], $_SESSION['admin_logged_in'], $_SESSION['admin_id'], $_SESSION['admin_username']);
}
$flashes = pull_flashes();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Member sign in · Kingdom Sons SHG</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(app_url('assets/css/portal.css')) ?>">
</head>
<body class="portal-body">
<div class="auth-shell">
    <section class="auth-story">
        <a class="portal-brand" href="<?= h(app_url()) ?>">The Kingdom Ministry</a>
        <div><h1>Build together.<br>Grow together.</h1><p>Securely follow your savings, loan applications, repayments, and member activity with Kingdom Sons Self Help Group.</p></div>
        <small>Financial stewardship with clarity and accountability.</small>
    </section>
    <main class="auth-panel">
        <div class="auth-card">
            <h2>Member sign in</h2><p class="muted">Use the phone number attached to your approved membership.</p>
            <?php foreach ($flashes as $message): ?><div class="portal-alert <?= h($message['type']) ?>"><?= h($message['message']) ?></div><?php endforeach; ?>
            <form class="portal-form" method="post" action="<?= h(app_url('portal/action.php')) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="login">
                <div class="form-field"><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" autocomplete="username" placeholder="0712 345 678" required></div>
                <div class="form-field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
                <button class="portal-btn" type="submit">Sign in</button>
            </form>
            <p>Not yet a member? <a class="auth-link" href="<?= h(app_url('portal/register.php')) ?>">Request to join</a></p>
        </div>
    </main>
</div>
</body></html>
