<?php
/**
 * Shapefile Data Validator — ตรวจสอบข้อมูลจาก .dbf ก่อนนำเข้า
 * ตรวจ: บัตรประชาชน, ชื่อ-สกุล, ข้อมูลแปลง, ซ้ำซ้อน, ที่อยู่
 * ผลลัพธ์บันทึกเป็น HTML เปิดดูได้สะดวก
 */

$dbfPath = __DIR__ . '/../ตรวจสอบคุณสมบัติ/Merge_แปลงสอบทาน.dbf';
$reportPath = __DIR__ . '/validation_report.html';

if (!file_exists($dbfPath)) { die("ไม่พบไฟล์: $dbfPath\n"); }

// ============================================================
// 1. Read all records from DBF
// ============================================================
function readAllDbf(string $path): array {
    $fh = fopen($path, 'rb');
    $headerData = fread($fh, 32);
    $header = unpack('Cversion/CyearMod/CmonthMod/CdayMod/VnumRecords/vheaderSize/vrecordSize', $headerData);

    // Read fields
    $fields = [];
    while (true) {
        $fd = fread($fh, 32);
        if (!$fd || strlen($fd) < 32 || ord($fd[0]) === 0x0D) break;
        $f = unpack('A11name/Atype/x4/Clength/Cdecimal', $fd);
        $f['name'] = trim($f['name']);
        $fields[] = $f;
    }

    // Read records
    fseek($fh, $header['headerSize']);
    $records = [];
    for ($i = 0; $i < $header['numRecords']; $i++) {
        fread($fh, 1); // deletion flag
        $row = [];
        foreach ($fields as $f) {
            $row[$f['name']] = trim(fread($fh, $f['length']));
        }
        $row['_ROW'] = $i + 1;
        $records[] = $row;
    }
    fclose($fh);
    return $records;
}

// ============================================================
// 2. Validation Functions
// ============================================================

/** ตรวจ checksum บัตรประชาชนไทย 13 หลัก */
function validateThaiId(string $id): array {
    $issues = [];
    
    if (empty($id)) {
        return [['type' => 'error', 'msg' => 'เลขบัตรว่าง']];
    }
    
    // ตรวจความยาว
    if (strlen($id) !== 13) {
        $issues[] = ['type' => 'error', 'msg' => "เลขบัตร $id ไม่ครบ 13 หลัก (" . strlen($id) . " หลัก)"];
        return $issues;
    }
    
    // ตรวจเป็นตัวเลขทั้งหมด
    if (!ctype_digit($id)) {
        $issues[] = ['type' => 'error', 'msg' => "เลขบัตร $id มีตัวอักษรปน"];
        return $issues;
    }
    
    // ตรวจขึ้นต้นด้วย 0
    if ($id[0] === '0') {
        $issues[] = ['type' => 'warning', 'msg' => "เลขบัตร $id ขึ้นต้นด้วย 0 (ผิดปกติ)"];
    }
    
    // Checksum: sum(d[i] * (13-i)) for i=0..11, check = (11 - sum%11) % 10
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$id[$i] * (13 - $i);
    }
    $check = (11 - ($sum % 11)) % 10;
    
    if ($check !== (int)$id[12]) {
        $issues[] = ['type' => 'error', 'msg' => "เลขบัตร $id checksum ไม่ถูกต้อง (คาดว่าหลักสุดท้ายควรเป็น $check)"];
    }
    
    return $issues;
}

/** ตรวจชื่อ-สกุล */
function validateName(string $title, string $name, string $surname): array {
    $issues = [];
    $validTitles = ['นาย', 'นาง', 'นางสาว', 'เด็กชาย', 'เด็กหญิง', 'ด.ช.', 'ด.ญ.'];
    
    if (empty($name)) {
        $issues[] = ['type' => 'error', 'msg' => 'ไม่มีชื่อ'];
    } elseif (mb_strlen($name) < 2) {
        $issues[] = ['type' => 'warning', 'msg' => "ชื่อ \"$name\" สั้นผิดปกติ"];
    }
    
    if (empty($surname)) {
        $issues[] = ['type' => 'error', 'msg' => 'ไม่มีนามสกุล'];
    } elseif (mb_strlen($surname) < 2) {
        $issues[] = ['type' => 'warning', 'msg' => "นามสกุล \"$surname\" สั้นผิดปกติ"];
    }
    
    // ตรวจตัวเลขในชื่อ
    if (preg_match('/[0-9]/', $name)) {
        $issues[] = ['type' => 'warning', 'msg' => "ชื่อ \"$name\" มีตัวเลขปน"];
    }
    if (preg_match('/[0-9]/', $surname)) {
        $issues[] = ['type' => 'warning', 'msg' => "นามสกุล \"$surname\" มีตัวเลขปน"];
    }
    
    // ตรวจคำนำหน้า
    if (!empty($title) && !in_array($title, $validTitles)) {
        $issues[] = ['type' => 'warning', 'msg' => "คำนำหน้า \"$title\" ไม่ตรงมาตรฐาน"];
    }
    
    return $issues;
}

