<?php
/**
 * ฟอร์มเพิ่ม/แก้ไข แปลงที่ดินทำกิน
 */
require_once __DIR__ . '/../../controllers/AuthController.php';
AuthController::requireRole(ROLE_ADMIN, ROLE_OFFICER);

require_once __DIR__ . '/../../models/Plot.php';
require_once __DIR__ . '/../../models/Villager.php';

$id = $_GET['id'] ?? null;
$isEdit = ($action === 'edit' && $id);
$plot = $isEdit ? Plot::find((int) $id) : null;

if ($isEdit && !$plot) {
    echo '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> ไม่พบข้อมูลแปลง</div>';
    return;
}

$p = $plot ?? [];
$villagers = Villager::getAllForSelect();
$nextCode = $isEdit ? $p['plot_code'] : Plot::generateCode();

// Pre-select villager from URL param
$preVillagerId = $_GET['villager_id'] ?? ($p['villager_id'] ?? '');
?>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i>
        <?= $_SESSION['flash_error'] ?>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3><i class="bi bi-<?= $isEdit ? 'pencil-square' : 'map-fill' ?>"
                style="color:var(--primary-600); margin-right:8px;"></i>
            <?= $isEdit ? 'แก้ไขแปลงที่ดิน' : 'เพิ่มแปลงที่ดินใหม่' ?>
        </h3>
        <a href="index.php?page=plots" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> กลับ</a>
    </div>
    <div class="card-body">
        <form method="POST" action="index.php?page=plots&action=<?= $isEdit ? "edit&id=$id" : 'create' ?>"
            enctype="multipart/form-data" id="plotForm">
            <?= csrf_field() ?>

            <!-- ข้อมูลแปลง -->
            <h4
                style="font-size:15px; color:var(--gray-600); margin-bottom:16px; padding-bottom:8px; border-bottom:2px solid var(--gray-100);">
                <i class="bi bi-geo-alt"></i> ข้อมูลแปลง
            </h4>

            <div class="form-row">
                <div class="form-group">
                    <label>รหัสแปลง <span class="required">*</span></label>
                    <input type="text" name="plot_code" class="form-control" required
                        value="<?= htmlspecialchars($nextCode) ?>" style="font-weight:600; color:var(--primary-700);">
                </div>
                <div class="form-group">
                    <label>เจ้าของ/ผู้ครอบครอง <span class="required">*</span></label>
                    <select name="villager_id" class="form-control" required>
                        <option value="">-- เลือกราษฎร --</option>
                        <?php foreach ($villagers as $vl): ?>
                            <option value="<?= $vl['villager_id'] ?>" <?= $preVillagerId == $vl['villager_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($vl['prefix'] ?? '') . $vl['first_name'] . ' ' . $vl['last_name']) ?>
                                (
                                <?= $vl['id_card_number'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>ชื่ออุทยานแห่งชาติ</label>
                    <input type="text" name="park_name" class="form-control"
                        value="<?= htmlspecialchars($p['park_name'] ?? '') ?>" placeholder="อุทยานแห่งชาติ...">
                </div>
                <div class="form-group">
                    <label>โซน/เขตพื้นที่</label>
                    <input type="text" name="zone" class="form-control"
                        value="<?= htmlspecialchars($p['zone'] ?? '') ?>" placeholder="เช่น A1, B2">
                </div>
            </div>

            <!-- ขนาดพื้นที่ -->
            <h4
                style="font-size:15px; color:var(--gray-600); margin:24px 0 16px; padding-bottom:8px; border-bottom:2px solid var(--gray-100);">
                <i class="bi bi-rulers"></i> ขนาดพื้นที่
            </h4>

            <div class="form-row">
                <div class="form-group">
                    <label>ไร่</label>
                    <input type="number" name="area_rai" class="form-control" min="0" step="1"
                        value="<?= $p['area_rai'] ?? 0 ?>">
                </div>
                <div class="form-group">
                    <label>งาน</label>
                    <input type="number" name="area_ngan" class="form-control" min="0" max="3" step="1"
                        value="<?= $p['area_ngan'] ?? 0 ?>">
                </div>
                <div class="form-group">
                    <label>ตารางวา</label>
                    <input type="number" name="area_sqwa" class="form-control" min="0" step="0.25"
                        value="<?= $p['area_sqwa'] ?? 0 ?>">
                </div>
            </div>

            <!-- การใช้ที่ดิน -->
            <h4
                style="font-size:15px; color:var(--gray-600); margin:24px 0 16px; padding-bottom:8px; border-bottom:2px solid var(--gray-100);">
                <i class="bi bi-tree"></i> การใช้ที่ดิน
            </h4>

            <div class="form-row">
                <div class="form-group">
                    <label>ประเภทการใช้ที่ดิน <span class="required">*</span></label>
                    <select name="land_use_type" class="form-control" required>
                        <?php foreach (LAND_USE_LABELS as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($p['land_use_type'] ?? 'agriculture') === $key ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>พืชที่ปลูก / รายละเอียด</label>
                    <input type="text" name="crop_type" class="form-control"
                        value="<?= htmlspecialchars($p['crop_type'] ?? '') ?>" placeholder="ข้าว, ข้าวโพด, ลำไย...">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>ครอบครองตั้งแต่ (ปี พ.ศ.)</label>
                    <input type="text" name="occupation_since" class="form-control" maxlength="4"
                        value="<?= htmlspecialchars($p['occupation_since'] ?? '') ?>" placeholder="เช่น 2530">
                </div>
                <div class="form-group">
                    <label>สถานะ <span class="required">*</span></label>
                    <select name="status" class="form-control" required>
                        <?php foreach (PLOT_STATUS_LABELS as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($p['status'] ?? 'pending_review') === $key ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- พิกัด GPS -->
            <h4
                style="font-size:15px; color:var(--gray-600); margin:24px 0 16px; padding-bottom:8px; border-bottom:2px solid var(--gray-100);">
                <i class="bi bi-pin-map"></i> พิกัด GPS
            </h4>

            <div class="form-row">
                <div class="form-group">
                    <label>ละติจูด (Latitude)</label>
                    <input type="number" name="latitude" class="form-control" step="0.000001"
                        value="<?= $p['latitude'] ?? '' ?>" placeholder="เช่น 14.882350">
                </div>
                <div class="form-group">
                    <label>ลองจิจูด (Longitude)</label>
                    <input type="number" name="longitude" class="form-control" step="0.000001"
                        value="<?= $p['longitude'] ?? '' ?>" placeholder="เช่น 100.395420">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="getLocation()"
                        style="margin-top:2px;">
                        <i class="bi bi-crosshair"></i> ดึงพิกัดปัจจุบัน
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label>Polygon (JSON) — ขอบเขตแปลง</label>
                <textarea name="polygon_coords" class="form-control" rows="3"
                    placeholder='[[14.88, 100.39], [14.89, 100.39], ...]'
                    style="font-family:monospace; font-size:12px;"><?= htmlspecialchars($p['polygon_coords'] ?? '') ?></textarea>
            </div>

            <!-- ข้อมูลทางราชการ (Shapefile) -->
            <h4 style="font-size:15px; color:var(--gray-600); margin:24px 0 16px; padding-bottom:8px; border-bottom:2px solid var(--gray-100);">
                <i class="bi bi-bank"></i> ข้อมูลทางราชการ (สำหรับ Shapefile)
            </h4>

            <div class="form-row">
                <div class="form-group">
                    <label>รหัสป่าอนุรักษ์ (CODE_DNP)</label>
                    <input type="text" name="code_dnp" class="form-control"
                        value="<?= htmlspecialchars($p['code_dnp'] ?? '') ?>" placeholder="เช่น 1051" maxlength="10">
                </div>
                <div class="form-group">
                    <label>ชื่อย่อหมู่บ้าน (BAN_E)</label>
                    <input type="text" name="ban_e" class="form-control"
                        value="<?= htmlspecialchars($p['ban_e'] ?? '') ?>" placeholder="Eng 3 ตัว เช่น HPG" maxlength="10">
                </div>
                <div class="form-group">
                    <label>ลักษณะหมู่บ้าน (BAN_TYPE)</label>
                    <select name="ban_type" class="form-control">
                        <option value="">-- ระบุ --</option>
                        <option value="1" <?= ($p['ban_type'] ?? '') == 1 ? 'selected' : '' ?>>1-ในเขตป่า</option>
                        <option value="2" <?= ($p['ban_type'] ?? '') == 2 ? 'selected' : '' ?>>2-นอกเขตทำกินใน</option>
                        <option value="3" <?= ($p['ban_type'] ?? '') == 3 ? 'selected' : '' ?>>3-คาบเกี่ยว</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>รหัสที่ดินสำรวจ (SPAR_CODE/SPAR_NO)</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" name="spar_code" class="form-control"
                            value="<?= htmlspecialchars($p['spar_code'] ?? '') ?>" placeholder="รหัส">
                        <input type="text" name="spar_no" class="form-control" style="max-width:80px;"
                            value="<?= htmlspecialchars($p['spar_no'] ?? '') ?>" placeholder="ลำดับ">
                    </div>
                </div>
                <div class="form-group">
                    <label>เลขที่แปลงสำรวจ (NUM_SPAR)</label>
                    <input type="text" name="num_spar" class="form-control"
                        value="<?= htmlspecialchars($p['num_spar'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>รหัสแปลงบริหาร (APAR_CODE)</label>
                    <input type="text" name="apar_code" class="form-control"
                        value="<?= htmlspecialchars($p['apar_code'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>เลขโครงการ (APAR_NO)</label>
                    <input type="text" name="apar_no" class="form-control"
                        value="<?= htmlspecialchars($p['apar_no'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>ที่ดินเลขที่ (NUM_APAR)</label>
                    <input type="text" name="num_apar" class="form-control"
                        value="<?= htmlspecialchars($p['num_apar'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>อ้างอิง Shapefile (Target_FID)</label>
                    <input type="number" name="target_fid" class="form-control"
                        value="<?= htmlspecialchars($p['target_fid'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>ประเภทที่ดิน (PTYPE)</label>
                    <input type="text" name="ptype" class="form-control"
                        value="<?= htmlspecialchars($p['ptype'] ?? '') ?>" placeholder="ที่อยู่อาศัยและที่ทำกิน">
                </div>
                <div class="form-group">
                    <label>ระยะรอบแปลง (เมตร)</label>
                    <input type="number" name="perimeter" class="form-control" step="0.001"
                        value="<?= $p['perimeter'] ?? '' ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>ที่ตั้งแปลง (ชื่อบ้าน/หมู่)</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" name="par_ban" class="form-control"
                            value="<?= htmlspecialchars($p['par_ban'] ?? '') ?>" placeholder="บ้าน...">
                        <input type="text" name="par_moo" class="form-control" style="max-width:80px;"
                            value="<?= htmlspecialchars($p['par_moo'] ?? '') ?>" placeholder="หมู่">
                    </div>
                </div>
                <div class="form-group">
                    <label>ตำบล / อำเภอ</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" name="par_tam" class="form-control"
                            value="<?= htmlspecialchars($p['par_tam'] ?? '') ?>">
                        <input type="text" name="par_amp" class="form-control"
                            value="<?= htmlspecialchars($p['par_amp'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>จังหวัด</label>
                    <input type="text" name="par_prov" class="form-control"
                        value="<?= htmlspecialchars($p['par_prov'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label style="color:#dc2626;"><i class="bi bi-exclamation-triangle"></i> ปัญหาข้อมูล (Data Issues)</label>
                <textarea name="data_issues" class="form-control" rows="2"
                    style="border-color:#fee2e2; background:#fffafb;"
                    placeholder="ระบุปัญหาที่ต้องแก้ไขหรือตรวจสอบ..."><?= htmlspecialchars($p['data_issues'] ?? '') ?></textarea>
                <p style="font-size:11px; color:var(--gray-400); margin-top:4px;">* หากแก้ไขข้อมูลถูกต้องแล้ว ให้ลบข้อความในช่องนี้ออก</p>
            </div>

            <!-- เอกสาร -->
            <h4
                style="font-size:15px; color:var(--gray-600); margin:24px 0 16px; padding-bottom:8px; border-bottom:2px solid var(--gray-100);">
                <i class="bi bi-file-earmark"></i> เอกสารและรูปถ่าย
            </h4>

            <div class="form-row">
                <div class="form-group">
                    <label>วันที่สำรวจ</label>
                    <input type="date" name="survey_date" class="form-control"
                        value="<?= $p['survey_date'] ?? date('Y-m-d') ?>">
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:10px; padding-top:24px;">
                    <input type="checkbox" name="has_document" id="has_document" value="1" <?= ($p['has_document'] ?? 0) ? 'checked' : '' ?>>
                    <label for="has_document" style="margin:0;">มีเอกสารสิทธิ์</label>
                </div>
                <div class="form-group">
                    <label>ประเภทเอกสาร</label>
                    <input type="text" name="document_type" class="form-control"
                        value="<?= htmlspecialchars($p['document_type'] ?? '') ?>" placeholder="เช่น สปก., ภบท.5">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>รูปถ่ายแปลง (jpg, png)</label>
                    <input type="file" name="plot_image" class="form-control" accept="image/*">
                    <?php if (!empty($p['plot_image_path'])): ?>
                        <div style="margin-top:8px;">
                            <img src="<?= htmlspecialchars($p['plot_image_path']) ?>"
                                style="max-width:200px; border-radius:8px;">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>เอกสารแนบเพิ่มเติม</label>
                    <input type="file" name="documents[]" class="form-control" multiple
                        accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                    <select name="doc_category" class="form-control mt-1" style="max-width:220px;">
                        <option value="boundary_image">ภาพขอบเขต</option>
                        <option value="permit">หนังสืออนุญาต</option>
                        <option value="map">แผนที่</option>
                        <option value="other">อื่นๆ</option>
                    </select>
                </div>
            </div>

            <!-- หมายเหตุ -->
            <div class="form-group">
                <label>หมายเหตุ</label>
                <textarea name="notes" class="form-control"
                    rows="3"><?= htmlspecialchars($p['notes'] ?? '') ?></textarea>
            </div>

            <!-- Buttons -->
            <div
                style="display:flex; gap:12px; margin-top:24px; padding-top:20px; border-top:1px solid var(--gray-100);">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-<?= $isEdit ? 'check-lg' : 'plus-lg' ?>"></i>
                    <?= $isEdit ? 'บันทึกการแก้ไข' : 'เพิ่มแปลงที่ดิน' ?>
                </button>
                <a href="index.php?page=plots" class="btn btn-secondary">ยกเลิก</a>
            </div>
        </form>

        <?php if ($isEdit && $_SESSION['role'] === ROLE_ADMIN): ?>
            <form method="POST" action="index.php?page=plots&action=delete&id=<?= $id ?>"
                style="margin-top:12px; padding-top:16px; border-top:1px solid var(--gray-100); display:flex; justify-content:flex-end;"
                onsubmit="return confirmDelete('ลบแปลงที่ดินนี้?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> ลบแปลง</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    function getLocation() {
        if (!navigator.geolocation) { showToast('เบราว์เซอร์ไม่รองรับ GPS', 'error'); return; }
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                document.querySelector('[name="latitude"]').value = pos.coords.latitude.toFixed(6);
                document.querySelector('[name="longitude"]').value = pos.coords.longitude.toFixed(6);
                showToast('ดึงพิกัดสำเร็จ!', 'success');
            },
            (err) => showToast('ไม่สามารถดึงพิกัดได้: ' + err.message, 'error')
        );
    }
</script>