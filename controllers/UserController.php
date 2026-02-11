<?php
/**
 * UserController — จัดการผู้ใช้ (Admin only)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';

class UserController
{

    public function store(): void
    {
        AuthController::requireRole(ROLE_ADMIN);

        $db = getDB();
        $data = array_map(fn($v) => is_string($v) ? trim($v) : $v, $_POST);

        if (empty($data['username']) || empty($data['password']) || empty($data['full_name'])) {
            $_SESSION['flash_error'] = 'กรุณากรอกข้อมูลให้ครบ';
            header('Location: index.php?page=users&action=create');
            exit;
        }

        try {
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, full_name, role, phone, is_active) 
                                  VALUES (:username, :password_hash, :full_name, :role, :phone, :is_active)");
            $stmt->execute([
                'username' => $data['username'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'full_name' => $data['full_name'],
                'role' => $data['role'] ?? ROLE_OFFICER,
                'phone' => $data['phone'] ?? null,
                'is_active' => isset($data['is_active']) ? 1 : 0,
            ]);

            $_SESSION['flash_success'] = 'เพิ่มผู้ใช้งานเรียบร้อย';
            header('Location: index.php?page=users');
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = $e->getCode() == 23000 ? 'Username ซ้ำในระบบ' : $e->getMessage();
            header('Location: index.php?page=users&action=create');
        }
        exit;
    }

    public function update(int $id): void
    {
        AuthController::requireRole(ROLE_ADMIN);

        $db = getDB();
        $data = array_map(fn($v) => is_string($v) ? trim($v) : $v, $_POST);

        try {
            // Update basic info
            $stmt = $db->prepare("UPDATE users SET full_name = :full_name, role = :role, phone = :phone, is_active = :is_active 
                                  WHERE user_id = :id");
            $stmt->execute([
                'id' => $id,
                'full_name' => $data['full_name'],
                'role' => $data['role'] ?? ROLE_OFFICER,
                'phone' => $data['phone'] ?? null,
                'is_active' => isset($data['is_active']) ? 1 : 0,
            ]);

            // Update password if provided
            if (!empty($data['password'])) {
                $db->prepare("UPDATE users SET password_hash = :hash WHERE user_id = :id")
                    ->execute(['hash' => password_hash($data['password'], PASSWORD_DEFAULT), 'id' => $id]);
            }

            $_SESSION['flash_success'] = 'แก้ไขผู้ใช้งานเรียบร้อย';
            header('Location: index.php?page=users');
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header("Location: index.php?page=users&action=edit&id=$id");
        }
        exit;
    }
}
