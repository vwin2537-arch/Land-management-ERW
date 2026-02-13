<?php
/**
 * บัญชี 1-2 — บัญชีรายชื่อแปลงที่ดินประเภทที่ดินอื่นๆ พร้อมจำนวนที่ดิน
 * — แบ่งหน้า: หัวตารางซ้ำทุกหน้า, ผลรวมสะสม/รวมทั้งสิ้น
 */
require_once __DIR__ . '/../../controllers/FormExportController.php';

$filters = [
    'park_name' => $_GET['park_name'] ?? '',
    'apar_no' => $_GET['apar_no'] ?? '',
];

$rows = FormExportController::getAccount12($filters);
$grandSummary = FormExportController::getAreaSummary($rows);

// Map ptype to main/sub categories
function classifyPtype(?string $ptype): array {
    $ptype = $ptype ?? '';
    if (str_contains($ptype, 'โรงเรียน') || str_contains($ptype, 'ศูนย์พัฒนาเด็ก') || str_contains($ptype, 'สถานศึกษา')) {
        return ['สถานศึกษา', $ptype ?: 'โรงเรียน'];
    }
    if (str_contains($ptype, 'วัด') || str_contains($ptype, 'สำนักสงฆ์') || str_contains($ptype, 'โบสถ์') || str_contains($ptype, 'ศาสนา')) {
        return ['สถานที่ทางศาสนา', $ptype ?: 'วัด'];
    }
    if (str_contains($ptype, 'ราชพัสดุ') || str_contains($ptype, 'ราชการ') || str_contains($ptype, 'สปก')) {
        return ['สถานที่ของหน่วยงานราชการอื่นๆ', $ptype ?: 'ที่ราชพัสดุ'];
    }
    if (str_contains($ptype, 'รวม') || str_contains($ptype, 'ที่ทำกินรวม') || str_contains($ptype, 'ที่อยู่อาศัยรวม')) {
        return ['แปลงที่ดินรวม', $ptype ?: 'ที่ทำกินรวม'];
    }
    return ['ที่ดินประเภทอื่นๆ', $ptype ?: 'อื่นๆ'];
}

// แบ่งหน้า
$rowsPerPage = 9;
$pages = array_chunk($rows, $rowsPerPage);
if (empty($pages)) $pages = [[]];
$totalPages = count($pages);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บัญชี 1-2 — แปลงที่ดินประเภทอื่นๆ</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/form-print.css">
</head>
<body class="form-print">

<div class="form-toolbar">
    <h2><i class="bi bi-file-earmark-text"></i> บัญชี 1-2 — แปลงที่ดินประเภทอื่นๆ</h2>
    <div class="btn-group">
        <button class="btn btn-back" onclick="window.close()"><i class="bi bi-x-lg"></i> ปิด</button>
        <button class="btn btn-print" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์ / PDF</button>
    </div>
</div>

<?php
$globalRowIdx = 0;

foreach ($pages as $pageIdx => $pageRows):
    $isLastPage = ($pageIdx === $totalPages - 1);

    // ข้อมูล header จาก row แรก
    $firstRow = !empty($pageRows) ? $pageRows[0] : ($rows[0] ?? []);
    $parkName = $firstRow['park_name'] ?? '...........................';
    $codeDnp = $firstRow['code_dnp'] ?? '............';
    $aparNo = $firstRow['apar_no'] ?? '.....................';
    $parBan = $firstRow['par_ban'] ?? '.....................';
    $parMoo = $firstRow['par_moo'] ?? '......';
    $parTam = $firstRow['par_tam'] ?? '.....................';
    $parAmp = $firstRow['par_amp'] ?? '.....................';
    $parProv = $firstRow['par_prov'] ?? '.....................';

    // คำนวณผลรวมสะสมถึงหน้านี้
    $rowsUpToNow = array_merge(...array_slice($pages, 0, $pageIdx + 1));
    $cumSummary = FormExportController::getAreaSummary($rowsUpToNow);
