<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';

$file = __DIR__ . '/migrations/ensure_tasks_table.sql';
if (!file_exists($file)) {
    echo "SQL file not found: $file\n";
    exit(1);
}

$sql = file_get_contents($file);
$pdo = get_db_connection();
try {
    // PDO->exec can run multiple statements; some statements may produce warnings but will execute.
    $pdo->exec($sql);
    echo "Migration executed successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(2);
}

// Show resulting columns
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM tasks");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns in tasks:\n";
    foreach ($cols as $c) {
        echo $c['Field'] . "\t" . $c['Type'] . "\t" . $c['Null'] . "\t" . ($c['Key']?:'-') . "\n";
    }
} catch (PDOException $e) {
    echo "Could not describe tasks: " . $e->getMessage() . "\n";
}

return 0;
