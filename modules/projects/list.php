<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = get_db();
    $current_user = get_current_user_id();
    $access_where = get_accessible_records('projects');

    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
    $search = $_GET['search']['value'] ?? '';
    $order_col = $_GET['order'][0]['column'] ?? 5;
    $order_dir = (($_GET['order'][0]['dir'] ?? 'desc') === 'desc') ? 'DESC' : 'ASC';

    $cols = [
        0 => 'p.name',
        1 => 'p.start_date',
        2 => 'p.end_date',
        3 => 'p.status',
        4 => 'u.full_name',
        5 => 'p.created_at'
    ];
    $order_by = $cols[$order_col] ?? 'p.created_at';

    $where = '';
    $params = [];
    if ($access_where) {
        $where = ' ' . $access_where;
        $qmarks = substr_count($where, '?');
        for ($i = 0; $i < $qmarks; $i++) $params[] = $current_user;
    }

    $total_sql = "SELECT COUNT(*) as cnt FROM projects p" . ($where ? str_replace('WHERE', ' WHERE', $where) : '');
    $total_result = $db->fetch($total_sql, $params);
    $recordsTotal = intval($total_result['cnt'] ?? 0);

    $filter_sql = "SELECT p.id, p.name, p.description, p.start_date, p.end_date, p.status, p.assigned_to, u.full_name as assigned_name, p.created_at FROM projects p LEFT JOIN users u ON p.assigned_to = u.id" . ($where ?: '');
    $filter_params = $params;
    if (!empty($search)) {
        $filter_sql .= $where ? ' AND ' : ' WHERE ';
        $filter_sql .= "(p.name LIKE ? OR p.description LIKE ?)";
        $sparam = "%{$search}%";
        $filter_params = array_merge($filter_params, [$sparam, $sparam]);
    }

    $count_sql = "SELECT COUNT(*) as cnt FROM (" . $filter_sql . ") tmp";
    $count_result = $db->fetch($count_sql, $filter_params);
    $recordsFiltered = intval($count_result['cnt'] ?? 0);

    $filter_sql .= " ORDER BY {$order_by} {$order_dir} LIMIT ?, ?";
    $filter_params[] = $start;
    $filter_params[] = $length;

    $rows = $db->fetchAll($filter_sql, $filter_params);

    $out = [
        'draw' => intval($_GET['draw'] ?? 0),
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $rows
    ];
    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('projects/list.php error: ' . $e->getMessage());
    echo json_encode(['data' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0]);
}

?>
