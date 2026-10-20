<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (is_logged_in()) {
    // Redirect to dashboard
    redirect('modules/dashboard.php');
} else {
    // Redirect to login page
    redirect('login.php');
}
?>