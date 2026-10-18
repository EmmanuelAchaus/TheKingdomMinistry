<?php
$admin = $admin ?? require_admin();
$page = $page ?? 'dashboard';
$pageTitle = $pageTitle ?? 'Chama administration';
$nav = [
    'dashboard' => 'Overview', 'members' => 'Members', 'contributions' => 'Contributions',
    'claims' => 'Payment claims', 'loans' => 'Loans', 'transactions' => 'Transactions',
    'opening' => 'Opening entries', 'audit' => 'Audit trail',
];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($pageTitle) ?> · Kingdom Sons SHG</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= h(app_url('assets/css/portal.css')) ?>">
</head><body class="portal-body"><div class="portal-shell"><aside class="portal-sidebar" id="portal-sidebar">
<a class="portal-brand" href="<?= h(app_url('admin/chama/')) ?>">Kingdom Sons<small>Administration</small></a>
<nav class="portal-nav" aria-label="Chama administration"><?php foreach($nav as $key=>$label): ?><a class="<?= $page===$key?'active':'' ?>" href="<?= h(app_url('admin/chama/index.php'.($key==='dashboard'?'':'?page='.$key))) ?>"><?= h($label) ?></a><?php endforeach; ?></nav>
<div class="portal-sidebar-foot"><strong><?= h($admin['full_name']) ?></strong><br>Administrator<a class="admin-switch" href="<?= h(app_url('portal/')) ?>">Open my member portal →</a><a class="admin-switch" href="<?= h(app_url('admin/dashboard.php')) ?>">Website content admin →</a><a class="admin-switch" href="<?= h(app_url('portal/logout.php')) ?>">Sign out</a></div>
</aside><main class="portal-main"><header class="portal-topbar"><div><h1 class="portal-title"><?= h($pageTitle) ?></h1><p class="portal-subtitle">Kingdom Sons Self Help Group</p></div><button class="portal-menu-btn" type="button" aria-controls="portal-sidebar" aria-expanded="false">Menu</button></header>
<?php foreach(pull_flashes() as $message): ?><div class="portal-alert <?= h($message['type']) ?>" role="alert"><?= h($message['message']) ?></div><?php endforeach; ?>
