<?php
/**
 * Compare area data between Shapefile (DBF) and Database
 * ตรวจสอบเนื้อที่ใน DB vs Shapefile (Merge_แปลงสอบทาน.shp)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

// ──────────────────────────────────────────────────────────
// 1) Read DBF file
// ──────────────────────────────────────────────────────────
$dbfPath = __DIR__ . '/../ตรวจสอบคุณสมบัติ/Merge_แปลงสอบทาน.dbf';
if (!file_exists($dbfPath)) { die("DBF file not found: $dbfPath\n"); }

$fh = fopen($dbfPath, 'rb');
$headerData = fread($fh, 32);
$header = unpack('Cversion/CyearMod/CmonthMod/CdayMod/VnumRecords/vheaderSize/vrecordSize', $headerData);

// Read field definitions
$fields = [];
while (true) {
    $fd = fread($fh, 32);
    if (!$fd || strlen($fd) < 32 || ord($fd[0]) === 0x0D) break;
    $f = unpack('A11name/Atype/x4/Clength/Cdecimal', $fd);
    $f['name'] = trim($f['name']);
    $fields[] = $f;
}

// Read all records
fseek($fh, $header['headerSize']);
$shpRecords = [];
for ($i = 0; $i < $header['numRecords']; $i++) {
    fread($fh, 1); // deleted flag
    $rec = [];
    foreach ($fields as $f) {
        $rec[$f['name']] = trim(fread($fh, $f['length']));
    }
    // Key by SPAR_CODE or NUM_APAR + APAR_NO
    $shpRecords[] = $rec;
}
fclose($fh);

echo "=== Shapefile: " . count($shpRecords) . " records ===\n\n";

// ──────────────────────────────────────────────────────────
// 2) Read DB data
// ──────────────────────────────────────────────────────────
$db = getDB();
$dbRows = $db->query("
    SELECT plot_id, plot_code, num_apar, apar_no, spar_code, num_spar,
           area_rai, area_ngan, area_sqwa, par_ban
    FROM land_plots
    ORDER BY apar_no, num_apar
")->fetchAll(PDO::FETCH_ASSOC);

echo "=== Database: " . count($dbRows) . " records ===\n\n";

// ──────────────────────────────────────────────────────────
// 3) Build lookup maps
// ──────────────────────────────────────────────────────────
// DB keyed by SPAR_CODE
$dbBySparCode = [];
foreach ($dbRows as $r) {
    $key = $r['spar_code'] ?? '';
    if ($key) $dbBySparCode[$key] = $r;
}

// SHP keyed by SPAR_CODE
$shpBySparCode = [];
foreach ($shpRecords as $r) {
    $key = $r['SPAR_CODE'] ?? '';
    if ($key) $shpBySparCode[$key] = $r;
}

// ──────────────────────────────────────────────────────────
// 4) Compare area values
// ──────────────────────────────────────────────────────────
$matched = 0;
$mismatched = 0;
$onlyInDb = 0;
$onlyInShp = 0;
$mismatchDetails = [];

// Total area comparison
$dbTotalSqwa = 0;
$shpTotalSqwa = 0;

foreach ($dbRows as $r) {
    $sparCode = $r['spar_code'] ?? '';
    $dbAreaSqwa = ($r['area_rai'] * 400) + ($r['area_ngan'] * 100) + $r['area_sqwa'];
    $dbTotalSqwa += $dbAreaSqwa;

    if (!$sparCode || !isset($shpBySparCode[$sparCode])) {
        $onlyInDb++;
        continue;
    }

    $shp = $shpBySparCode[$sparCode];
    $shpAreaRai = (float)($shp['AREA_RAI'] ?? 0);
    $shpNgan = (float)($shp['NGAN'] ?? 0);
    $shpWaSq = (float)($shp['WA_SQ'] ?? 0);
    // SHP stores: AREA_RAI (rai) + NGAN (ngan) + WA_SQ (remaining sqwa)
    $shpAreaSqwa = ($shpAreaRai * 400) + ($shpNgan * 100) + $shpWaSq;

    $diff = abs($dbAreaSqwa - $shpAreaSqwa);

    if ($diff > 1) { // tolerance: 1 sqwa
        $mismatched++;
        if (count($mismatchDetails) < 30) { // show first 30
            $mismatchDetails[] = [
                'spar_code' => $sparCode,
                'num_apar' => $r['num_apar'],
                'apar_no' => $r['apar_no'],
                'db_rai' => $r['area_rai'],
                'db_ngan' => $r['area_ngan'],
                'db_sqwa' => $r['area_sqwa'],
                'db_total_sqwa' => round($dbAreaSqwa, 2),
                'shp_area_rai' => $shpAreaRai,
                'shp_ngan' => $shpNgan,
                'shp_wa_sq' => $shpWaSq,
                'shp_total_sqwa' => round($shpAreaSqwa, 2),
                'diff_sqwa' => round($diff, 2),
            ];
        }
    } else {
        $matched++;
    }
}

// Records only in SHP
foreach ($shpRecords as $r) {
    $sparCode = $r['SPAR_CODE'] ?? '';
    if ($sparCode && !isset($dbBySparCode[$sparCode])) {
        $onlyInShp++;
    }
}

// SHP total area
foreach ($shpRecords as $r) {
    $shpAreaRai = (float)($r['AREA_RAI'] ?? 0);
    $shpNganR = (float)($r['NGAN'] ?? 0);
    $shpWaSq = (float)($r['WA_SQ'] ?? 0);
    $shpTotalSqwa += ($shpAreaRai * 400) + ($shpNganR * 100) + $shpWaSq;
}

// ──────────────────────────────────────────────────────────
// 5) Output Results
// ──────────────────────────────────────────────────────────
echo "=== COMPARISON RESULTS ===\n";
echo "Records in DB:  " . count($dbRows) . "\n";
echo "Records in SHP: " . count($shpRecords) . "\n\n";

echo "Matched (area diff <= 1 sqwa): $matched\n";
echo "Mismatched (area diff > 1 sqwa): $mismatched\n";
echo "Only in DB (no SHP match):      $onlyInDb\n";
echo "Only in SHP (no DB match):      $onlyInShp\n\n";

// Total area comparison
$dbTotalRai = floor($dbTotalSqwa / 400);
$shpTotalRai = floor($shpTotalSqwa / 400);
echo "=== TOTAL AREA ===\n";
echo "DB total:  " . number_format($dbTotalSqwa, 2) . " sqwa (" . number_format($dbTotalRai) . " rai)\n";
echo "SHP total: " . number_format($shpTotalSqwa, 2) . " sqwa (" . number_format($shpTotalRai) . " rai)\n";
echo "Diff:      " . number_format(abs($dbTotalSqwa - $shpTotalSqwa), 2) . " sqwa\n\n";

if (!empty($mismatchDetails)) {
    echo "=== MISMATCHED RECORDS (first 30) ===\n";
    echo str_pad("SPAR_CODE", 20) . str_pad("NUM_APAR", 10) . str_pad("APAR_NO", 10)
        . str_pad("DB_RAI", 8) . str_pad("DB_NGAN", 8) . str_pad("DB_SQWA", 8)
        . str_pad("SHP_RAI", 10) . str_pad("SHP_WASQ", 10)
        . str_pad("DIFF_SQ", 10) . "\n";
    echo str_repeat("-", 94) . "\n";
    foreach ($mismatchDetails as $m) {
        echo str_pad($m['spar_code'], 20)
            . str_pad($m['num_apar'], 10)
            . str_pad($m['apar_no'], 10)
            . str_pad($m['db_rai'], 8)
            . str_pad($m['db_ngan'], 8)
            . str_pad($m['db_sqwa'], 8)
            . str_pad($m['shp_area_rai'], 10)
            . str_pad(round($m['shp_wa_sq'], 2), 10)
            . str_pad($m['diff_sqwa'], 10) . "\n";
    }
}

echo "\nDone.\n";
