<?php
/**
 * VillagerController — CRUD ราษฎร
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../models/Villager.php';
require_once __DIR__ . '/../models/Document.php';

class VillagerController
{

    /**
     * Store new villager
     */
    public function store(): void
    {
        if ($_SESSION['role'] === ROLE_VIEWER) {
            $this->forbidden();
            return;
        }

        $data = $this->sanitize($_POST);

        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $data['photo_path'] = $this->uploadPhoto($_FILES['photo']);
        }

        try {
            $id = Villager::create($data);
            $this->logActivity('create', 'villagers', $id, 'เพิ่มราษฎร: ' . $data['first_name'] . ' ' . $data['last_name']);

            // Handle additional document uploads
            if (isset($_FILES['documents'])) {
                $this->uploadDocuments($_FILES['documents'], 'villager', $id);
            }

            $_SESSION['flash_success'] = 'เพิ่มข้อมูลราษฎรเรียบร้อย';
            header("Location: index.php?page=villagers&action=view&id=$id");
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'เกิดข้อผิดพลาด: ' . ($e->getCode() == 23000 ? 'เลขบัตร ปชช. ซ้ำในระบบ' : $e->getMessage());
            header('Location: index.php?page=villagers&action=create');
        }
        exit;
    }

    /**
     * Update villager
     */
    public function update(int $id): void
    {
        if ($_SESSION['role'] === ROLE_VIEWER) {
            $this->forbidden();
            return;
        }

        $existing = Villager::find($id);
        if (!$existing) {
            header('Location: index.php?page=villagers');
            exit;
        }

        $data = $this->sanitize($_POST);

        // Handle photo upload (keep old if no new)
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $data['photo_path'] = $this->uploadPhoto($_FILES['photo']);
        } else {
            $data['photo_path'] = $existing['photo_path'];
        }

        try {
            Villager::update($id, $data);
            $this->logActivity('update', 'villagers', $id, 'แก้ไขราษฎร: ' . $data['first_name'] . ' ' . $data['last_name']);

            if (isset($_FILES['documents'])) {
                $this->uploadDocuments($_FILES['documents'], 'villager', $id);
            }

            $_SESSION['flash_success'] = 'แก้ไขข้อมูลเรียบร้อย';
            header("Location: index.php?page=villagers&action=view&id=$id");
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            header("Location: index.php?page=villagers&action=edit&id=$id");
        }
        exit;
    }

    /**
     * Delete villager
     */
    public function delete(int $id): void
    {
        if ($_SESSION['role'] !== ROLE_ADMIN) {
            $this->forbidden();
            return;
        }

        $v = Villager::find($id);
        if ($v) {
            Villager::delete($id);
            $this->logActivity('delete', 'villagers', $id, 'ลบราษฎร: ' . $v['first_name'] . ' ' . $v['last_name']);
            $_SESSION['flash_success'] = 'ลบข้อมูลเรียบร้อย';
        }
        header('Location: index.php?page=villagers');
        exit;
    }

    // --- Helpers ---

    private function sanitize(array $post): array
    {
        return array_map(function ($v) {
            return is_string($v) ? trim($v) : $v; }, $post);
    }

    private function uploadPhoto(array $file): ?string
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_IMAGE_TYPES))
            return null;

        $dir = UPLOAD_PHOTOS;
        if (!is_dir($dir))
            mkdir($dir, 0755, true);

        $name = 'villager_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $dir . $name);
        return 'uploads/photos/' . $name;
    }

    private function uploadDocuments(array $files, string $type, int $id): void
    {
        if (!isset($files['name']) || !is_array($files['name']))
            return;
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK)
                continue;
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

    private function logActivity(string $action, string $table, int $recordId, string $desc): void
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
        }
    }

    private function forbidden(): void
    {
        $_SESSION['flash_error'] = 'คุณไม่มีสิทธิ์ดำเนินการนี้';
        header('Location: index.php?page=villagers');
        exit;
    }
}
