<?php
/**
 * Database Configuration
 * ระบบจัดการที่ดินทำกินในเขตอุทยานแห่งชาติ
 */

/**
 * Load .env file if exists (for local development)
 */
function load_env_file() {
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) return;

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Remove surrounding quotes
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        if (!getenv($key)) {
            putenv("$key=$value");
        }
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
    }
}

load_env_file();

/**
 * Get environment variable from multiple sources
 */
function get_config($key, $default = null) {
    if (getenv($key) !== false) return getenv($key);
    if (isset($_ENV[$key])) return $_ENV[$key];
    if (isset($_SERVER[$key])) return $_SERVER[$key];
    return $default;
}

// 1. ลองดึงจาก MYSQL_URL (Railway)
$mysql_url = get_config('MYSQL_URL') ?: get_config('MYSQLDATABASE_URL');
$p = $mysql_url ? parse_url($mysql_url) : null;

if ($p && is_array($p)) {
    define('DB_HOST', $p['host'] ?? '127.0.0.1');
    define('DB_PORT', $p['port'] ?? '3306');
    define('DB_USER', $p['user'] ?? 'root');
    define('DB_PASS', $p['pass'] ?? '');
    define('DB_NAME', ltrim($p['path'] ?? 'land_management', '/'));
} else {
    // 2. ถ้าไม่มี MYSQL_URL ให้ไล่เช็คทีละตัว
    define('DB_HOST',    get_config('DB_HOST') ?: get_config('MYSQLHOST') ?: get_config('MYSQL_HOST') ?: '127.0.0.1');
    define('DB_NAME',    get_config('DB_NAME') ?: get_config('MYSQLDATABASE') ?: 'land_management');
    define('DB_USER',    get_config('DB_USER') ?: get_config('MYSQLUSER') ?: 'root');
    define('DB_PASS',    get_config('DB_PASS') !== false ? get_config('DB_PASS') : (get_config('MYSQLPASSWORD') !== false ? get_config('MYSQLPASSWORD') : ''));
    define('DB_PORT',    get_config('DB_PORT') ?: get_config('MYSQLPORT') ?: '3306');
}
define('DB_CHARSET', get_config('DB_CHARSET', 'utf8mb4'));

/**
 * Get PDO Connection
 * @return PDO
 */
function getDB(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . (defined('DB_PORT') ? DB_PORT : '3306') . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("❌ Database Connection Failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}
