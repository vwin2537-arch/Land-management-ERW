<?php
/**
 * แบบ อส.6-1 — บัญชีรายชื่อผู้อยู่อาศัยหรือทำกิน ประเภทผู้ครอบครองที่ดิน
 * — แบ่งหน้า: หัวตารางซ้ำทุกหน้า, ผลรวมแยกแต่ละหน้า, Grand Total หน้าสุดท้าย
 * — เลือก "ทั้งหมด": แยกแสดงตามเขตโครงการ แต่รันเลขลำดับต่อเนื่อง
 */
require_once __DIR__ . '/../../controllers/FormExportController.php';

$filters = [
    'par_ban' => $_GET['par_ban'] ?? '',
    'park_name' => $_GET['park_name'] ?? '',
    'apar_no' => $_GET['apar_no'] ?? '',
];

$rows = FormExportController::getForm61($filters);
$grandSummary = FormExportController::getAreaSummary($rows);

// นับจำนวนราย (unique villager) — ภาพรวมทั้งหมด
$seenVillagerAll = [];
foreach ($rows as $r) {
    $vid = $r['villager_id'];
    if (!isset($seenVillagerAll[$vid])) $seenVillagerAll[$vid] = true;
}

// จัดกลุ่มข้อมูลตามเขตโครงการ (apar_no)
$zoneGroups = [];
foreach ($rows as $r) {
    $zone = $r['apar_no'] ?? '-';
    $zoneGroups[$zone][] = $r;
}
// เรียงเขตโครงการฯ น้อย → มาก
ksort($zoneGroups);
// ถ้ามีเขตเดียวหรือเลือกเขตเจาะจง ก็ใช้กลุ่มเดียว
if (count($zoneGroups) <= 1) {
    $zoneGroups = ['' => $rows];
}

// นับหน้ารวมทั้งหมด (ทุกเขตรวมกัน)
$rowsPerPage = 9;
$allPagesFlat = [];
foreach ($zoneGroups as $zoneKey => $zoneRows) {
    $zPages = array_chunk($zoneRows, $rowsPerPage);
    if (empty($zPages)) $zPages = [[]];
    foreach ($zPages as $zp) {
        $allPagesFlat[] = ['zone' => $zoneKey, 'rows' => $zp];
    }
}
$totalPages = count($allPagesFlat);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แบบ อส.6-1 — บัญชีผู้ครอบครองที่ดิน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/form-print.css">
</head>
<body class="form-print">

<!-- Toolbar -->
<div class="form-toolbar">
    <h2><i class="bi bi-file-earmark-text"></i> แบบ อส.6-1 — บัญชีผู้ครอบครองที่ดิน</h2>
    <div class="btn-group">
        <button class="btn btn-back" onclick="window.close()"><i class="bi bi-x-lg"></i> ปิด</button>
        <button class="btn btn-print" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์ / PDF</button>
    </div>
</div>

<?php
// ============================================================
// วนแต่ละหน้า (แยกตามเขตโครงการ)
// ============================================================
$zoneRowIdx = 0; // ลำดับที่รีเซ็ตทุกเขต
$prevZone = null;

