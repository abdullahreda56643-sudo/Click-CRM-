<?php
// AJAX endpoint for marking all notifications as read
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

if (!$input) {
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
    $user_id = get_current_user_id();
    
    // Mark all notifications as read for this user. Support older schemas without `read_at`.
    $db = get_db();
    try {
        $result = $db->query(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0",
            [$user_id]
        );
    } catch (Throwable $e_inner) {
        $msg = $e_inner->getMessage();
        if (stripos($msg, 'Unknown column') !== false && stripos($msg, 'read_at') !== false) {
            try {
                $result = $db->query(
                    "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0",
                    [$user_id]
                );
            } catch (Throwable $e_retry) {
                throw $e_retry;
            }
        } else {
            throw $e_inner;
        }
    }
    
    $buf = ob_get_clean();
    if (!empty($buf)) error_log('mark_all_notifications_read unexpected output: ' . $buf);
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to mark notifications as read']);
    }
} catch (Throwable $e) {
    $buf = ob_get_clean();
    if (!empty($buf)) error_log('mark_all_notifications_read unexpected output: ' . $buf);
    error_log('mark_all_notifications_read exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
?>
