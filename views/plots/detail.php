<?php
/**
 * รายละเอียดแปลงที่ดิน
 */
require_once __DIR__ . '/../../models/Plot.php';
require_once __DIR__ . '/../../models/Document.php';

$id = (int) ($_GET['id'] ?? 0);
$plot = Plot::find($id);

if (!$plot) {
    echo '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> ไม่พบข้อมูลแปลง</div>';
    return;
}

$documents = Document::getByRelated('plot', $id);
$p = $plot;
$statusColor = PLOT_STATUS_COLORS[$p['status']] ?? '#6b7280';
?>

<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success" data-dismiss><i class="bi bi-check-circle-fill"></i>
        <?= $_SESSION['flash_success'] ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<!-- Header -->
<div class="d-flex justify-between align-center mb-3">
    <a href="index.php?page=plots" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> กลับ</a>
    <div class="d-flex gap-1">
        <?php if ($p['latitude'] && $p['longitude']): ?>
            <a href="index.php?page=map&plot=<?= $id ?>" class="btn btn-info btn-sm"><i class="bi bi-geo-alt"></i>
                ดูแผนที่</a>
        <?php endif; ?>
        <?php if ($_SESSION['role'] !== ROLE_VIEWER): ?>
            <a href="index.php?page=plots&action=edit&id=<?= $id ?>" class="btn btn-warning btn-sm"><i
                    class="bi bi-pencil"></i> แก้ไข</a>
        <?php endif; ?>
    </div>
</div>

