<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('reports', 'view');

// Allow GET exports
$report = $_GET['report_type'] ?? 'contacts_summary';
$from = $_GET['from_date'] ?? null;
$to = $_GET['to_date'] ?? null;
$assigned = intval($_GET['assigned_to'] ?? 0) ?: null;

try {
    $db = get_db();
    $filename = 'report_' . $report . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    if ($report === 'contacts_summary') {
        fputcsv($out, ['الاسم','البريد الإلكتروني','الهاتف','الشركة','تاريخ الإنشاء']);
        $sql = 'SELECT CONCAT(first_name, " ", last_name) as name, email, phone, company, created_at FROM contacts WHERE 1=1';
        $params = [];
        if ($from) { $sql .= ' AND created_at >= ?'; $params[] = $from . ' 00:00:00'; }
        if ($to) { $sql .= ' AND created_at <= ?'; $params[] = $to . ' 23:59:59'; }
        if ($assigned) { $sql .= ' AND assigned_to = ?'; $params[] = $assigned; }
        $rows = $db->fetchAll($sql, $params);
        foreach ($rows as $r) fputcsv($out, [$r['name'],$r['email'],$r['phone'],$r['company'],$r['created_at']]);

    } elseif ($report === 'deals_by_status') {
        fputcsv($out, ['الحالة','العدد']);
        $sql = 'SELECT status, COUNT(*) as count FROM deals WHERE 1=1';
        $params = [];
        if ($from) { $sql .= ' AND created_at >= ?'; $params[] = $from . ' 00:00:00'; }
        if ($to) { $sql .= ' AND created_at <= ?'; $params[] = $to . ' 23:59:59'; }
        $sql .= ' GROUP BY status';
        $rows = $db->fetchAll($sql, $params);
        foreach ($rows as $r) fputcsv($out, [$r['status'],$r['count']]);

    } elseif ($report === 'tasks_overdue') {
        fputcsv($out, ['المهمة','المسؤول','تاريخ الاستحقاق','الحالة']);
        $sql = 'SELECT t.title, u.full_name as assigned_name, t.due_date, t.status FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.due_date < ? AND t.status != ?';
        $params = [ date('Y-m-d H:i:s'), 'completed' ];
        if ($assigned) { $sql .= ' AND t.assigned_to = ?'; $params[] = $assigned; }
        $rows = $db->fetchAll($sql, $params);
        foreach ($rows as $r) fputcsv($out, [$r['title'],$r['assigned_name'],$r['due_date'],$r['status']]);

    } else {
        fputcsv($out, ['Unknown report']);
    }

    fclose($out);
    exit;

} catch (Exception $e) {
    error_log('reports/export.php error: ' . $e->getMessage());
    echo 'خطأ في التصدير';
}

?>
