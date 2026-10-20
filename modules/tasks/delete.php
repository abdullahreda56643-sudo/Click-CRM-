<?php
if (!defined('CRM_CONFIG')) require_once __DIR__ . '/../../config/config.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
require_permission('tasks', 'delete');

if ($id) {
    $db = get_db();
    if (!can_access_record('tasks', $id, 'delete')) {
        $_SESSION['error'] = 'ليس لديك صلاحية حذف هذه المهمة.';
    } else {
        // fetch task for notification
        $task = $db->fetch('SELECT id, title, assigned_to, created_by FROM tasks WHERE id = ?', [$id]);
        $db->delete('tasks', 'id = ?', [$id]);
        $_SESSION['success'] = 'تم حذف المهمة.';
        $name = $task['title'] ?? $id;
        $title = 'حذف مهمة: ' . $name;
        $message = 'تم حذف المهمة: ' . $name . ' من قبل: ' . (get_current_user_id() ? get_user_info(get_current_user_id())['full_name'] : '-') . '.';
        if (!empty($task['assigned_to'])) {
            create_notification($task['assigned_to'], $title, $message, 'info', 'task', $id);
        }
        notify_managers($title, $message, 'task', $id);
    }
}
redirect('modules/tasks/index.php');
