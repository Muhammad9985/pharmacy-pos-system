<?php
require_once '../config/centralized_db.php';
require_once '../includes/centralized_auth.php';

$auth->logout();
header('Location: ../login.php');
exit;
?>