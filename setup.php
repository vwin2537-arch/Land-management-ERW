<?php
/**
 * Database Setup — Import full backup to Railway MySQL
 * เปิดหน้านี้ครั้งเดียวหลัง deploy เพื่อนำเข้าข้อมูล
 * URL: https://your-app.railway.app/setup.php
 */

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Database Setup</h2>";

try {
    $db = getDB();
    $version = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
    echo "<p>Connected to MySQL: $version</p>";
    echo "<p>Database: " . DB_NAME . " @ " . DB_HOST . ":" . DB_PORT . "</p>";

    // Check if tables already exist
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('users', $tables)) {
        echo "<p style='color:green;'>Tables already exist (" . count($tables) . " tables).</p>";
        echo "<ul>";
        foreach ($tables as $t) echo "<li>$t</li>";
        echo "</ul>";
        echo "<p><a href='index.php'>Go to app</a></p>";
        exit;
    }

    // Import full backup
    $backupFile = __DIR__ . '/sql/full_backup_railway.sql';
    if (!file_exists($backupFile)) {
        die("<p style='color:red;'>full_backup_railway.sql not found!</p>");
    }

    $sql = file_get_contents($backupFile);

    // Fix MariaDB -> MySQL 9.4 compatibility
    // 1. Replace unsupported collation
    $sql = str_replace('utf8mb4_thai_520_w2', 'utf8mb4_unicode_ci', $sql);
    // 2. Remove MariaDB CHECK constraints (json_valid) not supported in MySQL CREATE TABLE
    $sql = preg_replace('/\s+CHECK\s*\(json_valid\(`[^`]+`\)\)/', '', $sql);
    // 3. Remove CREATE DATABASE / USE land_management (Railway uses 'railway')
    $sql = preg_replace('/^CREATE DATABASE.*$/m', '', $sql);
    $sql = preg_replace('/^USE\s+`?land_management`?\s*;/m', '', $sql);

    // Execute full SQL dump
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $db->exec("SET FOREIGN_KEY_CHECKS=0");

    // Split by semicolons but respect strings
    $statements = array_filter(array_map('trim', explode(";\n", $sql)));

    $success = 0;
    $errors = [];
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        if (strpos($statement, '--') === 0 && strpos($statement, "\n") === false) continue;

        try {
            $db->exec($statement);
            $success++;
        } catch (PDOException $e) {
            $errors[] = $e->getMessage() . " | SQL: " . mb_substr($statement, 0, 100);
        }
    }

    $db->exec("SET FOREIGN_KEY_CHECKS=1");

    echo "<p style='color:green;'>Executed $success statements successfully.</p>";

    if (!empty($errors)) {
        echo "<h3>Warnings (" . count($errors) . "):</h3><ul>";
        foreach ($errors as $err) echo "<li style='color:orange;'>" . htmlspecialchars($err) . "</li>";
        echo "</ul>";
    }

    // Verify
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Tables (" . count($tables) . "):</h3><ul>";
    foreach ($tables as $t) {
        $count = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "<li>$t ($count rows)</li>";
    }
    echo "</ul>";

    echo "<p><strong>Admin login:</strong> username: <code>admin</code> / password: <code>admin123</code></p>";
    echo "<p><a href='index.php'>Go to app</a></p>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Connection Error: " . $e->getMessage() . "</p>";
    echo "<p>Check your MYSQL_URL environment variable in Railway.</p>";
}
