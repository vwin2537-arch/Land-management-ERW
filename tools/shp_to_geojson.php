<?php
/**
 * Convert Shapefile (.shp + .dbf) to GeoJSON
 * Handles UTM Zone 47N → WGS84 coordinate conversion
 */

$shpPath = __DIR__ . '/../ตรวจสอบคุณสมบัติ/Merge_แปลงสอบทาน.shp';
$dbfPath = __DIR__ . '/../ตรวจสอบคุณสมบัติ/Merge_แปลงสอบทาน.dbf';
$outPath = __DIR__ . '/../data/plots_boundaries.geojson';

if (!file_exists($shpPath)) die("ไม่พบไฟล์ .shp\n");
if (!file_exists($dbfPath)) die("ไม่พบไฟล์ .dbf\n");

// Ensure output directory exists
$outDir = dirname($outPath);
if (!is_dir($outDir)) mkdir($outDir, 0755, true);

// ============================================================
// UTM Zone 47N → WGS84
// ============================================================
function utmToLatLng(float $e, float $n): array {
    $a = 6378137.0;
    $f = 1 / 298.257223563;
    $ee = sqrt(2 * $f - $f * $f);
    $e2 = $ee * $ee / (1 - $ee * $ee);
    $k0 = 0.9996;
    $x = $e - 500000.0;
    $y = $n;
    $M = $y / $k0;
    $mu = $M / ($a * (1 - $ee*$ee/4 - 3*pow($ee,4)/64 - 5*pow($ee,6)/256));
    $e1 = (1 - sqrt(1 - $ee*$ee)) / (1 + sqrt(1 - $ee*$ee));
    $phi1 = $mu + (3*$e1/2 - 27*pow($e1,3)/32)*sin(2*$mu)
                + (21*pow($e1,2)/16 - 55*pow($e1,4)/32)*sin(4*$mu)
                + (151*pow($e1,3)/96)*sin(6*$mu)
                + (1097*pow($e1,4)/512)*sin(8*$mu);
    $C1 = $e2 * cos($phi1)*cos($phi1);
    $T1 = tan($phi1)*tan($phi1);
    $N1 = $a / sqrt(1 - $ee*$ee*sin($phi1)*sin($phi1));
    $R1 = $a*(1 - $ee*$ee) / pow(1 - $ee*$ee*sin($phi1)*sin($phi1), 1.5);
    $D = $x / ($N1 * $k0);
    $lat = $phi1 - ($N1*tan($phi1)/$R1)*(
        $D*$D/2 - (5+3*$T1+10*$C1-4*$C1*$C1-9*$e2)*pow($D,4)/24
        + (61+90*$T1+298*$C1+45*$T1*$T1-252*$e2-3*$C1*$C1)*pow($D,6)/720);
    $lng0 = deg2rad(46*6-180+3);
    $lng = $lng0 + ($D - (1+2*$T1+$C1)*pow($D,3)/6
        + (5-2*$C1+28*$T1-3*$C1*$C1+8*$e2+24*$T1*$T1)*pow($D,5)/120) / cos($phi1);
    return [round(rad2deg($lng), 7), round(rad2deg($lat), 7)]; // [lng, lat] for GeoJSON
}

// ============================================================
// Read DBF attributes
// ============================================================
function readDbfRecords(string $path): array {
    $fh = fopen($path, 'rb');
    $h = unpack('Cv/Cy/Cm/Cd/VnR/vhS/vrS', fread($fh, 32));
    $fields = [];
    while (true) {
        $fd = fread($fh, 32);
        if (!$fd || strlen($fd)<32 || ord($fd[0])===0x0D) break;
        $f = unpack('A11name/Atype/x4/Clength/Cdecimal', $fd);
        $f['name'] = trim($f['name']);
        $fields[] = $f;
    }
    fseek($fh, $h['hS']);
    $records = [];
    for ($i=0; $i<$h['nR']; $i++) {
        fread($fh, 1);
        $row = [];
        foreach ($fields as $f) $row[$f['name']] = trim(fread($fh, $f['length']));
        $records[] = $row;
    }
    fclose($fh);
    return $records;
}

