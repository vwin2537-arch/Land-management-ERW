<?php
/**
 * แบ่งแปลงที่ดิน — Dashboard สรุปสถานะการแบ่งแปลง
 * ข้อมูลแบ่งแปลงจริงสร้างอัตโนมัติจากหน้า "ตรวจสอบสิทธิ์"
 */
require_once __DIR__ . '/../../controllers/SubdivisionController.php';

$filter = $_GET['filter'] ?? 'all';
$villagers = SubdivisionController::getQualifyingVillagers($filter);

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<div class="content-area">
    <div class="page-header">
        <div>
            <h1><i class="bi bi-scissors"></i> แบ่งแปลงที่ดิน</h1>
            <p class="page-subtitle">สรุปสถานะการแบ่งแปลง — จัดสรรจริงจากหน้า "ตรวจสอบสิทธิ์" → สร้างแปลงขึ้นต้น 3 (ครัวเรือน) และ 4 (ม.19) อัตโนมัติ</p>
        </div>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="card" style="margin-bottom:20px;">
        <div style="display:flex; gap:10px; padding:16px;">
            <a href="index.php?page=subdivision&filter=all" 
               class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline' ?>" style="text-decoration:none;">
                ทั้งหมด (>20 ไร่)
            </a>
            <a href="index.php?page=subdivision&filter=over20" 
               class="btn <?= $filter === 'over20' ? 'btn-primary' : 'btn-outline' ?>" style="text-decoration:none;">
                เกิน 20 ไร่ (แบ่งครัวเรือน)
            </a>
            <a href="index.php?page=subdivision&filter=over40" 
               class="btn <?= $filter === 'over40' ? 'btn-primary' : 'btn-outline' ?>" style="text-decoration:none;">
                เกิน 40 ไร่ (ม.19)
            </a>
        </div>
    </div>

    <!-- Summary -->
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:20px;">
        <?php
            $countAll = count($villagers);
            $countOver40 = count(array_filter($villagers, fn($v) => $v['total_rai'] > 40));
            $countOver20 = $countAll - $countOver40;
            $countDone = count(array_filter($villagers, fn($v) => $v['subdivided_count'] > 0));
        ?>
        <div class="card" style="padding:20px; text-align:center;">
            <div style="font-size:28px; font-weight:700; color:#b45309;"><?= $countAll ?></div>
            <div style="color:#666;">ผู้ครอบครอง >20 ไร่ ทั้งหมด</div>
        </div>
        <div class="card" style="padding:20px; text-align:center;">
            <div style="font-size:28px; font-weight:700; color:#dc2626;"><?= $countOver40 ?></div>
            <div style="color:#666;">เกิน 40 ไร่ (ม.19)</div>
        </div>
        <div class="card" style="padding:20px; text-align:center;">
            <div style="font-size:28px; font-weight:700; color:#16a34a;"><?= $countDone ?></div>
            <div style="color:#666;">แบ่งแปลงแล้ว</div>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>ชื่อ-สกุล</th>
                        <th>เลขบัตรประชาชน</th>
                        <th style="text-align:right;">จำนวนแปลง</th>
                        <th style="text-align:right;">เนื้อที่รวม (ไร่)</th>
                        <th>เขตโครงการ</th>
                        <th style="text-align:center;">สถานะ</th>
                        <th style="text-align:center;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($villagers)): ?>
                        <tr><td colspan="8" style="padding:40px; text-align:center; color:#999;">
                            <i class="bi bi-info-circle" style="font-size:24px;"></i><br>
                            ไม่พบผู้ครอบครองที่เข้าเงื่อนไข
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($villagers as $idx => $v): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars(($v['prefix'] ?? '') . $v['first_name'] . ' ' . $v['last_name']) ?></strong>
                                </td>
                                <td style="font-family:monospace;"><?= htmlspecialchars($v['id_card_number'] ?? '-') ?></td>
                                <td style="text-align:right;"><?= $v['plot_count'] ?></td>
                                <td style="text-align:right;">
                                    <strong style="color:<?= $v['total_rai'] > 40 ? '#dc2626' : '#b45309' ?>;">
                                        <?= number_format($v['total_rai'], 2) ?>
                                    </strong>
                                    <?php if ($v['total_rai'] > 40): ?>
                                        <span style="font-size:11px; color:#dc2626;">(ม.19)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($v['zones'] ?? '-') ?></td>
                                <td style="text-align:center;">
                                    <?php if ($v['subdivided_count'] > 0): ?>
                                        <span class="badge badge-success">แบ่งแล้ว <?= $v['subdivided_count'] ?> แปลง</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">รอดำเนินการ</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($v['subdivided_count'] > 0): ?>
                                        <a href="index.php?page=subdivision&action=process&id=<?= $v['villager_id'] ?>" 
                                           class="btn btn-sm btn-outline" title="ดูรายละเอียด">
                                            <i class="bi bi-eye"></i> ดูรายละเอียด
                                        </a>
                                    <?php else: ?>
                                        <a href="index.php?page=verification&action=process&id=<?= $v['villager_id'] ?>" 
                                           class="btn btn-sm btn-primary" title="ไปจัดสรร">
                                            <i class="bi bi-clipboard-check"></i> จัดสรร
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
