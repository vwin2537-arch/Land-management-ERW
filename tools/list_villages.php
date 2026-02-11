<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$r = $db->query("SELECT ban_e, par_ban, par_moo, par_tam, par_amp, par_prov, 
    COUNT(*) as plots, 
    COUNT(DISTINCT villager_id) as villagers
    FROM land_plots 
    GROUP BY ban_e 
    ORDER BY ban_e");
echo "ban_e | par_ban | par_moo | par_tam | plots | villagers\n";
echo str_repeat('-', 80) . "\n";
foreach($r as $row) {
    echo implode(' | ', $row) . "\n";
}
