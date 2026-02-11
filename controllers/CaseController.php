<?php
/**
 * CaseController — CRUD คำร้อง/เรื่องร้องเรียน
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../models/Document.php';

class CaseController
{

    public function store(): void
    {
        if ($_SESSION['role'] === ROLE_VIEWER) {
            $this->forbidden();
            return;
        }

        $db = getDB();
        $data = $this->sanitize($_POST);

        // Generate case number
        $year = date('Y') + 543;
        $countStmt = $db->query("SELECT COUNT(*) + 1 FROM cases WHERE YEAR(created_at) = YEAR(CURDATE())");
        $seq = str_pad($countStmt->fetchColumn(), 4, '0', STR_PAD_LEFT);
        $caseNumber = "CS-$year-$seq";

        try {
            $stmt = $db->prepare("INSERT INTO cases 
                (case_number, case_type, subject, description, villager_id, plot_id, priority, status, assigned_to, created_by)
                VALUES (:case_number, :case_type, :subject, :description, :villager_id, :plot_id, :priority, :status, :assigned_to, :created_by)");

            $stmt->execute([
                'case_number' => $caseNumber,
                'case_type' => $data['case_type'],
                'subject' => $data['subject'],
                'description' => $data['description'] ?? null,
                'villager_id' => $data['villager_id'],
                'plot_id' => $data['plot_id'] ?: null,
                'priority' => $data['priority'] ?? 'medium',
                'status' => 'new',
                'assigned_to' => $data['assigned_to'] ?: null,
                'created_by' => $_SESSION['user_id'],
            ]);

            $id = $db->lastInsertId();
            $this->logActivity('create', 'cases', $id, "สร้างคำร้อง: $caseNumber - {$data['subject']}");

            if (isset($_FILES['documents'])) {
                $this->uploadDocs($_FILES['documents'], 'case', $id);
            }

            $_SESSION['flash_success'] = "สร้างคำร้องเรียบร้อย ($caseNumber)";
            header("Location: index.php?page=cases&action=view&id=$id");
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            header('Location: index.php?page=cases&action=create');
        }
        exit;
    }

    public function update(int $id): void
    {
        if ($_SESSION['role'] === ROLE_VIEWER) {
            $this->forbidden();
            return;
        }

        $db = getDB();
        $data = $this->sanitize($_POST);

        try {
            $stmt = $db->prepare("UPDATE cases SET
                case_type = :case_type, subject = :subject, description = :description,
                villager_id = :villager_id, plot_id = :plot_id, priority = :priority,
                status = :status, assigned_to = :assigned_to, resolution = :resolution
                WHERE case_id = :id");

            $stmt->execute([
                'id' => $id,
                'case_type' => $data['case_type'],
                'subject' => $data['subject'],
                'description' => $data['description'] ?? null,
                'villager_id' => $data['villager_id'],
                'plot_id' => $data['plot_id'] ?: null,
                'priority' => $data['priority'] ?? 'medium',
                'status' => $data['status'] ?? 'new',
                'assigned_to' => $data['assigned_to'] ?: null,
                'resolution' => $data['resolution'] ?? null,
            ]);

            $this->logActivity('update', 'cases', $id, "แก้ไขคำร้อง ID $id");

            if (isset($_FILES['documents'])) {
                $this->uploadDocs($_FILES['documents'], 'case', $id);
            }

            $_SESSION['flash_success'] = 'แก้ไขคำร้องเรียบร้อย';
            header("Location: index.php?page=cases&action=view&id=$id");
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            header("Location: index.php?page=cases&action=edit&id=$id");
        }
        exit;
    }

    private function sanitize(array $post): array
    {
        return array_map(fn($v) => is_string($v) ? trim($v) : $v, $post);
    }

    private function uploadDocs(array $files, string $type, int $id): void
    {
        if (!isset($files['name']) || !is_array($files['name']))
            return;
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK)
                continue;
            Document::upload(
                ['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'size' => $files['size'][$i], 'error' => $files['error'][$i]],
                $type,
                $id,
                $_POST['doc_category'] ?? 'other'
            );
        }
    }

    private function logActivity(string $action, string $table, int $rid, string $desc): void
    {
        try {
            $db = getDB();
            $db->prepare("INSERT INTO activity_logs (user_id,action,table_name,record_id,description,ip_address) VALUES (:u,:a,:t,:r,:d,:ip)")
                ->execute(['u' => $_SESSION['user_id'], 'a' => $action, 't' => $table, 'r' => $rid, 'd' => $desc, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
        } catch (PDOException $e) {
        }
    }

    private function forbidden(): void
    {
        $_SESSION['flash_error'] = 'คุณไม่มีสิทธิ์ดำเนินการนี้';
        header('Location: index.php?page=cases');
        exit;
    }
}
