<?php
// Cleanup audit logs older than 7 days
// Run via CLI or scheduler (php scripts/cleanup_audit.php)

if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../config/config.php';
}

require_once __DIR__ . '/../config/db.php';

try {
    $db = get_db();
    $threshold = date('Y-m-d H:i:s', strtotime('-7 days'));

    $sql = 'DELETE FROM audit_log WHERE created_at < ?';
    $stmt = $db->query($sql, [$threshold]);
    $deleted = $stmt->rowCount();

    $msg = date('Y-m-d H:i:s') . " - Deleted {$deleted} audit log(s) older than 7 days (before {$threshold})\n";
    @file_put_contents(__DIR__ . '/../logs/cleanup_audit.log', $msg, FILE_APPEND);
    echo $msg;

} catch (Exception $e) {
    $err = date('Y-m-d H:i:s') . " - Error cleaning audit logs: " . $e->getMessage() . "\n";
    @file_put_contents(__DIR__ . '/../logs/cleanup_audit.log', $err, FILE_APPEND);
    echo $err;
    exit(1);
}

return 0;
