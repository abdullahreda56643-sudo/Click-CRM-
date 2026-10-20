<?php
// AJAX endpoint for getting unread notification count
// Prevent direct access
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

// Ensure core helpers and notifications are loaded for standalone AJAX calls
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../notifications.php';

// Disable display of errors for AJAX responses and buffer output
ini_set('display_errors', 0);
ob_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get unread count
try {
    if (function_exists('get_unread_notification_count')) {
        $unread_count = get_unread_notification_count();
    } else {
        // Fallback: query DB directly to avoid dependency on notifications.php
        try {
            $db = get_db();
            $user_id = null;
            if (function_exists('get_current_user_id')) {
                $user_id = get_current_user_id();
            } elseif (isset($_SESSION['user_id'])) {
                $user_id = $_SESSION['user_id'];
            }
            if ($user_id) {
                $row = $db->fetch("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0", [$user_id]);
                $unread_count = $row['cnt'] ?? 0;
            } else {
                $unread_count = 0;
            }
        } catch (Exception $ex) {
            error_log('get_unread_count fallback DB error: ' . $ex->getMessage());
            $unread_count = 0;
        }
    }
    // Clean any prior output (warnings/notices) so only valid JSON is returned
    $buf = ob_get_clean();
    if (!empty($buf)) {
        error_log('get_unread_count unexpected output: ' . $buf);
    }
    echo json_encode(['count' => $unread_count]);
} catch (Exception $e) {
    $buf = ob_get_clean();
    if (!empty($buf)) error_log('get_unread_count unexpected output: ' . $buf);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get unread count']);
}
?>
