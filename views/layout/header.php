<?php
/**
 * Layout Header — Sidebar + Top Bar
 */
$currentPage = $_GET['page'] ?? 'dashboard';
$pageTitle = match ($currentPage) {
    'dashboard' => 'แดชบอร์ด',
    'villagers' => 'ทะเบียนราษฎร',
    'villages' => '12 หมู่บ้านสำรวจ',
    'plots' => 'แปลงที่ดินทำกิน',
    'cases' => 'คำร้อง/เรื่องร้องเรียน',
    'map' => 'แผนที่',
    'reports' => 'รายงาน',
    'users' => 'จัดการผู้ใช้งาน',
    default => 'แดชบอร์ด',
};

// Get user initials for avatar
$initials = mb_substr($_SESSION['full_name'] ?? 'U', 0, 1, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $pageTitle ?> —
        <?= APP_NAME ?>
    </title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- App CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

    <?php if ($currentPage === 'map' || $currentPage === 'dashboard' || $currentPage === 'plots' || $currentPage === 'villages' || $currentPage === 'villagers'): ?>
        <!-- Leaflet.js for Map -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <?php endif; ?>
</head>

<body>
    <div class="app-wrapper">

        <!-- ===== Sidebar ===== -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon">
                    <i class="bi bi-tree-fill"></i>
                </div>
                <div class="logo-text">
                    <h2>
                        <?= APP_NAME ?>
                    </h2>
                    <p>
                        <?= APP_SUBTITLE ?>
                    </p>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section-title">หน้าหลัก</div>

                <a href="index.php?page=dashboard" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>แดชบอร์ด</span>
                </a>

                <div class="nav-section-title">ข้อมูลหลัก</div>

                <a href="index.php?page=villagers" class="nav-item <?= $currentPage === 'villagers' ? 'active' : '' ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>ทะเบียนราษฎร</span>
                </a>

                <a href="index.php?page=plots" class="nav-item <?= $currentPage === 'plots' ? 'active' : '' ?>">
                    <i class="bi bi-map-fill"></i>
                    <span>แปลงที่ดินทำกิน</span>
                </a>

                <a href="index.php?page=villages" class="nav-item <?= $currentPage === 'villages' ? 'active' : '' ?>">
                    <i class="bi bi-houses-fill"></i>
                    <span>12 หมู่บ้านสำรวจ</span>
                </a>

                <a href="index.php?page=cases" class="nav-item <?= $currentPage === 'cases' ? 'active' : '' ?>">
                    <i class="bi bi-folder-fill"></i>
                    <span>คำร้อง/เรื่องร้องเรียน</span>
                </a>

                <div class="nav-section-title">เครื่องมือ</div>

                <a href="index.php?page=map" class="nav-item <?= $currentPage === 'map' ? 'active' : '' ?>">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>แผนที่</span>
                </a>

                <a href="index.php?page=reports" class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-bar-graph-fill"></i>
                    <span>รายงาน / PDF</span>
                </a>

                <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
                    <div class="nav-section-title">ผู้ดูแลระบบ</div>

                    <a href="index.php?page=users" class="nav-item <?= $currentPage === 'users' ? 'active' : '' ?>">
                        <i class="bi bi-person-gear"></i>
                        <span>จัดการผู้ใช้งาน</span>
                    </a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="avatar">
                        <?= $initials ?>
                    </div>
                    <div class="user-info">
                        <div class="name">
                            <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?>
                        </div>
                        <div class="role">
                            <?= ROLE_LABELS[$_SESSION['role'] ?? 'officer'] ?>
                        </div>
                    </div>
                    <a href="index.php?page=logout" title="ออกจากระบบ"
                        style="color:rgba(255,255,255,0.5); font-size:18px;">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>
        </aside>

        <!-- ===== Main Content ===== -->
        <div class="main-content">
            <!-- Top Bar -->
            <header class="topbar">
                <div class="topbar-left">
                    <button class="topbar-btn d-none-desktop" id="sidebarToggle" onclick="toggleSidebar()">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <div class="page-title">
                            <?= $pageTitle ?>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="search-bar">
                        <i class="bi bi-search"></i>
                        <input type="text" id="globalSearch" placeholder="ค้นหา... (บัตร ปชช. / ชื่อ / รหัสแปลง)">
                    </div>
                    <button class="topbar-btn" title="ออกจากระบบ" onclick="location.href='index.php?page=logout'">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-content">