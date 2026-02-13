<?php
/**
 * SHP Attribute Quality Audit — ตรวจสอบ attribute ของ Shapefile ทั้งหมด
 * สร้างรายงาน QA ให้เจ้าหน้าที่ตรวจแก้ไขก่อน import เข้า DB
 */

$dbfPath = __DIR__ . '/../ตรวจสอบคุณสมบัติ/Merge_แปลงสอบทาน.dbf';
if (!file_exists($dbfPath)) { die("DBF file not found\n"); }

// ──────────────────────────────────────────────────────────
// Read DBF
// ──────────────────────────────────────────────────────────
$fh = fopen($dbfPath, 'rb');
$headerData = fread($fh, 32);
$header = unpack('Cversion/CyearMod/CmonthMod/CdayMod/VnumRecords/vheaderSize/vrecordSize', $headerData);
$fields = [];
while (true) {
    $fd = fread($fh, 32);
    if (!$fd || strlen($fd) < 32 || ord($fd[0]) === 0x0D) break;
    $f = unpack('A11name/Atype/x4/Clength/Cdecimal', $fd);
    $f['name'] = trim($f['name']);
    $fields[] = $f;
}
fseek($fh, $header['headerSize']);
$records = [];
for ($i = 0; $i < $header['numRecords']; $i++) {
    fread($fh, 1);
    $rec = [];
    foreach ($fields as $f) {
        $rec[$f['name']] = trim(fread($fh, $f['length']));
    }
    $rec['_ROW'] = $i + 1;
    $records[] = $rec;
}
fclose($fh);

$total = count($records);
$out = [];
$out[] = "╔══════════════════════════════════════════════════════════════╗";
$out[] = "║  SHP ATTRIBUTE QUALITY AUDIT REPORT                        ║";
$out[] = "║  File: Merge_แปลงสอบทาน.shp                               ║";
$out[] = "║  Records: $total                                              ║";
$out[] = "║  Date: " . date('Y-m-d H:i:s') . "                              ║";
$out[] = "╚══════════════════════════════════════════════════════════════╝";
$out[] = "";

$issueCount = 0;
$issuesByType = [];

function addIssue(&$issuesByType, &$issueCount, $type, $detail) {
    $issuesByType[$type][] = $detail;
    $issueCount++;
}

// ══════════════════════════════════════════════════════════════
// CHECK 1: SPAR_CODE duplicates
// ══════════════════════════════════════════════════════════════
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$out[] = "CHECK 1: SPAR_CODE ซ้ำกัน (Duplicate SPAR_CODE)";
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$sparCodeCount = [];
$sparCodeRows = [];
foreach ($records as $r) {
    $code = $r['SPAR_CODE'] ?? '';
    $sparCodeCount[$code] = ($sparCodeCount[$code] ?? 0) + 1;
    $sparCodeRows[$code][] = $r['_ROW'];
}
$dupSparCodes = array_filter($sparCodeCount, fn($c) => $c > 1);
$out[] = "Duplicate SPAR_CODE: " . count($dupSparCodes) . " codes (" . array_sum($dupSparCodes) . " records)";
$out[] = "แนวทาง: ต้องลบ record ซ้ำออก ให้เหลือ 1 record ต่อ 1 SPAR_CODE";
$out[] = "";
if (!empty($dupSparCodes)) {
    $out[] = str_pad("SPAR_CODE", 22) . str_pad("ซ้ำ", 6) . "Rows";
    $out[] = str_repeat("-", 60);
    foreach ($dupSparCodes as $code => $cnt) {
        $rows = implode(', ', $sparCodeRows[$code]);
        $out[] = str_pad($code, 22) . str_pad($cnt, 6) . $rows;
        addIssue($issuesByType, $issueCount, 'SPAR_CODE_DUP', "SPAR_CODE=$code rows=$rows");
    }
}
$out[] = "";

