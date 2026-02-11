<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$plots = $db->query("SELECT COUNT(*) FROM land_plots")->fetchColumn();
$villagers = $db->query("SELECT COUNT(*) FROM villagers")->fetchColumn();
$issues = $db->query("SELECT COUNT(*) FROM land_plots WHERE data_issues IS NOT NULL")->fetchColumn();
$sample = $db->query("SELECT plot_code, park_name, latitude, longitude, data_issues FROM land_plots LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

echo "Plots: $plots\n";
echo "Villagers: $villagers\n";
echo "With issues: $issues\n";
echo "Clean: " . ($plots - $issues) . "\n\n";
echo "Sample:\n";
foreach ($sample as $s) {
    echo "  {$s['plot_code']} | {$s['park_name']} | lat={$s['latitude']} lng={$s['longitude']}";
    if ($s['data_issues']) echo " | ⚠️ {$s['data_issues']}";
    echo "\n";
}