<!-- Plot Code Header Badge -->
<div class="card mb-3"
    style="background: linear-gradient(135deg, var(--primary-700) 0%, var(--primary-500) 100%); color:white; border:none;">
    <div class="card-body" style="padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
                <h2 style="font-size:24px; margin-bottom:4px;">🗺️
                    <?= htmlspecialchars($p['plot_code']) ?>
                </h2>
                <p style="opacity:0.9;">
                    เจ้าของ:
                    <?= htmlspecialchars(($p['prefix'] ?? '') . $p['first_name'] . ' ' . $p['last_name']) ?>
                    <a href="index.php?page=villagers&action=view&id=<?= $p['villager_id'] ?>" style="color:#bef;">
                        (
                        <?= $p['id_card_number'] ?>) →
                    </a>
                </p>
            </div>
            <div style="text-align:right;">
                <span
                    style="background:<?= $statusColor ?>; padding:6px 16px; border-radius:20px; font-weight:600; font-size:14px;">
                    <?= PLOT_STATUS_LABELS[$p['status']] ?? $p['status'] ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
    <!-- Left: ข้อมูลแปลง -->
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-info-circle-fill" style="color:var(--info);margin-right:8px;"></i>ข้อมูลแปลง</h3>
        </div>
        <div class="card-body">
            <table style="width:100%;">
                <tr>
                    <td style="padding:8px 0;color:var(--gray-500);width:140px;">อุทยาน</td>
                    <td style="padding:8px 0;">
                        <?= htmlspecialchars($p['park_name'] ?? '-') ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:var(--gray-500);">โซน</td>
                    <td>
                        <?= htmlspecialchars($p['zone'] ?? '-') ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:var(--gray-500);">พื้นที่</td>
                    <td><strong>
                            <?= $p['area_rai'] ?> ไร่
                            <?= $p['area_ngan'] ?> งาน
                            <?= $p['area_sqwa'] ?> ตร.วา
                        </strong></td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:var(--gray-500);">ประเภทการใช้</td>
                    <td><span class="badge badge-info">
                            <?= LAND_USE_LABELS[$p['land_use_type']] ?? $p['land_use_type'] ?>
                        </span></td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:var(--gray-500);">พืชที่ปลูก</td>
                    <td>
                        <?= htmlspecialchars($p['crop_type'] ?? '-') ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:var(--gray-500);">ครอบครองตั้งแต่</td>
                    <td>
                        <?= htmlspecialchars($p['occupation_since'] ?? '-') ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:var(--gray-500);">เอกสารสิทธิ์</td>
                    <td>
                        <?= $p['has_document'] ? '<span class="badge badge-success">มี</span> ' . htmlspecialchars($p['document_type'] ?? '') : '<span class="badge badge-gray">ไม่มี</span>' ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:var(--gray-500);">วันที่สำรวจ</td>
                    <td>
                        <?= $p['survey_date'] ? date('d/m/Y', strtotime($p['survey_date'])) : '-' ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:var(--gray-500);">ผู้สำรวจ</td>
                    <td>
                        <?= htmlspecialchars($p['surveyor_name'] ?? '-') ?>
                    </td>
                </tr>
            </table>
            
            <h4 style="font-size:14px; color:var(--gray-600); margin:20px 0 10px; border-bottom:1px solid var(--gray-200); padding-bottom:5px;">
                ข้อมูลทางราชการ
            </h4>
            <table style="width:100%;">
                <tr>
                    <td style="padding:4px 0;color:var(--gray-500);width:140px;">รหัสป่า (CODE_DNP)</td>
                    <td><?= htmlspecialchars($p['code_dnp'] ?? '-') ?></td>
                </tr>
                <tr>
                    <td style="padding:4px 0;color:var(--gray-500);">ตัวย่อบ้าน (BAN_E)</td>
                    <td><?= htmlspecialchars($p['ban_e'] ?? '-') ?></td>
                </tr>
                <tr>
                    <td style="padding:4px 0;color:var(--gray-500);">เลขที่แปลงสำรวจ</td>
                    <td><?= htmlspecialchars($p['num_spar'] ?? '-') ?> (ลำดับ: <?= htmlspecialchars($p['spar_no'] ?? '-') ?>)</td>
                </tr>
                <tr>
                    <td style="padding:4px 0;color:var(--gray-500);">รหัสบริหาร / แปลง</td>
                    <td><?= htmlspecialchars($p['apar_code'] ?? '-') ?> / <?= htmlspecialchars($p['num_apar'] ?? '-') ?></td>
                </tr>
                <tr>
                    <td style="padding:4px 0;color:var(--gray-500);">ที่ตั้งแปลง (บ้าน/หมู่)</td>
                    <td><?= htmlspecialchars($p['par_ban'] ?? '-') ?> ม.<?= htmlspecialchars($p['par_moo'] ?? '-') ?></td>
                </tr>
                <tr>
                    <td style="padding:4px 0;color:var(--gray-500);">ตำบล/อำเภอ/จังหวัด</td>
                    <td><?= htmlspecialchars($p['par_tam'] ?? '-') ?> / <?= htmlspecialchars($p['par_amp'] ?? '-') ?> / <?= htmlspecialchars($p['par_prov'] ?? '-') ?></td>
                </tr>
                <tr>
                    <td style="padding:4px 0;color:var(--gray-500);">เส้นรอบวง</td>
                    <td><?= $p['perimeter'] ? number_format($p['perimeter'], 2) . ' ม.' : '-' ?></td>
                </tr>
                <tr>
                    <td style="padding:4px 0;color:var(--gray-500);">ประเภทที่ดิน (PTYPE)</td>
                    <td><?= htmlspecialchars($p['ptype'] ?? '-') ?> (BAN_TYPE: <?= htmlspecialchars($p['ban_type'] ?? '-') ?>)</td>
                </tr>
            </table>

            <?php if ($p['data_issues']): ?>
                <div style="margin-top:16px; padding:12px; background:#fef2f2; border:1px solid #fee2e2; border-radius:8px; font-size:13px; color:#991b1b;">
                    <strong style="display:block; margin-bottom:4px;"><i class="bi bi-exclamation-triangle-fill"></i> ปัญหาข้อมูลที่พบ:</strong>
                    <?= nl2br(htmlspecialchars($p['data_issues'])) ?>
                </div>
            <?php endif; ?>

            <?php if ($p['notes']): ?>
                <div style="margin-top:16px; padding:12px; background:var(--gray-50); border-radius:8px; font-size:13px;">
                    <strong>หมายเหตุ:</strong>
                    <?= nl2br(htmlspecialchars($p['notes'])) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Map preview + Image -->
    <div>
        <!-- Plot Image -->
        <?php if ($p['plot_image_path']): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <h3><i class="bi bi-image" style="color:var(--success); margin-right:8px;"></i>รูปถ่ายแปลง</h3>
                </div>
                <div class="card-body" style="padding:12px; text-align:center;">
                    <a href="<?= htmlspecialchars($p['plot_image_path']) ?>" target="_blank">
                        <img src="<?= htmlspecialchars($p['plot_image_path']) ?>"
                            style="max-width:100%; border-radius:10px;">
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Mini Map -->
        <?php if ($p['latitude'] && $p['longitude']): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="bi bi-pin-map-fill" style="color:var(--danger); margin-right:8px;"></i>ตำแหน่ง GPS</h3>
                </div>
                <div class="card-body" style="padding:0;">
                    <div id="detailMap" style="height:280px; border-radius:0 0 12px 12px;"></div>
                    <div style="padding:10px 16px; font-size:12px; color:var(--gray-500);">
                        📍
                        <?= $p['latitude'] ?>,
                        <?= $p['longitude'] ?>
                    </div>
                </div>
            </div>
            <script>
                const dMap = L.map('detailMap').setView([<?= $p['latitude'] ?>, <?= $p['longitude'] ?>], 15);
                L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    maxZoom: 19, attribution: '© Esri'
                }).addTo(dMap);
                L.marker([<?= $p['latitude'] ?>, <?= $p['longitude'] ?>]).addTo(dMap)
                    .bindPopup('<b><?= htmlspecialchars($p['plot_code']) ?></b>').openPopup();

                <?php if ($p['polygon_coords']): ?>
                    try {
                        const polyCoords = <?= $p['polygon_coords'] ?>;
                        if (Array.isArray(polyCoords) && polyCoords.length > 2) {
                            L.polygon(polyCoords, { color: '<?= $statusColor ?>', weight: 2, fillOpacity: 0.3 }).addTo(dMap);
                        }
                    } catch (e) { }
                <?php endif; ?>
            </script>
        <?php endif; ?>
    </div>
