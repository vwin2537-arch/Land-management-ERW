<?php
/**
 * Application Constants
 * ระบบจัดการที่ดินทำกินในเขตอุทยานแห่งชาติ
 */

// App Info
define('APP_NAME', 'ระบบจัดการที่ดินทำกิน');
define('APP_SHORT_NAME', 'LandMS');
define('APP_VERSION', '1.0.0');
define('APP_SUBTITLE', 'อุทยานแห่งชาติเอราวัณ');

// Paths
define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('UPLOAD_PATH', BASE_PATH . 'uploads' . DIRECTORY_SEPARATOR);
define('VIEW_PATH', BASE_PATH . 'views' . DIRECTORY_SEPARATOR);

// Upload Sub-directories
define('UPLOAD_PHOTOS', UPLOAD_PATH . 'photos' . DIRECTORY_SEPARATOR);
define('UPLOAD_DOCUMENTS', UPLOAD_PATH . 'documents' . DIRECTORY_SEPARATOR);
define('UPLOAD_PLOT_IMAGES', UPLOAD_PATH . 'plot_images' . DIRECTORY_SEPARATOR);
define('UPLOAD_MAPS', UPLOAD_PATH . 'maps' . DIRECTORY_SEPARATOR);

// File Upload Settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Pagination
define('ITEMS_PER_PAGE', 20);

// Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_OFFICER', 'officer');
define('ROLE_VIEWER', 'viewer');

// Status Labels (Thai)
define('PLOT_STATUS_LABELS', [
    'surveyed'          => 'สำรวจแล้ว',
    'pending_review'    => 'รอตรวจสอบ',
    'temporary_permit'  => 'อนุญาตชั่วคราว',
    'must_relocate'     => 'ต้องอพยพ',
    'disputed'          => 'มีข้อพิพาท',
]);

define('PLOT_STATUS_COLORS', [
    'surveyed'          => '#28a745',
    'pending_review'    => '#ffc107',
    'temporary_permit'  => '#17a2b8',
    'must_relocate'     => '#dc3545',
    'disputed'          => '#fd7e14',
]);

define('CASE_STATUS_LABELS', [
    'new'                => 'ใหม่',
    'in_progress'        => 'กำลังดำเนินการ',
    'awaiting_approval'  => 'รอผลอนุมัติ',
    'closed'             => 'ปิดเรื่อง',
    'rejected'           => 'ยกเลิก',
]);

define('CASE_TYPE_LABELS', [
    'complaint'       => 'ร้องเรียน',
    'request_use'     => 'ขอใช้พื้นที่',
    'trespass_report' => 'รายงานบุกรุก',
    'renewal'         => 'ขอต่ออายุ',
    'other'           => 'อื่นๆ',
]);

define('LAND_USE_LABELS', [
    'agriculture'  => 'เกษตรกรรม',
    'residential'  => 'ที่อยู่อาศัย',
    'garden'       => 'ทำสวน',
    'livestock'    => 'เลี้ยงสัตว์',
    'mixed'        => 'ผสม',
    'other'        => 'อื่นๆ',
]);

define('ROLE_LABELS', [
    'admin'   => 'ผู้ดูแลระบบ',
    'officer' => 'เจ้าหน้าที่',
    'viewer'  => 'ผู้ชม',
]);

// ============================================================
// CSRF Protection Helpers
// ============================================================

/**
 * Generate or retrieve CSRF token for the current session
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Output hidden input field with CSRF token (use inside forms)
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

/**
 * Validate CSRF token from POST request
 * @return bool true if valid
 */
function csrf_validate(): bool
{
    $token = $_POST['_csrf_token'] ?? '';
    return !empty($token) && hash_equals($_SESSION['_csrf_token'] ?? '', $token);
}
