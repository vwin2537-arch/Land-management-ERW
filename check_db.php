<?php
require_once __DIR__ . '/config/database.php';

echo "<h2>Database Connection Test</h2>";

try {
    echo "Attempting to connect to: " . DB_HOST . " (Port: " . (getenv('DB_PORT') ?: '3306') . ")<br>";
    echo "User: " . DB_USER . "<br>";
    echo "Database: " . DB_NAME . "<br>";
    
    $start = microtime(true);
    $db = getDB();
    $end = microtime(true);
    
    echo "<h3 style='color:green;'>✅ Connection Successful!</h3>";
    echo "Time taken: " . round($end - $start, 4) . " seconds<br>";
    
    // Check tables
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h4>Tables found (" . count($tables) . "):</h4>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";

} catch (Exception $e) {
    echo "<h3 style='color:red;'>❌ Connection Failed</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    
    echo "<h4>Debug Info:</h4>";
    echo "DSN: mysql:host=" . DB_HOST . ";port=" . (getenv('DB_PORT') ?: '3306') . ";dbname=" . DB_NAME . "<br>";
}
