<?php
/**
 * Database Configuration
 * ระบบจัดการที่ดินทำกินในเขตอุทยานแห่งชาติ
 */

// ใช้ค่าจาก Environment Variables (รองรับทั้ง DB_* และ MYSQL* สำหรับ Cloud/Railway)
define('DB_HOST',    getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: getenv('MYSQL_HOST') ?: '127.0.0.1');
define('DB_NAME',    getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'land_management');
define('DB_USER',    getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'root');
define('DB_PASS',    getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : ''));
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

/**
 * Get PDO Connection
 * @return PDO
 */
function getDB(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $port = getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: '3306';
        $dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
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
