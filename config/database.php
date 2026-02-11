<?php
/**
 * Database Configuration
 * ระบบจัดการที่ดินทำกินในเขตอุทยานแห่งชาติ
 */

// ใช้ค่าจาก Environment Variables (ถ้ามี) สำหรับ Cloud Deployment
define('DB_HOST',    getenv('DB_HOST')     ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')     ?: 'land_management');
define('DB_USER',    getenv('DB_USER')     ?: 'root');
define('DB_PASS',    getenv('DB_PASS')     !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', getenv('DB_CHARSET')  ?: 'utf8mb4');

/**
 * Get PDO Connection
 * @return PDO
 */
function getDB(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("❌ Database Connection Failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}
