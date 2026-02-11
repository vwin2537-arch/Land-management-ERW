<?php
/**
 * รายละเอียดราษฎร
 */
require_once __DIR__ . '/../../models/Villager.php';
require_once __DIR__ . '/../../models/Plot.php';
require_once __DIR__ . '/../../models/Document.php';

$id = (int) ($_GET['id'] ?? 0);
$villager = Villager::find($id);

if (!$villager) {
    echo '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> ไม่พบข้อมูลราษฎร</div>';
    return;
}

$plots = Plot::getByVillager($id);
$documents = Document::getByRelated('villager', $id);
$v = $villager;
?>

<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success" data-dismiss><i class="bi bi-check-circle-fill"></i>
        <?= $_SESSION['flash_success'] ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<!-- Header -->
<div class="d-flex justify-between align-center mb-3">
    <a href="index.php?page=villagers" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> กลับ</a>
    <div class="d-flex gap-1">
        <?php if ($_SESSION['role'] !== ROLE_VIEWER): ?>
            <a href="index.php?page=villagers&action=edit&id=<?= $id ?>" class="btn btn-warning btn-sm">
                <i class="bi bi-pencil"></i> แก้ไข
            </a>
            <a href="index.php?page=plots&action=create&villager_id=<?= $id ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> เพิ่มแปลงที่ดิน
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="villager-detail-grid">
    <!-- Left: ข้อมูลส่วนตัว -->
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-person-vcard-fill" style="color:var(--primary-600); margin-right:8px;"></i>ข้อมูลราษฎร
            </h3>
        </div>
        <div class="card-body">
            <div style="display:flex; gap:20px; margin-bottom:20px;">
                <?php if ($v['photo_path']): ?>
                    <img src="<?= htmlspecialchars($v['photo_path']) ?>"
                        style="width:100px; height:120px; object-fit:cover; border-radius:12px; border:2px solid var(--gray-200);">
                <?php else: ?>
                    <div
                        style="width:100px; height:120px; border-radius:12px; background:var(--gray-100); display:flex; align-items:center; justify-content:center; color:var(--gray-400); font-size:36px;">
                        <i class="bi bi-person"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <h2 style="font-size:20px; margin-bottom:4px;">
                        <?= htmlspecialchars(($v['prefix'] ?? '') . $v['first_name'] . ' ' . $v['last_name']) ?>
                    </h2>
                    <p style="font-family:monospace; color:var(--gray-500); letter-spacing:1px; font-size:15px;">
                        <?= htmlspecialchars($v['id_card_number']) ?>
                    </p>
                </div>
            </div>

            <table style="width:100%;">
                <tr>
                    <td style="padding:8px 0; color:var(--gray-500); width:130px;">วันเกิด</td>
                    <td style="padding:8px 0;">
                        <?= $v['birth_date'] ? date('d/m/', strtotime($v['birth_date'])) . (date('Y', strtotime($v['birth_date'])) + 543) : '-' ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0; color:var(--gray-500);">โทรศัพท์</td>
                    <td style="padding:8px 0;">
                        <?= htmlspecialchars($v['phone'] ?? '-') ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0; color:var(--gray-500);">ที่อยู่</td>
                    <td style="padding:8px 0;">
                        <?= htmlspecialchars($v['address'] ?? '-') ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0; color:var(--gray-500);">หมู่บ้าน</td>
                    <td style="padding:8px 0;">หมู่
                        <?= htmlspecialchars($v['village_no'] ?? '-') ?>
                        <?= htmlspecialchars($v['village_name'] ?? '') ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0; color:var(--gray-500);">ตำบล/อำเภอ</td>
                    <td style="padding:8px 0;">
                        <?= htmlspecialchars($v['sub_district'] ?? '-') ?> /
                        <?= htmlspecialchars($v['district'] ?? '-') ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0; color:var(--gray-500);">จังหวัด</td>
                    <td style="padding:8px 0;">
                        <?= htmlspecialchars($v['province'] ?? '-') ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0; color:var(--gray-500);">บันทึกเมื่อ</td>
                    <td style="padding:8px 0;">
                        <?= date('d/m/Y H:i', strtotime($v['created_at'])) ?>
                    </td>
                </tr>
            </table>

            <?php if ($v['notes']): ?>
                <div
                    style="margin-top:16px; padding:12px; background:var(--gray-50); border-radius:8px; font-size:13px; color:var(--gray-600);">
                    <strong>หมายเหตุ:</strong>
                    <?= nl2br(htmlspecialchars($v['notes'])) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: แปลงที่ดิน -->
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-map-fill" style="color:var(--info); margin-right:8px;"></i>แปลงที่ดินทำกิน
                <span class="badge badge-info" style="margin-left:8px;">
                    <?= count($plots) ?> แปลง
                </span>
            </h3>
            <?php
            // Calculate Total Area
            $sumRai = 0; $sumNgan = 0; $sumSqWa = 0;
            foreach ($plots as $p) {
                $sumRai += $p['area_rai'];
                $sumNgan += $p['area_ngan'];
                $sumSqWa += $p['area_sqwa'];
            }
            $sumNgan += floor($sumSqWa / 100);
            $sumSqWa = fmod($sumSqWa, 100);
            $sumRai += floor($sumNgan / 4);
            $sumNgan = $sumNgan % 4;
            ?>
            <div style="font-size:14px; color:var(--gray-600); margin-top:4px;">
                รวมพื้นที่: <strong style="color:var(--primary-700);"><?= number_format($sumRai) ?></strong> ไร่ 
                <strong><?= $sumNgan ?></strong> งาน 
                <strong><?= number_format($sumSqWa, 1) ?></strong> ตร.วา
            </div>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($plots)): ?>
                <div class="empty-state" style="padding:40px;">
                    <i class="bi bi-map"></i>
                    <p>ยังไม่มีแปลงที่ดิน</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>รหัสแปลง</th>
                                <th>พื้นที่</th>
                                <th>การใช้</th>
                                <th>สถานะ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($plots as $p): ?>
                                <tr>
                                    <td><strong style="color:var(--primary-700);">
                                            <?= htmlspecialchars($p['plot_code']) ?>
                                        </strong></td>
                                    <td style="white-space:nowrap;">
                                        <?= $p['area_rai'] ?> ไร่
                                        <?= $p['area_ngan'] ?> งาน
                                        <?= $p['area_sqwa'] ?> วา
                                    </td>
                                    <td>
                                        <?= LAND_USE_LABELS[$p['land_use_type']] ?? $p['land_use_type'] ?>
                                    </td>
                                    <td>
                                        <?php $sb = match ($p['status']) { 'surveyed' => 'badge-success', 'pending_review' => 'badge-warning', 'temporary_permit' => 'badge-info', 'must_relocate' => 'badge-danger', 'disputed' => 'badge-orange', default => 'badge-gray'}; ?>
                                        <span class="badge <?= $sb ?>">
                                            <?= PLOT_STATUS_LABELS[$p['status']] ?? $p['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="index.php?page=plots&action=view&id=<?= $p['plot_id'] ?>"
                                            class="btn btn-secondary btn-sm"><i class="bi bi-eye"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- แผนที่ภาพรวมแปลงทั้งหมดของราษฎร -->
<?php
$plotsWithCoords = array_filter($plots, fn($p) => !empty($p['latitude']) && !empty($p['longitude']));
if (!empty($plotsWithCoords)):
?>
<div class="card mt-3">
    <div class="card-header">
        <h3><i class="bi bi-pin-map-fill" style="color:var(--danger); margin-right:8px;"></i>แผนที่ภาพรวมแปลงที่ดิน
            <span class="badge badge-info" style="margin-left:8px;"><?= count($plotsWithCoords) ?> แปลง</span>
        </h3>
    </div>
    <div class="card-body" style="padding:0;">
        <div id="villagerPlotsMap" style="height:420px; border-radius:0 0 12px 12px;"></div>
    </div>
</div>

<script>
(function() {
    const plotsData = <?= json_encode(array_values(array_map(function($p) {
        return [
            'id' => $p['plot_id'],
            'code' => $p['plot_code'],
            'lat' => (float)$p['latitude'],
            'lng' => (float)$p['longitude'],
            'area_rai' => $p['area_rai'],
            'area_ngan' => $p['area_ngan'],
            'status' => $p['status'],
            'land_use' => LAND_USE_LABELS[$p['land_use_type']] ?? $p['land_use_type'],
            'polygon' => $p['polygon_coords'] ?? null,
        ];
    }, $plotsWithCoords))) ?>;

    const statusColors = {
        'surveyed': '#22c55e',
        'pending_review': '#f59e0b',
        'temporary_permit': '#3b82f6',
        'must_relocate': '#ef4444',
        'disputed': '#f97316'
    };

    const statusLabels = <?= json_encode(PLOT_STATUS_LABELS) ?>;

    const map = L.map('villagerPlotsMap').setView([plotsData[0].lat, plotsData[0].lng], 14);
    
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, attribution: '© Esri'
    }).addTo(map);

    const bounds = L.latLngBounds();
    const plotColors = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316'];

    plotsData.forEach((plot, idx) => {
        const color = plotColors[idx % plotColors.length];
        const statusColor = statusColors[plot.status] || '#6b7280';
        const statusLabel = statusLabels[plot.status] || plot.status;

        // วาด Polygon จากข้อมูล
        if (plot.polygon) {
            try {
                const coords = typeof plot.polygon === 'string' ? JSON.parse(plot.polygon) : plot.polygon;
                if (Array.isArray(coords) && coords.length > 2) {
                    const poly = L.polygon(coords, {
                        color: color,
                        weight: 3,
                        fillOpacity: 0.3,
                        fillColor: color
                    }).addTo(map);
                    
                    poly.bindPopup(`
                        <div style="font-family:Prompt; min-width:180px;">
                            <strong style="font-size:14px; color:${color};">${plot.code}</strong>
                            <hr style="margin:6px 0; border:0; border-top:1px solid #eee;">
                            <div style="font-size:12px;">
                                <div style="margin:3px 0;">📐 ${plot.area_rai} ไร่ ${plot.area_ngan} งาน</div>
                                <div style="margin:3px 0;">🌾 ${plot.land_use}</div>
                                <div style="margin:3px 0;">
                                    <span style="display:inline-block; padding:2px 8px; border-radius:4px; background:${statusColor}; color:white; font-size:11px;">${statusLabel}</span>
                                </div>
                            </div>
                            <a href="index.php?page=plots&action=view&id=${plot.id}" 
                               style="display:block; text-align:center; margin-top:8px; padding:4px; background:${color}; color:white; border-radius:4px; text-decoration:none; font-size:12px;">
                                ดูรายละเอียดแปลง →
                            </a>
                        </div>
                    `);
                    bounds.extend(poly.getBounds());
                }
            } catch (e) {
                console.warn('Polygon parse error for', plot.code, e);
            }
        }

        // วาง Marker ที่จุดศูนย์กลาง
        const marker = L.circleMarker([plot.lat, plot.lng], {
            radius: 7,
            color: '#ffffff',
            weight: 2,
            fillColor: color,
            fillOpacity: 1
        }).addTo(map);
        
        marker.bindTooltip(plot.code, {
            permanent: false,
            direction: 'top',
            className: 'plot-label',
            offset: [0, -8]
        });

        bounds.extend([plot.lat, plot.lng]);
    });

    if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
    }
})();
</script>
<?php endif; ?>
<!-- Documents Section -->
<div class="card mt-3">
    <div class="card-header">
        <h3><i class="bi bi-folder-fill" style="color:var(--warning); margin-right:8px;"></i>เอกสาร/ภาพถ่าย
            <span class="badge badge-warning" style="margin-left:8px;">
                <?= count($documents) ?>
            </span>
        </h3>
        <?php if ($_SESSION['role'] !== ROLE_VIEWER): ?>
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('uploadModal').classList.add('show')">
                <i class="bi bi-upload"></i> อัปโหลด
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($documents)): ?>
            <div class="empty-state" style="padding:30px;">
                <i class="bi bi-folder2-open"></i>
                <p>ยังไม่มีเอกสาร</p>
            </div>
        <?php else: ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:12px;">
                <?php foreach ($documents as $doc): ?>
                    <div
                        style="border:1px solid var(--gray-200); border-radius:10px; padding:12px; text-align:center; transition:var(--transition);">
                        <?php if (in_array($doc['file_type'], ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank">
                                <img src="<?= htmlspecialchars($doc['file_path']) ?>"
                                    style="width:100%; height:100px; object-fit:cover; border-radius:6px; margin-bottom:8px;">
                            </a>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank">
                                <div
                                    style="height:100px; display:flex; align-items:center; justify-content:center; background:var(--gray-50); border-radius:6px; margin-bottom:8px;">
                                    <i class="bi bi-file-earmark-<?= $doc['file_type'] === 'pdf' ? 'pdf' : 'text' ?>"
                                        style="font-size:36px; color:var(--gray-400);"></i>
                                </div>
                            </a>
                        <?php endif; ?>
                        <p
                            style="font-size:11px; color:var(--gray-500); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= htmlspecialchars($doc['file_name']) ?>
                        </p>
                        <?php if ($_SESSION['role'] !== ROLE_VIEWER): ?>
                            <form method="POST" action="index.php?page=documents&action=delete&id=<?= $doc['doc_id'] ?>"
                                style="margin-top:4px;" onsubmit="return confirmDelete('ลบเอกสารนี้?')">
                                <button class="btn btn-danger btn-sm" style="padding:4px 10px; font-size:11px;"><i
                                        class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal">
        <div class="modal-header">
            <h3>อัปโหลดเอกสาร</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('show')"><i
                    class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" action="index.php?page=documents&action=upload" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="related_type" value="villager">
                <input type="hidden" name="related_id" value="<?= $id ?>">
                <div class="form-group">
                    <label>เลือกไฟล์</label>
                    <input type="file" name="file" class="form-control" required
                        accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                </div>
                <div class="form-group">
                    <label>หมวดหมู่</label>
                    <select name="doc_category" class="form-control">
                        <option value="id_copy">สำเนาบัตร ปชช.</option>
                        <option value="photo">ภาพถ่าย</option>
                        <option value="permit">หนังสืออนุญาต</option>
                        <option value="map">แผนที่</option>
                        <option value="other">อื่นๆ</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>คำอธิบาย</label>
                    <input type="text" name="description" class="form-control" placeholder="(ไม่จำเป็น)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    onclick="this.closest('.modal-overlay').classList.remove('show')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> อัปโหลด</button>
            </div>
        </form>
    </div>
</div>