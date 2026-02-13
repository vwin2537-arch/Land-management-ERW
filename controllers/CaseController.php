<?php
/**
 * CaseController — CRUD คำร้อง/เรื่องร้องเรียน
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Case_.php';

class CaseController extends BaseController
{

    public function store(): void
    {
        if ($this->isViewer()) {
            $this->forbidden();
            return;
        }

        $data = $this->sanitize($_POST);

        try {
            $id = Case_::create($data);
            $caseNumber = Case_::getCaseNumber($id);
            $this->logActivity('create', 'cases', $id, "สร้างคำร้อง: $caseNumber - {$data['subject']}");

            if (isset($_FILES['documents'])) {
                $this->uploadDocuments($_FILES['documents'], 'case', $id);
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
        if ($this->isViewer()) {
            $this->forbidden();
            return;
        }

        $data = $this->sanitize($_POST);

        try {
            Case_::update($id, $data);
            $this->logActivity('update', 'cases', $id, "แก้ไขคำร้อง ID $id");

            if (isset($_FILES['documents'])) {
                $this->uploadDocuments($_FILES['documents'], 'case', $id);
            }

            $_SESSION['flash_success'] = 'แก้ไขคำร้องเรียบร้อย';
            header("Location: index.php?page=cases&action=view&id=$id");
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            header("Location: index.php?page=cases&action=edit&id=$id");
        }
        exit;
    }

}