// ══════════════════════════════════════════════════════════════
// CHECK 2: Empty/Missing required fields
// ══════════════════════════════════════════════════════════════
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$out[] = "CHECK 2: ข้อมูลว่าง (Empty/Missing fields)";
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$requiredFields = [
    'NAME_TITLE' => 'คำนำหน้า',
    'NAME'       => 'ชื่อ',
    'SURNAME'    => 'นามสกุล',
    'IDCARD'     => 'เลขบัตรประชาชน',
    'APAR_NO'    => 'เขตโครงการฯ',
    'NUM_APAR'   => 'ลำดับที่แปลง',
    'SPAR_CODE'  => 'รหัสแปลง',
    'SPAR_NO'    => 'ลำดับแปลงย่อย',
    'PAR_BAN'    => 'หมู่บ้าน',
    'PAR_MOO'    => 'หมู่ที่',
    'PAR_TAM'    => 'ตำบล',
    'PAR_AMP'    => 'อำเภอ',
    'PAR_PROV'   => 'จังหวัด',
    'AREA_RAI'   => 'เนื้อที่ (ไร่)',
    'WA_SQ'      => 'เนื้อที่ (ตร.วา)',
    'REMARK'     => 'หมายเหตุ (ล่อแหลม/ไม่ล่อแหลม)',
];

$emptyStats = [];
$emptyRows = [];
foreach ($requiredFields as $field => $label) {
    $emptyStats[$field] = 0;
    $emptyRows[$field] = [];
    foreach ($records as $r) {
        if (($r[$field] ?? '') === '') {
            $emptyStats[$field]++;
            if (count($emptyRows[$field]) < 10) {
                $emptyRows[$field][] = $r['_ROW'];
            }
        }
    }
}

$out[] = str_pad("Field", 15) . str_pad("คำอธิบาย", 28) . str_pad("ว่าง", 8) . str_pad("จาก", 8) . "สถานะ";
$out[] = str_repeat("-", 72);
foreach ($requiredFields as $field => $label) {
    $cnt = $emptyStats[$field];
    $status = $cnt === 0 ? 'OK' : "** ต้องตรวจ **";
    $out[] = str_pad($field, 15) . str_pad($label, 28) . str_pad($cnt, 8) . str_pad($total, 8) . $status;
    if ($cnt > 0) {
        $sample = implode(', ', $emptyRows[$field]);
        $suffix = $cnt > 10 ? " ..." : "";
        $out[] = "    → ตัวอย่าง rows: $sample$suffix";
        addIssue($issuesByType, $issueCount, 'EMPTY_' . $field, "$cnt records");
    }
}
$out[] = "";

// ══════════════════════════════════════════════════════════════
// CHECK 3: IDCARD format validation
// ══════════════════════════════════════════════════════════════
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$out[] = "CHECK 3: รูปแบบเลขบัตรประชาชน (IDCARD)";
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$idcardOk = 0;
$idcardBad = [];
$idcardEmpty = 0;
foreach ($records as $r) {
    $id = $r['IDCARD'] ?? '';
    if ($id === '') {
        $idcardEmpty++;
    } elseif (!preg_match('/^\d{13}$/', $id)) {
        $idcardBad[] = ['row' => $r['_ROW'], 'idcard' => $id, 'spar' => $r['SPAR_CODE'] ?? ''];
        addIssue($issuesByType, $issueCount, 'IDCARD_FORMAT', "Row {$r['_ROW']}: $id");
    } else {
        $idcardOk++;
    }
}
$out[] = "ถูกต้อง (13 หลัก): $idcardOk";
$out[] = "ว่าง: $idcardEmpty";
$out[] = "ผิดรูปแบบ: " . count($idcardBad);
if (!empty($idcardBad)) {
    $out[] = "";
    $out[] = str_pad("Row", 6) . str_pad("SPAR_CODE", 22) . "IDCARD";
    $out[] = str_repeat("-", 55);
    foreach (array_slice($idcardBad, 0, 20) as $b) {
        $out[] = str_pad($b['row'], 6) . str_pad($b['spar'], 22) . $b['idcard'];
    }
    if (count($idcardBad) > 20) $out[] = "  ... และอีก " . (count($idcardBad) - 20) . " รายการ";
}
$out[] = "";

