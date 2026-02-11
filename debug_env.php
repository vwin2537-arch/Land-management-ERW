<?php
/**
 * Temporary debug: show which DB env vars are available on Railway
 * DELETE THIS FILE AFTER DEBUGGING
 */
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$vars = [
    'MYSQL_URL', 'MYSQLDATABASE_URL', 'DATABASE_URL',
    'DB_HOST', 'MYSQLHOST', 'MYSQL_HOST',
    'DB_NAME', 'MYSQLDATABASE', 'MYSQL_DATABASE',
    'DB_USER', 'MYSQLUSER', 'MYSQL_USER',
    'DB_PASS', 'MYSQLPASSWORD', 'MYSQL_PASSWORD',
    'DB_PORT', 'MYSQLPORT', 'MYSQL_PORT',
    'MYSQL_PUBLIC_URL', 'MYSQL_PRIVATE_URL',
];

$result = [];
foreach ($vars as $v) {
    $val_getenv = getenv($v);
    $val_env = $_ENV[$v] ?? null;
    $val_server = $_SERVER[$v] ?? null;
    
    $found = $val_getenv !== false ? $val_getenv : ($val_env ?? $val_server);
    
    if ($found !== null && $found !== false) {
        // Mask password values
        if (stripos($v, 'PASS') !== false || stripos($v, 'URL') !== false) {
            $result[$v] = substr($found, 0, 8) . '***MASKED***';
        } else {
            $result[$v] = $found;
        }
        $result[$v . '_source'] = $val_getenv !== false ? 'getenv' : ($val_env !== null ? '$_ENV' : '$_SERVER');
    }
}

// Also try to connect
require_once __DIR__ . '/config/database.php';
$result['RESOLVED_DB_HOST'] = DB_HOST;
$result['RESOLVED_DB_NAME'] = DB_NAME;
$result['RESOLVED_DB_USER'] = DB_USER;
$result['RESOLVED_DB_PORT'] = defined('DB_PORT') ? DB_PORT : 'NOT_DEFINED';

try {
    $pdo = getDB();
    $result['CONNECTION'] = 'SUCCESS';
    $result['MYSQL_VERSION'] = $pdo->query("SELECT VERSION()")->fetchColumn();
} catch (Throwable $e) {
    $result['CONNECTION'] = 'FAILED';
    $result['ERROR'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
