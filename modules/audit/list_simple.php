<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Expect simplified POST fields to avoid complex query strings that trigger WAF
// Fields: ajax=1, start, length, search_value, order_column, order_dir, draw, csrf_token (optional)
// Diagnostic logging: capture incoming request details to help debug 403/blocks
$diag_file = __DIR__ . '/../../logs/debug_audit_list.log';
$diag_entry = [
    'time' => date('c'),
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
    'headers' => function_exists('getallheaders') ? getallheaders() : null,
    'cookie_header' => $_SERVER['HTTP_COOKIE'] ?? null,
    'post' => $_POST,
    'request' => $_REQUEST,
    'raw_input' => file_get_contents('php://input'),
    'session_vars' => isset($_SESSION) ? array_intersect_key($_SESSION, array_flip(['user_id','username','user_role'])) : null
];
@file_put_contents($diag_file, json_encode($diag_entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
if (!isset($_REQUEST['ajax']) || $_REQUEST['ajax'] != 1) {
    echo json_encode(['error' => 'invalid_request']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated']);
    exit;
}

if (!check_permission('audit', 'view')) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

// Optional CSRF validation if token provided
if (!empty($_REQUEST['csrf_token']) && function_exists('validate_csrf_token')) {
    if (!validate_csrf_token($_REQUEST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'invalid_csrf']);
        exit;
    }
}

try {
    $db = get_db();

    $start = isset($_REQUEST['start']) ? intval($_REQUEST['start']) : 0;
    $length = isset($_REQUEST['length']) ? intval($_REQUEST['length']) : 10;
    $search = trim($_REQUEST['search_value'] ?? '');
    $order_col = isset($_REQUEST['order_column']) ? intval($_REQUEST['order_column']) : 6;
    $order_dir = (isset($_REQUEST['order_dir']) && strtolower($_REQUEST['order_dir']) === 'asc') ? 'ASC' : 'DESC';

    $cols = [
        0 => 'u.full_name',
        1 => 'a.action',
        2 => 'a.table_name',
        3 => 'a.record_id',
        4 => 'a.description',
        5 => 'a.ip_address',
        6 => 'a.created_at'
    ];

    $order_by = $cols[$order_col] ?? 'a.created_at';

    // Total count
    $total_sql = "SELECT COUNT(*) as cnt FROM audit_log a";
    $total_result = $db->fetch($total_sql);
    $recordsTotal = intval($total_result['cnt'] ?? 0);

    // Base query
    $base_sql = "SELECT a.id, a.user_id, u.full_name as user_name, a.action, a.table_name, a.record_id, a.description, a.ip_address, a.created_at FROM audit_log a LEFT JOIN users u ON a.user_id = u.id";
    $params = [];

    if (!empty($search)) {
        $base_sql .= ' WHERE (a.action LIKE ? OR a.table_name LIKE ? OR a.description LIKE ? OR u.full_name LIKE ? OR a.ip_address LIKE ? )';
        $sparam = "%{$search}%";
        $params = [$sparam, $sparam, $sparam, $sparam, $sparam];
    }

    // Filtered count
    $count_sql = "SELECT COUNT(*) as cnt FROM (" . $base_sql . ") tmp";
    $count_result = $db->fetch($count_sql, $params);
    $recordsFiltered = intval($count_result['cnt'] ?? 0);

    // Ordering and limit
    $base_sql .= " ORDER BY {$order_by} {$order_dir} LIMIT ?, ?";
    $params[] = $start;
    $params[] = $length;

    $rows = $db->fetchAll($base_sql, $params);

    $out = [
        'draw' => intval($_REQUEST['draw'] ?? 0),
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $rows
    ];

    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('audit/list_simple.php error: ' . $e->getMessage());
    echo json_encode(['data' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0]);
}

?>
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Expect simplified POST fields to avoid complex query strings that trigger WAF
// Fields: ajax=1, start, length, search_value, order_column, order_dir, draw, csrf_token (optional)
if (!isset($_REQUEST['ajax']) || $_REQUEST['ajax'] != 1) {
    echo json_encode(['error' => 'invalid_request']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated']);
    exit;
}

if (!check_permission('audit', 'view')) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

// Optional CSRF validation if token provided
if (!empty($_REQUEST['csrf_token']) && function_exists('validate_csrf_token')) {
    if (!validate_csrf_token($_REQUEST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'invalid_csrf']);
        exit;
    }
}

try {
    $db = get_db();

    $start = isset($_REQUEST['start']) ? intval($_REQUEST['start']) : 0;
    $length = isset($_REQUEST['length']) ? intval($_REQUEST['length']) : 10;
    $search = trim($_REQUEST['search_value'] ?? '');
    $order_col = isset($_REQUEST['order_column']) ? intval($_REQUEST['order_column']) : 6;
    $order_dir = (isset($_REQUEST['order_dir']) && strtolower($_REQUEST['order_dir']) === 'asc') ? 'ASC' : 'DESC';

    $cols = [
        0 => 'u.full_name',
        1 => 'a.action',
        2 => 'a.table_name',
        3 => 'a.record_id',
        4 => 'a.description',
        5 => 'a.ip_address',
        6 => 'a.created_at'
    ];

    $order_by = $cols[$order_col] ?? 'a.created_at';

    // Total count
    $total_sql = "SELECT COUNT(*) as cnt FROM audit_log a";
    $total_result = $db->fetch($total_sql);
    $recordsTotal = intval($total_result['cnt'] ?? 0);

    // Base query
    $base_sql = "SELECT a.id, a.user_id, u.full_name as user_name, a.action, a.table_name, a.record_id, a.description, a.ip_address, a.created_at FROM audit_log a LEFT JOIN users u ON a.user_id = u.id";
    $params = [];

    if (!empty($search)) {
        $base_sql .= ' WHERE (a.action LIKE ? OR a.table_name LIKE ? OR a.description LIKE ? OR u.full_name LIKE ? OR a.ip_address LIKE ? )';
        $sparam = "%{$search}%";
        $params = [$sparam, $sparam, $sparam, $sparam, $sparam];
    }

    // Filtered count
    $count_sql = "SELECT COUNT(*) as cnt FROM (" . $base_sql . ") tmp";
    $count_result = $db->fetch($count_sql, $params);
    $recordsFiltered = intval($count_result['cnt'] ?? 0);

    // Ordering and limit
    $base_sql .= " ORDER BY {$order_by} {$order_dir} LIMIT ?, ?";
    $params[] = $start;
    $params[] = $length;

    $rows = $db->fetchAll($base_sql, $params);

    $out = [
        'draw' => intval($_REQUEST['draw'] ?? 0),
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $rows
    ];

    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('audit/list_simple.php error: ' . $e->getMessage());
    echo json_encode(['data' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0]);
}

?>
