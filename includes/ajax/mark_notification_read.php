<?php
// AJAX endpoint for marking notification as read
// Prevent direct access
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

// Ensure core helpers are available for CSRF and DB helpers
require_once __DIR__ . '/../functions.php';

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

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['notification_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Validate CSRF token
if (!isset($input['csrf_token']) || !validate_csrf_token($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

try {
    $notification_id = (int)$input['notification_id'];
    $user_id = get_current_user_id();
    
    // Mark notification as read. Some installations may not have `read_at` column
    $db = get_db();
    try {
        $result = $db->query(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?",
            [$notification_id, $user_id]
        );
    } catch (Throwable $e_inner) {
        // If column `read_at` doesn't exist, retry without it
        $msg = $e_inner->getMessage();
        if (stripos($msg, 'Unknown column') !== false && stripos($msg, 'read_at') !== false) {
            try {
                $result = $db->query(
                    "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?",
                    [$notification_id, $user_id]
                );
            } catch (Throwable $e_retry) {
                throw $e_retry;
            }
        } else {
            throw $e_inner;
        }
    }
    
    $buf = ob_get_clean();
    if (!empty($buf)) error_log('mark_notification_read unexpected output: ' . $buf);
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to mark notification as read']);
    }
} catch (Throwable $e) {
    $buf = ob_get_clean();
    if (!empty($buf)) error_log('mark_notification_read unexpected output: ' . $buf);
    error_log('mark_notification_read exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
?>
