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
    'verification' => 'ตรวจสอบสิทธิ์',
    'subdivision' => 'แบ่งแปลงที่ดิน',
    'forms' => 'แบบฟอร์มราชการ',
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

    <?php if ($currentPage === 'map' || $currentPage === 'dashboard' || $currentPage === 'plots' || $currentPage === 'villages' || $currentPage === 'villagers' || $currentPage === 'verification'): ?>
        <!-- Leaflet.js for Map -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="assets/js/map-popup.js"></script>
    <?php endif; ?>
    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#166534">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
    
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('sw.js')
            .then(reg => console.log('SW registered'))
            .catch(err => console.log('SW registration failed: ', err));
        });
      }
    </script>
</head>

<body>
    <div class="app-wrapper">

        <!-- ===== Sidebar ===== -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon" style="background:none; width:44px; height:44px; display:flex; align-items:center; justify-content:center;">
                    <svg viewBox="0 0 100 100" width="40" height="40" xmlns="http://www.w3.org/2000/svg">
                        <!-- Shield shape -->
                        <path d="M50 5 L90 20 L90 55 Q90 80 50 95 Q10 80 10 55 L10 20 Z" fill="#166534" stroke="#a7f3d0" stroke-width="2.5"/>
                        <!-- Inner shield border -->
                        <path d="M50 12 L83 24 L83 53 Q83 74 50 88 Q17 74 17 53 L17 24 Z" fill="none" stroke="#a7f3d0" stroke-width="1" opacity="0.5"/>
                        <!-- Land plot grid -->
                        <rect x="28" y="35" width="18" height="14" rx="1" fill="#4ade80" opacity="0.9"/>
                        <rect x="49" y="35" width="22" height="14" rx="1" fill="#22c55e" opacity="0.8"/>
                        <rect x="28" y="52" width="12" height="16" rx="1" fill="#22c55e" opacity="0.8"/>
                        <rect x="43" y="52" width="16" height="16" rx="1" fill="#86efac" opacity="0.7"/>
                        <rect x="62" y="52" width="9" height="16" rx="1" fill="#4ade80" opacity="0.9"/>
                        <!-- Tree on top -->
                        <polygon points="50,18 42,32 58,32" fill="#bbf7d0"/>
                        <polygon points="50,23 44,34 56,34" fill="#86efac"/>
                        <rect x="48" y="32" width="4" height="4" fill="#a7f3d0"/>
                    </svg>
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

                <a href="index.php?page=verification" class="nav-item <?= $currentPage === 'verification' ? 'active' : '' ?>">
                    <i class="bi bi-clipboard-check"></i>
                    <span>ตรวจสอบสิทธิ์</span>
                </a>

                <a href="index.php?page=subdivision" class="nav-item <?= $currentPage === 'subdivision' ? 'active' : '' ?>">
                    <i class="bi bi-scissors"></i>
                    <span>แบ่งแปลงที่ดิน</span>
                </a>

                <a href="index.php?page=forms" class="nav-item <?= $currentPage === 'forms' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>แบบฟอร์มราชการ</span>
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
                <div id="pwa-install-btn" class="nav-item" style="background:rgba(255,255,255,0.15); margin-bottom:12px; justify-content:center; color:white; font-weight:600;">
                    <i class="bi bi-download"></i>
                    <span>ติดตั้งแอป</span>
                </div>

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

        <script>
        // PWA Install Logic
        let deferredPrompt;
        const installBtn = document.getElementById('pwa-install-btn');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            installBtn.style.display = 'flex';
        });

        installBtn.addEventListener('click', () => {
            installBtn.style.display = 'none';
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User accepted the A2HS prompt');
                }
                deferredPrompt = null;
            });
        });
        </script>

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