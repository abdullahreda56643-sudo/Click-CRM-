<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = get_db();
    $rows = $db->fetchAll("SELECT t.*, c.first_name, c.last_name, u.full_name as assigned_name FROM tickets t LEFT JOIN contacts c ON t.contact_id = c.id LEFT JOIN users u ON t.assigned_to = u.id ORDER BY t.created_at DESC");

    $priority_labels = [ 'low' => 'منخفض', 'medium' => 'متوسط', 'high' => 'عالي', 'urgent' => 'عاجل' ];
    $status_labels = [ 'open' => 'مفتوح', 'in_progress' => 'قيد العمل', 'resolved' => 'محلول', 'closed' => 'مغلق' ];

    foreach ($rows as &$r) {
        $r['contact_name'] = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: null;
        $r['priority_label'] = $priority_labels[$r['priority']] ?? $r['priority'];
        $r['status_label'] = $status_labels[$r['status']] ?? $r['status'];
        // For compatibility with DataTable in index.php
        $r['priority'] = $r['priority_label'];
        $r['status'] = $r['status_label'];
    }

    echo json_encode(['data' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('tickets/list.php error: ' . $e->getMessage());
    echo json_encode(['data' => []]);
}

?>
