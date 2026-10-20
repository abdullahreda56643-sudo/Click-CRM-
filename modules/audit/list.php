<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Compatibility: if DataTables sent complex GET arrays (old client), map them to simplified request fields
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET) && (isset($_GET['columns']) || strpos($_SERVER['QUERY_STRING'] ?? '', 'columns%5B') !== false)) {
    // Map common DataTables GET params into $_REQUEST for downstream logic
    $_REQUEST['ajax'] = $_GET['ajax'] ?? ($_REQUEST['ajax'] ?? 1);
    $_REQUEST['start'] = $_GET['start'] ?? ($_REQUEST['start'] ?? 0);
    $_REQUEST['length'] = $_GET['length'] ?? ($_REQUEST['length'] ?? 10);
    $_REQUEST['draw'] = $_GET['draw'] ?? ($_REQUEST['draw'] ?? 0);
    $_REQUEST['search_value'] = $_GET['search']['value'] ?? ($_REQUEST['search_value'] ?? '');
    if (isset($_GET['order'][0]['column'])) {
        $_REQUEST['order_column'] = $_GET['order'][0]['column'];
        $_REQUEST['order_dir'] = $_GET['order'][0]['dir'] ?? 'desc';
    }
    // log mapping for diagnostics
    @file_put_contents(__DIR__ . '/../../logs/debug_audit_list.log', json_encode(['time'=>date('c'),'event'=>'mapped_get_to_request','query'=>$_SERVER['QUERY_STRING']]) . "\n", FILE_APPEND);
}

// Detect AJAX/DataTables requests (accept GET or POST)
$is_ajax = isset($_REQUEST['ajax']) && ($_REQUEST['ajax'] == 1 || $_REQUEST['ajax'] === '1');

