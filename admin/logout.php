<?php
session_start();
session_unset();
session_destroy();
header('Location: /TheKingdomMinistry 2/admin/login.php');
exit;
?>
