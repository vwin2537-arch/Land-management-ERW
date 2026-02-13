<?php
/**
 * Entry Point — ระบบจัดการที่ดินทำกิน
 * Routes all requests through a simple router
 */

session_start();

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';

// Simple Router
$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

// Auth check — redirect to login if not logged in
$publicPages = ['login'];
if (!isset($_SESSION['user_id']) && !in_array($page, $publicPages)) {
    header('Location: index.php?page=login');
    exit;
}

// Handle Login/Logout POST
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/controllers/AuthController.php';
    $auth = new AuthController();
    $auth->login();
    exit;
}

if ($page === 'logout') {
    require_once __DIR__ . '/controllers/AuthController.php';
    $auth = new AuthController();
    $auth->logout();
    exit;
}

// Simple JSON API endpoints
if ($page === 'api_plots' && isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    $vid = (int) ($_GET['villager_id'] ?? 0);
    if ($vid > 0) {
        $db = getDB();
        $stmt = $db->prepare("SELECT plot_id, plot_code FROM land_plots WHERE villager_id = :vid ORDER BY plot_code");
        $stmt->execute(['vid' => $vid]);
        echo json_encode($stmt->fetchAll());
    } else {
        echo '[]';
    }
    exit;
}

// Verification POST handlers (before layout)
if ($page === 'verification' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/controllers/VerificationController.php';

    if ($action === 'verify_simple') {
        $vid = (int)($_POST['villager_id'] ?? 0);
        if ($vid && VerificationController::verifySimple($vid)) {
            $v = VerificationController::getVillagerById($vid);
            $idCard = $v['id_card_number'] ?? '';
            $_SESSION['flash_success'] = 'ยืนยันสิทธิ์สำเร็จ — สามารถพิมพ์หนังสือรับรองตนเองได้';
            header("Location: index.php?page=verification&id_card=" . urlencode($idCard));
        } else {
            $_SESSION['flash_error'] = 'เกิดข้อผิดพลาดในการยืนยันสิทธิ์';
            header("Location: index.php?page=verification");
        }
        exit;
    }

    if ($action === 'save_allocation') {
        $vid = (int)($_POST['villager_id'] ?? 0);
        $rawAllocs = $_POST['allocations'] ?? [];

        // เพิ่มสมาชิกครัวเรือนใหม่ก่อน แล้วได้ member_id กลับมา
        $newMemberIds = []; // 'new_1' => actual_member_id
        $newMembers = $_POST['new_members'] ?? [];
        foreach ($newMembers as $idx => $nm) {
            $fname = trim($nm['first_name'] ?? '');
            $lname = trim($nm['last_name'] ?? '');
            if ($fname && $lname) {
                $mid = VerificationController::addHouseholdMember($vid, [
                    'prefix' => $nm['prefix'] ?? '',
                    'first_name' => $fname,
                    'last_name' => $lname,
                    'id_card_number' => $nm['id_card'] ?? '',
                    'relationship' => $nm['relationship'] ?? '',
                ]);
                $newMemberIds['new_' . $idx] = $mid;
            }
        }

        // สร้าง allocations array สำหรับ controller
        $allocations = [];
        foreach ($rawAllocs as $a) {
            $memberId = null;
            $rawMid = $a['member_id'] ?? '';
            if ($rawMid && str_starts_with($rawMid, 'new_')) {
                $memberId = $newMemberIds[$rawMid] ?? null;
            } elseif ($rawMid && is_numeric($rawMid)) {
                $memberId = (int)$rawMid;
            }

            $allocations[] = [
                'plot_id' => (int)($a['plot_id'] ?? 0),
                'type' => $a['type'] ?? 'section19',
                'area' => (float)($a['area'] ?? 0),
                'member_id' => $memberId,
            ];
        }

        if ($vid && VerificationController::saveAllocation($vid, $allocations)) {
            $v = VerificationController::getVillagerById($vid);
            $idCard = $v['id_card_number'] ?? '';
            $_SESSION['flash_success'] = 'บันทึกการจัดสรรที่ดินสำเร็จ';
            header("Location: index.php?page=verification&id_card=" . urlencode($idCard));
        } else {
            $_SESSION['flash_error'] = 'เกิดข้อผิดพลาดในการบันทึก';
            header("Location: index.php?page=verification&action=process&id=" . $vid);
        }
        exit;
    }
}

// Form Print Export (standalone page — no layout)
if ($page === 'forms' && $action === 'print' && isset($_SESSION['user_id'])) {
    $type = $_GET['type'] ?? '';
    $formView = match ($type) {
        'form61' => 'forms/form61.php',
        'form62' => 'forms/form62.php',
        'form63' => 'forms/form63.php',
        'account11' => 'forms/account11.php',
        'account12' => 'forms/account12.php',
        'self_cert' => 'forms/self_cert.php',
        'self_cert_heir' => 'forms/self_cert_heir.php',
        default => null,
    };
    if ($formView) {
        include VIEW_PATH . $formView;
        exit;
    }
}

