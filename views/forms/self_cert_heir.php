<?php
/**
 * หนังสือรับรองตนเอง (ทายาท/สมาชิกครัวเรือน)
 * พิมพ์ให้ทายาทแต่ละคนที่ได้รับจัดสรรที่ดิน
 */
require_once __DIR__ . '/../../controllers/FormExportController.php';
require_once __DIR__ . '/../../controllers/VerificationController.php';

$villagerId = (int)($_GET['villager_id'] ?? 0);
$v = FormExportController::getSelfCert($villagerId);

if (!$v) {
    echo '<h2>ไม่พบข้อมูลราษฎร</h2>';
    return;
}

$ownerName = ($v['prefix'] ?? '') . $v['first_name'] . ' ' . $v['last_name'];

// ดึง allocations ประเภททายาทที่มี member_id
$allocs = VerificationController::getAllocations($villagerId);
$heirAllocs = array_filter($allocs, fn($a) => $a['allocation_type'] === 'heir' && !empty($a['member_id']));

// Group by member_id
$heirsByMember = [];
foreach ($heirAllocs as $a) {
    $mid = $a['member_id'];
    if (!isset($heirsByMember[$mid])) {
        $heirsByMember[$mid] = [
            'member_id' => $mid,
            'name' => ($a['m_prefix'] ?? '') . ($a['m_first_name'] ?? '') . ' ' . ($a['m_last_name'] ?? ''),
            'relationship' => $a['m_relationship'] ?? '',
            'total_rai' => 0,
            'plots' => [],
        ];
    }
    $heirsByMember[$mid]['total_rai'] += (float)($a['allocated_area_rai'] ?? 0);
    $heirsByMember[$mid]['plots'][] = $a;
}

