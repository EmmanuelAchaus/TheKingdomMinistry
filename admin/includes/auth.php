<?php

require_once dirname(__DIR__, 2) . '/includes/app.php';

$portalAdmin = current_user();
if ($portalAdmin && $portalAdmin['role'] === 'admin' && $portalAdmin['account_status'] === 'active') {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = (int) $portalAdmin['id'];
    $_SESSION['admin_username'] = $portalAdmin['phone'];
} else {
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_id'], $_SESSION['admin_username']);
    redirect('admin/login.php');
}
