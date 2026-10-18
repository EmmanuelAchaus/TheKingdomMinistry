<?php

require_once dirname(__DIR__) . '/includes/app.php';

$user = current_user();
if ($user && $user['role'] === 'admin' && $user['account_status'] === 'active') {
    redirect('admin/chama/');
}

flash('warning', 'Administrators now use the secure member sign-in.');
redirect('portal/login.php');
