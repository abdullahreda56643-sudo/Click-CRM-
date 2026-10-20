<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = get_db();
    $rows = $db->fetchAll("SELECT d.*, c.first_name, c.last_name, u.full_name as created_by_name FROM deals d LEFT JOIN contacts c ON d.contact_id = c.id LEFT JOIN users u ON d.created_by = u.id ORDER BY d.created_at DESC");

    $stage_labels = [
        'prospecting' => 'اكتشاف',
        'qualification' => 'تأهيل',
        'proposal' => 'عرض',
        'negotiation' => 'تفاوض',
        'closed_won' => 'مغلقة - فائزة',
        'closed_lost' => 'مغلقة - خاسرة'
    ];

    foreach ($rows as &$r) {
        $r['contact_name'] = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: null;
        $r['stage_label'] = $stage_labels[$r['stage']] ?? $r['stage'];
    }

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([]);
}

?>