/** ตรวจข้อมูลแปลง */
function validatePlot(array $row): array {
    $issues = [];
    
    $rai = (float)($row['RAI'] ?? 0);
    $ngan = (float)($row['NGAN'] ?? 0);
    $wa = (float)($row['WA_SQ'] ?? 0);
    
    if ($rai <= 0 && $ngan <= 0 && $wa <= 0) {
        $issues[] = ['type' => 'warning', 'msg' => 'พื้นที่เป็น 0 ทุกช่อง'];
    }
    if ($rai < 0 || $ngan < 0 || $wa < 0) {
        $issues[] = ['type' => 'error', 'msg' => "พื้นที่เป็นค่าลบ: ไร่=$rai งาน=$ngan วา=$wa"];
    }
    if ($rai > 100) {
        $issues[] = ['type' => 'warning', 'msg' => "พื้นที่ใหญ่ผิดปกติ: $rai ไร่"];
    }
    
    // ตรวจพิกัด UTM Zone 47N (E: ~100,000-900,000, N: ~500,000-2,200,000 สำหรับไทย)
    $e = (float)($row['E'] ?? 0);
    $n = (float)($row['N'] ?? 0);
    if ($e > 0 && $n > 0) {
        if ($e < 100000 || $e > 900000) {
            $issues[] = ['type' => 'error', 'msg' => "พิกัด E=$e อยู่นอกช่วง UTM Zone 47N ของไทย"];
        }
        if ($n < 500000 || $n > 2200000) {
            $issues[] = ['type' => 'error', 'msg' => "พิกัด N=$n อยู่นอกช่วง UTM Zone 47N ของไทย"];
        }
    } else {
        $issues[] = ['type' => 'warning', 'msg' => 'ไม่มีพิกัด E/N'];
    }
    
    // ตรวจ SPAR_CODE
    if (empty($row['SPAR_CODE'])) {
        $issues[] = ['type' => 'error', 'msg' => 'ไม่มี SPAR_CODE'];
    }
    
    // ตรวจ PERIMETER
    $peri = (float)($row['PERIMETER'] ?? 0);
    if ($peri <= 0) {
        $issues[] = ['type' => 'warning', 'msg' => 'PERIMETER เป็น 0 หรือไม่มี'];
    }
    
    return $issues;
}

/** ตรวจที่อยู่ */
function validateAddress(array $row): array {
    $issues = [];
    $required = [
        'PAR_BAN' => 'ชื่อบ้าน (แปลง)',
        'PAR_TAM' => 'ตำบล (แปลง)',
        'PAR_AMP' => 'อำเภอ (แปลง)',
        'PAR_PROV' => 'จังหวัด (แปลง)',
    ];
    foreach ($required as $field => $label) {
        if (empty($row[$field])) {
            $issues[] = ['type' => 'warning', 'msg' => "$label ($field) ว่างเปล่า"];
        }
    }
    return $issues;
}

// ============================================================
// 3. Run Validation
// ============================================================
echo "อ่านข้อมูล...\n";
$records = readAllDbf($dbfPath);
$total = count($records);
echo "พบ $total records กำลังตรวจ...\n";

$errors = [];    // ข้อผิดพลาดร้ายแรง
$warnings = [];  // น่าสงสัย
$idMap = [];     // เก็บ IDCARD => [rows] สำหรับตรวจซ้ำ
$sparMap = [];   // เก็บ SPAR_CODE => [rows] สำหรับตรวจซ้ำ
$passCount = 0;
$errorRecords = [];