// ══════════════════════════════════════════════════════════════
// CHECK 4: NUM_APAR format (should start with 1 or 2, 5 digits)
// ══════════════════════════════════════════════════════════════
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$out[] = "CHECK 4: รูปแบบ NUM_APAR (ลำดับที่แปลง)";
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$numAparPrefixes = [];
$numAparBad = [];
foreach ($records as $r) {
    $num = $r['NUM_APAR'] ?? '';
    if ($num === '') continue;
    $prefix = substr($num, 0, 1);
    $numAparPrefixes[$prefix] = ($numAparPrefixes[$prefix] ?? 0) + 1;
    if (!preg_match('/^[1234]\d{4}$/', $num)) {
        $numAparBad[] = ['row' => $r['_ROW'], 'num_apar' => $num, 'spar' => $r['SPAR_CODE'] ?? ''];
    }
}
ksort($numAparPrefixes);
$out[] = "Distribution by prefix:";
foreach ($numAparPrefixes as $p => $c) {
    $label = match($p) {
        '1' => 'มติ ครม.',
        '2' => 'คสช. 66/2557',
        '3' => 'เกิน 20 ไร่',
        '4' => 'เกิน 40 ไร่',
        default => 'ไม่ทราบ',
    };
    $out[] = "  Code $p ($label): $c records";
}
$out[] = "ผิดรูปแบบ (ไม่ใช่ [1-4]XXXX): " . count($numAparBad);
if (!empty($numAparBad)) {
    foreach (array_slice($numAparBad, 0, 10) as $b) {
        $out[] = "  Row {$b['row']}: NUM_APAR={$b['num_apar']} SPAR={$b['spar']}";
        addIssue($issuesByType, $issueCount, 'NUM_APAR_FORMAT', "Row {$b['row']}: {$b['num_apar']}");
    }
}
$out[] = "";

// ══════════════════════════════════════════════════════════════
// CHECK 5: APAR_NO consistency
// ══════════════════════════════════════════════════════════════
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$out[] = "CHECK 5: เขตโครงการฯ (APAR_NO) — รายชื่อทั้งหมด";
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$aparNoStats = [];
foreach ($records as $r) {
    $apar = $r['APAR_NO'] ?? '';
    if (!isset($aparNoStats[$apar])) {
        $aparNoStats[$apar] = ['count' => 0, 'par_bans' => []];
    }
    $aparNoStats[$apar]['count']++;
    $pb = $r['PAR_BAN'] ?? '';
    if ($pb) $aparNoStats[$apar]['par_bans'][$pb] = true;
}
ksort($aparNoStats);
$out[] = str_pad("APAR_NO", 10) . str_pad("แปลง", 8) . "หมู่บ้าน";
$out[] = str_repeat("-", 60);
foreach ($aparNoStats as $apar => $s) {
    $bans = implode(', ', array_keys($s['par_bans']));
    $out[] = str_pad($apar ?: '(ว่าง)', 10) . str_pad($s['count'], 8) . $bans;
}
$out[] = "";

// ══════════════════════════════════════════════════════════════
// CHECK 6: PAR_BAN + PAR_MOO consistency
// ══════════════════════════════════════════════════════════════
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$out[] = "CHECK 6: หมู่บ้าน (PAR_BAN + PAR_MOO) — สรุป";
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$villageStats = [];
foreach ($records as $r) {
    $ban = $r['PAR_BAN'] ?? '';
    $moo = $r['PAR_MOO'] ?? '';
    $key = "$ban|$moo";
    $villageStats[$key] = ($villageStats[$key] ?? 0) + 1;
}
ksort($villageStats);
$out[] = str_pad("หมู่บ้าน", 25) . str_pad("หมู่ที่", 8) . "แปลง";
$out[] = str_repeat("-", 45);
foreach ($villageStats as $key => $cnt) {
    [$ban, $moo] = explode('|', $key);
    $out[] = str_pad($ban ?: '(ว่าง)', 25) . str_pad($moo ?: '(ว่าง)', 8) . $cnt;
}
$out[] = "";

