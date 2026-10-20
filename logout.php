<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

// Logout the user
logout_user();

// Clear remember me cookie if exists
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
    unset($_COOKIE['remember_me']);
}

// Redirect to login page with success message
$_SESSION['success'] = 'تم تسجيل الخروج بنجاح';
redirect('login.php');
?>