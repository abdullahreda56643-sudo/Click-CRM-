<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid method']); exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['error' => 'Invalid CSRF']); exit;
}

require_permission('reports', 'view');

try {
    $db = get_db();
    $report = $_POST['report_type'] ?? 'contacts_summary';
    $from = $_POST['from_date'] ?? null;
    $to = $_POST['to_date'] ?? null;
    $assigned = intval($_POST['assigned_to'] ?? 0) ?: null;

    $out = ['columns'=>[], 'data'=>[]];

    if ($report === 'contacts_summary') {
        $out['columns'] = [ ['key'=>'name','label'=>'الاسم'], ['key'=>'email','label'=>'البريد الإلكتروني'], ['key'=>'phone','label'=>'الهاتف'], ['key'=>'company','label'=>'الشركة'], ['key'=>'created_at','label'=>'تاريخ الإنشاء'] ];

        $sql = 'SELECT CONCAT(first_name, " ", last_name) as name, email, phone, company, created_at FROM contacts WHERE 1=1';
        $params = [];
        if ($from) { $sql .= ' AND created_at >= ?'; $params[] = $from . ' 00:00:00'; }
        if ($to) { $sql .= ' AND created_at <= ?'; $params[] = $to . ' 23:59:59'; }
        if ($assigned) { $sql .= ' AND assigned_to = ?'; $params[] = $assigned; }
        $sql .= ' ORDER BY created_at DESC LIMIT 1000';
        $rows = $db->fetchAll($sql, $params);
        $out['data'] = $rows;

    } elseif ($report === 'deals_by_status') {
        $out['columns'] = [ ['key'=>'status','label'=>'الحالة'], ['key'=>'count','label'=>'العدد'] ];
        $sql = 'SELECT status, COUNT(*) as count FROM deals WHERE 1=1';
        $params = [];
        if ($from) { $sql .= ' AND created_at >= ?'; $params[] = $from . ' 00:00:00'; }
        if ($to) { $sql .= ' AND created_at <= ?'; $params[] = $to . ' 23:59:59'; }
        $sql .= ' GROUP BY status';
        $rows = $db->fetchAll($sql, $params);
        $out['data'] = $rows;

    } elseif ($report === 'tasks_overdue') {
        $out['columns'] = [ ['key'=>'title','label'=>'المهمة'], ['key'=>'assigned_name','label'=>'المسؤول'], ['key'=>'due_date','label'=>'تاريخ الاستحقاق'], ['key'=>'status','label'=>'الحالة'] ];
        $sql = 'SELECT t.title, u.full_name as assigned_name, t.due_date, t.status FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.due_date < ? AND t.status != ?';
        $params = [ date('Y-m-d H:i:s'), 'completed' ];
        if ($assigned) { $sql .= ' AND t.assigned_to = ?'; $params[] = $assigned; }
        $sql .= ' ORDER BY t.due_date ASC LIMIT 1000';
        $rows = $db->fetchAll($sql, $params);
        $out['data'] = $rows;

    } else {
        echo json_encode(['error'=>'Unknown report']); exit;
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('reports/generate.php error: '.$e->getMessage());
    echo json_encode(['error'=>'Server error']);
}

?>