// ══════════════════════════════════════════════════════════════
// CHECK 7: NGAN field — should be 0-3
// ══════════════════════════════════════════════════════════════
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$out[] = "CHECK 7: ค่า NGAN (ต้องเป็น 0-3)";
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$nganBad = [];
$nganEmpty = 0;
foreach ($records as $r) {
    $ngan = $r['NGAN'] ?? '';
    if ($ngan === '') {
        $nganEmpty++;
        continue;
    }
    $nv = (float)$ngan;
    if ($nv < 0 || $nv > 3 || floor($nv) != $nv) {
        $nganBad[] = ['row' => $r['_ROW'], 'ngan' => $ngan, 'spar' => $r['SPAR_CODE'] ?? ''];
        addIssue($issuesByType, $issueCount, 'NGAN_INVALID', "Row {$r['_ROW']}: NGAN=$ngan");
    }
}
$out[] = "ว่าง: $nganEmpty";
$out[] = "ผิดค่า (ไม่ใช่ 0-3 จำนวนเต็ม): " . count($nganBad);
if (!empty($nganBad)) {
    foreach (array_slice($nganBad, 0, 10) as $b) {
        $out[] = "  Row {$b['row']}: NGAN={$b['ngan']} SPAR={$b['spar']}";
    }
}
$out[] = "";

// ══════════════════════════════════════════════════════════════
// CHECK 8: AREA_RAI consistency — should be integer
// ══════════════════════════════════════════════════════════════
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$out[] = "CHECK 8: AREA_RAI (ต้องเป็นจำนวนเต็ม >= 0)";
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$areaRaiBad = [];
foreach ($records as $r) {
    $val = $r['AREA_RAI'] ?? '';
    if ($val === '') continue;
    $fv = (float)$val;
    if ($fv < 0 || floor($fv) != $fv) {
        $areaRaiBad[] = ['row' => $r['_ROW'], 'area_rai' => $val, 'spar' => $r['SPAR_CODE'] ?? ''];
        addIssue($issuesByType, $issueCount, 'AREA_RAI_BAD', "Row {$r['_ROW']}: AREA_RAI=$val");
    }
}
$out[] = "ผิดค่า (ไม่ใช่จำนวนเต็ม >= 0): " . count($areaRaiBad);
if (!empty($areaRaiBad)) {
    foreach (array_slice($areaRaiBad, 0, 10) as $b) {
        $out[] = "  Row {$b['row']}: AREA_RAI={$b['area_rai']} SPAR={$b['spar']}";
    }
}
$out[] = "";

// ══════════════════════════════════════════════════════════════
// CHECK 9: WA_SQ — should be 0-99.xx
// ══════════════════════════════════════════════════════════════
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$out[] = "CHECK 9: WA_SQ ตารางวา (ควรอยู่ 0-99.xx)";
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$waSqBad = [];
foreach ($records as $r) {
    $val = $r['WA_SQ'] ?? '';
    if ($val === '') continue;
    $fv = (float)$val;
    if ($fv < 0 || $fv >= 100) {
        $waSqBad[] = ['row' => $r['_ROW'], 'wa_sq' => $val, 'spar' => $r['SPAR_CODE'] ?? '', 'area_rai' => $r['AREA_RAI'] ?? '', 'ngan' => $r['NGAN'] ?? ''];
        addIssue($issuesByType, $issueCount, 'WA_SQ_RANGE', "Row {$r['_ROW']}: WA_SQ=$val");
    }
}
$out[] = "ค่าผิดช่วง (< 0 หรือ >= 100): " . count($waSqBad);
if (!empty($waSqBad)) {
    $out[] = str_pad("Row", 6) . str_pad("SPAR_CODE", 22) . str_pad("AREA_RAI", 10) . str_pad("NGAN", 6) . "WA_SQ";
    $out[] = str_repeat("-", 60);
    foreach (array_slice($waSqBad, 0, 20) as $b) {
        $out[] = str_pad($b['row'], 6) . str_pad($b['spar'], 22) . str_pad($b['area_rai'], 10) . str_pad($b['ngan'], 6) . $b['wa_sq'];
    }
}
$out[] = "";

// ══════════════════════════════════════════════════════════════
// CHECK 10: REMARK — ล่อแหลม/ไม่ล่อแหลม
// ══════════════════════════════════════════════════════════════
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$out[] = "CHECK 10: REMARK (หมายเหตุ) — ค่าทั้งหมด";
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$remarkValues = [];
foreach ($records as $r) {
    $val = $r['REMARK'] ?? '';
    $remarkValues[$val] = ($remarkValues[$val] ?? 0) + 1;
}
arsort($remarkValues);
foreach ($remarkValues as $val => $cnt) {
    $display = $val === '' ? '(ว่าง)' : $val;
    $out[] = str_pad($display, 35) . $cnt . " records";
}
$out[] = "";

