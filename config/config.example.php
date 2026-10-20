<?php
// Copy this file to config.php and update the database settings.

if (!defined('CRM_CONFIG')) {
    define('CRM_CONFIG', true);
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'crm_db');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'Click CRM');
define('APP_VERSION', '1.0.0');
$protocol = is_https() ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
$projectRoot = realpath(__DIR__ . '/../');
$base_path = str_replace('\\', '/', str_replace($docRoot, '', $projectRoot));
$base_path = '/' . trim($base_path, '/');
define('BASE_URL', rtrim($protocol . $host . $base_path, '/'));
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10485760);

define('HASH_ALGO', PASSWORD_DEFAULT);
define('SESSION_LIFETIME', 7200);
define('COOKIE_SECURE', false);
define('COOKIE_HTTPONLY', true);

define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls');

define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('TIMEZONE', 'UTC');

define('ITEMS_PER_PAGE', 20);
define('MAX_ITEMS_PER_PAGE', 100);
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt']);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', COOKIE_SECURE);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
date_default_timezone_set(TIMEZONE);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';

function get_setting($key, $default = null) {
    static $settings = null;

    if ($settings === null) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settings = [];
        }
    }

    return isset($settings[$key]) ? $settings[$key] : $default;
}

function set_setting($key, $value) {
    try {
        $pdo = get_db_connection();
        $sql = "REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        error_log('set_setting error: ' . $e->getMessage());
        return false;
    }
}

try {
    $last = get_setting('audit_last_cleanup', null);
    $need = empty($last) || strtotime($last) === false || strtotime($last) < strtotime('-7 days');

    if ($need) {
        $pdo = get_db_connection();
        $threshold = date('Y-m-d H:i:s', strtotime('-7 days'));
        $del = $pdo->prepare('DELETE FROM audit_log WHERE created_at < ?');
        $del->execute([$threshold]);
        $deleted = $del->rowCount();
        set_setting('audit_last_cleanup', date('Y-m-d H:i:s'));
        @file_put_contents(__DIR__ . '/../logs/cleanup_audit.log', date('Y-m-d H:i:s') . " - Auto-cleanup deleted {$deleted} entries older than {$threshold}\n", FILE_APPEND);
    }
} catch (Exception $e) {
    error_log('Auto-cleanup audit error: ' . $e->getMessage());
}

function base_url($path = '') {
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function redirect($url) {
    header("Location: " . base_url($url));
    exit;
}

function is_https() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           $_SERVER['SERVER_PORT'] == 443;
}

if (is_https() && strpos(BASE_URL, 'http://') === 0) {
    define('BASE_URL_SECURE', str_replace('http://', 'https://', BASE_URL));
} else {
    define('BASE_URL_SECURE', BASE_URL);
}
?>