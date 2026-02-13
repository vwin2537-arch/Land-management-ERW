<?php
/**
 * แบบ อส.6-3 — บัญชีรายชื่อผู้ครอบครองที่ดินที่ไม่ผ่านการตรวจสอบคุณสมบัติ
 * — แบ่งหน้า: หัวตารางซ้ำทุกหน้า, ผลรวมสะสม/รวมทั้งสิ้น
 * — ลายเซ็น 2 ช่อง ตามแบบราชการ
 */
require_once __DIR__ . '/../../controllers/FormExportController.php';

$filters = [
    'par_ban' => $_GET['par_ban'] ?? '',
    'park_name' => $_GET['park_name'] ?? '',
    'apar_no' => $_GET['apar_no'] ?? '',
];

$rows = FormExportController::getForm63($filters);
$grandSummary = FormExportController::getAreaSummary($rows);

// นับจำนวนราย (unique villager)
$seenVillagerAll = [];
foreach ($rows as $r) {
    $vid = $r['id_card_number'] ?? '';
    if ($vid && !isset($seenVillagerAll[$vid])) $seenVillagerAll[$vid] = true;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบ อส.6-3 — ผู้ไม่ผ่านตรวจสอบคุณสมบัติ</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/form-print.css">
</head>
<body class="form-print">

<div class="form-toolbar">
    <h2><i class="bi bi-file-earmark-text"></i> แบบ อส.6-3 — ผู้ไม่ผ่านตรวจสอบคุณสมบัติ</h2>
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
    $seenCum = [];
    foreach ($rowsUpToNow as $r) {
        $vid = $r['id_card_number'] ?? '';
        if ($vid) $seenCum[$vid] = true;
    }
?>
<div class="form-page">
    <div class="form-header">
        <span class="form-code">แบบ อส. 6 - 3</span>
        <h1>บัญชีรายชื่อผู้ครอบครองที่ดินที่ไม่ผ่านการตรวจสอบคุณสมบัติให้อยู่อาศัยหรือทำกิน</h1>
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
            <?php if ($totalPages > 1): ?>
            <span style="float:right; font-size:11pt; color:#666;">หน้า <?= $pageIdx + 1 ?>/<?= $totalPages ?></span>
            <?php endif; ?>
        </p>
    </div>

    <table class="form-table">
        <thead>
            <tr>
                <th rowspan="2" style="width:40px;">ลำดับที่</th>
                <th rowspan="2" style="width:40px;">คำนำ<br>หน้าชื่อ</th>
                <th rowspan="2">ชื่อ</th>
                <th rowspan="2">นามสกุล</th>
                <th rowspan="2" style="width:120px;">เลขประจำตัวประชาชน</th>
                <th rowspan="2" style="width:60px;">เขต<br>สำรวจที่</th>
                <th rowspan="2" style="width:70px;">ที่ดินสำรวจ<br>เลขที่</th>
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
                <tr><td colspan="11" style="padding:20px; color:#999; font-size:14pt;">
                    — ไม่มี —
                </td></tr>
            <?php else: ?>
                <?php foreach ($pageRows as $r):
                    $globalRowIdx++;
                ?>
                    <tr>
                        <td><?= $globalRowIdx ?></td>
                        <td><?= htmlspecialchars($r['prefix'] ?? '') ?></td>
                        <td class="text-left"><?= htmlspecialchars($r['first_name']) ?></td>
                        <td class="text-left"><?= htmlspecialchars($r['last_name']) ?></td>
                        <td style="font-family:monospace; font-size:11pt;"><?= htmlspecialchars($r['id_card_number']) ?></td>
                        <td><?= htmlspecialchars($r['spar_no'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['num_spar'] ?? '') ?></td>
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
                <td colspan="7" style="text-align:right; padding-right:12px;">
                    รวมทั้งสิ้น <?= count($seenVillagerAll) ?> ราย <?= count($rows) ?> แปลง เนื้อที่ประมาณ
                </td>
                <td class="text-right"><?= $grandSummary['rai'] ?></td>
                <td class="text-right"><?= $grandSummary['ngan'] ?></td>
                <td class="text-right"><?= $grandSummary['sqwa'] ?></td>
                <td></td>
            </tr>
            <?php else: ?>
            <tr>
                <td colspan="7" style="text-align:right; padding-right:12px;">
                    รวมสะสม <?= count($seenCum) ?> ราย <?= count($rowsUpToNow) ?> แปลง เนื้อที่ประมาณ
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
        <div class="form-signatures">
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
            <li>ให้ผู้ที่ไม่ผ่านการตรวจสอบคุณสมบัติ ออกจากพื้นที่ที่ได้ดำเนินการสำรวจการครอบครองที่ดิน ตามหลักเกณฑ์ วิธีการและเงื่อนไข ที่อธิบดีประกาศกำหนด</li>
            <li>หากไม่มีผู้ไม่ผ่านการตรวจสอบคุณสมบัติ ให้ขีดขวางตารางและระบุว่า "ไม่มี"</li>
        </ol>
    </div>
</div>

<?php endforeach; ?>

</body>
</html>
