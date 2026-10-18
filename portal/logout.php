<?php
require_once dirname(__DIR__) . '/includes/app.php';
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
}
session_destroy();
session_id('');
session_start();
flash('success', 'You have been signed out.');
redirect('portal/login.php');
