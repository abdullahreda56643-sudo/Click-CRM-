<?php
// Delete ticket (AJAX JSON)
if (!defined('CRM_CONFIG')) require_once __DIR__ . '/../../config/config.php';

// Buffer unexpected output to ensure we always return clean JSON
if (session_status() === PHP_SESSION_NONE) session_start();
ob_start();

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_permission('tickets', 'delete');

function send_json($data, $http_code = 200) {
    $buf = ob_get_clean();
    if ($buf) {
        error_log("modules/tickets/delete.php - unexpected output: " . $buf);
    }
    http_response_code($http_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? ($_POST['id'] ?? 0));
$token = $input['csrf_token'] ?? ($_POST['csrf_token'] ?? '');

if (!validate_csrf_token($token)) { send_json(['success'=>false,'message'=>'رمز CSRF غير صالح'], 400); }
if (!$id) { send_json(['success'=>false,'message'=>'معرّف غير صالح'], 400); }

if (!can_access_record('tickets', $id, 'delete')) { send_json(['success'=>false,'message'=>'ليس لديك صلاحية حذف هذه التذكرة'], 403); }

try {
    $db = get_db();
    $ticket = $db->fetch('SELECT id, subject, assigned_to FROM tickets WHERE id = ?', [$id]);
    $deleted = $db->delete('tickets', 'id = ?', [$id]);
    if ($deleted) {
        log_audit('delete', 'tickets', $id, 'Deleted ticket');
        if (!empty($ticket['assigned_to']) && function_exists('create_notification')) {
            create_notification($ticket['assigned_to'], 'تم حذف التذكرة', 'تم حذف التذكرة: '.$ticket['subject'], 'info', 'ticket', $id);
        }
        if (function_exists('notify_managers')) {
            notify_managers('حذف تذكرة', 'تم حذف تذكرة: ' . ($ticket['subject'] ?? $id), 'ticket', $id);
        }
        send_json(['success'=>true,'message'=>'تم حذف التذكرة'], 200);
    }
    send_json(['success'=>false,'message'=>'فشل الحذف'], 500);
} catch (Exception $e) {
    error_log('modules/tickets/delete.php exception: ' . $e->getMessage());
    send_json(['success'=>false,'message'=>'خطأ في الخادم'], 500);
}

?>
