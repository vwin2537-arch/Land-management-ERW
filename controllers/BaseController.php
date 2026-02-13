<?php
/**
 * BaseController — Shared methods for all controllers
 * ป้องกันการเขียนโค้ดซ้ำ (DRY)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../models/Document.php';

class BaseController
{

    /**
     * Sanitize POST data — trim all string values
     */
    protected function sanitize(array $post): array
    {
        return array_map(fn($v) => is_string($v) ? trim($v) : $v, $post);
    }

    /**
     * Log user activity to activity_logs table
     */
    protected function logActivity(string $action, string $table, int $recordId, string $desc): void
    {
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, description, ip_address) 
                                  VALUES (:uid, :action, :tbl, :rid, :desc, :ip)");
            $stmt->execute([
                'uid' => $_SESSION['user_id'],
                'action' => $action,
                'tbl' => $table,
                'rid' => $recordId,
                'desc' => $desc,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);
        } catch (PDOException $e) {
            // Silently fail — logging should not break the app
        }
    }

    /**
     * Upload multiple documents from $_FILES array
     */
    protected function uploadDocuments(array $files, string $type, int $id): void
    {
        if (!isset($files['name']) || !is_array($files['name'])) {
            return;
        }
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            $single = [
                'name' => $files['name'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'size' => $files['size'][$i],
                'error' => $files['error'][$i],
            ];
            $category = $_POST['doc_category'] ?? 'other';
            Document::upload($single, $type, $id, $category);
        }
    }

    /**
     * Redirect with permission error
     */
    protected function forbidden(string $redirectPage = 'dashboard'): void
    {
        $_SESSION['flash_error'] = 'คุณไม่มีสิทธิ์ดำเนินการนี้';
        header("Location: index.php?page=$redirectPage");
        exit;
    }

    /**
     * Check if current user is viewer (read-only)
     */
    protected function isViewer(): bool
    {
        return ($_SESSION['role'] ?? '') === ROLE_VIEWER;
    }

    /**
     * Check if current user is admin
     */
    protected function isAdmin(): bool
    {
        return ($_SESSION['role'] ?? '') === ROLE_ADMIN;
    }
}
