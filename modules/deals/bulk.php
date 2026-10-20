<?php
// Bulk actions for deals (change stage, assign, delete)
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Disable display of errors for AJAX responses and buffer output
ini_set('display_errors', 0);
ob_start();

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload) throw new Exception('البيانات المرسلة غير صالحة');

    $csrf = $payload['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'رمز CSRF غير صالح']);
        exit;
    }

    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'غير مصرح بالوصول']);
        exit;
    }

    $action = $payload['action'] ?? '';
    $ids = $payload['ids'] ?? [];
    if (!is_array($ids)) $ids = [$ids];
    if (empty($ids)) throw new Exception('لم يتم توفير معرفات');

    $db = get_db();

    switch ($action) {
        case 'change_stage':
            $value = $payload['value'] ?? null;
            if ($value === null) throw new Exception('لم يتم تحديد قيمة المرحلة');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE deals SET stage = ?, updated_at = ? WHERE id IN ($placeholders)";
            $params = array_merge([$value, date('Y-m-d H:i:s')], $ids);
            $db->query($sql, $params);
            foreach ($ids as $id) log_audit('deal_stage_changed', 'deals', $id, "Stage changed to $value");
            $buf = ob_get_clean(); if (!empty($buf)) error_log('bulk.php unexpected output: ' . $buf);
            echo json_encode(['success' => true, 'message' => 'تم تحديث المرحلة']);
            exit;
            break;

        case 'assign_to':
            $value = $payload['value'] ?? null;
            if ($value === null) throw new Exception('لم يتم تحديد معرف المستخدم');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE deals SET assigned_to = ?, updated_at = ? WHERE id IN ($placeholders)";
            $params = array_merge([$value, date('Y-m-d H:i:s')], $ids);
            $db->query($sql, $params);
            foreach ($ids as $id) log_audit('deal_assigned', 'deals', $id, "Assigned to user $value");
            $buf = ob_get_clean(); if (!empty($buf)) error_log('bulk.php unexpected output: ' . $buf);
            echo json_encode(['success' => true, 'message' => 'تم تعيين المسؤول']);
            exit;
            break;

        case 'delete':
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            // Use delete helper
            $db->delete('deals', "id IN ($placeholders)", $ids);
            foreach ($ids as $id) log_audit('deal_deleted', 'deals', $id, "Deal deleted");
            $buf = ob_get_clean(); if (!empty($buf)) error_log('bulk.php unexpected output: ' . $buf);
            echo json_encode(['success' => true, 'message' => 'تم حذف الصفقات']);
            exit;
            break;

        default:
            throw new Exception('إجراء غير معروف');
    }

} catch (Exception $e) {
    $buf = ob_get_clean();
    if (!empty($buf)) error_log('bulk.php unexpected output: ' . $buf);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
