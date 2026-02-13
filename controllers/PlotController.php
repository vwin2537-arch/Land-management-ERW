<?php
/**
 * PlotController — CRUD แปลงที่ดินทำกิน
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Plot.php';

class PlotController extends BaseController
{

    public function store(): void
    {
        if ($_SESSION['role'] === ROLE_VIEWER) {
            $this->forbidden();
            return;
        }

        $data = $this->sanitize($_POST);
        $data['has_document'] = isset($_POST['has_document']) ? 1 : 0;

        // Handle plot image
        if (isset($_FILES['plot_image']) && $_FILES['plot_image']['error'] === UPLOAD_ERR_OK) {
            $data['plot_image_path'] = $this->uploadPlotImage($_FILES['plot_image']);
        }

        try {
            $id = Plot::create($data);
            $this->logActivity('create', 'land_plots', $id, 'เพิ่มแปลง: ' . $data['plot_code']);

            if (isset($_FILES['documents'])) {
                $this->uploadDocuments($_FILES['documents'], 'plot', $id);
            }

            $_SESSION['flash_success'] = 'เพิ่มแปลงที่ดินเรียบร้อย';
            header("Location: index.php?page=plots&action=view&id=$id");
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'เกิดข้อผิดพลาด: ' . ($e->getCode() == 23000 ? 'รหัสแปลงซ้ำ' : $e->getMessage());
            header('Location: index.php?page=plots&action=create');
        }
        exit;
    }

    public function update(int $id): void
    {
        if ($_SESSION['role'] === ROLE_VIEWER) {
            $this->forbidden();
            return;
        }

        $existing = Plot::find($id);
        if (!$existing) {
            header('Location: index.php?page=plots');
            exit;
        }

        $data = $this->sanitize($_POST);
        $data['has_document'] = isset($_POST['has_document']) ? 1 : 0;

        if (isset($_FILES['plot_image']) && $_FILES['plot_image']['error'] === UPLOAD_ERR_OK) {
            $data['plot_image_path'] = $this->uploadPlotImage($_FILES['plot_image']);
        } else {
            $data['plot_image_path'] = $existing['plot_image_path'];
        }

        try {
            Plot::update($id, $data);
            $this->logActivity('update', 'land_plots', $id, 'แก้ไขแปลง: ' . $data['plot_code']);

            if (isset($_FILES['documents'])) {
                $this->uploadDocuments($_FILES['documents'], 'plot', $id);
            }

            $_SESSION['flash_success'] = 'แก้ไขข้อมูลแปลงเรียบร้อย';
            header("Location: index.php?page=plots&action=view&id=$id");
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            header("Location: index.php?page=plots&action=edit&id=$id");
        }
        exit;
    }

    public function delete(int $id): void
    {
        if ($_SESSION['role'] !== ROLE_ADMIN) {
            $this->forbidden();
            return;
        }

        $p = Plot::find($id);
        if ($p) {
            Plot::delete($id);
            $this->logActivity('delete', 'land_plots', $id, 'ลบแปลง: ' . $p['plot_code']);
            $_SESSION['flash_success'] = 'ลบแปลงที่ดินเรียบร้อย';
        }
        header('Location: index.php?page=plots');
        exit;
    }

    private function uploadPlotImage(array $file): ?string
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_IMAGE_TYPES))
            return null;
        $dir = UPLOAD_PLOT_IMAGES;
        if (!is_dir($dir))
            mkdir($dir, 0755, true);
        $name = 'plot_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $dir . $name);
        return 'uploads/plot_images/' . $name;
    }

}
