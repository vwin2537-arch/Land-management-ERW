<?php
/**
 * แบบ อส.6-2 — บัญชีรายชื่อผู้อยู่อาศัยหรือทำกิน ประเภทสมาชิกในครอบครัวหรือครัวเรือน
 * — แยกตามเขตโครงการฯ (apar_no) เหมือน อส.6-1
 * — แบ่งหน้า: หัวตารางซ้ำทุกหน้า, ผลรวมสะสม/รวมทั้งสิ้น
 * — ลายเซ็น 2 ช่อง ตามแบบราชการ
 */
require_once __DIR__ . '/../../controllers/FormExportController.php';

$filters = [
    'par_ban' => $_GET['par_ban'] ?? '',
    'park_name' => $_GET['park_name'] ?? '',
    'apar_no' => $_GET['apar_no'] ?? '',
];

$rows = FormExportController::getForm62($filters);

// ──────────────────────────────────────────────────────────
// 1) จัดกลุ่มตามเขตโครงการฯ (apar_no)
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

// ──────────────────────────────────────────────────────────
// 2) ภายในแต่ละ zone → group by owner+plot → flatten → paginate
// ──────────────────────────────────────────────────────────
function flattenZoneRows(array $zoneRows): array {
    // Group by villager + plot
    $grouped = [];
    foreach ($zoneRows as $r) {
        $vid = $r['villager_id'];
        $key = $vid . '_' . ($r['num_apar'] ?? '');
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'owner' => ($r['owner_prefix'] ?? '') . $r['owner_first'] . ' ' . $r['owner_last'],
                'num_apar' => $r['num_apar'] ?? '',
                'members' => [],
                'raw' => $r,
            ];
        }
        if ($r['member_first']) {
            $mkey = ($r['member_idcard'] ?? '') . '_' . $r['member_first'];
            if (!isset($grouped[$key]['members'][$mkey])) {
                $grouped[$key]['members'][$mkey] = $r;
            }
        }
    }

    $displayRows = [];
    $memberCount = 0;
    foreach ($grouped as $group) {
        $ownerLabel = ($group['num_apar'] ? $group['num_apar'] . '/' : '') . $group['owner'];
        $members = array_values($group['members']);

        if (empty($members)) {
            $displayRows[] = [
                'type' => 'empty',
                'owner_label' => $ownerLabel,
                'raw' => $group['raw'],
            ];
        } else {
            foreach ($members as $mi => $m) {
                $memberCount++;
                $displayRows[] = [
                    'type' => 'member',
                    'owner_label' => $mi === 0 ? $ownerLabel : '',
                    'idx' => $mi + 1,
                    'member' => $m,
                    'raw' => $group['raw'],
                ];
            }
        }
    }
    return ['displayRows' => $displayRows, 'memberCount' => $memberCount];
}

// สร้าง flat pages ทุกเขตรวมกัน
$rowsPerPage = 12;
$allPagesFlat = [];
$totalMembers = 0;

foreach ($zoneGroups as $zoneKey => $zoneRows) {
    $result = flattenZoneRows($zoneRows);
    $totalMembers += $result['memberCount'];

    $zPages = array_chunk($result['displayRows'], $rowsPerPage);
    if (empty($zPages)) $zPages = [[]];
    foreach ($zPages as $zp) {
        $allPagesFlat[] = [
            'zone' => $zoneKey,
            'rows' => $zp,
            'zoneMemberCount' => $result['memberCount'],
        ];
    }
}

$totalPages = count($allPagesFlat);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบ อส.6-2 — บัญชีสมาชิกครอบครัว/ครัวเรือน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/form-print.css">
</head>
<body class="form-print">

<div class="form-toolbar">
    <h2><i class="bi bi-file-earmark-text"></i> แบบ อส.6-2 — บัญชีสมาชิกครอบครัว/ครัวเรือน</h2>
    <div class="btn-group">
        <button class="btn btn-back" onclick="window.close()"><i class="bi bi-x-lg"></i> ปิด</button>
        <button class="btn btn-print" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์ / PDF</button>
    </div>
</div>

<?php
$prevZone = null;

foreach ($allPagesFlat as $pageIdx => $pageData):
    $pageRows = $pageData['rows'];
    $currentZone = $pageData['zone'];
    $isLastPage = ($pageIdx === $totalPages - 1);

    // ข้อมูล header จาก row แรกของหน้านี้
    $firstRaw = !empty($pageRows) ? $pageRows[0]['raw'] : ($rows[0] ?? []);
    $parkName = $firstRaw['park_name'] ?? '...........................';
    $codeDnp = $firstRaw['code_dnp'] ?? '............';
    $aparNo = $currentZone ?: ($firstRaw['apar_no'] ?? '.....................');
    $parBan = $firstRaw['par_ban'] ?? '.....................';
    $parMoo = $firstRaw['par_moo'] ?? '......';
    $parTam = $firstRaw['par_tam'] ?? '.....................';
    $parAmp = $firstRaw['par_amp'] ?? '.....................';
    $parProv = $firstRaw['par_prov'] ?? '.....................';

    // ตรวจว่าเป็นหน้าสุดท้ายของเขตนี้หรือไม่
    $nextZone = isset($allPagesFlat[$pageIdx + 1]) ? $allPagesFlat[$pageIdx + 1]['zone'] : null;
    $isLastPageOfZone = ($nextZone !== $currentZone);

    // นับสะสมถึงหน้านี้ (เฉพาะเขตเดียวกัน)
    $cumMembers = 0;
    for ($i = 0; $i <= $pageIdx; $i++) {
        if ($allPagesFlat[$i]['zone'] !== $currentZone) continue;
        foreach ($allPagesFlat[$i]['rows'] as $dr) {
            if ($dr['type'] === 'member') $cumMembers++;
        }
    }

    $prevZone = $currentZone;
