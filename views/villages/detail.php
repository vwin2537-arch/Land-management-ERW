<?php
/**
 * หมู่บ้านสำรวจ — รายละเอียด
 */

$db = getDB();
$name = $_GET['name'] ?? '';

if (empty($name)) {
    echo '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> ไม่ระบุชื่อหมู่บ้าน</div>';
    return;
}

// ข้อมูลสรุปหมู่บ้าน

$summaryStmt = $db->prepare("SELECT 
    MAX(ban_e) as ban_e, par_ban, MAX(par_moo) as par_moo, MAX(par_tam) as par_tam, MAX(par_amp) as par_amp, MAX(par_prov) as par_prov,
    COUNT(*) as total_plots,
    COUNT(DISTINCT villager_id) as total_villagers,
    ROUND(SUM(area_rai + area_ngan/4 + area_sqwa/400), 2) as total_area_rai,
    SUM(CASE WHEN status = 'surveyed' THEN 1 ELSE 0 END) as surveyed_count,
    SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN data_issues IS NOT NULL AND data_issues != '' THEN 1 ELSE 0 END) as issues_count,
    ROUND(SUM(perimeter), 2) as total_perimeter
FROM land_plots WHERE par_ban = :name GROUP BY par_ban");
$summaryStmt->execute(['name' => $name]);
$village = $summaryStmt->fetch();

if (!$village) {
    echo '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> ไม่พบหมู่บ้าน: ' . htmlspecialchars($name) . '</div>';
    return;
}

// ข้อมูลแปลงทั้งหมดในหมู่บ้าน (พร้อม pagination)
$page_num = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset = ($page_num - 1) * $perPage;

