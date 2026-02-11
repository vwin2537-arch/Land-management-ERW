<?php
/**
 * อัพเดท polygon_coords ในฐานข้อมูลจาก GeoJSON
 * ใช้ plot_code (SPAR_CODE) เป็น key จับคู่
 */
require_once __DIR__ . '/../config/database.php';

$geojsonPath = __DIR__ . '/../data/plots_boundaries.geojson';
if (!file_exists($geojsonPath)) die("ไม่พบ GeoJSON\n");

echo "อ่าน GeoJSON...\n";
$geojson = json_decode(file_get_contents($geojsonPath), true);
$features = $geojson['features'];
echo "  พบ " . count($features) . " polygons\n";

$db = getDB();

// GeoJSON uses [lng, lat] but Leaflet polygon uses [lat, lng]
// Convert coordinates to Leaflet format
function convertToLeaflet(array $rings): array {
    $result = [];
    foreach ($rings[0] as $point) { // Use first ring (outer boundary)
        $result[] = [$point[1], $point[0]]; // [lat, lng]
    }
    return $result;
}

$stmt = $db->prepare("UPDATE land_plots SET polygon_coords = :coords WHERE plot_code = :code");

$updated = 0;
$notFound = 0;

foreach ($features as $f) {
    $plotCode = $f['properties']['plot_code'];
    $coords = $f['geometry']['coordinates'];
    
    if (empty($plotCode) || empty($coords)) continue;
    
    $leafletCoords = convertToLeaflet($coords);
    $jsonCoords = json_encode($leafletCoords);
    
    $stmt->execute([
        'coords' => $jsonCoords,
        'code' => $plotCode,
    ]);
    
    if ($stmt->rowCount() > 0) {
        $updated++;
    } else {
        $notFound++;
    }
}

// Verify
$withCoords = $db->query("SELECT COUNT(*) FROM land_plots WHERE polygon_coords IS NOT NULL")->fetchColumn();
$total = $db->query("SELECT COUNT(*) FROM land_plots")->fetchColumn();

echo "\n✅ อัพเดทเสร็จ!\n";
echo "   อัพเดท polygon: $updated\n";
echo "   ไม่พบ plot_code: $notFound\n";
echo "   มี polygon: $withCoords / $total\n";
