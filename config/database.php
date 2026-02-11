<?php
/**
 * Database Configuration
 * ระบบจัดการที่ดินทำกินในเขตอุทยานแห่งชาติ
 */

// Railway: parse MYSQL_URL if available
$mysql_url = getenv('MYSQL_URL') ?: getenv('MYSQLDATABASE_URL');
$p = $mysql_url ? parse_url($mysql_url) : null;

if ($p && is_array($p)) {
    define('DB_HOST', $p['host'] ?? '127.0.0.1');
    define('DB_PORT', (string)($p['port'] ?? '3306'));
    define('DB_USER', $p['user'] ?? 'root');
    define('DB_PASS', $p['pass'] ?? '');
    define('DB_NAME', ltrim($p['path'] ?? 'land_management', '/'));
} else {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
    define('DB_NAME', getenv('DB_NAME') ?: 'land_management');
}
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

/**
 * Get PDO Connection
 * @return PDO
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Database Connection Failed: " . $e->getMessage());
        }
    }

    return $pdo;
}