$plotsStmt = $db->prepare("SELECT lp.*, v.prefix, v.first_name, v.last_name, v.id_card_number
    FROM land_plots lp
    JOIN villagers v ON lp.villager_id = v.villager_id
    WHERE lp.par_ban = :name
    ORDER BY lp.plot_code
    LIMIT $perPage OFFSET $offset");
$plotsStmt->execute(['name' => $name]);
$plots = $plotsStmt->fetchAll();
$totalPages = max(1, ceil($village['total_plots'] / $perPage));

// สถิติ: แยกตามประเภทที่ดิน
$byLandUse = $db->prepare("SELECT land_use_type, COUNT(*) as cnt FROM land_plots WHERE par_ban = :name GROUP BY land_use_type ORDER BY cnt DESC");
$byLandUse->execute(['name' => $name]);
$landUseData = $byLandUse->fetchAll();

// สถิติ: แยกตามสถานะ
$byStatus = $db->prepare("SELECT status, COUNT(*) as cnt FROM land_plots WHERE par_ban = :name GROUP BY status ORDER BY cnt DESC");
$byStatus->execute(['name' => $name]);
$statusData = $byStatus->fetchAll();

// ข้อมูลแปลงสำหรับแผนที่ (ที่มีพิกัด)
$mapStmt = $db->prepare("SELECT plot_id, plot_code, latitude, longitude, polygon_coords, status, area_rai, area_ngan
    FROM land_plots WHERE par_ban = :name AND latitude IS NOT NULL AND longitude IS NOT NULL");
$mapStmt->execute(['name' => $name]);
$mapPlots = $mapStmt->fetchAll();

$v = $village;
$pct = $v['total_plots'] > 0 ? round($v['surveyed_count'] / $v['total_plots'] * 100) : 0;

// สีของหมู่บ้าน (ใช้รหัสหมู่บ้าน ban_e ในการเลือกสี)
$villageColors = [
    'BKR' => '#059669', 'PDS' => '#2563eb', 'TSL' => '#7c3aed', 'CSD' => '#dc2626',
    'TKT' => '#ea580c', 'TKY' => '#0891b2', 'BPT' => '#16a34a', 'BMT' => '#9333ea',
    'BPM' => '#0284c7', 'BKK' => '#b45309', 'POK' => '#be185d', 'BTS' => '#4f46e5',
];
$themeColor = $villageColors[$v['ban_e'] ?? ''] ?? '#059669';
?>

<!-- Back + Title -->
<div class="d-flex justify-between align-center mb-3">
    <a href="index.php?page=villages" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> กลับ</a>
</div>

<!-- Village Header -->
<div class="card mb-3" style="background:linear-gradient(135deg, <?= $themeColor ?> 0%, <?= $themeColor ?>cc 100%); color:white; border:none;">
    <div class="card-body" style="padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
                <h2 style="font-size:24px; margin-bottom:4px;">🏘️ บ้าน<?= htmlspecialchars($v['par_ban']) ?></h2>
                <p style="opacity:0.9;">
                    รหัส <strong><?= htmlspecialchars($v['ban_e'] ?? '-') ?></strong> ·
                    ม.<?= htmlspecialchars($v['par_moo']) ?>
                    ต.<?= htmlspecialchars($v['par_tam']) ?>
                    อ.<?= htmlspecialchars($v['par_amp']) ?>
                    จ.<?= htmlspecialchars($v['par_prov']) ?>
                </p>
            </div>
            <div style="display:flex; gap:16px; text-align:center;">
                <div style="background:rgba(255,255,255,0.15); padding:12px 20px; border-radius:12px;">
                    <div style="font-size:28px; font-weight:700;"><?= $v['total_plots'] ?></div>
                    <div style="font-size:11px; opacity:0.85;">แปลง</div>
                </div>
                <div style="background:rgba(255,255,255,0.15); padding:12px 20px; border-radius:12px;">
                    <div style="font-size:28px; font-weight:700;"><?= $v['total_villagers'] ?></div>
                    <div style="font-size:11px; opacity:0.85;">ราษฎร</div>
                </div>
                <div style="background:rgba(255,255,255,0.15); padding:12px 20px; border-radius:12px;">
                    <div style="font-size:28px; font-weight:700;"><?= number_format($v['total_area_rai'], 1) ?></div>
                    <div style="font-size:11px; opacity:0.85;">ไร่</div>
                </div>
            </div>
        </div>
        <!-- Progress -->
        <div style="margin-top:16px;">
            <div style="display:flex; justify-content:space-between; font-size:12px; opacity:0.85; margin-bottom:4px;">
                <span>สำรวจแล้ว <?= $v['surveyed_count'] ?> / <?= $v['total_plots'] ?> (<?= $pct ?>%)</span>
                <?php if ($v['issues_count'] > 0): ?>
                    <span>⚠️ <?= $v['issues_count'] ?> แปลงมีปัญหาข้อมูล</span>
                <?php endif; ?>
            </div>
            <div style="height:8px; background:rgba(255,255,255,0.2); border-radius:4px; overflow:hidden;">
                <div style="height:100%; width:<?= $pct ?>%; background:rgba(255,255,255,0.8); border-radius:4px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Grid: Map + Charts -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
    <!-- Map -->
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-pin-map-fill" style="color:var(--danger); margin-right:8px;"></i>แผนที่แปลงในหมู่บ้าน</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <div id="villageMap" style="height:380px; border-radius:0 0 12px 12px;"></div>
        </div>
    </div>

    <!-- Charts -->
    <div style="display:flex; flex-direction:column; gap:20px;">
        <div class="card" style="flex:1;">
            <div class="card-header">
                <h3><i class="bi bi-pie-chart-fill" style="color:var(--info); margin-right:8px;"></i>ประเภทการใช้ที่ดิน</h3>
            </div>
            <div class="card-body" style="display:flex; align-items:center; justify-content:center;">
                <canvas id="landUseChart" style="max-height:160px;"></canvas>
            </div>
        </div>
        <div class="card" style="flex:1;">
            <div class="card-header">
                <h3><i class="bi bi-bar-chart-fill" style="color:var(--warning); margin-right:8px;"></i>สถานะแปลง</h3>
            </div>
            <div class="card-body" style="display:flex; align-items:center; justify-content:center;">
                <canvas id="statusChart" style="max-height:160px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Plots Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="bi bi-list-ul" style="color:var(--primary-600); margin-right:8px;"></i>รายการแปลงที่ดิน
            <span class="badge badge-info" style="margin-left:8px;"><?= $v['total_plots'] ?></span>
        </h3>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>รหัสแปลง</th>
                        <th>เจ้าของ</th>
                        <th>พื้นที่</th>
                        <th>ประเภท</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plots as $i => $p): ?>
                        <tr>
                            <td><?= $offset + $i + 1 ?></td>
                            <td><strong style="color:<?= $themeColor ?>;"><?= htmlspecialchars($p['plot_code']) ?></strong></td>
                            <td>
                                <?= htmlspecialchars(($p['prefix'] ?? '') . $p['first_name'] . ' ' . $p['last_name']) ?>
                                <br><small class="text-muted" style="font-family:monospace;"><?= $p['id_card_number'] ?></small>
                            </td>
                            <td style="white-space:nowrap;">
                                <?= $p['area_rai'] ?> ไร่ <?= $p['area_ngan'] ?> งาน
                            </td>
                            <td><?= LAND_USE_LABELS[$p['land_use_type']] ?? $p['land_use_type'] ?></td>
                            <td>
                                <?php
                                $statusBadge = match ($p['status']) {
                                    'surveyed' => 'badge-success',
                                    'pending_review' => 'badge-warning',
                                    'temporary_permit' => 'badge-info',
                                    'must_relocate' => 'badge-danger',
                                    'disputed' => 'badge-orange',
                                    default => 'badge-gray',
                                };
                                ?>
                                <span class="badge <?= $statusBadge ?>"><?= PLOT_STATUS_LABELS[$p['status']] ?? $p['status'] ?></span>
                                <?php if (!empty($p['data_issues'])): ?>
                                    <i class="bi bi-exclamation-triangle-fill" style="color:#ef4444; font-size:12px;"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="index.php?page=plots&action=view&id=<?= $p['plot_id'] ?>" class="btn btn-secondary btn-sm" title="ดู"><i class="bi bi-eye"></i></a>
                                    <?php if ($p['latitude'] && $p['longitude']): ?>
                                        <a href="index.php?page=map&plot=<?= $p['plot_id'] ?>" class="btn btn-info btn-sm" title="แผนที่"><i class="bi bi-geo-alt"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <div class="pagination">
                    <?php if ($page_num > 1): ?>
                        <a href="index.php?page=villages&action=view&name=<?= urlencode($name) ?>&p=<?= $page_num - 1 ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    <?php for ($pg = max(1, $page_num - 2); $pg <= min($totalPages, $page_num + 2); $pg++): ?>
                        <?php if ($pg == $page_num): ?>
                            <span class="active"><?= $pg ?></span>
                        <?php else: ?>
                            <a href="index.php?page=villages&action=view&name=<?= urlencode($name) ?>&p=<?= $pg ?>"><?= $pg ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_num < $totalPages): ?>
                        <a href="index.php?page=villages&action=view&name=<?= urlencode($name) ?>&p=<?= $page_num + 1 ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// ===== Leaflet Map =====
