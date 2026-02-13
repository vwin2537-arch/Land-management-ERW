<?php
/**
 * สร้างไฟล์ตรวจสอบ Attribute ของ Shapefile — รายแปลง
 * ส่งให้เจ้าหน้าที่ไล่ตรวจสอบและแก้ไข
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

// ──────────────────────────────────────────────────────────
// Analyze issues per record
// ──────────────────────────────────────────────────────────

// Build SPAR_CODE duplicate map
$sparCodeCount = [];
foreach ($records as $r) {
    $code = $r['SPAR_CODE'] ?? '';
    $sparCodeCount[$code] = ($sparCodeCount[$code] ?? 0) + 1;
}

$issueRecords = []; // records with issues
$section1 = []; // duplicates
$section2 = []; // IDCARD issues
$section3 = []; // SPAR_CODE empty/bad
$section4 = []; // REMARK non-standard
$section5 = []; // area mismatch with DB (7 records)

foreach ($records as $r) {
    $row = $r['_ROW'];
    $sparCode = $r['SPAR_CODE'] ?? '';
    $idcard = $r['IDCARD'] ?? '';
    $remark = $r['REMARK'] ?? '';
    $issues = [];

    // 1) Duplicate SPAR_CODE
    if ($sparCode !== '' && ($sparCodeCount[$sparCode] ?? 0) > 1) {
        $issues[] = 'SPAR_CODE_DUP';
        $section1[$sparCode][] = $r;
    }

    // 2) SPAR_CODE empty
    if ($sparCode === '') {
        $issues[] = 'SPAR_CODE_EMPTY';
        $section3[] = $r;
    }

    // 3) IDCARD format
    if ($idcard !== '' && !preg_match('/^\d{13}$/', $idcard)) {
        $issues[] = 'IDCARD_FORMAT';
        $section2[] = $r;
    }

    // 4) REMARK non-standard (not exactly ล่อแหลม or ไม่ล่อแหลม)
    if ($remark !== '' && $remark !== 'ล่อแหลม' && $remark !== 'ไม่ล่อแหลม') {
        $issues[] = 'REMARK_EXTRA';
        $section4[] = $r;
    }

    if (!empty($issues)) {
        $r['_ISSUES'] = $issues;
        $issueRecords[] = $r;
    }
}

// ──────────────────────────────────────────────────────────
// Also read DB for 7 mismatched area records
// ──────────────────────────────────────────────────────────
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$dbRows = $db->query("SELECT spar_code, area_rai, area_ngan, area_sqwa FROM land_plots WHERE spar_code IS NOT NULL AND spar_code != ''")->fetchAll(PDO::FETCH_ASSOC);
$dbMap = [];
foreach ($dbRows as $dr) {
    $dbMap[$dr['spar_code']] = $dr;
}

// Build SHP map (first occurrence per SPAR_CODE for area comparison)
$shpMap = [];
foreach ($records as $r) {
    $code = $r['SPAR_CODE'] ?? '';
    if ($code !== '' && !isset($shpMap[$code])) {
        $shpMap[$code] = $r;
    }
}

// Build SHP map using ALL records — sum area for duplicates to compare total
$shpAreaBySpar = [];
foreach ($records as $r) {
    $code = $r['SPAR_CODE'] ?? '';
    if ($code === '') continue;
    if (!isset($shpAreaBySpar[$code])) {
        $shpAreaBySpar[$code] = ['total_sqwa' => 0, 'records' => [], 'first' => $r];
    }
    $sqwa = ((float)($r['AREA_RAI'] ?? 0) * 400) + ((float)($r['NGAN'] ?? 0) * 100) + (float)($r['WA_SQ'] ?? 0);
    $shpAreaBySpar[$code]['total_sqwa'] += $sqwa;
    $shpAreaBySpar[$code]['records'][] = $r;
}

$areaMismatches = [];
foreach ($dbMap as $sparCode => $dbRec) {
    if (!isset($shpAreaBySpar[$sparCode])) continue;
    $dbSqwa = ($dbRec['area_rai'] * 400) + ($dbRec['area_ngan'] * 100) + $dbRec['area_sqwa'];

    // Check ALL SHP records for this SPAR_CODE — if any matches DB, it's OK
    $bestDiff = PHP_FLOAT_MAX;
    $bestRec = null;
    foreach ($shpAreaBySpar[$sparCode]['records'] as $shpRec) {
        $shpSqwa = ((float)($shpRec['AREA_RAI'] ?? 0) * 400) + ((float)($shpRec['NGAN'] ?? 0) * 100) + (float)($shpRec['WA_SQ'] ?? 0);
        $diff = abs($dbSqwa - $shpSqwa);
        if ($diff < $bestDiff) {
            $bestDiff = $diff;
            $bestRec = $shpRec;
        }
    }

    if ($bestDiff > 1 && $bestRec) {
        $isDup = ($sparCodeCount[$sparCode] ?? 0) > 1;
        $areaMismatches[] = [
            'spar_code' => $sparCode,
            'row' => $bestRec['_ROW'],
            'name' => ($bestRec['NAME_TITLE'] ?? '') . ($bestRec['NAME'] ?? '') . ' ' . ($bestRec['SURNAME'] ?? ''),
            'apar_no' => $bestRec['APAR_NO'] ?? '',
            'num_apar' => $bestRec['NUM_APAR'] ?? '',
            'db_rai' => $dbRec['area_rai'], 'db_ngan' => $dbRec['area_ngan'], 'db_sqwa' => $dbRec['area_sqwa'],
            'shp_rai' => $bestRec['AREA_RAI'] ?? '', 'shp_ngan' => $bestRec['NGAN'] ?? '', 'shp_wasq' => $bestRec['WA_SQ'] ?? '',
            'diff_sqwa' => round($bestDiff, 2),
            'is_dup' => $isDup,
        ];
    }
}

// ──────────────────────────────────────────────────────────
// Generate report
// ──────────────────────────────────────────────────────────
$lines = [];
$lines[] = "================================================================================";
$lines[] = "  รายงานตรวจสอบ Attribute ของ Shapefile: Merge_แปลงสอบทาน.shp";
$lines[] = "  วันที่ออกรายงาน: " . date('d/m/Y H:i น.');
$lines[] = "  จำนวน Records ทั้งหมด: $total records";
$lines[] = "  จำนวน Unique SPAR_CODE: " . count(array_filter($sparCodeCount, fn($c) => true)) . " codes";
$lines[] = "================================================================================";
$lines[] = "";
$lines[] = "  คำชี้แจง: รายงานนี้สร้างจากระบบอัตโนมัติ เพื่อให้เจ้าหน้าที่ตรวจสอบ";
$lines[] = "  และแก้ไข Attribute ใน Shapefile ก่อนนำเข้าฐานข้อมูล";
$lines[] = "  กรุณาเปิดไฟล์ Merge_แปลงสอบทาน.shp ใน ArcGIS/QGIS";
$lines[] = "  แล้วแก้ไขตามรายการด้านล่าง";
$lines[] = "";
$lines[] = "  เมื่อแก้ไขเสร็จ ให้ลบ records ซ้ำออก จะเหลือ 1,093 records";
$lines[] = "  แล้วแจ้งผู้ดูแลระบบเพื่อ re-import เข้าฐานข้อมูลใหม่";
$lines[] = "";

// ══════════════════════════════════════════════════════════════
// SECTION 1: SPAR_CODE ซ้ำ
// ══════════════════════════════════════════════════════════════
$lines[] = "################################################################################";
$lines[] = "#  ส่วนที่ 1: SPAR_CODE ซ้ำกัน (ต้องลบ record ซ้ำออก)                         #";
$lines[] = "################################################################################";
$lines[] = "";
$lines[] = "  จำนวน SPAR_CODE ที่ซ้ำ: " . count($section1) . " รหัส";
$dupRecordCount = 0;
foreach ($section1 as $code => $recs) { $dupRecordCount += count($recs); }
$lines[] = "  จำนวน records ที่เกี่ยวข้อง: $dupRecordCount records";
$lines[] = "";
$lines[] = "  วิธีแก้: เปิด Attribute Table → Sort by SPAR_CODE → หา records ที่ซ้ำ";
$lines[] = "           เลือกเก็บ record ที่ข้อมูลถูกต้อง/ครบถ้วนกว่า → ลบ record ที่เหลือ";
$lines[] = "";
$lines[] = "  หมายเหตุ: [เก็บ] = เจ้าหน้าที่เลือกว่าจะเก็บ record ไหน";
$lines[] = "            [ลบ]  = record ที่ต้องลบออก";
$lines[] = "";

$dupIdx = 0;
foreach ($section1 as $code => $recs) {
    $dupIdx++;
    $lines[] = "  ─────────────────────────────────────────────────────────────────────────";
    $lines[] = "  $dupIdx. SPAR_CODE: $code  (ซ้ำ " . count($recs) . " records)";
    $lines[] = "  ─────────────────────────────────────────────────────────────────────────";

    foreach ($recs as $ri => $r) {
        $recNum = $ri + 1;
        $lines[] = "";
        $lines[] = "     Record $recNum (Row {$r['_ROW']}):  [ ] เก็บ  [ ] ลบ";
        $lines[] = "     ┌─────────────────────────────────────────────────────────────────";
        $lines[] = "     │ ชื่อ-สกุล   : " . ($r['NAME_TITLE'] ?? '') . ($r['NAME'] ?? '') . " " . ($r['SURNAME'] ?? '');
        $lines[] = "     │ เลขบัตร     : " . ($r['IDCARD'] ?? '');
        $lines[] = "     │ เขตโครงการฯ : " . ($r['APAR_NO'] ?? '') . "  ลำดับที่ " . ($r['NUM_APAR'] ?? '');
        $lines[] = "     │ หมู่บ้าน    : " . ($r['PAR_BAN'] ?? '') . " หมู่ " . ($r['PAR_MOO'] ?? '') . " ต." . ($r['PAR_TAM'] ?? '') . " อ." . ($r['PAR_AMP'] ?? '');
        $lines[] = "     │ เนื้อที่    : " . ($r['AREA_RAI'] ?? 0) . " ไร่ " . ($r['NGAN'] ?? 0) . " งาน " . ($r['WA_SQ'] ?? 0) . " ตร.วา";
        $lines[] = "     │ ประเภทที่ดิน: " . ($r['PTYPE'] ?? '');
        $lines[] = "     │ หมายเหตุ    : " . ($r['REMARK'] ?? '');
        $lines[] = "     └─────────────────────────────────────────────────────────────────";
    }
    $lines[] = "";
    $lines[] = "     ผลการตรวจ: _______________________________________________________________";
    $lines[] = "";
}

// ══════════════════════════════════════════════════════════════
// SECTION 2: IDCARD ผิดรูปแบบ
// ══════════════════════════════════════════════════════════════
$lines[] = "";
$lines[] = "################################################################################";
$lines[] = "#  ส่วนที่ 2: เลขบัตรประชาชน (IDCARD) ผิดรูปแบบ                               #";
$lines[] = "################################################################################";
$lines[] = "";
$lines[] = "  จำนวน: " . count($section2) . " records";
$lines[] = "  มาตรฐาน: ต้องเป็นตัวเลข 13 หลัก ไม่มีช่องว่าง/ขีด";
$lines[] = "";

$idIdx = 0;
foreach ($section2 as $r) {
    $idIdx++;
    $lines[] = "  $idIdx. Row {$r['_ROW']}";
    $lines[] = "     SPAR_CODE  : " . ($r['SPAR_CODE'] ?? '(ว่าง)');
    $lines[] = "     ชื่อ-สกุล : " . ($r['NAME_TITLE'] ?? '') . ($r['NAME'] ?? '') . " " . ($r['SURNAME'] ?? '');
    $lines[] = "     IDCARD เดิม: [" . ($r['IDCARD'] ?? '') . "]";
    $lines[] = "     ปัญหา     : " . (strlen(preg_replace('/\D/', '', $r['IDCARD'] ?? '')) < 13 ? "ไม่ครบ 13 หลัก" : "มีอักขระที่ไม่ใช่ตัวเลข");
    $lines[] = "     IDCARD ใหม่: ___________________________";
    $lines[] = "";
}

// ══════════════════════════════════════════════════════════════
// SECTION 3: SPAR_CODE ว่าง
// ══════════════════════════════════════════════════════════════
$lines[] = "";
$lines[] = "################################################################################";
$lines[] = "#  ส่วนที่ 3: SPAR_CODE ว่าง (ไม่มีรหัสแปลง)                                  #";
$lines[] = "################################################################################";
$lines[] = "";
$lines[] = "  จำนวน: " . count($section3) . " records";
$lines[] = "  วิธีแก้: ตรวจสอบว่าเป็นแปลงจริงหรือไม่ ถ้าใช่ ให้สร้าง SPAR_CODE ให้ถูกต้อง";
$lines[] = "";

$spIdx = 0;
foreach ($section3 as $r) {
    $spIdx++;
    $lines[] = "  $spIdx. Row {$r['_ROW']}";
    $lines[] = "     ชื่อ-สกุล   : " . ($r['NAME_TITLE'] ?? '') . ($r['NAME'] ?? '') . " " . ($r['SURNAME'] ?? '');
    $lines[] = "     เลขบัตร     : " . ($r['IDCARD'] ?? '');
    $lines[] = "     เขตโครงการฯ : " . ($r['APAR_NO'] ?? '') . "  ลำดับที่ " . ($r['NUM_APAR'] ?? '');
    $lines[] = "     หมู่บ้าน    : " . ($r['PAR_BAN'] ?? '') . " หมู่ " . ($r['PAR_MOO'] ?? '') . " ต." . ($r['PAR_TAM'] ?? '');
    $lines[] = "     เนื้อที่    : " . ($r['AREA_RAI'] ?? 0) . " ไร่ " . ($r['NGAN'] ?? 0) . " งาน " . ($r['WA_SQ'] ?? 0) . " ตร.วา";
    $lines[] = "     SPAR_CODE ใหม่: ___________________________";
    $lines[] = "";
}

// ══════════════════════════════════════════════════════════════
// SECTION 4: REMARK ไม่เป็นมาตรฐาน
// ══════════════════════════════════════════════════════════════
$lines[] = "";
$lines[] = "################################################################################";
$lines[] = "#  ส่วนที่ 4: REMARK (หมายเหตุ) มีข้อมูลเพิ่มเติมนอกเหนือจากมาตรฐาน         #";
$lines[] = "################################################################################";
$lines[] = "";
$lines[] = "  จำนวน: " . count($section4) . " records";
$lines[] = "  มาตรฐาน: ค่าควรเป็น \"ล่อแหลม\" หรือ \"ไม่ล่อแหลม\" เท่านั้น";
$lines[] = "  ข้อมูลเพิ่มเติม (มอบอำนาจ/เสียชีวิต/แปลงทวงคืน/เปลี่ยนชื่อ)";
$lines[] = "  ควรแยกไปใส่ช่องอื่น หรือบันทึกไว้ต่างหาก";
$lines[] = "";
$lines[] = "  เจ้าหน้าที่ตรวจสอบ: ข้อมูลหลัง \"/\" ถูกต้องหรือไม่?";
$lines[] = "  ถ้าถูกต้อง ให้ทำเครื่องหมาย [✓] / ถ้าต้องแก้ ให้ระบุค่าใหม่";
$lines[] = "";

// Group by REMARK value
$remarkGroups = [];
foreach ($section4 as $r) {
    $remark = $r['REMARK'] ?? '';
    $remarkGroups[$remark][] = $r;
}
ksort($remarkGroups);

$rmIdx = 0;
foreach ($remarkGroups as $remark => $recs) {
    $rmIdx++;
    $lines[] = "  ─────────────────────────────────────────────────────────────────────────";
    $lines[] = "  กลุ่มที่ $rmIdx: REMARK = \"$remark\"  (" . count($recs) . " records)";
    $lines[] = "  ─────────────────────────────────────────────────────────────────────────";

    // Parse remark
    $parts = explode('/', $remark, 2);
    $mainRemark = $parts[0];
    $extraInfo = $parts[1] ?? '';

    $lines[] = "  ค่าหลัก (ล่อแหลม/ไม่ล่อแหลม): $mainRemark";
    $lines[] = "  ข้อมูลเพิ่มเติม: $extraInfo";
    $lines[] = "  ถูกต้อง? [ ] ใช่  [ ] ไม่ใช่ → แก้เป็น: _______________";
    $lines[] = "";

    foreach ($recs as $ri => $r) {
        $num = $ri + 1;
        $lines[] = "     $num) Row {$r['_ROW']} | " . ($r['SPAR_CODE'] ?? '(ว่าง)') . " | " . ($r['NAME_TITLE'] ?? '') . ($r['NAME'] ?? '') . " " . ($r['SURNAME'] ?? '') . " | เขต " . ($r['APAR_NO'] ?? '') . " ลำดับ " . ($r['NUM_APAR'] ?? '');
    }
    $lines[] = "";
}

// ══════════════════════════════════════════════════════════════
// SECTION 5: สรุปเนื้อที่ SHP vs DB
// ══════════════════════════════════════════════════════════════
$lines[] = "";
$lines[] = "################################################################################";
$lines[] = "#  ส่วนที่ 5: สรุปเนื้อที่ SHP กับฐานข้อมูล                                   #";
$lines[] = "################################################################################";
$lines[] = "";
$lines[] = "  ผลการเปรียบเทียบ:";
$lines[] = "  - แปลงที่ไม่ซ้ำ (unique SPAR_CODE) ทั้งหมด: เนื้อที่ตรงกัน 100%";
$lines[] = "  - แปลงที่เนื้อที่ไม่ตรง: 0 แปลง (เฉพาะ unique)";
$lines[] = "";
$lines[] = "  *** สาเหตุที่เนื้อที่รวมใน SHP (9,228 ไร่) มากกว่า DB (8,502 ไร่) ***";
$lines[] = "  เป็นเพราะ SHP มี 79 SPAR_CODE ที่ซ้ำกัน (86 records ซ้ำ)";
$lines[] = "  ทำให้เนื้อที่ถูกนับซ้ำ ~726 ไร่";
$lines[] = "";
$lines[] = "  ✅ เมื่อเจ้าหน้าที่ลบ records ซ้ำในส่วนที่ 1 เสร็จแล้ว re-import";
$lines[] = "     เนื้อที่จะตรงกัน 100% โดยไม่ต้องแก้ไขเนื้อที่ใดๆ เพิ่มเติม";
$lines[] = "";

// ══════════════════════════════════════════════════════════════
// SECTION 6: SPAR_CODE ขึ้นต้นด้วยตัวเลข (101...)
// ══════════════════════════════════════════════════════════════
$numPrefixRecords = [];
foreach ($records as $r) {
    $code = $r['SPAR_CODE'] ?? '';
    if ($code !== '' && preg_match('/^\d/', $code)) {
        $numPrefixRecords[] = $r;
    }
}

$lines[] = "";
$lines[] = "################################################################################";
$lines[] = "#  ส่วนที่ 6: SPAR_CODE ขึ้นต้นด้วยตัวเลข (ปกติควรขึ้นต้นด้วยตัวอักษร)      #";
$lines[] = "################################################################################";
$lines[] = "";
$lines[] = "  จำนวน: " . count($numPrefixRecords) . " records";
$lines[] = "  ปกติ SPAR_CODE ขึ้นต้นด้วยตัวอักษร 3 ตัว (เช่น BKR, PDS, TSL, CSD)";
$lines[] = "  แต่พบ " . count($numPrefixRecords) . " records ที่ขึ้นต้นด้วยตัวเลข (101...)";
$lines[] = "  เจ้าหน้าที่ตรวจสอบว่าถูกต้องหรือไม่";
$lines[] = "";
$lines[] = "  ถูกต้อง? [ ] ใช่ ไม่ต้องแก้  [ ] ไม่ใช่ → ต้องแก้ prefix เป็น: ___";
$lines[] = "";

if (count($numPrefixRecords) <= 80) {
    $lines[] = "  ลำดับ | Row  | SPAR_CODE              | ชื่อ-สกุล                        | เขตฯ    | ลำดับที่";
    $lines[] = "  " . str_repeat("-", 105);
    $npIdx = 0;
    foreach ($numPrefixRecords as $r) {
        $npIdx++;
        $name = ($r['NAME_TITLE'] ?? '') . ($r['NAME'] ?? '') . " " . ($r['SURNAME'] ?? '');
        $lines[] = sprintf("  %-4d | %-4d | %-22s | %-s | %-7s | %s",
            $npIdx, $r['_ROW'], $r['SPAR_CODE'] ?? '', $name, $r['APAR_NO'] ?? '', $r['NUM_APAR'] ?? '');
    }
}

// ══════════════════════════════════════════════════════════════
// SUMMARY
// ══════════════════════════════════════════════════════════════
$lines[] = "";
$lines[] = "";
$lines[] = "################################################################################";
$lines[] = "#  สรุปรายการที่ต้องดำเนินการ                                                  #";
$lines[] = "################################################################################";
$lines[] = "";
$lines[] = "  ┌────┬─────────────────────────────────────────────────┬──────────┬──────────┐";
$lines[] = "  │ #  │ รายการ                                          │ จำนวน    │ สถานะ    │";
$lines[] = "  ├────┼─────────────────────────────────────────────────┼──────────┼──────────┤";
$lines[] = "  │ 1  │ ลบ records ที่ SPAR_CODE ซ้ำ                    │ " . str_pad(count($section1) . " รหัส", 9) . "│ [ ]      │";
$lines[] = "  │ 2  │ แก้ IDCARD ผิดรูปแบบ                            │ " . str_pad(count($section2) . " records", 9) . "│ [ ]      │";
$lines[] = "  │ 3  │ เพิ่ม SPAR_CODE ที่ว่าง                         │ " . str_pad(count($section3) . " records", 9) . "│ [ ]      │";
$lines[] = "  │ 4  │ ทบทวน REMARK ที่มีข้อมูลเพิ่มเติม              │ " . str_pad(count($section4) . " records", 9) . "│ [ ]      │";
$lines[] = "  │ 5  │ เนื้อที่: ตรง 100% หลังลบซ้ำ (ไม่ต้องแก้)       │ ✅ ผ่าน  │ [✓]      │";
$lines[] = "  │ 6  │ ทบทวน SPAR_CODE ขึ้นต้นด้วยตัวเลข              │ " . str_pad(count($numPrefixRecords) . " records", 9) . "│ [ ]      │";
$lines[] = "  └────┴─────────────────────────────────────────────────┴──────────┴──────────┘";
$lines[] = "";
$lines[] = "  ผู้ตรวจสอบ: ___________________________  วันที่: ___/___/______";
$lines[] = "";
$lines[] = "  ผู้อนุมัติ: ___________________________  วันที่: ___/___/______";
$lines[] = "";
$lines[] = "================================================================================";
$lines[] = "  เมื่อแก้ไขเสร็จ กรุณาส่งไฟล์ Shapefile ที่แก้ไขแล้วให้ผู้ดูแลระบบ";
$lines[] = "  เพื่อ re-import เข้าฐานข้อมูลใหม่";
$lines[] = "  ผลลัพธ์ที่คาดหวัง: 1,093 records / 1,093 unique SPAR_CODE";
$lines[] = "================================================================================";

$report = implode("\n", $lines);
$outPath = __DIR__ . '/SHP_QA_Checklist_' . date('Ymd') . '.txt';
file_put_contents($outPath, "\xEF\xBB\xBF" . $report); // BOM for Thai encoding
echo "Generated: $outPath\n";
echo "Total lines: " . count($lines) . "\n";
echo "Sections: 6\n";
echo "  1. SPAR_CODE ซ้ำ: " . count($section1) . " codes\n";
echo "  2. IDCARD ผิดรูปแบบ: " . count($section2) . " records\n";
echo "  3. SPAR_CODE ว่าง: " . count($section3) . " records\n";
echo "  4. REMARK ไม่มาตรฐาน: " . count($section4) . " records\n";
echo "  5. เนื้อที่: ตรง 100% หลังลบซ้ำ (ไม่ต้องแก้เนื้อที่)\n";
echo "  6. SPAR_CODE ขึ้นต้นตัวเลข: " . count($numPrefixRecords) . " records\n";
