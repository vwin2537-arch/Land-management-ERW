<?php
/**
 * Database Setup — Import full_backup_railway.sql to Railway MySQL
 * เปิดครั้งเดียวหลัง deploy: https://your-app.railway.app/setup.php
 */

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Database Setup</h2>";

try {
    $db = getDB();
    echo "<p>Connected: MySQL " . $db->getAttribute(PDO::ATTR_SERVER_VERSION) . "</p>";
    echo "<p>Database: " . DB_NAME . " @ " . DB_HOST . ":" . DB_PORT . "</p>";

    // Check if already set up
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('users', $tables) && !isset($_GET['reset'])) {
        echo "<p style='color:green;'>Tables already exist (" . count($tables) . " tables).</p>";
        echo "<p><a href='index.php'>Go to app</a></p>";
        echo "<hr><p style='color:#666;'>ถ้าต้องการ import ข้อมูลใหม่ทั้งหมด (drop แล้ว re-import):</p>";
        echo "<p><a href='setup.php?reset=1' style='color:red;' onclick=\"return confirm('จะลบข้อมูลเก่าทั้งหมดแล้ว import ใหม่ แน่ใจหรือไม่?');\">⚠️ Reset & Re-import</a></p>";
        exit;
    }

    // Drop all existing tables if reset requested
    if (isset($_GET['reset']) && !empty($tables)) {
        echo "<p style='color:orange;'>Dropping " . count($tables) . " tables...</p>";
        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        foreach ($tables as $t) {
            $db->exec("DROP TABLE IF EXISTS `$t`");
        }
        $db->exec("SET FOREIGN_KEY_CHECKS=1");
        echo "<p>Done. Re-importing...</p>";
    }

    // Read backup file
    $file = __DIR__ . '/sql/full_backup_railway.sql';
    if (!file_exists($file)) {
        die("<p style='color:red;'>full_backup_railway.sql not found!</p>");
    }

    $sql = file_get_contents($file);

    // Strip UTF-8 BOM if present
    if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
        $sql = substr($sql, 3);
    }

    // Normalize line endings (Windows CRLF -> LF)
    $sql = str_replace("\r\n", "\n", $sql);
    $sql = str_replace("\r", "\n", $sql);

    // Fix MariaDB -> MySQL 9.x compatibility
    $sql = str_replace('utf8mb4_thai_520_w2', 'utf8mb4_unicode_ci', $sql);
    $sql = preg_replace('/\s+CHECK\s*\(json_valid\(`[^`]+`\)\)/', '', $sql);
    $sql = preg_replace('/^CREATE DATABASE.*$/m', '', $sql);
    $sql = preg_replace('/^USE\s+`?land_management`?\s*;?\s*$/m', '', $sql);
    $sql = preg_replace('/^USE\s+`?railway`?\s*;?\s*$/m', '', $sql);

    echo "<p>SQL size: " . strlen($sql) . " bytes</p>";

    // Execute
    $db->exec("SET NAMES utf8mb4");
    $db->exec("SET CHARACTER SET utf8mb4");
    $db->exec("SET FOREIGN_KEY_CHECKS=0");
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

    // Split on semicolon followed by newline (handles multi-line statements)
    $statements = preg_split('/;\s*\n/', $sql);
    $statements = array_filter(array_map('trim', $statements));

    $success = 0;
    $errors = [];

    foreach ($statements as $s) {
        if (empty($s) || $s === ';') continue;
        // Skip pure comment lines
        if (preg_match('/^--/', $s) && strpos($s, "\n") === false) continue;
        try {
            $db->exec($s);
            $success++;
        } catch (PDOException $e) {
            $errors[] = substr($s, 0, 80) . '... → ' . $e->getMessage();
        }
    }

    $db->exec("SET FOREIGN_KEY_CHECKS=1");

    echo "<p style='color:green;'>OK: $success statements executed.</p>";
    if ($errors) {
        echo "<details><summary>Warnings (" . count($errors) . ")</summary><ul>";
        foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>";
        echo "</ul></details>";
    }

    // Show result
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Tables (" . count($tables) . "):</h3><ul>";
    foreach ($tables as $t) {
        $c = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "<li>$t ($c rows)</li>";
    }
    echo "</ul>";
    echo "<p>Login: <code>admin</code> / <code>admin123</code></p>";
    echo "<p><a href='index.php'>Go to app</a></p>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
