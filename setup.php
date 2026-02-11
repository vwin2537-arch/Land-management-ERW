<?php
/**
 * Database Setup — Import schema to Railway MySQL
 * เปิดหน้านี้ครั้งเดียวหลัง deploy เพื่อสร้างตาราง
 * URL: https://your-app.railway.app/setup.php
 */

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Database Setup</h2>";

try {
    $db = getDB();
    echo "<p>Connected to MySQL: " . $db->getAttribute(PDO::ATTR_SERVER_VERSION) . "</p>";
    echo "<p>Database: " . DB_NAME . " @ " . DB_HOST . ":" . DB_PORT . "</p>";

    // Check if tables already exist
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('users', $tables)) {
        echo "<p style='color:green;'>Tables already exist (" . count($tables) . " tables). No action needed.</p>";
        echo "<ul>";
        foreach ($tables as $t) echo "<li>$t</li>";
        echo "</ul>";
        echo "<p><a href='index.php'>Go to app</a></p>";
        exit;
    }

    // Import schema
    $schemaFile = __DIR__ . '/sql/schema_railway.sql';
    if (!file_exists($schemaFile)) {
        die("<p style='color:red;'>schema_railway.sql not found!</p>");
    }

    $sql = file_get_contents($schemaFile);

    // Split by semicolons (simple split, works for this schema)
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $success = 0;
    $errors = [];
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) continue;
        // Skip SET and USE statements that might cause issues
        if (preg_match('/^(SET |USE )/i', $statement)) {
            $db->exec($statement);
            continue;
        }
        try {
            $db->exec($statement);
            $success++;
        } catch (PDOException $e) {
            $errors[] = $e->getMessage() . " | SQL: " . mb_substr($statement, 0, 80);
        }
    }

    echo "<p style='color:green;'>Executed $success statements successfully.</p>";

    if (!empty($errors)) {
        echo "<h3>Warnings:</h3><ul>";
        foreach ($errors as $err) echo "<li style='color:orange;'>$err</li>";
        echo "</ul>";
    }

    // Verify
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Created tables (" . count($tables) . "):</h3><ul>";
    foreach ($tables as $t) echo "<li>$t</li>";
    echo "</ul>";

    echo "<p><strong>Admin login:</strong> username: <code>admin</code> / password: <code>admin123</code></p>";
    echo "<p><a href='index.php'>Go to app</a></p>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Connection Error: " . $e->getMessage() . "</p>";
    echo "<p>Check your MYSQL_URL environment variable in Railway.</p>";
}
