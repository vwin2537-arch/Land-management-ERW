<?php
/**
 * AuthController — Login / Logout
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class AuthController
{

    /**
     * Handle login POST request
     */
    public function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $_SESSION['login_error'] = 'กรุณากรอก Username และ Password';
            header('Location: index.php?page=login');
            exit;
        }

        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1 LIMIT 1");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Login success
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                // Update last login
                $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :id");
                $updateStmt->execute(['id' => $user['user_id']]);

                // Log activity
                $this->logActivity($user['user_id'], 'login', 'users', $user['user_id'], 'เข้าสู่ระบบ');

                header('Location: index.php?page=dashboard');
                exit;
            } else {
                $_SESSION['login_error'] = 'Username หรือ Password ไม่ถูกต้อง';
                header('Location: index.php?page=login');
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['login_error'] = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage();
            header('Location: index.php?page=login');
            exit;
        }
    }

    /**
     * Handle logout
     */
    public function logout(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id'], 'ออกจากระบบ');
        }

        session_destroy();
        header('Location: index.php?page=login');
        exit;
    }

    /**
     * Log user activity
     */
    private function logActivity(int $userId, string $action, string $table, int $recordId, string $desc): void
    {
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, description, ip_address) 
                                  VALUES (:user_id, :action, :table_name, :record_id, :description, :ip)");
            $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'table_name' => $table,
                'record_id' => $recordId,
                'description' => $desc,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);
        } catch (PDOException $e) {
            // Silently fail — logging should not break the app
        }
    }

    /**
     * Check if user has required role
     */
    public static function requireRole(string ...$roles): void
    {
        if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles)) {
            http_response_code(403);
            echo '<div class="alert alert-danger">คุณไม่มีสิทธิ์เข้าถึงหน้านี้</div>';
            exit;
        }
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }
}
