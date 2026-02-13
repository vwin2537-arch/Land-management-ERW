<?php
/**
 * แบ่งแปลงที่ดิน — หน้าดำเนินการแบ่งแปลง
 */
require_once __DIR__ . '/../../controllers/SubdivisionController.php';

$villagerId = (int)($_GET['id'] ?? 0);
if (!$villagerId) {
    echo '<div class="content-area"><div class="alert alert-danger">ไม่พบข้อมูลผู้ครอบครอง</div></div>';
    return;
}

$villager = SubdivisionController::getVillagerDetail($villagerId);
if (!$villager) {
    echo '<div class="content-area"><div class="alert alert-danger">ไม่พบข้อมูลผู้ครอบครอง ID: ' . $villagerId . '</div></div>';
    return;
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$totalRai = $villager['total_rai'];
$over20 = $totalRai > 20;
$over40 = $totalRai > 40;
$excessOver20 = max(0, $totalRai - 20);
$excessOver40 = max(0, $totalRai - 40);
?>

<div class="content-area">
    <div class="page-header">
        <div>
            <h1><i class="bi bi-scissors"></i> แบ่งแปลงที่ดิน</h1>
            <p class="page-subtitle">
                <?= htmlspecialchars(($villager['prefix'] ?? '') . $villager['first_name'] . ' ' . $villager['last_name']) ?>
                — เลขบัตร <?= htmlspecialchars($villager['id_card_number'] ?? '-') ?>
            </p>
        </div>
        <a href="index.php?page=subdivision" class="btn btn-outline">
            <i class="bi bi-arrow-left"></i> กลับรายชื่อ
        </a>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <!-- สรุปเนื้อที่ -->
    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:20px;">
        <div class="card" style="padding:16px; text-align:center;">
            <div style="font-size:24px; font-weight:700; color:#1d4ed8;"><?= number_format($totalRai, 2) ?></div>
            <div style="color:#666; font-size:13px;">เนื้อที่รวมทั้งหมด (ไร่)</div>
        </div>
        <div class="card" style="padding:16px; text-align:center;">
            <div style="font-size:24px; font-weight:700; color:#b45309;"><?= number_format($excessOver20, 2) ?></div>
            <div style="color:#666; font-size:13px;">ส่วนเกิน 20 ไร่ (ต้องแบ่งครัวเรือน)</div>
        </div>
        <div class="card" style="padding:16px; text-align:center;">
            <div style="font-size:24px; font-weight:700; color:<?= $over40 ? '#dc2626' : '#16a34a' ?>;"><?= number_format($excessOver40, 2) ?></div>
            <div style="color:#666; font-size:13px;">ส่วนเกิน 40 ไร่ (ม.19)</div>
        </div>
        <div class="card" style="padding:16px; text-align:center;">
            <div style="font-size:24px; font-weight:700; color:#16a34a;"><?= number_format($villager['subdivided_rai'], 2) ?></div>
            <div style="color:#666; font-size:13px;">แบ่งแล้ว (ไร่)</div>
        </div>
    </div>

    <!-- แปลงต้นฉบับ -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <h3><i class="bi bi-map"></i> แปลงที่ดินต้นฉบับ (<?= count($villager['original_plots']) ?> แปลง)</h3>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ที่ดินเลขที่</th>
                        <th>เขตโครงการ</th>
                        <th>เขตสำรวจ</th>
                        <th>ที่ดินสำรวจ</th>
                        <th style="text-align:right;">ไร่</th>
                        <th style="text-align:right;">งาน</th>
                        <th style="text-align:right;">ตร.วา</th>
                        <th style="text-align:right;">รวม (ไร่)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($villager['original_plots'] as $p): 
                        $pRai = $p['area_rai'] + $p['area_ngan']/4 + $p['area_sqwa']/400;
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['num_apar'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars($p['apar_no'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($p['spar_no'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($p['num_spar'] ?? '-') ?></td>
                            <td style="text-align:right;"><?= (int)$p['area_rai'] ?></td>
                            <td style="text-align:right;"><?= (int)$p['area_ngan'] ?></td>
                            <td style="text-align:right;"><?= (int)$p['area_sqwa'] ?></td>
                            <td style="text-align:right;"><strong><?= number_format($pRai, 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight:700; background:#f9fafb;">
                        <td colspan="7" style="text-align:right;">รวมทั้งหมด</td>
                        <td style="text-align:right;"><?= number_format($totalRai, 2) ?> ไร่</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- แปลงที่แบ่งแล้ว -->
    <?php if (!empty($villager['subdivided_plots'])): ?>
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <h3><i class="bi bi-check-circle" style="color:#16a34a;"></i> แปลงที่แบ่งแล้ว (<?= count($villager['subdivided_plots']) ?> แปลง)</h3>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ที่ดินเลขที่</th>
                        <th>ประเภท</th>
                        <th>ผู้ครอบครอง</th>
                        <th style="text-align:right;">ไร่</th>
                        <th style="text-align:right;">งาน</th>
                        <th style="text-align:right;">ตร.วา</th>
                        <th>หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($villager['subdivided_plots'] as $sp): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($sp['num_apar'] ?? '-') ?></strong></td>
                            <td>
                                <?php if (str_starts_with($sp['num_apar'] ?? '', '3')): ?>
                                    <span class="badge badge-info">แบ่งครัวเรือน</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">ม.19</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(($sp['assigned_prefix'] ?? '') . ($sp['assigned_first_name'] ?? '') . ' ' . ($sp['assigned_last_name'] ?? '')) ?></td>
                            <td style="text-align:right;"><?= (int)$sp['area_rai'] ?></td>
                            <td style="text-align:right;"><?= (int)$sp['area_ngan'] ?></td>
                            <td style="text-align:right;"><?= (int)$sp['area_sqwa'] ?></td>
                            <td style="font-size:12px;"><?= htmlspecialchars($sp['notes'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="card" style="margin-bottom:20px;">
        <div style="padding:40px; text-align:center; color:#999;">
            <i class="bi bi-info-circle" style="font-size:32px;"></i>
            <p style="margin-top:8px;">ยังไม่มีแปลงที่แบ่ง — กรุณาจัดสรรจากหน้า "ตรวจสอบสิทธิ์" ก่อน</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ปุ่มไปจัดสรร -->
    <div class="card">
        <div style="padding:20px; display:flex; gap:12px; justify-content:center;">
            <a href="index.php?page=verification&action=process&id=<?= $villagerId ?>" class="btn btn-primary">
                <i class="bi bi-clipboard-check"></i> ไปหน้าจัดสรร (ตรวจสอบสิทธิ์)
            </a>
            <a href="index.php?page=subdivision" class="btn btn-outline">
                <i class="bi bi-arrow-left"></i> กลับรายชื่อ
            </a>
        </div>
    </div>
</div>
