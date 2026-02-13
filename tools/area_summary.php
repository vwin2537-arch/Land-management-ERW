<?php
/**
 * สรุปเนื้อที่และจำนวนแปลง — DB vs SHP
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

// ──────────────────────────────────────────────────────────
// 1) Read SHP (DBF)
// ──────────────────────────────────────────────────────────
$dbfPath = __DIR__ . '/../ตรวจสอบคุณสมบัติ/Merge_แปลงสอบทาน.dbf';
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
$shpRecords = [];
for ($i = 0; $i < $header['numRecords']; $i++) {
    fread($fh, 1);
    $rec = [];
    foreach ($fields as $f) {
        $rec[$f['name']] = trim(fread($fh, $f['length']));
    }
    $shpRecords[] = $rec;
}
fclose($fh);

// ──────────────────────────────────────────────────────────
// 2) SHP Summary
// ──────────────────────────────────────────────────────────
echo "========================================\n";
echo "  SHP: Merge_แปลงสอบทาน.shp\n";
echo "========================================\n";
echo "จำนวนแปลงทั้งหมด (records): " . count($shpRecords) . "\n";

// Unique SPAR_CODE
$shpSparCodes = [];
$shpDuplicates = [];
$shpTotalSqwa = 0;
$shpUniqueVillagers = [];

foreach ($shpRecords as $r) {
    $sparCode = $r['SPAR_CODE'] ?? '';
    $areaRai = (float)($r['AREA_RAI'] ?? 0);
    $ngan = (float)($r['NGAN'] ?? 0);
    $waSq = (float)($r['WA_SQ'] ?? 0);
    $totalSq = ($areaRai * 400) + ($ngan * 100) + $waSq;
    $shpTotalSqwa += $totalSq;

    $idcard = $r['IDCARD'] ?? '';
    if ($idcard) $shpUniqueVillagers[$idcard] = true;

    if (isset($shpSparCodes[$sparCode])) {
        $shpDuplicates[] = $sparCode;
    }
    $shpSparCodes[$sparCode] = ($shpSparCodes[$sparCode] ?? 0) + 1;
}

$shpRai = intdiv(intval(round($shpTotalSqwa)), 400);
$shpNgan = intdiv(intval(round($shpTotalSqwa)) % 400, 100);
$shpSqwaRem = intval(round($shpTotalSqwa)) % 100;

echo "Unique SPAR_CODE: " . count($shpSparCodes) . "\n";
echo "Duplicate SPAR_CODE: " . count($shpDuplicates) . "\n";
echo "Unique ราย (IDCARD): " . count($shpUniqueVillagers) . "\n";
echo "เนื้อที่รวม: " . number_format($shpRai) . " ไร่ " . $shpNgan . " งาน " . $shpSqwaRem . " ตร.วา\n";
echo "  (= " . number_format($shpTotalSqwa, 2) . " ตร.วา)\n\n";

// Show duplicates detail
if (!empty($shpDuplicates)) {
    echo "--- Duplicate SPAR_CODEs ---\n";
    $dupCount = 0;
    foreach ($shpSparCodes as $code => $cnt) {
        if ($cnt > 1) {
            $dupCount++;
            if ($dupCount <= 20) echo "  $code: $cnt records\n";
        }
    }
    echo "Total unique SPAR_CODEs with duplicates: $dupCount\n\n";
}

// ──────────────────────────────────────────────────────────
// 3) DB Summary
// ──────────────────────────────────────────────────────────
$db = getDB();

echo "========================================\n";
echo "  DB: land_plots\n";
echo "========================================\n";

$dbPlotCount = $db->query("SELECT COUNT(*) FROM land_plots")->fetchColumn();
$dbVillagerCount = $db->query("SELECT COUNT(DISTINCT villager_id) FROM land_plots")->fetchColumn();
$dbArea = $db->query("SELECT COALESCE(SUM(area_rai),0) as rai, COALESCE(SUM(area_ngan),0) as ngan, COALESCE(SUM(area_sqwa),0) as sqwa FROM land_plots")->fetch();
$dbTotalSqwa = ($dbArea['rai'] * 400) + ($dbArea['ngan'] * 100) + $dbArea['sqwa'];
$dbRai = intdiv(intval(round($dbTotalSqwa)), 400);
$dbNgan = intdiv(intval(round($dbTotalSqwa)) % 400, 100);
$dbSqwaRem = intval(round($dbTotalSqwa)) % 100;

echo "จำนวนแปลง: $dbPlotCount\n";
echo "จำนวนราย (unique villager): $dbVillagerCount\n";
echo "เนื้อที่รวม: " . number_format($dbRai) . " ไร่ " . $dbNgan . " งาน " . $dbSqwaRem . " ตร.วา\n";
echo "  (= " . number_format($dbTotalSqwa, 2) . " ตร.วา)\n\n";

// ──────────────────────────────────────────────────────────
// 4) Unique SPAR_CODEs in SHP but not in DB
// ──────────────────────────────────────────────────────────
$dbSparCodes = $db->query("SELECT spar_code FROM land_plots WHERE spar_code IS NOT NULL AND spar_code != ''")->fetchAll(PDO::FETCH_COLUMN);
$dbSparSet = array_flip($dbSparCodes);

$onlyInShp = [];
$extraSqwa = 0;
foreach ($shpRecords as $r) {
    $sparCode = $r['SPAR_CODE'] ?? '';
    if ($sparCode && !isset($dbSparSet[$sparCode])) {
        $areaRai = (float)($r['AREA_RAI'] ?? 0);
        $ngan = (float)($r['NGAN'] ?? 0);
        $waSq = (float)($r['WA_SQ'] ?? 0);
        $totalSq = ($areaRai * 400) + ($ngan * 100) + $waSq;
        $extraSqwa += $totalSq;
        $onlyInShp[$sparCode] = [
            'apar_no' => $r['APAR_NO'] ?? '',
            'num_apar' => $r['NUM_APAR'] ?? '',
            'area_rai' => $areaRai,
            'ngan' => $ngan,
            'wa_sq' => $waSq,
            'name' => ($r['NAME'] ?? '') . ' ' . ($r['SURNAME'] ?? ''),
        ];
    }
}

echo "========================================\n";
echo "  แปลงที่อยู่ใน SHP แต่ไม่อยู่ใน DB\n";
echo "========================================\n";
echo "จำนวน: " . count($onlyInShp) . " แปลง\n";
$extraRai = intdiv(intval(round($extraSqwa)), 400);
echo "เนื้อที่รวม: " . number_format($extraRai) . " ไร่ (= " . number_format($extraSqwa, 2) . " ตร.วา)\n\n";

if (count($onlyInShp) <= 30) {
    echo str_pad("SPAR_CODE", 22) . str_pad("APAR_NO", 10) . str_pad("NUM_APAR", 10) . str_pad("RAI", 8) . str_pad("NGAN", 6) . str_pad("WASQ", 10) . "\n";
    echo str_repeat("-", 66) . "\n";
    foreach ($onlyInShp as $code => $d) {
        echo str_pad($code, 22) . str_pad($d['apar_no'], 10) . str_pad($d['num_apar'], 10) . str_pad($d['area_rai'], 8) . str_pad($d['ngan'], 6) . str_pad(round($d['wa_sq'], 2), 10) . "\n";
    }
}

echo "\n========================================\n";
echo "  สรุปสุดท้าย\n";
echo "========================================\n";
echo "SHP unique SPAR_CODE: " . count($shpSparCodes) . " | แปลงใน DB: $dbPlotCount | ส่วนต่าง: " . (count($shpSparCodes) - $dbPlotCount) . "\n";
echo "เนื้อที่ DB: " . number_format($dbRai) . "-$dbNgan-$dbSqwaRem | SHP: " . number_format($shpRai) . "-$shpNgan-$shpSqwaRem\n";
echo "Done.\n";
