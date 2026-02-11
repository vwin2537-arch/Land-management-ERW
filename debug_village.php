<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    echo "Connected.<br>";
    
    // Check count
    $total = $db->query("SELECT COUNT(*) FROM land_plots")->fetchColumn();
    echo "Total plots: $total<br>";
    
    // Check ban_e
    $sql = "SELECT ban_e, par_ban, COUNT(*) as c FROM land_plots GROUP BY ban_e, par_ban ORDER BY c DESC LIMIT 20";
    $stm = $db->query($sql);
    $rows = $stm->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'><tr><th>BAN_E</th><th>PAR_BAN</th><th>Count</th></tr>";
    foreach ($rows as $r) {
        echo "<tr>";
        echo "<td>[" . htmlspecialchars($r['ban_e'] ?? 'NULL') . "]</td>";
        echo "<td>" . htmlspecialchars($r['par_ban'] ?? 'NULL') . "</td>";
        echo "<td>" . $r['c'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo $e->getMessage();
}
