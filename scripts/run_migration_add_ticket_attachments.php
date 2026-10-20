<?php
// Run migration to create ticket_attachments table
// Ensure minimal SERVER vars for CLI environment to avoid config warnings
if (php_sapi_name() === 'cli') {
    if (empty($_SERVER['HTTP_HOST'])) $_SERVER['HTTP_HOST'] = 'localhost';
    if (empty($_SERVER['SERVER_PORT'])) $_SERVER['SERVER_PORT'] = 80;
    if (empty($_SERVER['HTTPS'])) $_SERVER['HTTPS'] = 'off';
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

$sqlFile = __DIR__ . '/migrations/add_ticket_attachments.sql';
if (!file_exists($sqlFile)) {
    echo "Migration SQL file not found: $sqlFile\n";
    exit(1);
}

$sql = file_get_contents($sqlFile);
if (empty($sql)) { echo "Empty SQL file\n"; exit(1); }

try {
    $pdo = get_db_connection();

    // Execute statements one by one for better error reporting
    $stmts = array_filter(array_map('trim', explode(";", $sql)));
    foreach ($stmts as $stmt) {
        if (empty($stmt)) continue;
        try {
            $pdo->exec($stmt);
        } catch (Exception $e) {
            throw new Exception('Statement failed: ' . $e->getMessage() . ' SQL: ' . $stmt);
        }
    }

    echo "Migration applied: ticket_attachments created (if not existed)\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    error_log('Migration error: ' . $e->getMessage());
    exit(1);
}

?>