// If AJAX, return JSON-friendly auth/permission responses instead of redirects
if ($is_ajax) {
    if (!is_logged_in()) {
        http_response_code(401);
        $dbg = [
            'time' => date('c'),
            'event' => 'audit_list_unauthenticated',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'cookies' => $_COOKIE ?? null,
            'session' => isset($_SESSION) ? array_intersect_key($_SESSION, array_flip(['user_id','username','user_role'])) : null,
        ];
        @file_put_contents(__DIR__ . '/../../logs/debug_audit_list.log', json_encode($dbg, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        echo json_encode(['error' => 'unauthenticated', 'message' => 'يجب تسجيل الدخول للوصول إلى البيانات']);
        exit;
    }

    if (!check_permission('audit', 'view')) {
        http_response_code(403);
        $dbg = [
            'time' => date('c'),
            'event' => 'audit_list_forbidden',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'user_id' => get_current_user_id(),
            'user_role' => get_current_user_role(),
            'cookies' => $_COOKIE ?? null,
        ];
        @file_put_contents(__DIR__ . '/../../logs/debug_audit_list.log', json_encode($dbg, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        echo json_encode(['error' => 'forbidden', 'message' => 'ليس لديك صلاحية لعرض سجل التدقيق']);
        exit;
    }
}

try {
    $db = get_db();

    // Accept parameters from GET or POST (DataTables may send either)
    $start = isset($_REQUEST['start']) ? intval($_REQUEST['start']) : 0;
    $length = isset($_REQUEST['length']) ? intval($_REQUEST['length']) : 10;
    $search = $_REQUEST['search']['value'] ?? ($_REQUEST['search_value'] ?? '');
    $order_col = $_REQUEST['order'][0]['column'] ?? ($_REQUEST['order_column'] ?? 6);
    $order_dir = (($_REQUEST['order'][0]['dir'] ?? ($_REQUEST['order_dir'] ?? 'desc')) === 'desc') ? 'DESC' : 'ASC';

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

    // Map common action keys to Arabic labels and translate common description fragments
    $action_labels = [
        'create' => 'إنشاء',
        'created' => 'إنشاء',
        'update' => 'تحديث',
        'updated' => 'تحديث',
        'delete' => 'حذف',
        'deleted' => 'حذف',
        'login' => 'تسجيل دخول',
        'login_failed' => 'محاولة تسجيل دخول فاشلة',
        'logout' => 'تسجيل خروج',
        'reply' => 'رد',
        'document_uploaded' => 'رفع ملف',
        'document_deleted' => 'حذف ملف',
        'document_downloaded' => 'تنزيل ملف',
        'notification_created' => 'إنشاء إشعار',
        'notification_read' => 'قراءة إشعار',
        'notifications_read_all' => 'قراءة كل الإشعارات',
        'user_created' => 'إنشاء مستخدم',
        'user_updated' => 'تحديث مستخدم',
        'user_deleted' => 'حذف مستخدم',
        'password_changed' => 'تغيير كلمة المرور',
        'ticket_status_changed' => 'تغيير حالة التذكرة',
        'reply' => 'رد على تذكرة',
        'assigned' => 'تعيين',
        'unassigned' => 'إزالة التعيين',
        'import' => 'استيراد',
        'export' => 'تصدير'
    ];

    $desc_map = [
        'Created new contact' => 'تم إنشاء جهة اتصال جديدة',
        'User logged in' => 'تم تسجيل دخول المستخدم',
        'Failed login attempt' => 'محاولة تسجيل دخول فاشلة',
        'User logged out' => 'تم تسجيل خروج المستخدم',
        'Notification created' => 'تم إنشاء إشعار',
        'Notification marked as read' => 'تم وضع الإشعار كمقروء',
        'All notifications marked as read' => 'تم وضع كل الإشعارات كمقروءة',
        'Notification deleted' => 'تم حذف الإشعار',
        'Replied to ticket' => 'تم الرد على التذكرة',
        'Updated ticket' => 'تم تحديث التذكرة',
        'Deleted ticket' => 'تم حذف التذكرة',
        'Document uploaded' => 'تم رفع المستند',
        'Document deleted' => 'تم حذف المستند',
        'Document downloaded' => 'تم تنزيل المستند'
    ];

    foreach ($rows as &$r) {
        $act = trim(strval($r['action'] ?? ''));
        $act_l = strtolower($act);

        // Extract key part (before space, colon or parentheses)
        if (preg_match('/^([a-z0-9_\-]+)/i', $act_l, $m)) {
            $key = $m[1];
        } else {
            $key = $act_l;
        }

        if (isset($action_labels[$key])) {
            $label = $action_labels[$key];
            // append remainder if exists (e.g., "user_created: username")
            $remainder = trim(substr($act, strlen($key)));
            if (!empty($remainder)) {
                $label .= ' ' . htmlspecialchars($remainder);
            }
            $r['action'] = $label;
        } else {
            // fallback: humanize and translate some common verbs
            $human = str_replace(['_', '-'], ' ', $act);
            $replacements = [
                'Created' => 'تم إنشاء', 'created' => 'تم إنشاء',
                'Updated' => 'تم تحديث', 'updated' => 'تم تحديث',
                'Deleted' => 'تم حذف', 'deleted' => 'تم حذف',
                'Login' => 'تسجيل دخول', 'login' => 'تسجيل دخول',
                'Logout' => 'تسجيل خروج', 'logout' => 'تسجيل خروج',
            ];
            $r['action'] = str_ireplace(array_keys($replacements), array_values($replacements), $human);
        }

        $desc = $r['description'] ?? '';
        // exact replacements
        if (isset($desc_map[$desc])) {
            $r['description'] = $desc_map[$desc];
        } else {
            // pattern-based translations for common phrases
            $patterns = [
                '/Created new contact/i' => 'تم إنشاء جهة اتصال جديدة',
                '/User logged in: (.*)/i' => 'تم تسجيل دخول المستخدم: $1',
                '/Failed login attempt for: (.*)/i' => 'محاولة تسجيل دخول فاشلة للمستخدم: $1',
                '/New user created: (.*)/i' => 'تم إنشاء مستخدم جديد: $1',
                '/Notification created for user (\d+): (.*)/i' => 'تم إنشاء إشعار للمستخدم رقم $1: $2',
                '/Reply(ed)? to ticket/i' => 'تم الرد على التذكرة',
                '/Assigned to user (\d+)/i' => 'تم التعيين للمستخدم رقم $1',
                '/Assigned to: (.*)/i' => 'تم التعيين إلى: $1',
                '/Status changed to (.*)/i' => 'تم تغيير الحالة إلى $1',
                '/(Created|Added) (.*) for (.*)/i' => 'تم إضافة $2 لـ $3',
            ];
            $translated = preg_replace(array_keys($patterns), array_values($patterns), $desc);

            if ($translated !== null && $translated !== $desc) {
                $r['description'] = $translated;
            } else {
                // final fallback: word-level replacements for common verbs
                $word_map = [
                    'Created' => 'تم إنشاء', 'created' => 'تم إنشاء',
                    'Updated' => 'تم تعديل', 'updated' => 'تم تعديل',
                    'Deleted' => 'تم حذف', 'deleted' => 'تم حذف',
                    'Failed' => 'فشل', 'failed' => 'فشل',
                    'Assigned' => 'تم التعيين', 'assigned' => 'تم التعيين',
                    'Uploaded' => 'تم رفع', 'uploaded' => 'تم رفع',
                    'Downloaded' => 'تم تنزيل', 'downloaded' => 'تم تنزيل',
                    'Added' => 'تم إضافة', 'added' => 'تم إضافة',
                    'Removed' => 'تم إزالة', 'removed' => 'تم إزالة',
                    'Changed' => 'تم تغيير', 'changed' => 'تم تغيير',
                    'Replied' => 'تم الرد', 'replied' => 'تم الرد'
                ];
                $r['description'] = str_ireplace(array_keys($word_map), array_values($word_map), $desc);
            }
        }
    }

    $out = [
        'draw' => intval($_REQUEST['draw'] ?? 0),
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $rows
    ];

    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('audit/list.php error: ' . $e->getMessage());
    echo json_encode(['data' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0]);
}

?>
