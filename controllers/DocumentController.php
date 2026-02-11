<?php
/**
 * DocumentController — Upload / Delete เอกสาร
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../models/Document.php';

class DocumentController
{

    public function upload(): void
    {
        if ($_SESSION['role'] === ROLE_VIEWER) {
            $_SESSION['flash_error'] = 'คุณไม่มีสิทธิ์อัปโหลด';
            header('Location: ' . $_SERVER['HTTP_REFERER'] ?? 'index.php');
            exit;
        }

        $relatedType = $_POST['related_type'] ?? '';
        $relatedId = (int) ($_POST['related_id'] ?? 0);
        $category = $_POST['doc_category'] ?? 'other';
        $description = trim($_POST['description'] ?? '');

        if (!$relatedType || !$relatedId || !isset($_FILES['file'])) {
            $_SESSION['flash_error'] = 'ข้อมูลไม่ครบ';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
            exit;
        }

        $docId = Document::upload($_FILES['file'], $relatedType, $relatedId, $category, $description);

        if ($docId) {
            $_SESSION['flash_success'] = 'อัปโหลดเอกสารเรียบร้อย';
        } else {
            $_SESSION['flash_error'] = 'อัปโหลดไม่สำเร็จ กรุณาตรวจสอบไฟล์';
        }

        // Redirect back to detail page
        $backPage = match ($relatedType) {
            'villager' => "index.php?page=villagers&action=view&id=$relatedId",
            'plot' => "index.php?page=plots&action=view&id=$relatedId",
            'case' => "index.php?page=cases&action=view&id=$relatedId",
            default => 'index.php',
        };
        header("Location: $backPage");
        exit;
    }

    public function delete(int $id): void
    {
        if ($_SESSION['role'] === ROLE_VIEWER) {
            $_SESSION['flash_error'] = 'คุณไม่มีสิทธิ์ลบ';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
            exit;
        }

        Document::delete($id);
        $_SESSION['flash_success'] = 'ลบเอกสารเรียบร้อย';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }
}