// ============================================================
// Read SHP file (Polygons)
// ============================================================
function readShpPolygons(string $path): array {
    $fh = fopen($path, 'rb');
    
    // File header: 100 bytes
    // Bytes 0-3: File code (9994 big-endian)
    // Bytes 24-27: File length (big-endian, in 16-bit words)
    // Bytes 32-35: Shape type
    $header = fread($fh, 100);
    $fileCode = unpack('N', substr($header, 0, 4))[1];
    $shapeType = unpack('V', substr($header, 32, 4))[1];
    
    echo "SHP: fileCode=$fileCode, shapeType=$shapeType\n";
    // Shape type 5 = Polygon
    
    $polygons = [];
    $recNum = 0;
    
    while (!feof($fh)) {
        // Record header: 8 bytes (big-endian)
        $rh = fread($fh, 8);
        if (!$rh || strlen($rh) < 8) break;
        
        $recHeader = unpack('NrecNo/NcontentLen', $rh);
        $contentLen = $recHeader['contentLen'] * 2; // Convert 16-bit words to bytes
        
        if ($contentLen <= 0) break;
        
        $content = fread($fh, $contentLen);
        if (!$content || strlen($content) < 4) break;
        
        $recShapeType = unpack('V', substr($content, 0, 4))[1];
        
        if ($recShapeType === 0) {
            // Null shape
            $polygons[] = null;
            $recNum++;
            continue;
        }
        
        if ($recShapeType === 5) {
            // Polygon
            // Bytes 4-35: Bounding Box (4 doubles)
            // Bytes 36-39: numParts (int32)
            // Bytes 40-43: numPoints (int32)
            $numParts = unpack('V', substr($content, 36, 4))[1];
            $numPoints = unpack('V', substr($content, 40, 4))[1];
            
            // Parts array: numParts * int32 (starting at byte 44)
            $parts = [];
            for ($i = 0; $i < $numParts; $i++) {
                $parts[] = unpack('V', substr($content, 44 + $i * 4, 4))[1];
            }
            
            // Points array: starts after parts array
            $pointsOffset = 44 + $numParts * 4;
            $rings = [];
            
            for ($p = 0; $p < $numParts; $p++) {
                $startIdx = $parts[$p];
                $endIdx = ($p + 1 < $numParts) ? $parts[$p + 1] : $numPoints;
                
                $ring = [];
                for ($i = $startIdx; $i < $endIdx; $i++) {
                    $offset = $pointsOffset + $i * 16; // 2 doubles per point
                    if ($offset + 16 > strlen($content)) break;
                    $x = unpack('d', substr($content, $offset, 8))[1];     // Easting (UTM)
                    $y = unpack('d', substr($content, $offset + 8, 8))[1]; // Northing (UTM)
                    $ring[] = utmToLatLng($x, $y); // Convert to [lng, lat]
                }
                $rings[] = $ring;
            }
            
            $polygons[] = $rings;
        } else {
            // Unknown shape type, skip
            $polygons[] = null;
        }
        
        $recNum++;
    }
    
    fclose($fh);
    return $polygons;
}

// ============================================================
// MAIN
// ============================================================
echo "อ่าน DBF...\n";
$dbfRecords = readDbfRecords($dbfPath);
echo "  DBF records: " . count($dbfRecords) . "\n";

echo "อ่าน SHP...\n";
$polygons = readShpPolygons($shpPath);
echo "  SHP records: " . count($polygons) . "\n";

// Build GeoJSON
$features = [];
$minCount = min(count($dbfRecords), count($polygons));

for ($i = 0; $i < $minCount; $i++) {
    $poly = $polygons[$i];
    $attr = $dbfRecords[$i];
    
    if ($poly === null) continue;
    
    $features[] = [
        'type' => 'Feature',
        'properties' => [
            'plot_code' => $attr['SPAR_CODE'] ?? '',
            'owner' => ($attr['NAME_TITLE'] ?? '') . ($attr['NAME'] ?? '') . ' ' . ($attr['SURNAME'] ?? ''),
            'park' => $attr['NAME_DNP'] ?? '',
            'area_rai' => (float)($attr['RAI'] ?? 0),
            'area_ngan' => (float)($attr['NGAN'] ?? 0),
            'area_sqwa' => (float)($attr['WA_SQ'] ?? 0),
            'ban_e' => $attr['BAN_E'] ?? '',
            'ban_type' => (int)($attr['BAN_TYPE'] ?? 0),
            'ptype' => $attr['PTYPE'] ?? '',
            'remark' => $attr['REMARK'] ?? '',
        ],
        'geometry' => [
            'type' => 'Polygon',
            'coordinates' => $poly,
        ],
    ];
}

$geojson = [
    'type' => 'FeatureCollection',
    'features' => $features,
];

$json = json_encode($geojson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
file_put_contents($outPath, $json);

$fileSize = round(filesize($outPath) / 1024, 1);
echo "\n✅ สร้าง GeoJSON สำเร็จ!\n";
echo "   Features: " . count($features) . "\n";
echo "   ไฟล์: $outPath ($fileSize KB)\n";