// ══════════════════════════════════════════════════════════════
// CHECK 11: SPAR_CODE format
// ══════════════════════════════════════════════════════════════
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$out[] = "CHECK 11: รูปแบบ SPAR_CODE (ควรเป็น XXX + ตัวเลข 16 หลัก)";
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$sparPrefixes = [];
$sparBadFormat = [];
foreach ($records as $r) {
    $code = $r['SPAR_CODE'] ?? '';
    if ($code === '') continue;
    $prefix = substr($code, 0, 3);
    $sparPrefixes[$prefix] = ($sparPrefixes[$prefix] ?? 0) + 1;
    if (!preg_match('/^[A-Z]{3}\d{13}$/', $code)) {
        $sparBadFormat[] = ['row' => $r['_ROW'], 'spar' => $code];
        addIssue($issuesByType, $issueCount, 'SPAR_CODE_FORMAT', "Row {$r['_ROW']}: $code");
    }
}
$out[] = "Prefix distribution: ";
foreach ($sparPrefixes as $p => $c) $out[] = "  $p: $c records";
$out[] = "ผิดรูปแบบ: " . count($sparBadFormat);
if (!empty($sparBadFormat)) {
    foreach (array_slice($sparBadFormat, 0, 10) as $b) {
        $out[] = "  Row {$b['row']}: {$b['spar']}";
    }
}
$out[] = "";

// ══════════════════════════════════════════════════════════════
// CHECK 12: HOME fields — ที่อยู่ตามทะเบียนบ้าน
// ══════════════════════════════════════════════════════════════
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$out[] = "CHECK 12: ที่อยู่ตามทะเบียนบ้าน (HOME_*)";
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$homeFields = ['HOME_NO', 'HOME_BAN', 'HOME_MOO', 'HOME_TAM', 'HOME_AMP', 'HOME_PROV'];
$homeLabels = ['บ้านเลขที่', 'บ้าน/ชุมชน', 'หมู่ที่', 'ตำบล', 'อำเภอ', 'จังหวัด'];
foreach ($homeFields as $i => $field) {
    $empty = 0;
    foreach ($records as $r) {
        if (($r[$field] ?? '') === '') $empty++;
    }
    $status = $empty === 0 ? 'OK' : "ว่าง $empty records";
    $out[] = str_pad($field, 15) . str_pad($homeLabels[$i], 20) . $status;
}
$out[] = "";

// ══════════════════════════════════════════════════════════════
// CHECK 13: NAME_TITLE consistency
// ══════════════════════════════════════════════════════════════
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$out[] = "CHECK 13: คำนำหน้า (NAME_TITLE) — ค่าทั้งหมด";
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$titleValues = [];
foreach ($records as $r) {
    $val = $r['NAME_TITLE'] ?? '';
    $titleValues[$val] = ($titleValues[$val] ?? 0) + 1;
}
arsort($titleValues);
foreach ($titleValues as $val => $cnt) {
    $display = $val === '' ? '(ว่าง)' : $val;
    $out[] = str_pad($display, 25) . $cnt . " records";
}
$out[] = "";

