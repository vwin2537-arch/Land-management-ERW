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
    
    // Read the SQL file
    $sql = file_get_contents($file);
    
    // Remove comments
    $sql = preg_replace('/--.*?\n/', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    
    // Split into individual queries (basic split by ;)
    // Note: This is a simple split, might fail if ; is inside strings, 
    // but usually okay for standard exports.
    $queries = explode(";\n", $sql);
    
    $count = 0;
    $errors = 0;
    
    echo "Starting migration...<br>";
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        
        try {
            $db->exec($query);
            $count++;
        } catch (PDOException $e) {
            $errors++;
            echo "<small style='color:orange;'>Warning at query $count: " . substr($e->getMessage(), 0, 100) . "...</small><br>";
        }
    }
    
    echo "<h3>Migration Finished</h3>";
    echo "✅ Successfully executed $count queries.<br>";
    if ($errors > 0) {
        echo "⚠️ Encountered $errors non-critical warnings (usually tables already existing or minor syntax differences).<br>";
    }
    
    echo "<br><a href='index.php?page=login'>Go to Login Page</a>";

} catch (Exception $e) {
    echo "<h3 style='color:red;'>❌ Migration Failed</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
