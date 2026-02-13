<?php
/**
 * ตรวจสอบสิทธิ — ค้นหาเลขบัตรประชาชน → แสดงผลรวมเนื้อที่ → ดำเนินการ
 */
require_once __DIR__ . '/../../controllers/VerificationController.php';

$stats = VerificationController::getStats();
$searchResult = null;
$searchIdCard = $_GET['id_card'] ?? '';

if ($searchIdCard) {
    $searchResult = VerificationController::searchByIdCard($searchIdCard);
}
?>

<!-- Stats Bar -->
<div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; margin-bottom:24px;">
    <div class="stat-card" style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:16px;">
        <div style="font-size:13px; color:#166534;">ตรวจแล้ว</div>
        <div style="font-size:28px; font-weight:700; color:#166534;"><?= $stats['verified'] ?></div>
    </div>
    <div class="stat-card" style="background:#fef9c3; border:1px solid #fde047; border-radius:12px; padding:16px;">
        <div style="font-size:13px; color:#854d0e;">ยังไม่ตรวจ</div>
        <div style="font-size:28px; font-weight:700; color:#854d0e;"><?= $stats['pending'] ?></div>
    </div>
    <div class="stat-card" style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:12px; padding:16px;">
        <div style="font-size:13px; color:#1e40af;">ทั้งหมด</div>
        <div style="font-size:28px; font-weight:700; color:#1e40af;"><?= $stats['total'] ?></div>
    </div>
</div>

<!-- Search Bar -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <h3><i class="bi bi-search"></i> ค้นหาเลขบัตรประชาชน</h3>
    </div>
    <div class="card-body">
        <form method="GET" action="index.php" style="display:flex; gap:12px; align-items:flex-end;">
            <input type="hidden" name="page" value="verification">
            <div class="form-group" style="flex:1;">
                <label>เลขบัตรประชาชน 13 หลัก</label>
                <input type="text" name="id_card" class="form-control" value="<?= htmlspecialchars($searchIdCard) ?>"
                       placeholder="กรอกเลขบัตรประชาชน..." maxlength="13" 
                       style="font-size:18px; font-family:monospace; letter-spacing:2px; padding:12px;">
            </div>
            <button type="submit" class="btn btn-primary" style="height:50px; padding:0 32px; font-size:16px;">
                <i class="bi bi-search"></i> ค้นหา
            </button>
        </form>
    </div>
</div>

<?php if ($searchIdCard && !$searchResult): ?>
    <!-- Not Found -->
    <div class="card" style="border:2px solid #fca5a5;">
        <div class="card-body" style="text-align:center; padding:40px;">
            <i class="bi bi-person-x" style="font-size:48px; color:#dc2626;"></i>
            <h3 style="margin-top:12px; color:#dc2626;">ไม่พบข้อมูลราษฎร</h3>
            <p style="color:#666;">เลขบัตรประชาชน <strong style="font-family:monospace;"><?= htmlspecialchars($searchIdCard) ?></strong> ไม่มีในฐานข้อมูล</p>
        </div>
    </div>