foreach ($records as $row) {
    $rowNum = $row['_ROW'];
    $rowIssues = [];
    
    // Validate ID Card
    $idIssues = validateThaiId($row['IDCARD'] ?? '');
    $rowIssues = array_merge($rowIssues, $idIssues);
    
    // Validate Names
    $nameIssues = validateName($row['NAME_TITLE'] ?? '', $row['NAME'] ?? '', $row['SURNAME'] ?? '');
    $rowIssues = array_merge($rowIssues, $nameIssues);
    
    // Validate Plot
    $plotIssues = validatePlot($row);
    $rowIssues = array_merge($rowIssues, $plotIssues);
    
    // Validate Address
    $addrIssues = validateAddress($row);
    $rowIssues = array_merge($rowIssues, $addrIssues);
    
    // Track duplicates
    $id = $row['IDCARD'] ?? '';
    if (!empty($id)) {
        $idMap[$id][] = $rowNum;
    }
    $spar = $row['SPAR_CODE'] ?? '';
    if (!empty($spar)) {
        $sparMap[$spar][] = $rowNum;
    }
    
    // Categorize
    foreach ($rowIssues as $issue) {
        $issue['row'] = $rowNum;
        $issue['idcard'] = $row['IDCARD'] ?? '-';
        $issue['name'] = ($row['NAME_TITLE'] ?? '') . ($row['NAME'] ?? '') . ' ' . ($row['SURNAME'] ?? '');
        if ($issue['type'] === 'error') {
            $errors[] = $issue;
        } else {
            $warnings[] = $issue;
        }
    }
    
    if (empty($rowIssues)) {
        $passCount++;
    }
}

// Check duplicates - same IDCARD with different names
foreach ($idMap as $id => $rows) {
    if (count($rows) > 1) {
        // Check if all names match
        $names = [];
        foreach ($rows as $r) {
            $rec = $records[$r - 1];
            $names[] = ($rec['NAME'] ?? '') . ' ' . ($rec['SURNAME'] ?? '');
        }
        $uniqueNames = array_unique($names);
        if (count($uniqueNames) > 1) {
            $errors[] = [
                'type' => 'error',
                'row' => implode(',', $rows),
                'idcard' => $id,
                'name' => implode(' / ', $uniqueNames),
                'msg' => "เลขบัตร $id ซ้ำแต่ชื่อต่างกัน (" . count($rows) . " แปลง): " . implode(', ', $uniqueNames)
            ];
        }
        // else: same person with multiple plots — normal
    }
}

// Check duplicate SPAR_CODE
foreach ($sparMap as $spar => $rows) {
    if (count($rows) > 1) {
        $errors[] = [
            'type' => 'error',
            'row' => implode(',', $rows),
            'idcard' => '-',
            'name' => '-',
            'msg' => "SPAR_CODE \"$spar\" ซ้ำกันใน " . count($rows) . " records (แถว: " . implode(', ', $rows) . ")"
        ];
    }
}

// Count people with multiple plots (normal)
$multiPlotPeople = array_filter($idMap, fn($rows) => count($rows) > 1);