if (empty($heirsByMember)) {
    echo '<h2>ไม่พบข้อมูลทายาทที่ได้รับจัดสรร</h2>';
    return;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หนังสือรับรองตนเอง (ทายาท) — <?= htmlspecialchars($ownerName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/form-print.css">
    <style>
        @page { size: A4 portrait; margin: 20mm 18mm; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body class="form-print-portrait">

<div class="form-toolbar">
    <h2><i class="bi bi-shield-check"></i> หนังสือรับรองตนเอง (ทายาท) — <?= htmlspecialchars($ownerName) ?></h2>
    <div class="btn-group">
        <button class="btn btn-back" onclick="window.close()"><i class="bi bi-x-lg"></i> ปิด</button>
        <button class="btn btn-print" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์ / PDF</button>
    </div>
</div>

<?php $heirIndex = 0; ?>
<?php foreach ($heirsByMember as $heir): ?>
<?php $heirIndex++; ?>

<?php if ($heirIndex > 1): ?><div class="page-break"></div><?php endif; ?>

<div class="form-page" style="max-width:210mm; padding:30px;">

    <div class="form-header" style="margin-bottom:20px;">
        <h1 style="font-size:20pt; margin-bottom:16px;">หนังสือรับรองตนเอง</h1>
        <p style="font-size:13pt; color:#7c3aed; text-align:center;">(สมาชิกครัวเรือน/ทายาท — ผู้ได้รับจัดสรรที่ดิน)</p>
    </div>

    <div style="text-align:right; font-size:14pt; margin-bottom:8px;">
        เขียนที่ <span style="border-bottom:1px dotted #000; display:inline-block; min-width:250px;"><?= htmlspecialchars($v['address'] ?? '') ?></span>
    </div>
    <div style="text-align:right; font-size:14pt; margin-bottom:8px;">
        ตำบล <span style="border-bottom:1px dotted #000; display:inline-block; min-width:100px;"><?= htmlspecialchars($v['sub_district'] ?? '') ?></span>
        อำเภอ <span style="border-bottom:1px dotted #000; display:inline-block; min-width:100px;"><?= htmlspecialchars($v['district'] ?? '') ?></span>
    </div>
    <div style="text-align:right; font-size:14pt; margin-bottom:8px;">
        จังหวัด <span style="border-bottom:1px dotted #000; display:inline-block; min-width:150px;"><?= htmlspecialchars($v['province'] ?? '') ?></span>
    </div>
    <div style="text-align:right; font-size:14pt; margin-bottom:20px;">
        วันที่ <span style="border-bottom:1px dotted #000; display:inline-block; min-width:30px;">........</span>
        เดือน <span style="border-bottom:1px dotted #000; display:inline-block; min-width:80px;">........................</span>
        พ.ศ. <span style="border-bottom:1px dotted #000; display:inline-block; min-width:50px;">............</span>
    </div>

    <div class="cert-body">
        <p>
            ข้าพเจ้า ชื่อ <span class="field-long"><?= htmlspecialchars($heir['name']) ?></span>
        </p>
        <p>
            ในฐานะ <span class="field-long" style="color:#7c3aed; font-weight:600;"><?= htmlspecialchars($heir['relationship'] ?: 'สมาชิกครัวเรือน') ?></span>
            ของ <span class="field-long"><?= htmlspecialchars($ownerName) ?></span>
            เลขประจำตัวประชาชน <span class="field-long" style="font-family:monospace; letter-spacing:2px;"><?= htmlspecialchars($v['id_card_number']) ?></span>
        </p>
        <p>
            ซึ่งเป็นผู้ได้รับการสำรวจการถือครองที่ดินภายในเขตป่าอนุรักษ์
            <span class="field-long"><?= htmlspecialchars($v['park_name'] ?? '...........................') ?></span>
            ท้องที่หมู่บ้าน <span class="field"><?= htmlspecialchars($v['par_ban'] ?? '......') ?></span>
            หมู่ที่ <span class="field"><?= htmlspecialchars($v['par_moo'] ?? '...') ?></span>
            ตำบล <span class="field"><?= htmlspecialchars($v['par_tam'] ?? '......') ?></span>
            อำเภอ <span class="field"><?= htmlspecialchars($v['par_amp'] ?? '......') ?></span>
            จังหวัด <span class="field"><?= htmlspecialchars($v['par_prov'] ?? '......') ?></span>
        </p>

        <p style="margin-top:12px; padding:8px 12px; border:1px solid #e5e7eb; border-radius:4px; background:#faf5ff;">
            <strong>ได้รับจัดสรรที่ดินจำนวน <?= count($heir['plots']) ?> แปลง
            เนื้อที่รวม <?= number_format($heir['total_rai'], 2) ?> ไร่</strong>
        </p>

        <p style="text-indent:40px; margin-top:12px;">
            ขอรับรองว่าข้าพเจ้ามีคุณสมบัติและไม่มีลักษณะต้องห้ามดังต่อไปนี้
        </p>

        <ul class="cert-checklist">
            <li>มีสัญชาติไทย</li>
            <li>อยู่อาศัยและทำประโยชน์บนที่ดินที่อยู่อาศัยหรือทำกินตามโครงการนี้อย่างต่อเนื่อง และไม่มีที่ดินทำกินอื่นนอกเขตพื้นที่โครงการ</li>
            <li>ไม่มีที่ดินที่เป็นกรรมสิทธิ์หรือมีสิทธิครอบครองในที่ดินทำกินหรือที่อยู่อาศัยอื่น</li>
            <li>ไม่เคยต้องคำพิพากษาถึงที่สุดให้ออกจากอุทยานแห่งชาติหรือเขตรักษาพันธุ์สัตว์ป่าหรือเขตห้ามล่าสัตว์ป่า</li>
            <li>ไม่เคยต้องคำพิพากษาถึงที่สุดในความผิดเกี่ยวกับการยึดถือหรือครอบครองที่ดิน ก่อสร้าง แผ้วถาง เผาป่า หรือกระทำด้วยประการใด ๆ ให้เสื่อมสภาพ หรือเปลี่ยนแปลงพื้นที่จากเดิม ทำไม้ ล่าสัตว์ป่าสงวนหรือสัตว์ป่าคุ้มครอง หรือค้าสัตว์ป่าสงวน สัตว์ป่าคุ้มครอง ซากสัตว์ป่าหรือผลิตภัณฑ์จากซากสัตว์ป่าดังกล่าว ตามกฎหมายว่าด้วยอุทยานแห่งชาติหรือกฎหมายว่าด้วยการสงวนและคุ้มครองสัตว์ป่านับแต่วันที่พระราชกฤษฎีกานี้มีผลใช้บังคับ</li>
            <li>ไม่เคยถูกพนักงานเจ้าหน้าที่มีคำสั่งถึงที่สุดให้เพิกถอนสิทธิการอยู่อาศัยหรือทำกินในอุทยานแห่งชาติ หรือเขตรักษาพันธุ์สัตว์ป่าหรือเขตห้ามล่าสัตว์ป่า</li>
        </ul>

        <p style="text-indent:40px; margin-top:16px;">
            ข้าพเจ้าขอรับรองว่าได้แจ้งข้อมูลเป็นจริง ครบถ้วน หากปรากฏในภายหลังว่าข้าพเจ้าขาดคุณสมบัติ
            หรือมีคุณสมบัติไม่ครบถ้วนตามที่ได้รับรองไว้ หรือเป็นบุคคลที่มีลักษณะต้องห้าม หรือมีข้อความอันเป็นเท็จ
            ข้าพเจ้ายินยอมสละสิทธิการถือครองที่ดินและคืนพื้นที่ให้กับกรมอุทยานแห่งชาติ สัตว์ป่า และพันธุ์พืช
        </p>
        <p style="text-indent:40px;">
            และข้าพเจ้าทราบดีว่าการให้ข้อมูลอันเป็นเท็จกับทางราชการเป็นความผิดและต้องรับโทษทางอาญา
            ตามมาตรา 137 และมาตรา 267 แห่งประมวลกฎหมายอาญา
        </p>
        <p style="text-indent:40px; margin-top:8px;">
            อนึ่ง หากข้าพเจ้าได้รับหนังสือรับรองการอยู่อาศัยหรือทำกินภายในเขตป่าอนุรักษ์
            ข้าพเจ้ายินดีที่จะปฏิบัติตามเงื่อนไขที่ระบุไว้ท้ายหนังสือดังกล่าวทุกประการ
            หากมีการฝ่าฝืนหรือไม่ปฏิบัติตาม ข้าพเจ้ายินยอมให้เพิกถอนสิทธิและต้องถูกดำเนินคดีตามกฎหมาย
        </p>
    </div>

    <!-- Signatures -->
    <div style="display:flex; justify-content:space-between; margin-top:40px; gap:30px;">
        <div style="text-align:center; flex:1;">
            <div style="margin-top:50px; border-top:1px dotted #000; width:220px; margin-left:auto; margin-right:auto;"></div>
            <p style="font-size:13pt;">ผู้ได้รับจัดสรรที่ดิน (ทายาท)</p>
            <p style="font-size:12pt; color:#555;">( <?= htmlspecialchars($heir['name']) ?> )</p>
        </div>
        <div style="text-align:center; flex:1;">
            <div style="margin-top:50px; border-top:1px dotted #000; width:220px; margin-left:auto; margin-right:auto;"></div>
            <p style="font-size:13pt;">ผู้ถือครองที่ดิน (เจ้าของเดิม)</p>
            <p style="font-size:12pt; color:#555;">( <?= htmlspecialchars($ownerName) ?> )</p>
        </div>
    </div>
    <div style="display:flex; justify-content:space-between; margin-top:20px; gap:30px;">
        <div style="text-align:center; flex:1;">
            <div style="margin-top:40px; border-top:1px dotted #000; width:220px; margin-left:auto; margin-right:auto;"></div>
            <p style="font-size:13pt;">ผู้ใหญ่บ้าน/กำนัน พยาน</p>
            <p style="font-size:12pt; color:#555;">( ......................................................... )</p>
        </div>
        <div style="text-align:center; flex:1;">
            <div style="margin-top:40px; border-top:1px dotted #000; width:220px; margin-left:auto; margin-right:auto;"></div>
            <p style="font-size:13pt;">พยาน</p>
            <p style="font-size:12pt; color:#555;">( ......................................................... )</p>
        </div>
    </div>

</div>

<?php endforeach; ?>

</body>
</html>