<?php elseif ($searchResult): ?>
    <?php
    $v = $searchResult;
    $plots = $v['plots'];
    $area = $v['area_summary'];
    $fullName = ($v['prefix'] ?? '') . ($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? '');
    $isVerified = ($v['verification_status'] ?? 'pending') === 'verified';

    // ดึง par_ban จากแปลงแรก
    $parBan = $plots[0]['par_ban'] ?? '-';
    $parMoo = $plots[0]['par_moo'] ?? '-';
    $parkName = $plots[0]['park_name'] ?? '-';
    ?>

    <!-- Villager Info -->
    <div class="card" style="margin-bottom:16px; <?= $isVerified ? 'border:2px solid #22c55e;' : '' ?>">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h3><i class="bi bi-person-fill"></i> ข้อมูลราษฎร</h3>
            <?php if ($isVerified): ?>
                <span style="background:#22c55e; color:#fff; padding:4px 16px; border-radius:20px; font-weight:600;">
                    <i class="bi bi-check-circle"></i> ตรวจสอบแล้ว
                </span>
            <?php else: ?>
                <span style="background:#eab308; color:#fff; padding:4px 16px; border-radius:20px; font-weight:600;">
                    <i class="bi bi-clock"></i> ยังไม่ได้ตรวจ
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px;">
                <div>
                    <small style="color:#888;">ชื่อ-นามสกุล</small>
                    <div style="font-size:18px; font-weight:600;"><?= htmlspecialchars($fullName) ?></div>
                </div>
                <div>
                    <small style="color:#888;">เลขบัตรประชาชน</small>
                    <div style="font-size:18px; font-family:monospace;"><?= htmlspecialchars($v['id_card_number']) ?></div>
                </div>
                <div>
                    <small style="color:#888;">บ้าน/หมู่</small>
                    <div style="font-size:18px;">บ.<?= htmlspecialchars($parBan) ?> ม.<?= htmlspecialchars($parMoo) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Area Summary -->
    <?php
    $statusColor = match($area['status']) {
        'within_20' => '#166534',
        'over_20' => '#b45309',
        'over_40' => '#dc2626',
    };
    $statusBg = match($area['status']) {
        'within_20' => '#f0fdf4',
        'over_20' => '#fffbeb',
        'over_40' => '#fef2f2',
    };
    $statusBorder = match($area['status']) {
        'within_20' => '#bbf7d0',
        'over_20' => '#fde68a',
        'over_40' => '#fecaca',
    };
    $statusIcon = match($area['status']) {
        'within_20' => 'bi-check-circle-fill',
        'over_20' => 'bi-exclamation-triangle-fill',
        'over_40' => 'bi-x-circle-fill',
    };
    ?>
    <div class="card" style="margin-bottom:16px; background:<?= $statusBg ?>; border:2px solid <?= $statusBorder ?>;">
        <div class="card-body" style="display:flex; align-items:center; gap:24px; padding:20px;">
            <i class="bi <?= $statusIcon ?>" style="font-size:48px; color:<?= $statusColor ?>;"></i>
            <div style="flex:1;">
                <div style="font-size:14px; color:<?= $statusColor ?>;">เนื้อที่ครอบครองรวม</div>
                <div style="font-size:32px; font-weight:700; color:<?= $statusColor ?>;">
                    <?= $area['rai'] ?> ไร่ <?= $area['ngan'] ?> งาน <?= $area['sqwa'] ?> ตร.วา
                    <small style="font-size:16px; font-weight:400;">(<?= number_format($area['total_in_rai'], 2) ?> ไร่)</small>
                </div>
                <div style="font-size:15px; color:<?= $statusColor ?>; font-weight:600;">
                    <?= $area['plot_count'] ?> แปลง — <?= $area['status_label'] ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Plot List -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header">
            <h3><i class="bi bi-map"></i> รายการแปลงที่ดิน (<?= count($plots) ?> แปลง)</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="data-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ที่ดินเลขที่</th>
                        <th>เขตสำรวจ</th>
                        <th>ที่ดินสำรวจ</th>
                        <th>เนื้อที่ (ไร่-งาน-ตร.วา)</th>
                        <th>เนื้อที่ (ไร่)</th>
                        <th>ประเภท</th>
                        <th>บ้าน</th>
                        <th>สถานะจัดสรร</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plots as $i => $p): ?>
                        <?php $plotRai = VerificationController::toRai((int)$p['area_rai'], (int)$p['area_ngan'], (int)$p['area_sqwa']); ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($p['num_apar'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($p['spar_no'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($p['num_spar'] ?? '-') ?></td>
                            <td><?= (int)$p['area_rai'] ?>-<?= (int)$p['area_ngan'] ?>-<?= (int)$p['area_sqwa'] ?></td>
                            <td style="font-weight:600;"><?= number_format($plotRai, 2) ?></td>
                            <td><?= htmlspecialchars($p['ptype'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($p['par_ban'] ?? '-') ?></td>
                            <td>
                                <?php
                                $atype = $p['allocation_type'] ?? 'unallocated';
                                $atypeLabel = match($atype) {
                                    'owner' => '<span style="color:#166534;">✓ ผู้ครอบครอง</span>',
                                    'heir' => '<span style="color:#2563eb;">→ ทายาท</span>',
                                    'section19' => '<span style="color:#dc2626;">ม.19</span>',
                                    default => '<span style="color:#999;">ยังไม่จัดสรร</span>',
                                };
                                echo $atypeLabel;
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Action Buttons -->
    <?php if (!$isVerified): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-gear-fill"></i> ดำเนินการ</h3>
        </div>
        <div class="card-body">
            <?php if ($area['status'] === 'within_20'): ?>
                <!-- ≤20 ไร่ : ยืนยันทันที -->
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:16px; margin-bottom:16px;">
                    <p style="color:#166534; font-weight:600; margin-bottom:8px;">
                        <i class="bi bi-check-circle-fill"></i> เนื้อที่รวมไม่เกิน 20 ไร่ — สามารถยืนยันสิทธิ์ได้ทันที
                    </p>
                    <p style="color:#666; font-size:14px;">เมื่อกดยืนยัน ระบบจะบันทึกว่าราษฎรผ่านการตรวจสอบ และสามารถพิมพ์หนังสือรับรองตนเองได้</p>
                </div>
                <form method="POST" action="index.php?page=verification&action=verify_simple" style="display:flex; gap:12px;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="villager_id" value="<?= $v['villager_id'] ?>">
                    <button type="submit" class="btn btn-primary" style="padding:12px 32px; font-size:16px;">
                        <i class="bi bi-check-lg"></i> ยืนยันสิทธิ์ — ออกหนังสือรับรอง
                    </button>
                </form>

            <?php else: ?>
                <!-- >20 ไร่ : ต้องจัดสรร -->
                <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:16px; margin-bottom:16px;">
                    <p style="color:#92400e; font-weight:600; margin-bottom:8px;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        เนื้อที่รวมเกิน 20 ไร่ — ต้องจัดสรรที่ดิน
                    </p>
                    <ul style="color:#666; font-size:14px; margin:0; padding-left:20px;">
                        <li>เลือกแปลงที่ผู้ครอบครองจะใช้สิทธิ์ (รวมไม่เกิน 20 ไร่)</li>
                        <li>ส่วนที่เหลือ (ไม่เกินอีก 20 ไร่) จัดสรรให้สมาชิกครัวเรือน/ทายาท</li>
                        <?php if ($area['status'] === 'over_40'): ?>
                            <li style="color:#dc2626; font-weight:600;">ส่วนเกิน 40 ไร่ → ดำเนินการตามมาตรา 19</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <a href="index.php?page=verification&action=process&id=<?= $v['villager_id'] ?>"
                   class="btn btn-primary" style="padding:12px 32px; font-size:16px; text-decoration:none;">
                    <i class="bi bi-sliders"></i> เข้าสู่หน้าจัดสรรที่ดิน
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <!-- Already verified — show print options -->
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-printer"></i> พิมพ์เอกสาร</h3>
        </div>
        <div class="card-body" style="display:flex; gap:12px; flex-wrap:wrap;">
            <a href="index.php?page=forms&action=print&type=self_cert&villager_id=<?= $v['villager_id'] ?>"
               target="_blank" class="btn btn-primary" style="text-decoration:none;">
                <i class="bi bi-file-earmark-text"></i> หนังสือรับรองตนเอง
            </a>
            <a href="index.php?page=forms&action=print&type=form61&par_ban=<?= urlencode($parBan) ?>"
               target="_blank" class="btn btn-secondary" style="text-decoration:none;">
                <i class="bi bi-file-earmark-text"></i> อส.6-1
            </a>
            <a href="index.php?page=forms&action=print&type=form62&par_ban=<?= urlencode($parBan) ?>"
               target="_blank" class="btn btn-secondary" style="text-decoration:none;">
                <i class="bi bi-file-earmark-text"></i> อส.6-2
            </a>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>
