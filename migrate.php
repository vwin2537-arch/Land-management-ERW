<?php
require_once __DIR__ . '/config/database.php';

echo "<h2>Cloud Database Migration</h2>";

$file = __DIR__ . '/sql/full_backup_railway.sql';

if (!file_exists($file)) {
    die("❌ Error: SQL file not found at $file");
}

try {
    $db = getDB();
    echo "Connected to database: " . DB_NAME . "<br>";
    
    // Disable foreign key checks first
    $db->exec("SET FOREIGN_KEY_CHECKS=0");
    $db->exec("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");
    $db->exec("SET NAMES utf8mb4");
    echo "Foreign key checks disabled.<br>";
    
    // Read the SQL file
    $sql = file_get_contents($file);
    
    // Normalize line endings
    $sql = str_replace("\r\n", "\n", $sql);
    $sql = str_replace("\r", "\n", $sql);
    
    // Replace problematic collation with standard one
    $sql = str_replace("utf8mb4_thai_520_w2", "utf8mb4_unicode_ci", $sql);
    
    // Remove only plain comments (lines starting with --)
    $sql = preg_replace('/^--.*$/m', '', $sql);
    
    // Split into individual statements by semicolon followed by newline
    $statements = explode(";\n", $sql);
    
    $count = 0;
    $errors = 0;
    $errorMessages = [];
    
    echo "Starting migration (" . count($statements) . " statements found)...<br>";
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        try {
            $db->exec($statement);
            $count++;
        } catch (PDOException $e) {
            $errors++;
            $msg = $e->getMessage();
            $errorMessages[] = "<small style='color:orange;'>⚠️ Query #$count: " . htmlspecialchars(substr($msg, 0, 150)) . "</small>";
        }
    }
    
    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS=1");
    
    echo "<h3>Migration Finished</h3>";
    echo "✅ Successfully executed <strong>$count</strong> queries.<br>";
    
    if ($errors > 0) {
        echo "⚠️ Encountered <strong>$errors</strong> warnings.<br>";
        echo "<details><summary>Click to see warnings</summary>";
        foreach ($errorMessages as $msg) {
            echo $msg . "<br>";
        }
        echo "</details>";
    }
    
    // Verify tables
    echo "<h3>Verification</h3>";
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "✅ Found <strong>" . count($tables) . "</strong> tables:<br>";
        echo "<ul>";
        foreach ($tables as $table) {
            $countStmt = $db->query("SELECT COUNT(*) FROM `$table`");
            $rowCount = $countStmt->fetchColumn();
            echo "<li><strong>$table</strong> ($rowCount rows)</li>";
        }
        echo "</ul>";
    } else {
        echo "❌ No tables found! Migration may have failed.<br>";
    }
    
    echo "<br><a href='index.php?page=login'>👉 Go to Login Page</a>";

} catch (Exception $e) {
    echo "<h3 style='color:red;'>❌ Migration Failed</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