// Report Excel Export (exits before layout rendering)
if ($page === 'reports' && $action === 'export' && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/controllers/ReportController.php';
    $code = $_GET['code'] ?? '';
    $filters = [
        'search' => $_GET['search'] ?? '',
        'status' => $_GET['status'] ?? '',
        'land_use_type' => $_GET['land_use_type'] ?? '',
        'zone' => $_GET['zone'] ?? '',
        'case_type' => $_GET['case_type'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'province' => $_GET['province'] ?? '',
    ];
    ReportController::exportExcel($code, $filters);
    exit;
}

// Handle CRUD POST actions (before rendering)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    // CSRF validation for all authenticated POST requests
    if (!csrf_validate()) {
        $_SESSION['flash_error'] = 'เซสชันหมดอายุ กรุณาลองใหม่อีกครั้ง';
        header('Location: index.php?page=' . urlencode($page));
        exit;
    }
    switch ($page) {
        case 'villagers':
            require_once __DIR__ . '/controllers/VillagerController.php';
            $ctrl = new VillagerController();
            if ($action === 'create') {
                $ctrl->store();
                exit;
            }
            if ($action === 'edit' && $id) {
                $ctrl->update($id);
                exit;
            }
            if ($action === 'delete' && $id) {
                $ctrl->delete($id);
                exit;
            }
            break;
        case 'plots':
            require_once __DIR__ . '/controllers/PlotController.php';
            $ctrl = new PlotController();
            if ($action === 'create') {
                $ctrl->store();
                exit;
            }
            if ($action === 'edit' && $id) {
                $ctrl->update($id);
                exit;
            }
            if ($action === 'delete' && $id) {
                $ctrl->delete($id);
                exit;
            }
            break;
        case 'cases':
            require_once __DIR__ . '/controllers/CaseController.php';
            $ctrl = new CaseController();
            if ($action === 'create') {
                $ctrl->store();
                exit;
            }
            if ($action === 'edit' && $id) {
                $ctrl->update($id);
                exit;
            }
            break;
        case 'users':
            require_once __DIR__ . '/controllers/UserController.php';
            $ctrl = new UserController();
            if ($action === 'create') {
                $ctrl->store();
                exit;
            }
            if ($action === 'edit' && $id) {
                $ctrl->update($id);
                exit;
            }
            break;
        case 'documents':
            require_once __DIR__ . '/controllers/DocumentController.php';
            $ctrl = new DocumentController();
            if ($action === 'upload') {
                $ctrl->upload();
                exit;
            }
            if ($action === 'delete' && $id) {
                $ctrl->delete($id);
                exit;
            }
            break;
    }
}

// If logged in, show appropriate page
if (isset($_SESSION['user_id'])) {
    // Load layout header (sidebar + topbar)
    include VIEW_PATH . 'layout/header.php';

    // Route to page + action
    switch ($page) {
        case 'dashboard':
            include VIEW_PATH . 'dashboard/index.php';
            break;
        case 'villagers':
            if ($action === 'create' || $action === 'edit') {
                include VIEW_PATH . 'villagers/form.php';
            } elseif ($action === 'view' && $id) {
                include VIEW_PATH . 'villagers/detail.php';
            } else {
                include VIEW_PATH . 'villagers/list.php';
            }
            break;
        case 'plots':
            if ($action === 'create' || $action === 'edit') {
                include VIEW_PATH . 'plots/form.php';
            } elseif ($action === 'view' && $id) {
                include VIEW_PATH . 'plots/detail.php';
            } else {
                include VIEW_PATH . 'plots/list.php';
            }
            break;
        case 'cases':
            if ($action === 'create' || $action === 'edit') {
                include VIEW_PATH . 'cases/form.php';
            } elseif ($action === 'view' && $id) {
                include VIEW_PATH . 'cases/detail.php';
            } else {
                include VIEW_PATH . 'cases/list.php';
            }
            break;
        case 'map':
            include VIEW_PATH . 'map/index.php';
            break;
        case 'villages':
            if ($action === 'view' && (isset($_GET['name']) || isset($_GET['ban_e']))) {
                include VIEW_PATH . 'villages/detail.php';
            } else {
                include VIEW_PATH . 'villages/list.php';
            }
            break;
        case 'subdivision':
            if ($action === 'process') {
                include VIEW_PATH . 'subdivision/process.php';
            } else {
                include VIEW_PATH . 'subdivision/index.php';
            }
            break;
        case 'verification':
            if ($action === 'process') {
                include VIEW_PATH . 'verification/process.php';
            } else {
                include VIEW_PATH . 'verification/index.php';
            }
            break;
        case 'forms':
            include VIEW_PATH . 'forms/index.php';
            break;
        case 'reports':
            if ($action === 'preview') {
                include VIEW_PATH . 'reports/preview.php';
            } else {
                include VIEW_PATH . 'reports/index.php';
            }
            break;
        case 'users':
            if ($action === 'create' || $action === 'edit') {
                include VIEW_PATH . 'users/form.php';
            } else {
                include VIEW_PATH . 'users/list.php';
            }
            break;
        default:
            include VIEW_PATH . 'dashboard/index.php';
            break;
    }

    // Load layout footer
    include VIEW_PATH . 'layout/footer.php';
} else {
    // Show login page
    include VIEW_PATH . 'auth/login.php';
}
