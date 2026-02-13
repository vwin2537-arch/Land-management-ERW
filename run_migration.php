<?php
require_once __DIR__ . '/config/database.php';

$sqlFile = $argv[1] ?? 'sql/migration_subdivision.sql';
if (!file_exists($sqlFile)) {
    die("File not found: $sqlFile\n");
}

$sql = file_get_contents($sqlFile);
echo "Running migration: $sqlFile\n";

// Use mysqli for multi_query support (handles PREPARE/EXECUTE/SET)
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'land_management';

$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error . "\n");
}
$mysqli->set_charset('utf8mb4');

if ($mysqli->multi_query($sql)) {
    $i = 0;
    do {
        $i++;
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
        if ($mysqli->errno) {
            echo "  Statement $i error: " . $mysqli->error . "\n";
        }
    } while ($mysqli->next_result());
    echo "Migration completed successfully! ($i statements)\n";
} else {
    echo "Migration failed: " . $mysqli->error . "\n";
}
$mysqli->close();