const mapPlotsData = <?= json_encode($mapPlots) ?>;
const themeColor = '<?= $themeColor ?>';

if (mapPlotsData.length > 0) {
    const vMap = L.map('villageMap').setView([mapPlotsData[0].latitude, mapPlotsData[0].longitude], 14);

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, attribution: '© Esri'
    }).addTo(vMap);

    const bounds = L.latLngBounds();

    mapPlotsData.forEach(plot => {
        if (plot.polygon_coords) {
            try {
                const coords = typeof plot.polygon_coords === 'string' ? JSON.parse(plot.polygon_coords) : plot.polygon_coords;
                if (Array.isArray(coords) && coords.length > 2) {
                    const poly = L.polygon(coords, {
                        color: themeColor, weight: 2, fillOpacity: 0.25, fillColor: themeColor
                    }).addTo(vMap);
                    poly.bindPopup(`<b>${plot.plot_code}</b><br>${plot.area_rai} ไร่ ${plot.area_ngan} งาน`);
                    bounds.extend(poly.getBounds());
                }
            } catch (e) {}
        }

        if (plot.latitude && plot.longitude) {
            bounds.extend([plot.latitude, plot.longitude]);
        }
    });

    if (bounds.isValid()) {
        vMap.fitBounds(bounds, { padding: [30, 30] });
    }
} else {
    document.getElementById('villageMap').innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--gray-400);"><i class="bi bi-geo-alt" style="font-size:48px;"></i><p>ไม่มีข้อมูลพิกัด</p></div>';
}

// ===== Charts =====
const landUseLabels = <?= json_encode(array_map(fn($r) => LAND_USE_LABELS[$r['land_use_type']] ?? $r['land_use_type'], $landUseData)) ?>;
const landUseCounts = <?= json_encode(array_column($landUseData, 'cnt')) ?>;
const luColors = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];

new Chart(document.getElementById('landUseChart'), {
    type: 'doughnut',
    data: {
        labels: landUseLabels,
        datasets: [{ data: landUseCounts, backgroundColor: luColors.slice(0, landUseLabels.length), borderWidth: 0 }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'right', labels: { font: { family: 'Prompt', size: 11 }, padding: 8 } } }
    }
});

const statusLabels = <?= json_encode(array_map(fn($r) => PLOT_STATUS_LABELS[$r['status']] ?? $r['status'], $statusData)) ?>;
const statusCounts = <?= json_encode(array_column($statusData, 'cnt')) ?>;
const statusBarColors = {
    'สำรวจแล้ว': '#22c55e', 'รอตรวจสอบ': '#f59e0b', 'อนุญาตชั่วคราว': '#3b82f6',
    'ต้องอพยพ': '#ef4444', 'มีข้อพิพาท': '#f97316'
};
const stColors = statusLabels.map(l => statusBarColors[l] || '#6b7280');

new Chart(document.getElementById('statusChart'), {
    type: 'bar',
    data: {
        labels: statusLabels,
        datasets: [{ data: statusCounts, backgroundColor: stColors, borderRadius: 6, borderWidth: 0 }]
    },
    options: {
        responsive: true,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { family: 'Prompt', size: 11 } } },
            y: { grid: { display: false }, ticks: { font: { family: 'Prompt', size: 11 } } }
        }
    }
});
</script>
