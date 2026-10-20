<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = get_db();
    $current_user = get_current_user_id();
    $access_where = get_accessible_records('contacts');

    // Server-side processing params
    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
    $search = $_GET['search']['value'] ?? '';
    $order_col = $_GET['order'][0]['column'] ?? 6;
    $order_dir = (($_GET['order'][0]['dir'] ?? 'desc') === 'desc') ? 'DESC' : 'ASC';

    // Column mapping (matches index.php columns)
    $cols = [
        0 => 'c.first_name',
        1 => 'c.email',
        2 => 'c.phone',
        3 => 'c.company',
        4 => 'c.type',
        5 => 'u.full_name',
        6 => 'c.created_at'
    ];
    $order_by = $cols[$order_col] ?? 'c.created_at';

    // Build where and params
    $where = '';
    $params = [];
    if ($access_where) {
        $where = ' ' . $access_where; // e.g. WHERE assigned_to = ?
        $qmarks = substr_count($where, '?');
        for ($i = 0; $i < $qmarks; $i++) $params[] = $current_user;
    }

    // Total
    $total_sql = "SELECT COUNT(*) as cnt FROM contacts c" . ($where ? str_replace('WHERE', ' WHERE', $where) : '');
    $total_result = $db->fetch($total_sql, $params);
    $recordsTotal = intval($total_result['cnt'] ?? 0);

    // Filter
    $filter_sql = "SELECT c.id, c.first_name, c.last_name, c.email, c.phone, c.company, c.type, c.assigned_to, u.full_name as assigned_name, c.created_at FROM contacts c LEFT JOIN users u ON c.assigned_to = u.id" . ($where ?: '');
    $filter_params = $params;
    if (!empty($search)) {
        $filter_sql .= $where ? ' AND ' : ' WHERE ';
        $filter_sql .= "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.company LIKE ? )";
        $sparam = "%{$search}%";
        $filter_params = array_merge($filter_params, [$sparam, $sparam, $sparam, $sparam]);
    }

    // Count filtered
    $count_sql = "SELECT COUNT(*) as cnt FROM (" . $filter_sql . ") tmp";
    $count_result = $db->fetch($count_sql, $filter_params);
    $recordsFiltered = intval($count_result['cnt'] ?? 0);

    // Add ordering and limit
    $filter_sql .= " ORDER BY {$order_by} {$order_dir} LIMIT ?, ?";
    $filter_params[] = $start;
    $filter_params[] = $length;

    $rows = $db->fetchAll($filter_sql, $filter_params);

    // Map type labels
    $type_labels = [ 'lead' => 'عميل محتمل', 'client' => 'عميل' ];
    foreach ($rows as &$r) {
        $r['type_label'] = $type_labels[$r['type']] ?? $r['type'];
    }

    $out = [
        'draw' => intval($_GET['draw'] ?? 0),
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $rows
    ];
    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('contacts/list.php error: ' . $e->getMessage());
    echo json_encode(['data' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0]);
}

?>
