<?php
$page = $page ?? 'dashboard';
$pageTitle = $pageTitle ?? 'Member Dashboard';
$user = $user ?? require_user();
$nav = [
    'dashboard' => ['Dashboard', 'portal/index.php'],
    'contributions' => ['Contributions', 'portal/index.php?page=contributions'],
    'payments' => ['Payment claims', 'portal/index.php?page=payments'],
    'loans' => ['My loans', 'portal/index.php?page=loans'],
    'notifications' => ['Notifications', 'portal/index.php?page=notifications'],
    'profile' => ['My profile', 'portal/index.php?page=profile'],
    'security' => ['Security', 'portal/index.php?page=security'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h($pageTitle) ?> · Kingdom Sons SHG</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(app_url('assets/css/portal.css')) ?>">
</head>
<body class="portal-body">
<div class="portal-shell">
    <aside class="portal-sidebar" id="portal-sidebar">
        <a class="portal-brand" href="<?= h(app_url('portal/')) ?>">Kingdom Sons<small>Self Help Group</small></a>
        <nav class="portal-nav" aria-label="Member portal">
            <?php foreach ($nav as $key => [$label, $href]): ?>
                <a class="<?= $page === $key ? 'active' : '' ?>" href="<?= h(app_url($href)) ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="portal-sidebar-foot">
            <strong><?= h($user['full_name']) ?></strong><br>
            <?= h($user['membership_number']) ?><br>
            <?php if ($user['role'] === 'admin'): ?>
                <a class="admin-switch" href="<?= h(app_url('admin/chama/')) ?>">Open administration →</a>
            <?php endif; ?>
            <a class="admin-switch" href="<?= h(app_url('portal/logout.php')) ?>">Sign out</a>
        </div>
    </aside>
    <main class="portal-main">
        <header class="portal-topbar">
            <div>
                <h1 class="portal-title"><?= h($pageTitle) ?></h1>
                <p class="portal-subtitle">Welcome, <?= h(explode(' ', trim($user['full_name']))[0]) ?>.</p>
            </div>
            <button class="portal-menu-btn" type="button" aria-controls="portal-sidebar" aria-expanded="false">Menu</button>
        </header>
        <?php foreach (pull_flashes() as $message): ?>
            <div class="portal-alert <?= h($message['type']) ?>" role="alert"><?= h($message['message']) ?></div>
        <?php endforeach; ?>
        <?php if ((int) $user['must_change_password'] === 1 && $page !== 'security'): ?>
            <div class="portal-alert warning">Your temporary password must be changed. <a href="<?= h(app_url('portal/index.php?page=security')) ?>">Change it now</a>.</div>
        <?php endif; ?>