foreach ($allPagesFlat as $pageIdx => $pageInfo):
    $pageRows = $pageInfo['rows'];
    $currentZone = $pageInfo['zone'];
    $isLastPage = ($pageIdx === $totalPages - 1);

    // รีเซ็ตลำดับเมื่อเปลี่ยนเขตโครงการ
    if ($currentZone !== $prevZone) {
        $zoneRowIdx = 0;
    }
    $prevZone = $currentZone;

    // ตรวจว่าเป็นหน้าสุดท้ายของเขตนี้หรือไม่
    $nextZone = isset($allPagesFlat[$pageIdx + 1]) ? $allPagesFlat[$pageIdx + 1]['zone'] : null;
    $isLastPageOfZone = ($nextZone !== $currentZone);

    // คำนวณสรุปของเขตนี้
    $zoneAllRows = $zoneGroups[$currentZone] ?? $rows;
    $zoneSummary = FormExportController::getAreaSummary($zoneAllRows);
    $seenVillagerZone = [];
    foreach ($zoneAllRows as $zr) {
        $seenVillagerZone[$zr['villager_id']] = true;
    }

    // ข้อมูล header จาก row แรกของหน้านี้
    $firstRow = !empty($pageRows) ? $pageRows[0] : ($rows[0] ?? []);
    $parkName = $firstRow['park_name'] ?? '...........................';
    $codeDnp = $firstRow['code_dnp'] ?? '............';
    $aparNo = $firstRow['apar_no'] ?? '.....................';
    $parBan = $firstRow['par_ban'] ?? '.....................';
    $parMoo = $firstRow['par_moo'] ?? '......';
    $parTam = $firstRow['par_tam'] ?? '.....................';
    $parAmp = $firstRow['par_amp'] ?? '.....................';
    $parProv = $firstRow['par_prov'] ?? '.....................';

    // คำนวณผลรวมสะสมภายในเขตเดียวกันถึงหน้านี้
    $rowsUpToNow = [];
    for ($i = 0; $i <= $pageIdx; $i++) {
        if ($allPagesFlat[$i]['zone'] === $currentZone) {
            $rowsUpToNow = array_merge($rowsUpToNow, $allPagesFlat[$i]['rows']);
        }
    }
    $cumSummary = FormExportController::getAreaSummary($rowsUpToNow);
    $cumPlots = count($rowsUpToNow);

    // นับราย unique สะสมภายในเขตนี้ถึงหน้านี้
    $seenCum = [];
    foreach ($rowsUpToNow as $r) {
        $vid = $r['villager_id'];
        if (!isset($seenCum[$vid])) $seenCum[$vid] = true;
    }