// ============================================================
// 4. Generate HTML Report
// ============================================================
$html = '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">
<title>รายงานตรวจสอบข้อมูล Shapefile</title>
<style>
body{font-family:"Segoe UI",Tahoma,sans-serif;max-width:1100px;margin:0 auto;padding:20px;background:#f5f7fa;color:#333}
h1{color:#1e40af;border-bottom:3px solid #3b82f6;padding-bottom:10px}
h2{color:#374151;margin-top:30px}
.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:20px 0}
.card{padding:20px;border-radius:12px;text-align:center;color:white;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.card h3{font-size:32px;margin:0}.card p{margin:5px 0 0;opacity:.9}
.green{background:linear-gradient(135deg,#059669,#10b981)}
.red{background:linear-gradient(135deg,#dc2626,#ef4444)}
.yellow{background:linear-gradient(135deg,#d97706,#f59e0b)}
.blue{background:linear-gradient(135deg,#2563eb,#3b82f6)}
table{width:100%;border-collapse:collapse;margin:10px 0;background:white;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1)}
th{background:#1e40af;color:white;padding:10px 12px;text-align:left;font-size:13px}
td{padding:8px 12px;border-bottom:1px solid #e5e7eb;font-size:13px}
tr:hover td{background:#f0f4ff}
.tag-error{background:#fef2f2;color:#991b1b;padding:2px 8px;border-radius:4px;font-size:12px}
.tag-warn{background:#fffbeb;color:#92400e;padding:2px 8px;border-radius:4px;font-size:12px}
.info{background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px;margin:10px 0}
.footer{margin-top:30px;padding:15px;background:#f9fafb;border-radius:8px;color:#6b7280;font-size:12px;text-align:center}
</style></head><body>';

$html .= '<h1>📋 รายงานตรวจสอบข้อมูล Shapefile</h1>';
$html .= '<p>ไฟล์: <code>Merge_แปลงสอบทาน.dbf</code> | ตรวจเมื่อ: ' . date('d/m/Y H:i:s') . '</p>';

// Summary cards
$html .= '<div class="summary">';
$html .= '<div class="card blue"><h3>' . number_format($total) . '</h3><p>แปลงทั้งหมด</p></div>';
$html .= '<div class="card green"><h3>' . number_format($passCount) . '</h3><p>ผ่านทุกข้อ ✅</p></div>';
$html .= '<div class="card red"><h3>' . count($errors) . '</h3><p>ข้อผิดพลาด 🔴</p></div>';
$html .= '<div class="card yellow"><h3>' . count($warnings) . '</h3><p>น่าสงสัย 🟡</p></div>';
$html .= '</div>';

// People with multiple plots
$html .= '<div class="info">👥 พบ <strong>' . count($multiPlotPeople) . '</strong> คนที่มีมากกว่า 1 แปลง (ปกติ) | ';
$uniqueIds = count($idMap);
$html .= 'จำนวนราษฎรไม่ซ้ำ: <strong>' . $uniqueIds . '</strong> คน</div>';

// Error table
if (!empty($errors)) {
    $html .= '<h2>🔴 ข้อผิดพลาดร้ายแรง (' . count($errors) . ' รายการ)</h2>';
    $html .= '<table><tr><th>แถว</th><th>เลขบัตร</th><th>ชื่อ</th><th>รายละเอียด</th></tr>';
    foreach ($errors as $e) {
        $html .= '<tr><td>' . $e['row'] . '</td><td><code>' . htmlspecialchars($e['idcard']) . '</code></td>';
        $html .= '<td>' . htmlspecialchars($e['name']) . '</td>';
        $html .= '<td><span class="tag-error">' . htmlspecialchars($e['msg']) . '</span></td></tr>';
    }
    $html .= '</table>';
}

// Warning table
if (!empty($warnings)) {
    $html .= '<h2>🟡 รายการน่าสงสัย (' . count($warnings) . ' รายการ)</h2>';
    $html .= '<table><tr><th>แถว</th><th>เลขบัตร</th><th>ชื่อ</th><th>รายละเอียด</th></tr>';
    foreach ($warnings as $w) {
        $html .= '<tr><td>' . $w['row'] . '</td><td><code>' . htmlspecialchars($w['idcard']) . '</code></td>';
        $html .= '<td>' . htmlspecialchars($w['name']) . '</td>';
        $html .= '<td><span class="tag-warn">' . htmlspecialchars($w['msg']) . '</span></td></tr>';
    }
    $html .= '</table>';
}

// Summary of unique values
$parks = array_unique(array_column($records, 'NAME_DNP'));
$provs = array_unique(array_column($records, 'PAR_PROV'));
$banTypes = array_count_values(array_map(fn($r) => $r['BAN_TYPE'] ?: '(ว่าง)', $records));

$html .= '<h2>📊 สรุปข้อมูลทั่วไป</h2>';
$html .= '<div class="info">';
$html .= '<strong>อุทยานฯ:</strong> ' . implode(', ', array_filter($parks)) . '<br>';
$html .= '<strong>จังหวัด (แปลง):</strong> ' . implode(', ', array_filter($provs)) . '<br>';
$html .= '<strong>BAN_TYPE:</strong> ';
foreach ($banTypes as $bt => $cnt) {
    $label = match($bt) {
        '1' => 'ในเขต', '2' => 'นอกเขตทำกินใน', '3' => 'คาบเกี่ยว',
        default => $bt
    };
    $html .= "$label=$cnt, ";
}
$html .= '</div>';

$html .= '<div class="footer">สร้างโดย validate_shapefile.php | ระบบจัดการที่ดินทำกิน v2</div>';
$html .= '</body></html>';

file_put_contents($reportPath, $html);

echo "\n=============================\n";
echo "✅ ตรวจเสร็จ $total records\n";
echo "   ผ่าน: $passCount\n";
echo "   ข้อผิดพลาด: " . count($errors) . "\n";
echo "   น่าสงสัย: " . count($warnings) . "\n";
echo "   ราษฎรที่ไม่ซ้ำ: $uniqueIds คน\n";
echo "   คนมีหลายแปลง: " . count($multiPlotPeople) . " คน\n";
echo "=============================\n";
echo "📄 รายงาน: $reportPath\n";