?>
<div class="form-page">
    <div class="form-header">
        <span class="form-code">(บัญชี 1-2)</span>
        <h1>บัญชีรายชื่อแปลงที่ดินประเภทที่ดินอื่นๆ พร้อมจำนวนที่ดิน</h1>
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
            <?php if ($totalPages > 1): ?>
            <span style="float:right; font-size:11pt; color:#666;">หน้า <?= $pageIdx + 1 ?>/<?= $totalPages ?></span>
            <?php endif; ?>
        </p>
    </div>

    <table class="form-table">
        <thead>
            <tr>
                <th rowspan="2" style="width:40px;">ลำดับที่</th>
                <th rowspan="2">ชื่อ</th>
                <th rowspan="2">ประเภทที่ดินหลัก</th>
                <th rowspan="2">ประเภทที่ดินย่อย</th>
                <th colspan="3">เนื้อที่ประมาณ</th>
                <th rowspan="2">หมายเหตุ</th>
            </tr>
            <tr>
                <th style="width:40px;">ไร่</th>
                <th style="width:40px;">งาน</th>
                <th style="width:50px;">ตารางวา</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pageRows)): ?>
                <tr><td colspan="8" style="padding:20px; color:#999;">ไม่พบข้อมูล</td></tr>
            <?php else: ?>
                <?php foreach ($pageRows as $r):
                    $globalRowIdx++;
                    [$mainType, $subType] = classifyPtype($r['ptype']);
                ?>
                    <tr>
                        <td><?= $globalRowIdx ?></td>
                        <td class="text-left"><?= htmlspecialchars($r['notes'] ?? $r['plot_code'] ?? '') ?></td>
                        <td class="text-left"><?= htmlspecialchars($mainType) ?></td>
                        <td class="text-left"><?= htmlspecialchars($subType) ?></td>
                        <td class="text-right"><?= (int)($r['area_rai'] ?? 0) ?></td>
                        <td class="text-right"><?= (int)($r['area_ngan'] ?? 0) ?></td>
                        <td class="text-right"><?= (int)($r['area_sqwa'] ?? 0) ?></td>
                        <td class="text-left" style="font-size:11pt;">
                            <?= htmlspecialchars(FormExportController::remarkLabel($r['remark_risk'] ?? null)) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <?php if ($isLastPage): ?>
            <tr style="font-weight:700;">
                <td colspan="4" style="text-align:right; padding-right:12px;">
                    รวมจำนวน <?= count($rows) ?> แปลง เนื้อที่ประมาณ
                </td>
                <td class="text-right"><?= $grandSummary['rai'] ?></td>
                <td class="text-right"><?= $grandSummary['ngan'] ?></td>
                <td class="text-right"><?= $grandSummary['sqwa'] ?></td>
                <td></td>
            </tr>
            <?php else: ?>
            <tr>
                <td colspan="4" style="text-align:right; padding-right:12px;">
                    รวมสะสม <?= count($rowsUpToNow) ?> แปลง เนื้อที่ประมาณ
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
            <p>- ไม่เป็นพื้นที่ล่อแหลมคุกคามต่อระบบนิเวศ แปลง <span class="count"><?= $grandSummary['not_risky_count'] ?></span></p>
            <p>- เป็นพื้นที่ล่อแหลมคุกคามต่อระบบนิเวศ แปลง <span class="count"><?= $grandSummary['risky_count'] ?></span></p>
        </div>

        <div class="form-signatures">
            <div class="sig-box">
                <p>ลงชื่อ ..............................................</p>
                <p class="sig-label">(.................................................................)</p>
                <p class="sig-label">ตำแหน่ง ............................................................</p>
            </div>
            <div class="sig-box">
                <p>ลงชื่อ ..............................................</p>
                <p class="sig-label">(.................................................................)</p>
                <p class="sig-label">ตำแหน่ง ............................................................</p>
            </div>
            <div class="sig-box">
                <p>ลงชื่อ ..............................................</p>
                <p class="sig-label">(.................................................................)</p>
                <p class="sig-label">หัวหน้า <?= htmlspecialchars($parkName) ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="form-notes">
        <h4>หมายเหตุ</h4>
        <ol>
            <li>เขตโครงการฯ ให้ขึ้นต้นด้วยเลข 1 และตามด้วยเลขเขตโครงการ ตัวเลข 4 หลัก เช่น เขตโครงการฯ ที่ 1 เป็น 10001 เป็นต้น</li>
            <li>ช่องชื่อ ให้กรอกชื่อสถานที่นั้น เช่น โรงเรียนบ้านส้ม วัดบ้านส้ม เป็นต้น</li>
            <li>ช่องประเภทที่ดินหลัก ให้กรอกแยกเป็น 5 ประเภท: สถานศึกษา, สถานที่ทางศาสนา, สถานที่ของหน่วยงานราชการอื่นๆ, แปลงที่ดินรวม, ที่ดินประเภทอื่นๆ</li>
        </ol>
    </div>
</div>

<?php endforeach; ?>

</body>
</html>
