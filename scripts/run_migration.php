<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

$sqlFile = __DIR__ . '/migrations/add_assigned_to_columns.sql';
if (!file_exists($sqlFile)) {
    echo "Migration file not found: $sqlFile\n";
    exit(1);
}

$sql = file_get_contents($sqlFile);
// Split statements by semicolon; naive but OK for simple migrations
$stmts = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));

$pdo = get_db_connection();
$errors = [];
foreach ($stmts as $stmt) {
    if ($stmt === '') continue;
    try {
        $pdo->exec($stmt);
        echo "Executed: " . (strlen($stmt) > 60 ? substr($stmt,0,60).'...' : $stmt) . "\n";
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
        echo "Error: " . $e->getMessage() . "\n";
    }
}

if ($errors) {
    echo "Migration finished with errors.\n";
    exit(2);
}

echo "Migration completed successfully.\n";
return 0;