?>
<div class="form-page">
    <!-- Header — ซ้ำทุกหน้า -->
    <div class="form-header">
        <span class="form-code">แบบ อส. 6 - 1</span>
        <h1>บัญชีรายชื่อผู้อยู่อาศัยหรือทำกิน ประเภทผู้ครอบครองที่ดิน</h1>
        <p class="form-subtitle">
            ภายใต้โครงการอนุรักษ์และดูแลรักษาทรัพยากรธรรมชาติภายใน
            <span class="underline-fill"><?= htmlspecialchars($parkName) ?></span>
            รหัสป่าอนุรักษ์ <span class="underline-fill"><?= htmlspecialchars($codeDnp) ?></span>
        </p>
        <p class="form-meta">
            ท้องที่หมู่บ้าน <span class="underline-fill"><?= htmlspecialchars($parBan) ?></span>
            หมู่ที่ <span class="underline-fill"><?= htmlspecialchars($parMoo) ?></span>
            ตำบล <span class="underline-fill"><?= htmlspecialchars($parTam) ?></span>
            อำเภอ <span class="underline-fill"><?= htmlspecialchars($parAmp) ?></span>
            จังหวัด <span class="underline-fill"><?= htmlspecialchars($parProv) ?></span>
        </p>
        <p class="form-meta">
            เขตโครงการอนุรักษ์และดูแลรักษาทรัพยากรธรรมชาติที่ <span class="underline-fill"><?= htmlspecialchars($aparNo) ?></span>
            <span style="float:right; font-size:11pt; color:#666;">หน้า <?= $pageIdx + 1 ?>/<?= $totalPages ?></span>
        </p>
    </div>

    <!-- Data Table -->
    <table class="form-table">
        <thead>
            <tr>
                <th rowspan="2" style="width:35px;">ลำดับที่</th>
                <th rowspan="2" style="width:55px;">คำนำ<br>หน้าชื่อ</th>
                <th rowspan="2" style="width:100px;">ชื่อ</th>
                <th rowspan="2" style="width:100px;">นามสกุล</th>
                <th rowspan="2" style="width:115px;">เลขประจำตัวประชาชน</th>
                <th rowspan="2" style="width:50px;">ที่ดิน<br>เลขที่</th>
                <th rowspan="2" style="width:45px;">เขต<br>สำรวจที่</th>
                <th rowspan="2" style="width:50px;">ที่ดินสำรวจ<br>เลขที่</th>
                <th colspan="3" style="width:100px;">เนื้อที่ประมาณ</th>
                <th rowspan="2" style="width:200px;">หมายเหตุ</th>
            </tr>
            <tr>
                <th style="width:30px;">ไร่</th>
                <th style="width:30px;">งาน</th>
                <th style="width:40px;">ตารางวา</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pageRows)): ?>
                <tr><td colspan="12" style="padding:20px; color:#999;">ไม่พบข้อมูล</td></tr>
            <?php else: ?>
                <?php foreach ($pageRows as $r):
                    $zoneRowIdx++;
                ?>
                    <tr>
                        <td><?= $zoneRowIdx ?></td>
                        <td><?= htmlspecialchars($r['prefix'] ?? '') ?></td>
                        <td class="text-left"><?= htmlspecialchars($r['first_name']) ?></td>
                        <td class="text-left"><?= htmlspecialchars($r['last_name']) ?></td>
                        <td><?= htmlspecialchars($r['id_card_number']) ?></td>
                        <td><?= htmlspecialchars($r['num_apar'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['spar_no'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['num_spar'] ?? '') ?></td>
                        <td class="text-right"><?= (int)($r['area_rai'] ?? 0) ?></td>
                        <td class="text-right"><?= (int)($r['area_ngan'] ?? 0) ?></td>
                        <td class="text-right"><?= (int)($r['area_sqwa'] ?? 0) ?></td>
                        <td style="font-size:10pt; text-align:left; padding-left:6px; white-space:nowrap;">
                            <?php
                                $plotCount = (int)($r['owner_plot_count'] ?? 1);
                                $totalRai = round((float)($r['owner_total_rai'] ?? 0), 2);
                                if ($totalRai > 40):
                            ?>
                                &nbsp;&nbsp;ครอบครอง <?= $plotCount ?> แปลง รวม <?= number_format($totalRai, 2) ?> ไร่ (เกิน 40 ไร่ ม.19)
                            <?php elseif ($totalRai > 20): ?>
                                &nbsp;&nbsp;ครอบครอง <?= $plotCount ?> แปลง รวม <?= number_format($totalRai, 2) ?> ไร่ (เกิน 20 ไร่)
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <?php if ($isLastPageOfZone): ?>
            <!-- หน้าสุดท้ายของเขต: รวมทั้งสิ้น -->
            <tr style="font-weight:700;">
                <td colspan="8" style="text-align:right; padding-right:12px;">
                    รวมทั้งสิ้น <?= count($seenVillagerZone) ?> ราย <?= count($zoneAllRows) ?> แปลง เนื้อที่ประมาณ
                </td>
                <td class="text-right"><?= $zoneSummary['rai'] ?></td>
                <td class="text-right"><?= $zoneSummary['ngan'] ?></td>
                <td class="text-right"><?= $zoneSummary['sqwa'] ?></td>
                <td></td>
            </tr>
            <?php else: ?>
            <!-- หน้าอื่นๆ: รวมสะสม -->
            <tr>
                <td colspan="8" style="text-align:right; padding-right:12px;">
                    รวมสะสม <?= count($seenCum) ?> ราย <?= $cumPlots ?> แปลง เนื้อที่ประมาณ
                </td>
                <td class="text-right"><?= $cumSummary['rai'] ?></td>
                <td class="text-right"><?= $cumSummary['ngan'] ?></td>
                <td class="text-right"><?= $cumSummary['sqwa'] ?></td>
                <td></td>
            </tr>
            <?php endif; ?>
        </tfoot>
    </table>

    <?php if ($isLastPageOfZone): ?>
    <!-- Footer — แสดงตอนจบแต่ละเขตโครงการ -->
    <div class="form-footer">
        <div class="summary-section">
            <p>- ไม่เป็นพื้นที่ล่อแหลมคุกคามต่อระบบนิเวศ ราย <span class="count"><?= $zoneSummary['not_risky_count'] ?></span> แปลง <span class="count"><?= $zoneSummary['not_risky_count'] ?></span></p>
            <p>- เป็นพื้นที่ล่อแหลมคุกคามต่อระบบนิเวศ ราย <span class="count"><?= $zoneSummary['risky_count'] ?></span> แปลง <span class="count"><?= $zoneSummary['risky_count'] ?></span></p>
        </div>

        <div class="form-signatures">
            <div class="sig-box">
                <p>ลงชื่อ ..............................................</p>
                <p class="sig-label">(.................................................................)</p>
                <p>ประธานคณะกรรมการพิจารณาผลฯ</p>
                <p class="sig-label">ตำแหน่ง ............................................................</p>
            </div>
            <div class="sig-box">
                <p>ลงชื่อ ..............................................</p>
                <p class="sig-label">(.................................................................)</p>
                <p>หัวหน้าคณะทำงานสำรวจการครอบครองฯ</p>
                <p class="sig-label">ตำแหน่ง ............................................................</p>
            </div>
            <div class="sig-box">
                <p>ลงชื่อ ..............................................</p>
                <p class="sig-label">(.................................................................)</p>
                <p>คณะทำงานและเลขานุการ</p>
                <p>คณะทำงานสำรวจการครอบครองฯ</p>
                <p class="sig-label">หัวหน้าอุทยานแห่งชาติเอราวัณ</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- หมายเหตุ — แสดงทุกหน้า -->
    <div class="form-notes">
        <h4>หมายเหตุ</h4>
        <ol>
            <li>ที่ดินเลขที่ขึ้นต้นด้วยเลข <strong>1</strong> หมายถึง แปลงที่ดินตามกรอบเวลามติ ครม. เมื่อวันที่ 30 มิถุนายน 2541 (ผู้ครอบครองบริเวณที่ดินเดิม) เช่น 10001, 10003 เป็นต้น</li>
            <li>ที่ดินเลขที่ขึ้นต้นด้วยเลข <strong>2</strong> หมายถึง แปลงที่ดินตามกรอบเวลาคำสั่ง คสช. ที่ 66/2557 (ไม่ใช่ผู้ครอบครองบริเวณที่ดินเดิม) เช่น 20002, 20004 เป็นต้น</li>
            <li>ที่ดินเลขที่ขึ้นต้นด้วยเลข <strong>3</strong> หมายถึง แปลงที่ดินของผู้ครอบครองที่ดินที่ขึ้นต้นด้วยเลข 1 หรือ 2 แต่มีเนื้อที่เกิน 20 ไร่ และได้บริหารจัดการพื้นที่ให้แก่ครัวเรือนของผู้ครอบครองที่ดิน เช่น 30002 เป็นต้น</li>
            <li>ที่ดินเลขที่ขึ้นต้นด้วยเลข <strong>4</strong> หมายถึง แปลงที่ดินของผู้ครอบครองที่ดินที่มีเนื้อที่เกิน 40 ไร่ และได้ขอเข้าร่วมดำเนินการกับชุมชนในพื้นที่โครงการ ตามพระราชกฤษฎีกาฯ มาตรา 19 เช่น 40002 เป็นต้น</li>
            <li>ช่องหมายเหตุ หากแปลงที่ดินอยู่ในเขตพื้นที่ต้นน้ำลำธาร ให้ระบุว่า "พื้นที่ลุ่มน้ำชั้นที่ 1" หรือ "พื้นที่ลุ่มน้ำชั้นที่ 2"</li>
        </ol>
    </div>
</div>

<?php endforeach; ?>

</body>
</html>
