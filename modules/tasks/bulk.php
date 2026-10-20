<?php
// Bulk actions for tasks (change status, assign, delete)
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

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
        case 'change_status':
            require_permission('tasks', 'edit');
            $value = $payload['value'] ?? null;
            if ($value === null) throw new Exception('لم يتم تحديد الحالة');

            // filter ids by access
            $allowed = [];
            foreach ($ids as $i) {
                if (can_access_record('tasks', $i, 'edit')) $allowed[] = $i;
            }
            if (empty($allowed)) throw new Exception('لا توجد مهام مصرح لك بتعديلها');

            $placeholders = implode(',', array_fill(0, count($allowed), '?'));
            $sql = "UPDATE tasks SET status = ?, updated_at = ? WHERE id IN ($placeholders)";
            $params = array_merge([$value, date('Y-m-d H:i:s')], $allowed);
            $db->query($sql, $params);
            foreach ($allowed as $id) {
                log_audit('task_status_changed', 'tasks', $id, "Status changed to $value");
                notify_task_status_changed($id, null, null, $value);
            }
            $buf = ob_get_clean(); if (!empty($buf)) error_log('tasks/bulk.php unexpected output: ' . $buf);
            echo json_encode(['success' => true, 'message' => 'تم تحديث الحالة']);
            exit;
            break;

        case 'assign_to':
            require_permission('tasks', 'edit');
            $value = $payload['value'] ?? null;
            if ($value === null) throw new Exception('لم يتم تحديد معرف المستخدم');

            // validate user exists
            $u = $db->fetch('SELECT id FROM users WHERE id = ?', [$value]);
            if (!$u) throw new Exception('المستخدم المحدد غير صالح');

            // filter ids by access
            $allowed = [];
            foreach ($ids as $i) {
                if (can_access_record('tasks', $i, 'edit')) $allowed[] = $i;
            }
            if (empty($allowed)) throw new Exception('لا توجد مهام مصرح لك بتعديلها');

            $placeholders = implode(',', array_fill(0, count($allowed), '?'));
            $sql = "UPDATE tasks SET assigned_to = ?, updated_at = ? WHERE id IN ($placeholders)";
            $params = array_merge([$value, date('Y-m-d H:i:s')], $allowed);
            $db->query($sql, $params);
            foreach ($allowed as $id) {
                log_audit('task_assigned', 'tasks', $id, "Assigned to user $value");
                notify_new_task($id, $value);
            }
            $buf = ob_get_clean(); if (!empty($buf)) error_log('tasks/bulk.php unexpected output: ' . $buf);
            echo json_encode(['success' => true, 'message' => 'تم تعيين المسؤول']);
            exit;
            break;

        case 'delete':
            require_permission('tasks', 'delete');
            // filter ids by access
            $allowed = [];
            foreach ($ids as $i) {
                if (can_access_record('tasks', $i, 'delete')) $allowed[] = $i;
            }
            if (empty($allowed)) throw new Exception('لا توجد مهام مصرح لك بحذفها');

            $placeholders = implode(',', array_fill(0, count($allowed), '?'));
            $db->delete('tasks', "id IN ($placeholders)", $allowed);
            foreach ($allowed as $id) log_audit('task_deleted', 'tasks', $id, "Task deleted");
            $buf = ob_get_clean(); if (!empty($buf)) error_log('tasks/bulk.php unexpected output: ' . $buf);
            echo json_encode(['success' => true, 'message' => 'تم حذف المهام']);
            exit;
            break;

        default:
            throw new Exception('إجراء غير معروف');
    }

} catch (Exception $e) {
    $buf = ob_get_clean();
    if (!empty($buf)) error_log('tasks/bulk.php unexpected output: ' . $buf);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
