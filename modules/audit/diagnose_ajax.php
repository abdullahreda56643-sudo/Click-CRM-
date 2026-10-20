<?php
// Diagnostic endpoint for audit AJAX issues
// Usage: call this URL in browser or via fetch to collect request/server/session diagnostics

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Collect environment info
$diag = [];
$diag['time'] = date('c');
$diag['request_uri'] = $_SERVER['REQUEST_URI'] ?? null;
$diag['method'] = $_SERVER['REQUEST_METHOD'] ?? null;
$diag['is_ajax_param'] = isset($_GET['ajax']) ? $_GET['ajax'] : null;
$diag['is_xhr'] = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Headers (if available)
if (function_exists('getallheaders')) {
    $diag['headers'] = getallheaders();
} else {
    $hdrs = [];
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $hdrs[$k] = $v;
        }
    }
    $diag['headers'] = $hdrs;
}

$diag['cookie_header'] = $_SERVER['HTTP_COOKIE'] ?? null;
$diag['cookies_array'] = $_COOKIE ?? null;

$diag['session_status'] = session_status();
$diag['session_id'] = session_id();
$diag['session_vars'] = isset($_SESSION) ? array_intersect_key($_SESSION, array_flip(['user_id','username','user_role','last_activity'])) : null;
$diag['is_logged_in'] = is_logged_in();
$diag['current_user_id'] = get_current_user_id();
$diag['current_user_role'] = function_exists('get_current_user_role') ? get_current_user_role() : null;

// Cookie / session ini settings
$diag['ini'] = [
    'session.cookie_secure' => ini_get('session.cookie_secure'),
    'session.cookie_httponly' => ini_get('session.cookie_httponly'),
    'session.use_only_cookies' => ini_get('session.use_only_cookies'),
    'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime')
];

// Permission check
try {
    $diag['permission_audit_view'] = check_permission('audit', 'view') ? true : false;
} catch (Throwable $t) {
    $diag['permission_audit_view_error'] = $t->getMessage();
}

// Filesystem checks for logs
$logs_dir = realpath(__DIR__ . '/../../logs');
$diag['logs_dir'] = $logs_dir;
$debug_log = __DIR__ . '/../../logs/debug_audit_list.log';
$error_log = __DIR__ . '/../../logs/error.log';
$diag['debug_log_exists'] = file_exists($debug_log);
$diag['debug_log_writable'] = is_writable($debug_log) || (is_dir(dirname($debug_log)) && is_writable(dirname($debug_log)));
$diag['error_log_writable'] = is_writable($error_log) || (is_dir(dirname($error_log)) && is_writable(dirname($error_log)));

// Try writing a test entry to debug log
$test_line = json_encode(['time'=>date('c'),'event'=>'diagnose_probe','remote'=>$_SERVER['REMOTE_ADDR'] ?? null]);
$wrote = @file_put_contents($debug_log, $test_line . "\n", FILE_APPEND);
$diag['debug_log_write_result'] = $wrote === false ? 'failed' : 'ok';

// DB connectivity quick test (count rows) without exposing credentials
try {
    $db = get_db();
    $res = $db->fetch('SELECT COUNT(*) as cnt FROM audit_log');
    $diag['db_audit_log_count'] = intval($res['cnt'] ?? -1);
} catch (Throwable $e) {
    $diag['db_error'] = $e->getMessage();
}

// PHP / server info minimal
$diag['php'] = [
    'version' => PHP_VERSION,
    'sapi' => php_sapi_name(),
    'server_name' => $_SERVER['SERVER_NAME'] ?? null,
    'server_port' => $_SERVER['SERVER_PORT'] ?? null,
    'https' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? true : false
];

// Echo and log the diagnostics
@file_put_contents($error_log, "[" . date('c') . "] diagnose_ajax: " . substr($test_line,0,200) . "\n", FILE_APPEND);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;

?>
