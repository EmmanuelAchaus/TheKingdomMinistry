<?php
require_once dirname(__DIR__) . '/includes/app.php';
if (current_user()) { redirect('portal/'); }
$flashes = pull_flashes();
?>
<!doctype html>
<html lang="en"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Join Kingdom Sons SHG</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(app_url('assets/css/portal.css')) ?>">
</head><body class="portal-body"><div class="auth-shell">
    <section class="auth-story"><a class="portal-brand" href="<?= h(app_url()) ?>">The Kingdom Ministry</a><div><h1>Join the chama.</h1><p>Submit your details for review. An official will contact you outside the portal when a decision has been made.</p></div><small>Protected membership information</small></section>
    <main class="auth-panel"><div class="auth-card"><h2>Membership request</h2><p class="muted">Required fields are marked with an asterisk.</p>
        <?php foreach ($flashes as $message): ?><div class="portal-alert <?= h($message['type']) ?>"><?= h($message['message']) ?></div><?php endforeach; ?>
        <form class="portal-form" method="post" action="<?= h(app_url('portal/action.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="register">
            <div class="form-grid">
                <div class="form-field full"><label for="full_name">Full name *</label><input id="full_name" name="full_name" maxlength="160" required></div>
                <div class="form-field"><label for="phone">Phone number *</label><input id="phone" name="phone" type="tel" maxlength="32" required></div>
                <div class="form-field"><label for="email">Email address</label><input id="email" name="email" type="email" maxlength="190"></div>
                <div class="form-field"><label for="password">Password *</label><input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required></div>
                <div class="form-field"><label for="password_confirm">Confirm password *</label><input id="password_confirm" name="password_confirm" type="password" minlength="12" autocomplete="new-password" required></div>
                <div class="form-field"><label for="national_id">National ID / passport</label><input id="national_id" name="national_id" maxlength="50"></div>
                <div class="form-field"><label for="date_of_birth">Date of birth</label><input id="date_of_birth" name="date_of_birth" type="date" max="<?= date('Y-m-d') ?>"></div>
                <div class="form-field"><label for="gender">Gender</label><select id="gender" name="gender"><option value="">Prefer not to provide</option><option>Male</option><option>Female</option><option>Other</option><option>Prefer not to say</option></select></div>
                <div class="form-field full"><label for="address">Residential address</label><input id="address" name="residential_address" maxlength="255"></div>
                <div class="form-field"><label for="kin_name">Next of kin / beneficiary</label><input id="kin_name" name="next_of_kin_name" maxlength="160"></div>
                <div class="form-field"><label for="kin_phone">Next of kin phone</label><input id="kin_phone" name="next_of_kin_phone" maxlength="32"></div>
                <div class="form-field full"><label for="kin_relationship">Relationship</label><input id="kin_relationship" name="next_of_kin_relationship" maxlength="80"></div>
            </div>
            <button class="portal-btn" type="submit">Submit membership request</button>
        </form>
        <p>Already approved? <a class="auth-link" href="<?= h(app_url('portal/login.php')) ?>">Sign in</a></p>
    </div></main>
</div></body></html>
