<?php
require_once 'config/centralized_db.php';
require_once 'includes/centralized_auth.php';

// Redirect based on login status and role
if ($auth->isLoggedIn()) {
    if ($auth->hasRole('super_admin')) {
        header('Location: admin/super_dashboard.php');
    } elseif ($auth->hasRole('shop_admin')) {
        header('Location: admin/shop_dashboard.php');
    } else {
        header('Location: pos/index.php');
    }
} else {
    header('Location: login.php');
}
exit;
?>