?>
<div class="form-page">
    <div class="form-header">
        <span class="form-code">แบบ อส. 6 - 2</span>
        <h1>บัญชีรายชื่อผู้อยู่อาศัยหรือทำกิน ประเภทสมาชิกในครอบครัวหรือครัวเรือน</h1>
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
                <th style="width:40px;">ลำดับที่</th>
                <th style="width:40px;">คำนำ<br>หน้าชื่อ</th>
                <th>ชื่อ</th>
                <th>นามสกุล</th>
                <th style="width:120px;">เลขประจำตัวประชาชน</th>
                <th style="width:200px;">ที่ดินเลขที่/ชื่อผู้ครอบครองที่ดิน</th>
                <th>หมายเหตุ</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pageRows)): ?>
                <tr><td colspan="7" style="padding:20px; color:#999;">ไม่พบข้อมูล</td></tr>
            <?php else: ?>
                <?php foreach ($pageRows as $dr): ?>
                    <?php if ($dr['type'] === 'empty'): ?>
                        <tr>
                            <td>-</td>
                            <td colspan="4" style="text-align:left; padding-left:8px; color:#999;">ยังไม่มีสมาชิกครอบครัว</td>
                            <td class="text-left"><?= htmlspecialchars($dr['owner_label']) ?></td>
                            <td></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td><?= $dr['idx'] ?></td>
                            <td><?= htmlspecialchars($dr['member']['member_prefix'] ?? '') ?></td>
                            <td class="text-left"><?= htmlspecialchars($dr['member']['member_first']) ?></td>
                            <td class="text-left"><?= htmlspecialchars($dr['member']['member_last']) ?></td>
                            <td style="font-family:monospace; font-size:11pt;"><?= htmlspecialchars($dr['member']['member_idcard'] ?? '') ?></td>
                            <td class="text-left"><?= htmlspecialchars($dr['owner_label']) ?></td>
                            <td class="text-left" style="font-size:11pt;"><?= htmlspecialchars($dr['member']['relationship'] ?? '') ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <?php if ($isLastPage && count($zoneGroups) > 1): ?>
            <tr>
                <td colspan="7" style="text-align:left; padding-left:12px;">
                    รวมเขตโครงการฯ ที่ <?= htmlspecialchars($aparNo) ?> จำนวน <?= $cumMembers ?> ราย
                </td>
            </tr>
            <tr style="font-weight:700;">
                <td colspan="7" style="text-align:left; padding-left:12px;">
                    รวมทั้งสิ้น <?= $totalMembers ?> ราย
                </td>
            </tr>
            <?php elseif ($isLastPageOfZone && count($zoneGroups) > 1): ?>
            <tr style="font-weight:600;">
                <td colspan="7" style="text-align:left; padding-left:12px;">
                    รวมเขตโครงการฯ ที่ <?= htmlspecialchars($aparNo) ?> จำนวน <?= $cumMembers ?> ราย
                </td>
            </tr>
            <?php elseif ($isLastPage): ?>
            <tr style="font-weight:700;">
                <td colspan="7" style="text-align:left; padding-left:12px;">
                    รวมทั้งสิ้น <?= $totalMembers ?> ราย
                </td>
            </tr>
            <?php else: ?>
            <tr>
                <td colspan="7" style="text-align:left; padding-left:12px;">
                    รวมสะสม <?= $cumMembers ?> ราย
                </td>
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
            <li>สมาชิกในครอบครัวหรือครัวเรือน เป็นรายชื่อตามผลการสำรวจการครอบครองที่ดิน (แบบ อส. 2)</li>
            <li>หากมีการเปลี่ยนแปลงเพิ่มขึ้นหรือลดลง หรือสิ้นสุดการอยู่อาศัยหรือทำกินตามพระราชกฤษฎีกาฯ มาตรา 13 ให้ผู้ครอบครองที่ดินแจ้งหัวหน้าป่าอนุรักษ์ ภายใน 30 วัน นับแต่วันที่มีการเปลี่ยนแปลง</li>
        </ol>
    </div>
</div>

<?php endforeach; /* allPagesFlat */ ?>

</body>
</html>
