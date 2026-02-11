<?php
/**
 * Shapefile Import Script — นำเข้าข้อมูลจาก .dbf เข้าฐานข้อมูล
 * นำเข้าทุก record + เก็บ issues สำหรับแก้ไขทีหลัง
 */

require_once __DIR__ . '/../config/database.php';

$dbfPath = __DIR__ . '/../ตรวจสอบคุณสมบัติ/Merge_แปลงสอบทาน.dbf';
if (!file_exists($dbfPath)) { die("ไม่พบไฟล์ .dbf\n"); }

// ============================================================
// UTM Zone 47N → WGS84 Lat/Lng Converter
// ============================================================
function utmToLatLng(float $easting, float $northing, int $zone = 47, bool $northern = true): array {
    $a = 6378137.0;          // WGS84 semi-major axis
    $f = 1 / 298.257223563;  // WGS84 flattening
    $e = sqrt(2 * $f - $f * $f);
    $e2 = $e * $e / (1 - $e * $e); // e'^2

    $k0 = 0.9996;
    $x = $easting - 500000.0;
    $y = $northern ? $northing : $northing - 10000000.0;

    $M = $y / $k0;
    $mu = $M / ($a * (1 - $e*$e/4 - 3*pow($e,4)/64 - 5*pow($e,6)/256));

    $e1 = (1 - sqrt(1 - $e*$e)) / (1 + sqrt(1 - $e*$e));
    $phi1 = $mu + (3*$e1/2 - 27*pow($e1,3)/32) * sin(2*$mu)
               + (21*pow($e1,2)/16 - 55*pow($e1,4)/32) * sin(4*$mu)
               + (151*pow($e1,3)/96) * sin(6*$mu)
               + (1097*pow($e1,4)/512) * sin(8*$mu);

    $C1 = $e2 * cos($phi1) * cos($phi1);
    $T1 = tan($phi1) * tan($phi1);
    $N1 = $a / sqrt(1 - $e*$e * sin($phi1)*sin($phi1));
    $R1 = $a * (1 - $e*$e) / pow(1 - $e*$e * sin($phi1)*sin($phi1), 1.5);
    $D = $x / ($N1 * $k0);

    $lat = $phi1 - ($N1 * tan($phi1) / $R1) * (
        $D*$D/2 
        - (5 + 3*$T1 + 10*$C1 - 4*$C1*$C1 - 9*$e2) * pow($D,4)/24
        + (61 + 90*$T1 + 298*$C1 + 45*$T1*$T1 - 252*$e2 - 3*$C1*$C1) * pow($D,6)/720
    );

    $lng0 = deg2rad(($zone - 1) * 6 - 180 + 3); // Central meridian
    $lng = $lng0 + ($D - (1 + 2*$T1 + $C1) * pow($D,3)/6
        + (5 - 2*$C1 + 28*$T1 - 3*$C1*$C1 + 8*$e2 + 24*$T1*$T1) * pow($D,5)/120) / cos($phi1);

    return [
        'lat' => round(rad2deg($lat), 7),
        'lng' => round(rad2deg($lng), 7)
    ];
}

// ============================================================
// Read DBF
// ============================================================
function readAllDbf(string $path): array {
    $fh = fopen($path, 'rb');
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
        $row = [];
        foreach ($fields as $f) {
            $row[$f['name']] = trim(fread($fh, $f['length']));
        }
        $records[] = $row;
    }
    fclose($fh);
    return $records;
}

// ============================================================
// Validate Thai ID (same logic as validator)
// ============================================================
function checkIdCard(string $id): ?string {
    if (empty($id)) return 'เลขบัตรว่าง';
    if (strlen($id) !== 13) return 'ไม่ครบ 13 หลัก';
    if (!ctype_digit($id)) return 'มีตัวอักษรปน';
    $sum = 0;
    for ($i = 0; $i < 12; $i++) $sum += (int)$id[$i] * (13 - $i);
    $check = (11 - ($sum % 11)) % 10;
    if ($check !== (int)$id[12]) return "checksum ผิด (ควรลงท้าย $check)";
    return null;
}

// ============================================================
// Map PTYPE to land_use_type enum
// ============================================================
function mapLandUse(string $ptype): string {
    $ptype = trim($ptype);
    if (str_contains($ptype, 'อยู่อาศัย') && str_contains($ptype, 'ทำกิน')) return 'mixed';
    if (str_contains($ptype, 'อยู่อาศัย')) return 'residential';
    if (str_contains($ptype, 'เกษตร') || str_contains($ptype, 'ทำกิน')) return 'agriculture';
    if (str_contains($ptype, 'สวน')) return 'garden';
    if (str_contains($ptype, 'ปศุสัตว์') || str_contains($ptype, 'เลี้ยง')) return 'livestock';
    return 'other';
}

// ============================================================
// MAIN IMPORT
// ============================================================
echo "=== เริ่มนำเข้าข้อมูล ===\n";
$records = readAllDbf($dbfPath);
$total = count($records);
echo "อ่านได้ $total records\n\n";

$db = getDB();
$db->beginTransaction();

