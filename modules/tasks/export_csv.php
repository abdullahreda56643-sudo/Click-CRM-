<?php
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';

require_permission('tasks', 'view');

$db = get_db();

$ids = isset($_GET['ids']) ? $_GET['ids'] : null;
if ($ids) {
    if (!is_array($ids)) $ids = explode(',', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $tasks = $db->fetchAll("SELECT t.*, u.full_name as assigned_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.id IN ($placeholders)", $ids);
} else {
    $tasks = $db->fetchAll('SELECT t.*, u.full_name as assigned_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id ORDER BY t.created_at DESC');
}

$rows = [];
foreach ($tasks as $t) {
    $rows[] = [
        'ID' => $t['id'],
        'Title' => $t['title'],
        'Description' => $t['description'],
        'Due Date' => $t['due_date'],
        'Priority' => $t['priority'],
        'Assigned To' => $t['assigned_name'] ?? '',
        'Status' => $t['status'] ?? '',
        'Created By' => $t['created_by'] ?? '',
        'Created At' => $t['created_at'] ?? '',
    ];
}

export_to_csv($rows, 'tasks_export_' . date('Ymd_His') . '.csv', array_keys($rows[0] ?? []));

?>
