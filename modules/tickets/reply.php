<?php
// Reply to ticket (AJAX JSON)
if (!defined('CRM_CONFIG')) require_once __DIR__ . '/../../config/config.php';

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$ticket_id = intval($input['ticket_id'] ?? 0);
$message = clean_input($input['message'] ?? '');
$csrf = $input['csrf_token'] ?? '';

if (!validate_csrf_token($csrf)) { echo json_encode(['success'=>false,'message'=>'رمز CSRF غير صالح']); exit; }
if (!$ticket_id || empty($message)) { echo json_encode(['success'=>false,'message'=>'بيانات غير كافية']); exit; }

try {
    $db = get_db();
    if (!can_access_record('tickets',$ticket_id,'view')) { echo json_encode(['success'=>false,'message'=>'غير مصرح']); exit; }
    $db->insert('ticket_replies', [ 'ticket_id'=>$ticket_id, 'message'=>$message, 'is_internal'=>0, 'created_by'=>get_current_user_id(), 'created_at'=>date('Y-m-d H:i:s') ]);
    log_audit('reply','ticket_replies',$ticket_id,'Replied to ticket');
    // notify assigned
    $t = $db->fetch('SELECT assigned_to FROM tickets WHERE id = ?', [$ticket_id]);
    if (!empty($t['assigned_to'])) create_notification($t['assigned_to'],'رد جديد','يوجد رد جديد على التذكرة','info','ticket',$ticket_id);
    echo json_encode(['success'=>true,'message'=>'تم إضافة الرد']);
} catch (Exception $e) {
    error_log($e->getMessage()); echo json_encode(['success'=>false,'message'=>'خطأ']);
}

?>