$inserted = 0;
$updated = 0;
$villagersCreated = 0;
$villagersExisting = 0;
$errorList = [];

try {
    // Prepare statements
    $findVillager = $db->prepare("SELECT villager_id FROM villagers WHERE id_card_number = :idc LIMIT 1");
    
    $insertVillager = $db->prepare("INSERT INTO villagers 
        (id_card_number, prefix, first_name, last_name, village_name, village_no, 
         sub_district, district, province, address)
        VALUES (:idc, :prefix, :fname, :lname, :vname, :vno, :sub, :dist, :prov, :addr)");

    $updateVillager = $db->prepare("UPDATE villagers SET
        prefix = COALESCE(:prefix, prefix),
        first_name = COALESCE(:fname, first_name),
        last_name = COALESCE(:lname, last_name),
        village_name = COALESCE(:vname, village_name),
        village_no = COALESCE(:vno, village_no),
        sub_district = COALESCE(:sub, sub_district),
        district = COALESCE(:dist, district),
        province = COALESCE(:prov, province),
        address = COALESCE(:addr, address)
        WHERE id_card_number = :idc");

    $checkPlot = $db->prepare("SELECT plot_id FROM land_plots WHERE plot_code = :code LIMIT 1");

    $insertPlot = $db->prepare("INSERT INTO land_plots 
        (plot_code, villager_id, park_name, zone, area_rai, area_ngan, area_sqwa,
         land_use_type, latitude, longitude, status, notes,
         code_dnp, apar_code, apar_no, num_apar, spar_code, ban_e, perimeter, ban_type,
         num_spar, spar_no, par_ban, par_moo, par_tam, par_amp, par_prov, ptype, target_fid,
         occupation_since, data_issues)
        VALUES 
        (:plot_code, :villager_id, :park_name, :zone, :area_rai, :area_ngan, :area_sqwa,
         :land_use_type, :latitude, :longitude, :status, :notes,
         :code_dnp, :apar_code, :apar_no, :num_apar, :spar_code, :ban_e, :perimeter, :ban_type,
         :num_spar, :spar_no, :par_ban, :par_moo, :par_tam, :par_amp, :par_prov, :ptype, :target_fid,
         :occupation_since, :data_issues)");

    foreach ($records as $idx => $row) {
        $rowNum = $idx + 1;
        $issues = [];
        
        // --- Validate ---
        $idErr = checkIdCard($row['IDCARD'] ?? '');
        if ($idErr) $issues[] = "บัตรปชช: $idErr";
        if (empty($row['NAME'])) $issues[] = 'ไม่มีชื่อ';
        if (empty($row['SURNAME'])) $issues[] = 'ไม่มีนามสกุล';
        
        $issueText = empty($issues) ? null : implode('; ', $issues);

        // --- Villager ---
        $idcard = $row['IDCARD'] ?? '';
        $villagerId = null;
        
        if (!empty($idcard)) {
            $findVillager->execute(['idc' => $idcard]);
            $existing = $findVillager->fetchColumn();
            
            $homeAddr = trim(($row['HOME_NO'] ?? '') . ' หมู่ ' . ($row['HOME_MOO'] ?? '-'));
            
            if ($existing) {
                $villagerId = (int)$existing;
                $villagersExisting++;
                // Update with latest data
                $updateVillager->execute([
                    'idc'    => $idcard,
                    'prefix' => $row['NAME_TITLE'] ?: null,
                    'fname'  => $row['NAME'] ?: null,
                    'lname'  => $row['SURNAME'] ?: null,
                    'vname'  => $row['HOME_BAN'] ?: null,
                    'vno'    => $row['HOME_MOO'] ?: null,
                    'sub'    => $row['HOME_TAM'] ?: null,
                    'dist'   => $row['HOME_AMP'] ?: null,
                    'prov'   => $row['HOME_PROV'] ?: null,
                    'addr'   => $homeAddr ?: null,
                ]);
            } else {
                $insertVillager->execute([
                    'idc'    => $idcard,
                    'prefix' => $row['NAME_TITLE'] ?: null,
                    'fname'  => $row['NAME'] ?: 'ไม่ระบุ',
                    'lname'  => $row['SURNAME'] ?: 'ไม่ระบุ',
                    'vname'  => $row['HOME_BAN'] ?: null,
                    'vno'    => $row['HOME_MOO'] ?: null,
                    'sub'    => $row['HOME_TAM'] ?: null,
                    'dist'   => $row['HOME_AMP'] ?: null,
                    'prov'   => $row['HOME_PROV'] ?: null,
                    'addr'   => $homeAddr ?: null,
                ]);
                $villagerId = (int)$db->lastInsertId();
                $villagersCreated++;
            }
        } else {
            // No IDCARD — create placeholder
            $placeholderIdc = 'TEMP_' . str_pad($rowNum, 5, '0', STR_PAD_LEFT);
            $insertVillager->execute([
                'idc'    => $placeholderIdc,
                'prefix' => $row['NAME_TITLE'] ?: null,
                'fname'  => $row['NAME'] ?: "ไม่ระบุ_$rowNum",
                'lname'  => $row['SURNAME'] ?: "ไม่ระบุ_$rowNum",
                'vname'  => null, 'vno' => null, 'sub' => null,
                'dist'   => null, 'prov' => null, 'addr' => null,
            ]);
            $villagerId = (int)$db->lastInsertId();
            $villagersCreated++;
            $issues[] = "ไม่มีเลขบัตร — ใช้รหัสชั่วคราว $placeholderIdc";
            $issueText = implode('; ', $issues);
        }

        // --- Convert UTM to LatLng ---
        $lat = null;
        $lng = null;
        $e = (float)($row['E'] ?? 0);
        $n = (float)($row['N'] ?? 0);
        if ($e > 0 && $n > 0) {
            $coords = utmToLatLng($e, $n, 47, true);
            $lat = $coords['lat'];
            $lng = $coords['lng'];
        }

        // --- Plot Code ---
        $plotCode = $row['SPAR_CODE'] ?? '';
        if (empty($plotCode)) {
            $plotCode = 'IMP-' . str_pad($rowNum, 5, '0', STR_PAD_LEFT);
            $issues[] = "ไม่มี SPAR_CODE — ใช้รหัส $plotCode";
            $issueText = implode('; ', $issues);
        }

        // Check if plot already exists
        $checkPlot->execute(['code' => $plotCode]);
        if ($checkPlot->fetchColumn()) {
            $plotCode .= '_DUP' . $rowNum;
            $issues[] = "SPAR_CODE ซ้ำ — ใช้รหัส $plotCode";
            $issueText = implode('; ', $issues);
        }

        // --- Year conversion: พ.ศ. → ค.ศ.  ---
        $year = $row['YEAR'] ?? '';
        $occupationSince = null;
        if (!empty($year) && is_numeric($year)) {
            $yearInt = (int)$year;
            if ($yearInt > 2400) $yearInt -= 543; // Convert พ.ศ. to ค.ศ.
            $occupationSince = $yearInt;
        }

        // --- Status ---
        $status = $issueText ? 'pending_review' : 'surveyed';

        // --- Insert Plot ---
        $insertPlot->execute([
            'plot_code'     => $plotCode,
            'villager_id'   => $villagerId,
            'park_name'     => $row['NAME_DNP'] ?: null,
            'zone'          => null,
            'area_rai'      => (float)($row['RAI'] ?? 0),
            'area_ngan'     => (float)($row['NGAN'] ?? 0),
            'area_sqwa'     => (float)($row['WA_SQ'] ?? 0),
            'land_use_type' => mapLandUse($row['PTYPE'] ?? ''),
            'latitude'      => $lat,
            'longitude'     => $lng,
            'status'        => $status,
            'notes'         => $row['REMARK'] ?: null,
            'code_dnp'      => $row['CODE_DNP'] ?: null,
            'apar_code'     => $row['APAR_CODE'] ?: null,
            'apar_no'       => $row['APAR_NO'] ?: null,
            'num_apar'      => $row['NUM_APAR'] ?: null,
            'spar_code'     => $row['SPAR_CODE'] ?: null,
            'ban_e'         => $row['BAN_E'] ?: null,
            'perimeter'     => (float)($row['PERIMETER'] ?? 0),
            'ban_type'      => !empty($row['BAN_TYPE']) ? (int)$row['BAN_TYPE'] : null,
            'num_spar'      => $row['NUM_SPAR'] ?: null,
            'spar_no'       => $row['SPAR_NO'] ?: null,
            'par_ban'       => $row['PAR_BAN'] ?: null,
            'par_moo'       => $row['PAR_MOO'] ?: null,
            'par_tam'       => $row['PAR_TAM'] ?: null,
            'par_amp'       => $row['PAR_AMP'] ?: null,
            'par_prov'      => $row['PAR_PROV'] ?: null,
            'ptype'         => $row['PTYPE'] ?: null,
            'target_fid'    => !empty($row['TARGET_FID']) ? (int)(float)$row['TARGET_FID'] : null,
            'occupation_since' => $occupationSince,
            'data_issues'   => $issueText,
        ]);
        $inserted++;

        if ($rowNum % 100 === 0) echo "  นำเข้า $rowNum / $total ...\n";
    }

    $db->commit();
    echo "\n=============================\n";
    echo "✅ นำเข้าสำเร็จ!\n";
    echo "   แปลงนำเข้า: $inserted\n";
    echo "   ราษฎรสร้างใหม่: $villagersCreated\n";
    echo "   ราษฎรมีอยู่แล้ว: $villagersExisting\n";
    
    // Count issues
    $issueCount = $db->query("SELECT COUNT(*) FROM land_plots WHERE data_issues IS NOT NULL")->fetchColumn();
    echo "   แปลงที่มีปัญหา (แก้ทีหลัง): $issueCount\n";
    echo "   แปลงสมบูรณ์: " . ($inserted - $issueCount) . "\n";
    echo "=============================\n";

} catch (Exception $ex) {
    $db->rollBack();
    echo "❌ เกิดข้อผิดพลาด: " . $ex->getMessage() . "\n";
    echo "ที่แถว: $rowNum\n";
    echo "Plot code: $plotCode\n";
}
