<?php
/**
 * Dashboard — ภาพรวมระบบ
 * รายงานสรุปตามประเภทที่ดิน, เขตโครงการฯ, พื้นที่ล่อแหลม, คุณสมบัติ
 */

try {
    $db = getDB();

    // ──────────────────────────────────────────────────────────
    // 1) Stat Cards — ตัวเลขหลัก
    // ──────────────────────────────────────────────────────────
    $villagerCount = $db->query("SELECT COUNT(DISTINCT villager_id) FROM land_plots")->fetchColumn();
    $plotCount = $db->query("SELECT COUNT(*) FROM land_plots")->fetchColumn();

    // เนื้อที่รวม (แปลงเป็น ไร่-งาน-ตร.วา)
    $areaRaw = $db->query("SELECT COALESCE(SUM(area_rai),0) as rai, COALESCE(SUM(area_ngan),0) as ngan, COALESCE(SUM(area_sqwa),0) as sqwa FROM land_plots")->fetch();
    $totalSqwa = intval(round(($areaRaw['rai'] * 400) + ($areaRaw['ngan'] * 100) + $areaRaw['sqwa']));
    $totalRai = intdiv($totalSqwa, 400);
    $totalNgan = intdiv($totalSqwa % 400, 100);
    $totalSqwaRem = $totalSqwa % 100;

    $memberCount = $db->query("SELECT COUNT(*) FROM household_members")->fetchColumn();

    // ──────────────────────────────────────────────────────────
    // 2) สรุปตามประเภทที่ดิน (Code prefix 1/2/3/4)
    // ──────────────────────────────────────────────────────────
    $codeTypeStmt = $db->query("
        SELECT 
            CASE 
                WHEN num_apar LIKE '1%' THEN '1'
                WHEN num_apar LIKE '2%' THEN '2'
                WHEN num_apar LIKE '3%' THEN '3'
                WHEN num_apar LIKE '4%' THEN '4'
                ELSE '0'
            END as code_prefix,
            COUNT(*) as plot_count,
            COUNT(DISTINCT villager_id) as villager_count,
            COALESCE(SUM(area_rai),0) as sum_rai,
            COALESCE(SUM(area_ngan),0) as sum_ngan,
            COALESCE(SUM(area_sqwa),0) as sum_sqwa
        FROM land_plots
        GROUP BY code_prefix
        ORDER BY code_prefix
    ");
    $codeTypes = $codeTypeStmt->fetchAll(PDO::FETCH_ASSOC);

    $codeTypeLabels = [
        '1' => 'มติ ครม. 30 มิ.ย. 2541 (ผู้ครอบครองเดิม)',
        '2' => 'คำสั่ง คสช. 66/2557 (ไม่ใช่ผู้ครอบครองเดิม)',
        '3' => 'เกิน 20 ไร่ บริหารจัดการครัวเรือน',
        '4' => 'เกิน 40 ไร่ ม.19 (ร่วมโครงการชุมชน)',
        '0' => 'ไม่ระบุ',
    ];

    // ──────────────────────────────────────────────────────────
    // 3) สรุปตามหมู่บ้าน (GROUP BY par_ban — 12 หมู่บ้าน เหมือน filter หน้าฟอร์ม)
    // ──────────────────────────────────────────────────────────
    $villageStmt = $db->query("
        SELECT 
            COALESCE(lp.par_ban, '-') as par_ban,
            MIN(lp.par_moo) as par_moo,
            MIN(lp.ban_e) as ban_e,
            MIN(lp.par_tam) as par_tam,
            COUNT(*) as plot_count,
            COUNT(DISTINCT lp.villager_id) as villager_count,
            COALESCE(SUM(lp.area_rai),0) as sum_rai,
            COALESCE(SUM(lp.area_ngan),0) as sum_ngan,
            COALESCE(SUM(lp.area_sqwa),0) as sum_sqwa,
            SUM(CASE WHEN lp.remark_risk IN ('risky','risky_case') THEN 1 ELSE 0 END) as risky_count,
            SUM(CASE WHEN lp.remark_risk NOT IN ('risky','risky_case') OR lp.remark_risk IS NULL THEN 1 ELSE 0 END) as not_risky_count
        FROM land_plots lp
        WHERE lp.par_ban IS NOT NULL AND lp.par_ban != ''
        GROUP BY lp.par_ban
        ORDER BY CAST(MIN(lp.par_moo) AS UNSIGNED) ASC, lp.par_ban ASC
    ");
    $villageSummary = $villageStmt->fetchAll(PDO::FETCH_ASSOC);

    // ──────────────────────────────────────────────────────────
    // 4) พื้นที่ล่อแหลม / ไม่ล่อแหลม
    // ──────────────────────────────────────────────────────────
    $riskStmt = $db->query("
        SELECT 
            CASE WHEN remark_risk IN ('risky','risky_case') THEN 'risky' ELSE 'not_risky' END as risk_group,
            COUNT(*) as cnt,
            COUNT(DISTINCT villager_id) as vcnt
        FROM land_plots
        GROUP BY risk_group
    ");
    $riskData = $riskStmt->fetchAll(PDO::FETCH_ASSOC);
    $riskyPlots = 0; $riskyVillagers = 0;
    $notRiskyPlots = 0; $notRiskyVillagers = 0;
    foreach ($riskData as $rd) {
        if ($rd['risk_group'] === 'risky') {
            $riskyPlots = $rd['cnt'];
            $riskyVillagers = $rd['vcnt'];
        } else {
            $notRiskyPlots = $rd['cnt'];
            $notRiskyVillagers = $rd['vcnt'];
        }
    }

    // ──────────────────────────────────────────────────────────
    // 5) คุณสมบัติราษฎร
    // ──────────────────────────────────────────────────────────
    $qualStmt = $db->query("
        SELECT qualification_status, COUNT(*) as cnt 
        FROM villagers 
        WHERE villager_id IN (SELECT DISTINCT villager_id FROM land_plots)
        GROUP BY qualification_status
    ");
    $qualData = $qualStmt->fetchAll(PDO::FETCH_ASSOC);
    $qualMap = ['passed' => 0, 'failed' => 0, 'pending' => 0];
    foreach ($qualData as $qd) {
        $qualMap[$qd['qualification_status'] ?? 'pending'] = (int)$qd['cnt'];
    }

    // ──────────────────────────────────────────────────────────
    // 6) ผู้ครอบครองที่ดินเกิน 20 / 40 ไร่
    // ──────────────────────────────────────────────────────────
    $overLimitStmt = $db->query("
        SELECT 
            SUM(CASE WHEN total_rai > 20 AND total_rai <= 40 THEN 1 ELSE 0 END) as over20,
            SUM(CASE WHEN total_rai > 40 THEN 1 ELSE 0 END) as over40
        FROM (
            SELECT villager_id, SUM(area_rai + area_ngan/4 + area_sqwa/400) as total_rai
            FROM land_plots GROUP BY villager_id
        ) sub
    ");
    $overLimit = $overLimitStmt->fetch(PDO::FETCH_ASSOC);

    // ──────────────────────────────────────────────────────────
    // 7) Charts data — ประเภทการใช้ที่ดิน + สถานะแปลง
    // ──────────────────────────────────────────────────────────
    $landUseStmt = $db->query("SELECT land_use_type, COUNT(*) as cnt FROM land_plots GROUP BY land_use_type");
    $landUses = $landUseStmt->fetchAll();

    // กราฟแท่ง: จำนวนแปลง + ราย ต่อหมู่บ้าน (ใช้ villageSummary ที่ query ไว้แล้ว)

} catch (PDOException $e) {
    $villagerCount = $plotCount = $memberCount = 0;
    $totalRai = $totalNgan = $totalSqwaRem = 0;
    $codeTypes = $villageSummary = $riskData = $qualData = [];
    $riskyPlots = $riskyVillagers = $notRiskyPlots = $notRiskyVillagers = 0;
    $qualMap = ['passed' => 0, 'failed' => 0, 'pending' => 0];
    $overLimit = ['over20' => 0, 'over40' => 0];
    $landUses = [];
}

// Helper: แปลง rai+ngan+sqwa เป็นตัวเลขที่ถูกต้อง
function normalizeArea($rai, $ngan, $sqwa) {
    $totalSq = intval(round(($rai * 400) + ($ngan * 100) + $sqwa));
    return [
        'rai' => intdiv($totalSq, 400),
        'ngan' => intdiv($totalSq % 400, 100),
        'sqwa' => $totalSq % 100,
    ];
}
?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- Row 1: Stat Cards -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="bi bi-people-fill"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">ผู้ครอบครองที่ดิน</div>
            <div class="stat-value"><?= number_format($villagerCount) ?></div>
            <div class="stat-change">ราย</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="bi bi-map-fill"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">แปลงที่ดินทำกิน</div>
            <div class="stat-value"><?= number_format($plotCount) ?></div>
            <div class="stat-change">แปลง</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="bi bi-rulers"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">เนื้อที่รวม</div>
            <div class="stat-value"><?= number_format($totalRai) ?></div>
            <div class="stat-change">ไร่ <?= $totalNgan ?> งาน <?= $totalSqwaRem ?> ตร.วา</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="bi bi-person-hearts"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">สมาชิกครอบครัว/ครัวเรือน</div>
            <div class="stat-value"><?= number_format($memberCount) ?></div>
            <div class="stat-change">คน (อส.6-2)</div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- Row 2: สรุปตามประเภทที่ดิน (Code 1/2/3/4) + พื้นที่ล่อแหลม + คุณสมบัติ -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px;">

    <!-- ตารางประเภทที่ดิน -->
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-grid-3x3-gap-fill" style="color:var(--primary-600); margin-right:8px;"></i>สรุปตามประเภทที่ดิน</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-container" style="overflow-x:auto;">
                <table style="width:100%; min-width:480px;">
                    <thead>
                        <tr>
                            <th style="width:40px;">Code</th>
                            <th>ประเภท</th>
                            <th style="width:55px; text-align:right;">ราย</th>
                            <th style="width:60px; text-align:right;">แปลง</th>
                            <th style="width:140px; text-align:right;">เนื้อที่ (ไร่-งาน-วา)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $gtVillager = 0; $gtPlot = 0; $gtSqwa = 0;
                        foreach ($codeTypes as $ct):
                            $prefix = $ct['code_prefix'];
                            $area = normalizeArea($ct['sum_rai'], $ct['sum_ngan'], $ct['sum_sqwa']);
                            $rowSqwa = ($ct['sum_rai'] * 400) + ($ct['sum_ngan'] * 100) + $ct['sum_sqwa'];
                            $gtVillager += $ct['villager_count'];
                            $gtPlot += $ct['plot_count'];
                            $gtSqwa += $rowSqwa;
                        ?>
                        <tr>
                            <td style="text-align:center; font-weight:600;">
                                <span style="display:inline-block; width:28px; height:28px; line-height:28px; border-radius:8px; font-size:13px; color:#fff;
                                    background:<?= ['1'=>'#22c55e','2'=>'#3b82f6','3'=>'#f59e0b','4'=>'#ef4444','0'=>'#6b7280'][$prefix] ?? '#6b7280' ?>;">
                                    <?= $prefix ?>
                                </span>
                            </td>
                            <td style="font-size:12px;"><?= $codeTypeLabels[$prefix] ?? 'ไม่ระบุ' ?></td>
                            <td style="text-align:right;"><?= number_format($ct['villager_count']) ?></td>
                            <td style="text-align:right;"><?= number_format($ct['plot_count']) ?></td>
                            <td style="text-align:right; font-family:monospace;"><?= number_format($area['rai']) ?>-<?= $area['ngan'] ?>-<?= $area['sqwa'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <?php $gtArea = normalizeArea(0, 0, $gtSqwa); ?>
                        <tr style="font-weight:700; background:var(--gray-50);">
                            <td colspan="2" style="text-align:right;">รวมทั้งสิ้น</td>
                            <td style="text-align:right;"><?= number_format($villagerCount) ?></td>
                            <td style="text-align:right;"><?= number_format($plotCount) ?></td>
                            <td style="text-align:right; font-family:monospace;"><?= number_format($gtArea['rai']) ?>-<?= $gtArea['ngan'] ?>-<?= $gtArea['sqwa'] ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- การ์ดสรุป: ล่อแหลม + คุณสมบัติ + เกิน 20/40 ไร่ -->
    <div style="display:flex; flex-direction:column; gap:20px;">
        <!-- พื้นที่ล่อแหลม -->
        <div class="card" style="flex:1;">
            <div class="card-header">
                <h3><i class="bi bi-exclamation-triangle-fill" style="color:var(--danger); margin-right:8px;"></i>สรุปพื้นที่ล่อแหลม</h3>
            </div>
            <div class="card-body" style="padding:16px 24px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div style="text-align:center; padding:12px; border-radius:12px; background:#fef2f2;">
                        <div style="font-size:11px; color:#991b1b;">ล่อแหลมคุกคาม</div>
                        <div style="font-size:24px; font-weight:700; color:#dc2626;"><?= number_format($riskyPlots) ?></div>
                        <div style="font-size:11px; color:#991b1b;">แปลง (<?= number_format($riskyVillagers) ?> ราย)</div>
                    </div>
                    <div style="text-align:center; padding:12px; border-radius:12px; background:#f0fdf4;">
                        <div style="font-size:11px; color:#166534;">ไม่ล่อแหลม</div>
                        <div style="font-size:24px; font-weight:700; color:#16a34a;"><?= number_format($notRiskyPlots) ?></div>
                        <div style="font-size:11px; color:#166534;">แปลง (<?= number_format($notRiskyVillagers) ?> ราย)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- คุณสมบัติ + เกิน 20/40 -->
        <div class="card" style="flex:1;">
            <div class="card-header">
                <h3><i class="bi bi-person-check-fill" style="color:var(--primary-600); margin-right:8px;"></i>คุณสมบัติ & เกินเกณฑ์</h3>
            </div>
            <div class="card-body" style="padding:16px 24px;">
                <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:12px;">
                    <div style="text-align:center; padding:10px 6px; border-radius:10px; background:#f0fdf4;">
                        <div style="font-size:10px; color:#166534;">ผ่านคุณสมบัติ</div>
                        <div style="font-size:22px; font-weight:700; color:#16a34a;"><?= $qualMap['passed'] ?></div>
                    </div>
                    <div style="text-align:center; padding:10px 6px; border-radius:10px; background:#fef2f2;">
                        <div style="font-size:10px; color:#991b1b;">ไม่ผ่าน</div>
                        <div style="font-size:22px; font-weight:700; color:#dc2626;"><?= $qualMap['failed'] ?></div>
                    </div>
                    <div style="text-align:center; padding:10px 6px; border-radius:10px; background:#fff7ed;">
                        <div style="font-size:10px; color:#9a3412;">เกิน 20 ไร่</div>
                        <div style="font-size:22px; font-weight:700; color:#ea580c;"><?= (int)($overLimit['over20'] ?? 0) ?></div>
                    </div>
                    <div style="text-align:center; padding:10px 6px; border-radius:10px; background:#fef2f2;">
                        <div style="font-size:10px; color:#991b1b;">เกิน 40 ไร่ ม.19</div>
                        <div style="font-size:22px; font-weight:700; color:#dc2626;"><?= (int)($overLimit['over40'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- Row 3: Charts — ประเภทการใช้ที่ดิน + จำนวนแปลง/รายตามหมู่บ้าน -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-pie-chart-fill" style="color:var(--primary-600); margin-right:8px;"></i>ประเภทการใช้ที่ดิน</h3>
        </div>
        <div class="card-body" style="height: 280px; display:flex; align-items:center; justify-content:center;">
            <?php if (empty($landUses)): ?>
                <div class="empty-state"><i class="bi bi-bar-chart"></i><p>ยังไม่มีข้อมูล</p></div>
            <?php else: ?>
                <canvas id="landUseChart"></canvas>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-bar-chart-fill" style="color:var(--info); margin-right:8px;"></i>จำนวนแปลง/รายตามหมู่บ้าน</h3>
        </div>
        <div class="card-body" style="height: 280px; display:flex; align-items:center; justify-content:center;">
            <?php if (empty($villageSummary)): ?>
                <div class="empty-state"><i class="bi bi-bar-chart"></i><p>ยังไม่มีข้อมูล</p></div>
            <?php else: ?>
                <canvas id="villageBarChart"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- Row 4: สรุปตามหมู่บ้านสำรวจ (12 หมู่บ้าน) -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="card" style="margin-bottom:28px;">
    <div class="card-header">
        <h3><i class="bi bi-houses-fill" style="color:var(--info); margin-right:8px;"></i>สรุปตามหมู่บ้านสำรวจ (<?= count($villageSummary) ?> หมู่บ้าน)</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-container" style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid var(--gray-200);">
                        <th rowspan="2" style="width:40px; vertical-align:middle;">ลำดับ</th>
                        <th rowspan="2" style="vertical-align:middle;">หมู่บ้านสำรวจ</th>
                        <th rowspan="2" style="width:60px; text-align:right; vertical-align:middle;">ราย</th>
                        <th rowspan="2" style="width:65px; text-align:right; vertical-align:middle;">แปลง</th>
                        <th colspan="3" style="text-align:center; border-bottom:1px solid var(--gray-200); background:var(--gray-50);">เนื้อที่ประมาณ</th>
                        <th colspan="2" style="text-align:center; border-bottom:1px solid var(--gray-200); background:#fef2f2;">พื้นที่ล่อแหลม</th>
                    </tr>
                    <tr>
                        <th style="width:55px; text-align:right; background:var(--gray-50);">ไร่</th>
                        <th style="width:45px; text-align:right; background:var(--gray-50);">งาน</th>
                        <th style="width:50px; text-align:right; background:var(--gray-50);">ตร.วา</th>
                        <th style="width:75px; text-align:center; background:#fef2f2; color:#dc2626;">ล่อแหลม</th>
                        <th style="width:85px; text-align:center; background:#f0fdf4; color:#16a34a;">ไม่ล่อแหลม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $vi = 0;
                    $vtVillager = 0; $vtPlot = 0; $vtSqwa = 0; $vtRisky = 0; $vtNotRisky = 0;
                    foreach ($villageSummary as $v):
                        $vi++;
                        $va = normalizeArea($v['sum_rai'], $v['sum_ngan'], $v['sum_sqwa']);
                        $vSqwa = intval(round(($v['sum_rai'] * 400) + ($v['sum_ngan'] * 100) + $v['sum_sqwa']));
                        $vtVillager += $v['villager_count'];
                        $vtPlot += $v['plot_count'];
                        $vtSqwa += $vSqwa;
                        $vtRisky += $v['risky_count'];
                        $vtNotRisky += $v['not_risky_count'];
                        $banPrefix = mb_strpos($v['par_ban'], 'บ้าน') === 0 ? '' : 'บ้าน';
                        $banLabel = $banPrefix . htmlspecialchars($v['par_ban']);
                        $banSub = 'รหัส ' . htmlspecialchars($v['ban_e'] ?? '-') . ' · ม.' . htmlspecialchars($v['par_moo'] ?? '-') . ' ต.' . htmlspecialchars($v['par_tam'] ?? '-');
                    ?>
                    <tr style="cursor:pointer;" onclick="window.location='index.php?page=forms&par_ban=<?= urlencode($v['par_ban']) ?>';" onmouseover="this.style.background='var(--gray-50)';" onmouseout="this.style.background='';">
                        <td style="text-align:center;"><?= $vi ?></td>
                        <td>
                            <div style="font-weight:500;"><?= $banLabel ?></div>
                            <div style="font-size:11px; color:var(--gray-500);"><?= $banSub ?></div>
                        </td>
                        <td style="text-align:right;"><?= number_format($v['villager_count']) ?></td>
                        <td style="text-align:right;"><?= number_format($v['plot_count']) ?></td>
                        <td style="text-align:right;"><?= number_format($va['rai']) ?></td>
                        <td style="text-align:right;"><?= $va['ngan'] ?></td>
                        <td style="text-align:right;"><?= $va['sqwa'] ?></td>
                        <td style="text-align:center; color:<?= $v['risky_count'] > 0 ? '#dc2626' : '#999' ?>;">
                            <?= $v['risky_count'] > 0 ? number_format($v['risky_count']) : '-' ?>
                        </td>
                        <td style="text-align:center; color:#16a34a;">
                            <?= number_format($v['not_risky_count']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php $vtArea = normalizeArea(0, 0, $vtSqwa); ?>
                    <tr style="font-weight:700; background:var(--gray-50);">
                        <td colspan="2" style="text-align:right;">รวมทั้งสิ้น</td>
                        <td style="text-align:right;"><?= number_format($villagerCount) ?></td>
                        <td style="text-align:right;"><?= number_format($plotCount) ?></td>
                        <td style="text-align:right;"><?= number_format($vtArea['rai']) ?></td>
                        <td style="text-align:right;"><?= $vtArea['ngan'] ?></td>
                        <td style="text-align:right;"><?= $vtArea['sqwa'] ?></td>
                        <td style="text-align:center; color:#dc2626;"><?= number_format($vtRisky) ?></td>
                        <td style="text-align:center; color:#16a34a;"><?= number_format($vtNotRisky) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- Row 5: ลิงก์ลัด — เมนูหลัก -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="card">
    <div class="card-header">
        <h3><i class="bi bi-grid-fill" style="color:var(--primary-600); margin-right:8px;"></i>เมนูลัด</h3>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
            <?php
            $quickLinks = [
                ['icon' => 'bi-printer-fill', 'title' => 'แบบฟอร์มราชการ', 'desc' => 'อส.6-1, 6-2, 6-3, บช.1-1, 1-2', 'url' => 'index.php?page=forms', 'color' => '#3b82f6'],
                ['icon' => 'bi-people-fill', 'title' => 'ทะเบียนราษฎร', 'desc' => 'ค้นหา/เพิ่ม/แก้ไขข้อมูลราษฎร', 'url' => 'index.php?page=villagers', 'color' => '#22c55e'],
                ['icon' => 'bi-map-fill', 'title' => 'แปลงที่ดิน', 'desc' => 'ค้นหา/ดูรายละเอียดแปลง', 'url' => 'index.php?page=plots', 'color' => '#f59e0b'],
                ['icon' => 'bi-clipboard-check-fill', 'title' => 'ตรวจสอบสิทธิ์', 'desc' => 'ตรวจสอบคุณสมบัติ + จัดสรรที่ดิน', 'url' => 'index.php?page=verification', 'color' => '#8b5cf6'],
                ['icon' => 'bi-scissors', 'title' => 'แบ่งแปลงที่ดิน', 'desc' => 'สรุปสถานะผู้ครอบครอง >20 ไร่', 'url' => 'index.php?page=subdivision', 'color' => '#ef4444'],
                ['icon' => 'bi-geo-alt-fill', 'title' => 'แผนที่', 'desc' => 'แสดงตำแหน่งแปลงบนแผนที่', 'url' => 'index.php?page=map', 'color' => '#06b6d4'],
            ];
            foreach ($quickLinks as $ql):
            ?>
            <a href="<?= $ql['url'] ?>" style="display:flex; align-items:center; gap:12px; padding:14px 16px; border-radius:12px; border:1px solid var(--gray-200); text-decoration:none; color:var(--gray-800); transition:var(--transition);"
               onmouseover="this.style.boxShadow='var(--shadow-md)'; this.style.borderColor='<?= $ql['color'] ?>';"
               onmouseout="this.style.boxShadow='none'; this.style.borderColor='var(--gray-200)';">
                <div style="width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px; color:#fff; background:<?= $ql['color'] ?>; flex-shrink:0;">
                    <i class="bi <?= $ql['icon'] ?>"></i>
                </div>
                <div>
                    <div style="font-size:13px; font-weight:600;"><?= $ql['title'] ?></div>
                    <div style="font-size:11px; color:var(--gray-500); line-height:1.3;"><?= $ql['desc'] ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- Charts JS -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if (!empty($landUses) || !empty($villageSummary)): ?>
<script>
<?php if (!empty($landUses)): ?>
    new Chart(document.getElementById('landUseChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(fn($r) => LAND_USE_LABELS[$r['land_use_type']] ?? $r['land_use_type'], $landUses)) ?>,
            datasets: [{
                data: <?= json_encode(array_map(fn($r) => (int)$r['cnt'], $landUses)) ?>,
                backgroundColor: ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#6b7280'],
                borderWidth: 0, hoverOffset: 8,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { font: { family: 'Prompt', size: 13 }, padding: 16 } } }
        }
    });
<?php endif; ?>
<?php if (!empty($villageSummary)): ?>
    new Chart(document.getElementById('villageBarChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($v) => $v['par_ban'], $villageSummary), JSON_UNESCAPED_UNICODE) ?>,
            datasets: [
                {
                    label: 'จำนวนแปลง',
                    data: <?= json_encode(array_map(fn($v) => (int)$v['plot_count'], $villageSummary)) ?>,
                    backgroundColor: '#3b82f6',
                    borderRadius: 4, borderSkipped: false,
                },
                {
                    label: 'จำนวนราย',
                    data: <?= json_encode(array_map(fn($v) => (int)$v['villager_count'], $villageSummary)) ?>,
                    backgroundColor: '#22c55e',
                    borderRadius: 4, borderSkipped: false,
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { font: { family: 'Prompt', size: 12 }, padding: 12 } } },
            scales: {
                y: { beginAtZero: true, ticks: { font: { family: 'Prompt' } } },
                x: { ticks: { font: { family: 'Prompt', size: 10 }, maxRotation: 45, minRotation: 30 } }
            }
        }
    });
<?php endif; ?>
</script>
<?php endif; ?>