</div>

<!-- Documents -->
<div class="card mt-3">
    <div class="card-header">
        <h3><i class="bi bi-folder-fill" style="color:var(--warning); margin-right:8px;"></i>เอกสาร
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
            <div class="empty-state" style="padding:30px;"><i class="bi bi-folder2-open"></i>
                <p>ยังไม่มีเอกสาร</p>
            </div>
        <?php else: ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:12px;">
                <?php foreach ($documents as $doc): ?>
                    <div style="border:1px solid var(--gray-200); border-radius:10px; padding:12px; text-align:center;">
                        <?php if (in_array($doc['file_type'], ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank">
                                <img src="<?= htmlspecialchars($doc['file_path']) ?>"
                                    style="width:100%; height:100px; object-fit:cover; border-radius:6px; margin-bottom:8px;">
                            </a>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank"
                                style="display:block; height:100px; display:flex; align-items:center; justify-content:center; background:var(--gray-50); border-radius:6px; margin-bottom:8px;">
                                <i class="bi bi-file-earmark-pdf" style="font-size:36px; color:var(--gray-400);"></i>
                            </a>
                        <?php endif; ?>
                        <p
                            style="font-size:11px; color:var(--gray-500); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= htmlspecialchars($doc['file_name']) ?>
                        </p>
                        <?php if ($_SESSION['role'] !== ROLE_VIEWER): ?>
                            <form method="POST" action="index.php?page=documents&action=delete&id=<?= $doc['doc_id'] ?>"
                                style="margin-top:4px;" onsubmit="return confirmDelete('ลบ?')">
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
                <input type="hidden" name="related_type" value="plot">
                <input type="hidden" name="related_id" value="<?= $id ?>">
                <div class="form-group">
                    <label>เลือกไฟล์</label>
                    <input type="file" name="file" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>หมวดหมู่</label>
                    <select name="doc_category" class="form-control">
                        <option value="boundary_image">ภาพขอบเขต</option>
                        <option value="permit">หนังสืออนุญาต</option>
                        <option value="map">แผนที่</option>
                        <option value="photo">ภาพถ่าย</option>
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