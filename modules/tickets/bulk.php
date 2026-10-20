<?php
// Bulk actions for tickets
if (!defined('CRM_CONFIG')) require_once __DIR__ . '/../../config/config.php';

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

ini_set('display_errors', 0); ob_start();
header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload) throw new Exception('البيانات المرسلة غير صالحة');

    $csrf = $payload['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'رمز CSRF غير صالح']); exit; }
    if (!is_logged_in()) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'غير مصرح']); exit; }

    $action = $payload['action'] ?? '';
    $ids = $payload['ids'] ?? []; if (!is_array($ids)) $ids = [$ids]; if (empty($ids)) throw new Exception('لم يتم توفير معرفات');

    $db = get_db();

    switch ($action) {
        case 'change_status':
            require_permission('tickets','edit');
            $value = $payload['value'] ?? null; if ($value===null) throw new Exception('لم يتم تحديد الحالة');
            $allowed = []; foreach ($ids as $i) if (can_access_record('tickets',$i,'edit')) $allowed[]=$i;
            if (empty($allowed)) throw new Exception('لا توجد تذاكر مصرح لك بتعديلها');
            $placeholders = implode(',', array_fill(0,count($allowed),'?'));
            $sql = "UPDATE tickets SET status = ?, updated_at = ? WHERE id IN ($placeholders)";
            $params = array_merge([$value, date('Y-m-d H:i:s')], $allowed);
            $db->query($sql,$params);
            foreach ($allowed as $id) log_audit('ticket_status_changed','tickets',$id,"Status changed to $value");
            echo json_encode(['success'=>true,'message'=>'تم تحديث الحالة']); exit;

        case 'assign_to':
            require_permission('tickets','edit');
            $value = $payload['value'] ?? null; if ($value===null) throw new Exception('لم يتم تحديد المعرف');
            $u = $db->fetch('SELECT id FROM users WHERE id = ?', [$value]); if (!$u) throw new Exception('المستخدم غير صالح');
            $allowed = []; foreach ($ids as $i) if (can_access_record('tickets',$i,'edit')) $allowed[]=$i;
            if (empty($allowed)) throw new Exception('لا توجد تذاكر مصرح لك بتعديلها');
            $placeholders = implode(',', array_fill(0,count($allowed),'?'));
            $sql = "UPDATE tickets SET assigned_to = ?, updated_at = ? WHERE id IN ($placeholders)";
            $params = array_merge([$value, date('Y-m-d H:i:s')], $allowed);
            $db->query($sql,$params);
            foreach ($allowed as $id) { log_audit('ticket_assigned','tickets',$id,"Assigned to user $value"); create_notification($value,'تم تعيين تذكرة','تم تعيين تذكرة لك', 'info','ticket',$id); }
            echo json_encode(['success'=>true,'message'=>'تم التعيين']); exit;

        case 'delete':
            require_permission('tickets','delete');
            $allowed = []; foreach ($ids as $i) if (can_access_record('tickets',$i,'delete')) $allowed[]=$i;
            if (empty($allowed)) throw new Exception('لا توجد تذاكر مصرح لك بحذفها');
            $placeholders = implode(',', array_fill(0,count($allowed),'?'));
            $db->delete('tickets', "id IN ($placeholders)", $allowed);
            foreach ($allowed as $id) log_audit('ticket_deleted','tickets',$id,'Ticket deleted');
            echo json_encode(['success'=>true,'message'=>'تم حذف التذاكر']); exit;

        default:
            throw new Exception('إجراء غير معروف');
    }

} catch (Exception $e) {
    $buf = ob_get_clean(); if (!empty($buf)) error_log('tickets/bulk unexpected output: '.$buf);
    http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}

?>
