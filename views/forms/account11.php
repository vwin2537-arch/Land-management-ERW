<?php
/**
 * บัญชี 1-1 — บัญชีรายชื่อราษฎรพร้อมจำนวนที่ดินผู้อยู่อาศัยหรือทำกิน
 * — แยกตามเขตโครงการฯ (apar_no) เหมือน อส.6-1
 * — แบ่งหน้า: หัวตารางซ้ำทุกหน้า, ผลรวมแยกแต่ละหน้า, Grand Total หน้าสุดท้าย
 */
require_once __DIR__ . '/../../controllers/FormExportController.php';

$filters = [
    'par_ban' => $_GET['par_ban'] ?? '',
    'park_name' => $_GET['park_name'] ?? '',
    'apar_no' => $_GET['apar_no'] ?? '',
];

$rows = FormExportController::getAccount11($filters);
$grandSummary = FormExportController::getAreaSummary($rows);

// นับจำนวนราย (unique villager) — ภาพรวมทั้งหมด
$seenVillagerAll = [];
foreach ($rows as $r) {
    $vid = $r['id_card_number'];
    if (!isset($seenVillagerAll[$vid])) $seenVillagerAll[$vid] = true;
}

// ──────────────────────────────────────────────────────────
// จัดกลุ่มตามเขตโครงการฯ (apar_no)
// ──────────────────────────────────────────────────────────
$zoneGroups = [];
foreach ($rows as $r) {
    $zone = $r['apar_no'] ?? '-';
    $zoneGroups[$zone][] = $r;
}
// เรียงเขตโครงการฯ น้อย → มาก
ksort($zoneGroups);

if (count($zoneGroups) <= 1) {
    $zoneGroups = ['' => $rows];
}

// สร้าง flat pages ทุกเขตรวมกัน
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บัญชี 1-1 — รายชื่อราษฎรพร้อมจำนวนที่ดิน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/form-print.css">
</head>
<body class="form-print">

<div class="form-toolbar">
    <h2><i class="bi bi-file-earmark-text"></i> บัญชี 1-1 — รายชื่อราษฎรพร้อมจำนวนที่ดิน</h2>
    <div class="btn-group">
        <button class="btn btn-back" onclick="window.close()"><i class="bi bi-x-lg"></i> ปิด</button>
        <button class="btn btn-print" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์ / PDF</button>
    </div>
</div>

<?php
$globalRowIdx = 0;
$prevZone = null;

foreach ($allPagesFlat as $pageIdx => $pageData):
    $pageRows = $pageData['rows'];
    $currentZone = $pageData['zone'];
    $isLastPage = ($pageIdx === $totalPages - 1);

    // รีเซ็ตลำดับที่เมื่อเปลี่ยนเขตโครงการฯ
    if ($currentZone !== $prevZone) {
        $globalRowIdx = 0;
        $prevZone = $currentZone;
    }

    // ข้อมูล header จาก row แรกของหน้านี้
    $firstRow = !empty($pageRows) ? $pageRows[0] : ($rows[0] ?? []);
    $parkName = $firstRow['park_name'] ?? '...........................';
    $codeDnp = $firstRow['code_dnp'] ?? '............';
    $aparNo = $currentZone ?: ($firstRow['apar_no'] ?? '.....................');
    $parBan = $firstRow['par_ban'] ?? '.....................';
    $parMoo = $firstRow['par_moo'] ?? '......';
    $parTam = $firstRow['par_tam'] ?? '.....................';
    $parAmp = $firstRow['par_amp'] ?? '.....................';
    $parProv = $firstRow['par_prov'] ?? '.....................';

    // ตรวจว่าเป็นหน้าสุดท้ายของเขตนี้หรือไม่
    $nextZone = isset($allPagesFlat[$pageIdx + 1]) ? $allPagesFlat[$pageIdx + 1]['zone'] : null;
    $isLastPageOfZone = ($nextZone !== $currentZone);

    // คำนวณผลรวมสะสมถึงหน้านี้ (เฉพาะเขตเดียวกัน)
    $rowsUpToNow = [];
    for ($i = 0; $i <= $pageIdx; $i++) {
        if ($allPagesFlat[$i]['zone'] !== $currentZone) continue;
        foreach ($allPagesFlat[$i]['rows'] as $r) $rowsUpToNow[] = $r;
    }
    $cumSummary = FormExportController::getAreaSummary($rowsUpToNow);
    $cumPlots = count($rowsUpToNow);
    $seenCum = [];
    foreach ($rowsUpToNow as $r) {
        $vid = $r['id_card_number'];
        if (!isset($seenCum[$vid])) $seenCum[$vid] = true;
    }
