<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = get_db();
    $current_user = get_current_user_id();

    // DataTables params
    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
    $search = $_GET['search']['value'] ?? '';
    $order_col = $_GET['order'][0]['column'] ?? 5;
    $order_dir = (($_GET['order'][0]['dir'] ?? 'desc') === 'desc') ? 'DESC' : 'ASC';

    $cols = [0=>'full_name',1=>'username',2=>'email',3=>'role',4=>'is_active',5=>'created_at'];
    $order_by = $cols[$order_col] ?? 'created_at';

    // Total
    $total_sql = "SELECT COUNT(*) as cnt FROM users";
    $total_result = $db->fetch($total_sql);
    $recordsTotal = intval($total_result['cnt'] ?? 0);

    // Filter
    $filter_sql = "SELECT id, username, email, full_name, role, is_active, created_at FROM users";
    $filter_params = [];
    if (!empty($search)) {
        $filter_sql .= ' WHERE (username LIKE ? OR email LIKE ? OR full_name LIKE ?)';
        $s = "%{$search}%";
        $filter_params = [$s,$s,$s];
    }

    // Count filtered
    $count_sql = "SELECT COUNT(*) as cnt FROM (" . $filter_sql . ") tmp";
    $count_result = $db->fetch($count_sql, $filter_params);
    $recordsFiltered = intval($count_result['cnt'] ?? 0);

    // Ordering and limit
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
    error_log('users/list.php error: '.$e->getMessage());
    echo json_encode(['data'=>[], 'recordsTotal'=>0, 'recordsFiltered'=>0]);
}

?>
