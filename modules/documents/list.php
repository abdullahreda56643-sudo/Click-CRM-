<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = get_db();
    $current_user = get_current_user_id();
    $access_where = get_accessible_records('documents');

    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
    // Support DataTables format or simple GET ?search= for cards view
    $search = '';
    if (isset($_GET['search']) && is_array($_GET['search'])) {
        $search = $_GET['search']['value'] ?? '';
    } else {
        $search = $_GET['search'] ?? '';
    }
    // Filters
    $filter_related_type = $_GET['related_type'] ?? null;
    $filter_uploaded_by = isset($_GET['uploaded_by']) && $_GET['uploaded_by'] !== '' ? intval($_GET['uploaded_by']) : null;
    $filter_file_type = $_GET['file_type'] ?? null;
    $filter_date_from = $_GET['date_from'] ?? null;
    $filter_date_to = $_GET['date_to'] ?? null;
    $order_col = $_GET['order'][0]['column'] ?? 4;
    $order_dir = (($_GET['order'][0]['dir'] ?? 'desc') === 'desc') ? 'DESC' : 'ASC';

    $cols = [
        0 => 'd.original_name',
        1 => 'd.file_type',
        2 => 'd.file_size',
        3 => 'u.full_name',
        4 => 'd.created_at'
    ];
    $order_by = $cols[$order_col] ?? 'd.created_at';

    $where = '';
    $params = [];
    if ($access_where) {
        $where = ' ' . $access_where;
        $qmarks = substr_count($where, '?');
        for ($i = 0; $i < $qmarks; $i++) $params[] = $current_user;
    }

    $total_sql = "SELECT COUNT(*) as cnt FROM documents d" . ($where ? str_replace('WHERE', ' WHERE', $where) : '');
    $total_result = $db->fetch($total_sql, $params);
    $recordsTotal = intval($total_result['cnt'] ?? 0);

    $filter_sql = "SELECT d.id, d.filename, d.original_name, d.file_type, d.file_size, d.description, d.sha256, d.related_type, d.related_id, d.uploaded_by, u.full_name as uploader_name, d.created_at FROM documents d LEFT JOIN users u ON d.uploaded_by = u.id" . ($where ?: '');
    $filter_params = $params;

    // Additional filters
    $extra_clauses = [];
    if (!empty($filter_related_type)) {
        $extra_clauses[] = "d.related_type = ?";
        $filter_params[] = $filter_related_type;
    }
    if (!empty($filter_uploaded_by)) {
        $extra_clauses[] = "d.uploaded_by = ?";
        $filter_params[] = $filter_uploaded_by;
    }
    if (!empty($filter_file_type)) {
        // map common type filters to SQL checks
        $ft = strtolower($filter_file_type);
        if ($ft === 'img') {
            $extra_clauses[] = "d.file_type LIKE 'image/%'";
        } elseif ($ft === 'pdf') {
            $extra_clauses[] = "d.file_type LIKE ?";
            $filter_params[] = "%pdf%";
        } elseif ($ft === 'doc') {
            $extra_clauses[] = "(LOWER(d.original_name) LIKE '%.doc' OR LOWER(d.original_name) LIKE '%.docx' OR LOWER(d.filename) LIKE '%.doc' OR LOWER(d.filename) LIKE '%.docx')";
        } elseif ($ft === 'xls') {
            $extra_clauses[] = "(LOWER(d.original_name) LIKE '%.xls' OR LOWER(d.original_name) LIKE '%.xlsx' OR LOWER(d.filename) LIKE '%.xls' OR LOWER(d.filename) LIKE '%.xlsx')";
        } else {
            $extra_clauses[] = "d.file_type LIKE ?";
            $filter_params[] = "%" . $filter_file_type . "%";
        }
    }
    if (!empty($filter_date_from)) {
        $extra_clauses[] = "d.created_at >= ?";
        $filter_params[] = $filter_date_from . ' 00:00:00';
    }
    if (!empty($filter_date_to)) {
        $extra_clauses[] = "d.created_at <= ?";
        $filter_params[] = $filter_date_to . ' 23:59:59';
    }

    if (!empty($extra_clauses)) {
        // decide connector depending on whether base query already has a WHERE from access control
        if (!empty($where)) {
            $filter_sql .= ' AND ' . implode(' AND ', $extra_clauses);
        } else {
            $filter_sql .= ' WHERE ' . implode(' AND ', $extra_clauses);
        }
    }
    if (!empty($search)) {
        $connector = (stripos($filter_sql, ' WHERE ') !== false) ? ' AND ' : ' WHERE ';
        $filter_sql .= $connector;
        $filter_sql .= "(d.original_name LIKE ? OR d.filename LIKE ? OR d.file_type LIKE ? OR u.full_name LIKE ?)";
        $sparam = "%{$search}%";
        $filter_params = array_merge($filter_params, [$sparam, $sparam, $sparam, $sparam]);
    }

    $count_sql = "SELECT COUNT(*) as cnt FROM (" . $filter_sql . ") tmp";
    $count_result = $db->fetch($count_sql, $filter_params);
    $recordsFiltered = intval($count_result['cnt'] ?? 0);

    $filter_sql .= " ORDER BY {$order_by} {$order_dir} LIMIT ?, ?";
    $filter_params[] = $start;
    $filter_params[] = $length;

    $rows = $db->fetchAll($filter_sql, $filter_params);

    // If requested as cards view, return simple JSON array
    if (isset($_GET['view']) && $_GET['view'] === 'cards') {
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $out = [
        'draw' => intval($_GET['draw'] ?? 0),
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $rows
    ];
    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('documents/list.php error: ' . $e->getMessage());
    echo json_encode(['data' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0]);
}

?>