?>
<div class="form-page">
    <div class="form-header">
        <span class="form-code">(บัญชี 1-1)</span>
        <h1>บัญชีรายชื่อราษฎรพร้อมจำนวนที่ดินผู้อยู่อาศัยหรือทำกิน</h1>
        <p class="form-subtitle">
            ภายใน <span class="underline-fill"><?= htmlspecialchars($parkName) ?></span>
        </p>
        <p class="form-subtitle" style="font-size:12pt; color:#555;">
            ภายใต้บันทึกข้อตกลงความร่วมมือในการบริหารจัดการที่ดินทรัพยากรธรรมชาติ
            และพัฒนาคุณภาพชีวิตของประชาชนที่อยู่อาศัยทำกินในเขตป่าอนุรักษ์
        </p>
        <p class="form-meta">
            ท้องที่หมู่บ้าน <span class="underline-fill"><?= htmlspecialchars($parBan) ?></span>
            หมู่ที่ <span class="underline-fill"><?= htmlspecialchars($parMoo) ?></span>
            ตำบล <span class="underline-fill"><?= htmlspecialchars($parTam) ?></span>
            อำเภอ <span class="underline-fill"><?= htmlspecialchars($parAmp) ?></span>
            จังหวัด <span class="underline-fill"><?= htmlspecialchars($parProv) ?></span>
        </p>
        <p class="form-meta">
            เขตโครงการอนุรักษ์และดูแลรักษาทรัพยากรธรรมชาติ ที่ <span class="underline-fill"><?= htmlspecialchars($aparNo) ?></span>
            <span style="float:right; font-size:11pt; color:#666;">หน้า <?= $pageIdx + 1 ?>/<?= $totalPages ?></span>
        </p>
    </div>

    <table class="form-table" style="table-layout:fixed; width:100%;">
        <colgroup>
            <col style="width:3%;"><!--  1. ลำดับที่ -->
            <col style="width:5.5%;"><!--  2. คำนำหน้า -->
            <col style="width:9%;"><!--  3. ชื่อ -->
            <col style="width:10%;"><!-- 4. นามสกุล -->
            <col style="width:12%;"><!-- 5. เลขบัตร 13 หลัก -->
            <col style="width:5%;"><!--  6. ที่ดินเลขที่ -->
            <col style="width:5%;"><!--  7. เขตสำรวจ -->
            <col style="width:5%;"><!--  8. ที่ดินสำรวจเลขที่ -->
            <col style="width:4%;"><!--  9. ไร่ -->
            <col style="width:3.5%;"><!-- 10. งาน -->
            <col style="width:5%;"><!-- 11. ตร.วา -->
            <col style="width:31%;"><!-- 12. หมายเหตุ -->
        </colgroup>
        <thead>
            <tr>
                <th rowspan="2">ลำดับ<br>ที่</th>
                <th rowspan="2">คำนำ<br>หน้าชื่อ</th>
                <th rowspan="2">ชื่อ</th>
                <th rowspan="2">นามสกุล</th>
                <th rowspan="2">เลขประจำตัว<br>ประชาชน</th>
                <th rowspan="2">ที่ดิน<br>เลขที่</th>
                <th rowspan="2">เขต<br>สำรวจที่</th>
                <th rowspan="2">ที่ดินสำรวจ<br>เลขที่</th>
                <th colspan="3">เนื้อที่ประมาณ</th>
                <th rowspan="2">หมายเหตุ</th>
            </tr>
            <tr>
                <th>ไร่</th>
                <th>งาน</th>
                <th>ตร.วา</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pageRows)): ?>
                <tr><td colspan="12" style="padding:20px; color:#999;">ไม่พบข้อมูล</td></tr>
            <?php else: ?>
                <?php foreach ($pageRows as $r):
                    $globalRowIdx++;
                ?>
                    <tr>
                        <td><?= $globalRowIdx ?></td>
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
                        <td class="text-left" style="text-indent:1em; word-break:break-word;">
                            <?= htmlspecialchars(FormExportController::remarkLabel($r['remark_risk'] ?? null)) ?>
                            <?php
                                $totalRai = round((float)($r['owner_total_rai'] ?? 0), 2);
                                if ($totalRai > 40):
                            ?>
                                รวม <?= number_format($totalRai, 2) ?> ไร่ (เกิน 40 ไร่ ม.19)
                            <?php elseif ($totalRai > 20): ?>
                                รวม <?= number_format($totalRai, 2) ?> ไร่ (เกิน 20 ไร่)
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <?php if ($isLastPage && count($zoneGroups) > 1): ?>
            <tr>
                <td colspan="8" style="text-align:right; padding-right:12px;">
                    รวมเขตโครงการฯ ที่ <?= htmlspecialchars($aparNo) ?> จำนวน <?= count($seenCum) ?> ราย <?= $cumPlots ?> แปลง เนื้อที่ประมาณ
                </td>
                <td class="text-right"><?= $cumSummary['rai'] ?></td>
                <td class="text-right"><?= $cumSummary['ngan'] ?></td>
                <td class="text-right"><?= $cumSummary['sqwa'] ?></td>
                <td></td>
            </tr>
            <tr style="font-weight:700;">
                <td colspan="8" style="text-align:right; padding-right:12px;">
                    รวมทั้งสิ้น <?= count($seenVillagerAll) ?> ราย <?= count($rows) ?> แปลง เนื้อที่ประมาณ
                </td>
                <td class="text-right"><?= $grandSummary['rai'] ?></td>
                <td class="text-right"><?= $grandSummary['ngan'] ?></td>
                <td class="text-right"><?= $grandSummary['sqwa'] ?></td>
                <td></td>
            </tr>
            <?php elseif ($isLastPageOfZone && count($zoneGroups) > 1): ?>
            <tr style="font-weight:600;">
                <td colspan="8" style="text-align:right; padding-right:12px;">
                    รวมเขตโครงการฯ ที่ <?= htmlspecialchars($aparNo) ?> จำนวน <?= count($seenCum) ?> ราย <?= $cumPlots ?> แปลง เนื้อที่ประมาณ
                </td>
                <td class="text-right"><?= $cumSummary['rai'] ?></td>
                <td class="text-right"><?= $cumSummary['ngan'] ?></td>
                <td class="text-right"><?= $cumSummary['sqwa'] ?></td>
                <td></td>
            </tr>
            <?php elseif ($isLastPage): ?>
            <tr style="font-weight:700;">
                <td colspan="8" style="text-align:right; padding-right:12px;">
                    รวมทั้งสิ้น <?= count($seenVillagerAll) ?> ราย <?= count($rows) ?> แปลง เนื้อที่ประมาณ
                </td>
                <td class="text-right"><?= $grandSummary['rai'] ?></td>
                <td class="text-right"><?= $grandSummary['ngan'] ?></td>
                <td class="text-right"><?= $grandSummary['sqwa'] ?></td>
                <td></td>
            </tr>
            <?php else: ?>
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

    <?php if ($isLastPage): ?>
    <div class="form-footer">
        <div class="summary-section">
            <p>- ไม่เป็นพื้นที่ล่อแหลมคุกคามต่อระบบนิเวศ ราย <span class="count"><?= $grandSummary['not_risky_count'] ?></span> แปลง <span class="count"><?= $grandSummary['not_risky_count'] ?></span></p>
            <p>- เป็นพื้นที่ล่อแหลมคุกคามต่อระบบนิเวศ ราย <span class="count"><?= $grandSummary['risky_count'] ?></span> แปลง <span class="count"><?= $grandSummary['risky_count'] ?></span></p>
        </div>

        <div class="form-signatures">
            <div class="sig-box">
                <div class="sig-line"></div>
                <p>ลงชื่อ ..............................................</p>
                <p class="sig-label">(.................................................................)</p>
                <p>ประธานคณะกรรมการพิจารณาผลฯ</p>
                <p class="sig-label">ตำแหน่ง ............................................................</p>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <p>ลงชื่อ ..............................................</p>
                <p class="sig-label">(.................................................................)</p>
                <p>หัวหน้าคณะทำงานสำรวจการครอบครองฯ</p>
                <p class="sig-label">ตำแหน่ง ............................................................</p>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <p>ลงชื่อ ..............................................</p>
                <p class="sig-label">(.................................................................)</p>
                <p>คณะทำงานและเลขานุการ</p>
                <p>คณะทำงานสำรวจการครอบครองฯ</p>
                <p class="sig-label">หัวหน้า <?= htmlspecialchars($parkName) ?></p>
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

<?php endforeach; /* allPagesFlat */ ?>

</body>
</html>