// ══════════════════════════════════════════════════════════════
// CHECK 14: PTYPE (ประเภทที่ดิน) values
// ══════════════════════════════════════════════════════════════
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
$out[] = "CHECK 14: PTYPE (ประเภทที่ดิน) — ค่าทั้งหมด";
$out[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

$ptypeValues = [];
foreach ($records as $r) {
    $val = $r['PTYPE'] ?? '';
    $ptypeValues[$val] = ($ptypeValues[$val] ?? 0) + 1;
}
arsort($ptypeValues);
foreach ($ptypeValues as $val => $cnt) {
    $display = $val === '' ? '(ว่าง)' : $val;
    $out[] = str_pad($display, 45) . $cnt . " records";
}
$out[] = "";

// ══════════════════════════════════════════════════════════════
// SUMMARY
// ══════════════════════════════════════════════════════════════
$out[] = "╔══════════════════════════════════════════════════════════════╗";
$out[] = "║  SUMMARY — สรุปสิ่งที่ต้องตรวจสอบ/แก้ไข                    ║";
$out[] = "╚══════════════════════════════════════════════════════════════╝";
$out[] = "";

$summaryItems = [
    ['label' => '1. SPAR_CODE ซ้ำ', 'type' => 'SPAR_CODE_DUP', 'action' => 'ลบ record ซ้ำออก (เหลือ 1 ต่อ 1 SPAR_CODE) — ระบุว่าเก็บ record ไหน'],
    ['label' => '2. IDCARD ว่าง', 'type' => 'EMPTY_IDCARD', 'action' => 'เพิ่มเลขบัตรประชาชน 13 หลัก'],
    ['label' => '3. IDCARD ผิดรูปแบบ', 'type' => 'IDCARD_FORMAT', 'action' => 'แก้ให้เป็น 13 หลัก ไม่มีขีด'],
    ['label' => '4. NAME ว่าง', 'type' => 'EMPTY_NAME', 'action' => 'เพิ่มชื่อ'],
    ['label' => '5. SURNAME ว่าง', 'type' => 'EMPTY_SURNAME', 'action' => 'เพิ่มนามสกุล'],
    ['label' => '6. NAME_TITLE ว่าง', 'type' => 'EMPTY_NAME_TITLE', 'action' => 'เพิ่มคำนำหน้า'],
    ['label' => '7. NUM_APAR ผิดรูปแบบ', 'type' => 'NUM_APAR_FORMAT', 'action' => 'แก้ให้เป็น [1-4]XXXX (5 หลัก)'],
    ['label' => '8. NGAN ผิดค่า', 'type' => 'NGAN_INVALID', 'action' => 'แก้ให้เป็น 0-3 จำนวนเต็ม'],
    ['label' => '9. WA_SQ เกินช่วง', 'type' => 'WA_SQ_RANGE', 'action' => 'ค่าต้อง 0-99.xx (ถ้าเกิน 100 ให้แปลงเป็น NGAN/RAI)'],
    ['label' => '10. SPAR_CODE ผิดรูปแบบ', 'type' => 'SPAR_CODE_FORMAT', 'action' => 'แก้ให้เป็น XXX + 13 หลัก'],
    ['label' => '11. PAR_BAN ว่าง', 'type' => 'EMPTY_PAR_BAN', 'action' => 'เพิ่มชื่อหมู่บ้าน'],
    ['label' => '12. PAR_MOO ว่าง', 'type' => 'EMPTY_PAR_MOO', 'action' => 'เพิ่มหมู่ที่'],
    ['label' => '13. REMARK ว่าง', 'type' => 'EMPTY_REMARK', 'action' => 'ระบุ ล่อแหลม/ไม่ล่อแหลม'],
    ['label' => '14. AREA_RAI ผิด', 'type' => 'AREA_RAI_BAD', 'action' => 'AREA_RAI ต้องเป็นจำนวนเต็ม >= 0'],
];

$actionRequired = 0;
foreach ($summaryItems as $si) {
    $cnt = count($issuesByType[$si['type']] ?? []);
    if ($cnt > 0) {
        $actionRequired++;
        $out[] = "❌ {$si['label']}: $cnt รายการ";
        $out[] = "   → แนวทาง: {$si['action']}";
        $out[] = "";
    } else {
        $out[] = "✅ {$si['label']}: ผ่าน";
    }
}

$out[] = "";
$out[] = "════════════════════════════════════════════════════";
$out[] = "รายการที่ต้องแก้ไข: $actionRequired หัวข้อ (จากทั้งหมด " . count($summaryItems) . ")";
$out[] = "Issues ทั้งหมด: $issueCount รายการ";
$out[] = "════════════════════════════════════════════════════";

// Save to file
$report = implode("\n", $out);
$reportPath = __DIR__ . '/shp_audit_report.txt';
file_put_contents($reportPath, $report);
echo $report;
echo "\n\nSaved to: $reportPath\n";
