<?php
/**
 * ฟอร์มเพิ่ม/แก้ไข ราษฎร
 */
require_once __DIR__ . '/../../controllers/AuthController.php';
AuthController::requireRole(ROLE_ADMIN, ROLE_OFFICER);

require_once __DIR__ . '/../../models/Villager.php';

$id = $_GET['id'] ?? null;
$isEdit = ($action === 'edit' && $id);
$villager = $isEdit ? Villager::find((int) $id) : null;

if ($isEdit && !$villager) {
    echo '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> ไม่พบข้อมูลราษฎร</div>';
    return;
}

$v = $villager ?? []; // shorthand
?>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i>
        <?= $_SESSION['flash_error'] ?>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3><i class="bi bi-<?= $isEdit ? 'pencil-square' : 'person-plus-fill' ?>"
                style="color:var(--primary-600); margin-right:8px;"></i>
            <?= $isEdit ? 'แก้ไขข้อมูลราษฎร' : 'เพิ่มราษฎรใหม่' ?>
        </h3>
        <a href="index.php?page=villagers" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> กลับ</a>
    </div>
    <div class="card-body">
        <form method="POST" action="index.php?page=villagers&action=<?= $isEdit ? "edit&id=$id" : 'create' ?>"
            enctype="multipart/form-data" id="villagerForm">

            <!-- ข้อมูลส่วนตัว -->
            <h4
                style="font-size:15px; color:var(--gray-600); margin-bottom:16px; padding-bottom:8px; border-bottom:2px solid var(--gray-100);">
                <i class="bi bi-person-vcard"></i> ข้อมูลส่วนตัว
            </h4>

            <div class="form-row">
                <div class="form-group">
                    <label>เลขบัตรประชาชน <span class="required">*</span></label>
                    <input type="text" name="id_card_number" class="form-control" maxlength="13" pattern="[0-9]{13}"
                        title="กรุณากรอกเลข 13 หลัก" value="<?= htmlspecialchars($v['id_card_number'] ?? '') ?>"
                        required placeholder="X-XXXX-XXXXX-XX-X" style="font-family:monospace; letter-spacing:2px;">
                </div>
                <div class="form-group">
                    <label>คำนำหน้า</label>
                    <select name="prefix" class="form-control">
                        <option value="">-- เลือก --</option>
                        <?php foreach (['นาย', 'นาง', 'นางสาว', 'เด็กชาย', 'เด็กหญิง'] as $p): ?>
                            <option value="<?= $p ?>" <?= ($v['prefix'] ?? '') === $p ? 'selected' : '' ?>>
                                <?= $p ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>ชื่อ <span class="required">*</span></label>
                    <input type="text" name="first_name" class="form-control" required
                        value="<?= htmlspecialchars($v['first_name'] ?? '') ?>" placeholder="ชื่อจริง">
                </div>
                <div class="form-group">
                    <label>นามสกุล <span class="required">*</span></label>
                    <input type="text" name="last_name" class="form-control" required
                        value="<?= htmlspecialchars($v['last_name'] ?? '') ?>" placeholder="นามสกุล">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>วันเกิด</label>
                    <input type="date" name="birth_date" class="form-control" value="<?= $v['birth_date'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>โทรศัพท์</label>
                    <input type="tel" name="phone" class="form-control" maxlength="15"
                        value="<?= htmlspecialchars($v['phone'] ?? '') ?>" placeholder="0XX-XXX-XXXX">
                </div>
            </div>

            <!-- ที่อยู่ -->
            <h4
                style="font-size:15px; color:var(--gray-600); margin:24px 0 16px; padding-bottom:8px; border-bottom:2px solid var(--gray-100);">
                <i class="bi bi-house-door"></i> ที่อยู่
            </h4>

            <div class="form-group">
                <label>ที่อยู่</label>
                <textarea name="address" class="form-control" rows="2"
                    placeholder="บ้านเลขที่ ซอย ถนน..."><?= htmlspecialchars($v['address'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>หมู่ที่</label>
                    <input type="text" name="village_no" class="form-control"
                        value="<?= htmlspecialchars($v['village_no'] ?? '') ?>" placeholder="หมู่ที่">
                </div>
                <div class="form-group">
                    <label>ชื่อหมู่บ้าน/ชุมชน</label>
                    <input type="text" name="village_name" class="form-control"
                        value="<?= htmlspecialchars($v['village_name'] ?? '') ?>" placeholder="บ้าน...">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>ตำบล</label>
                    <input type="text" name="sub_district" class="form-control"
                        value="<?= htmlspecialchars($v['sub_district'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>อำเภอ</label>
                    <input type="text" name="district" class="form-control"
                        value="<?= htmlspecialchars($v['district'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>จังหวัด</label>
                    <input type="text" name="province" class="form-control"
                        value="<?= htmlspecialchars($v['province'] ?? '') ?>">
                </div>
            </div>

            <!-- รูปถ่ายและเอกสาร -->
            <h4
                style="font-size:15px; color:var(--gray-600); margin:24px 0 16px; padding-bottom:8px; border-bottom:2px solid var(--gray-100);">
                <i class="bi bi-camera"></i> รูปถ่ายและเอกสาร
            </h4>

            <div class="form-row">
                <div class="form-group">
                    <label>รูปถ่าย (jpg, png, webp)</label>
                    <input type="file" name="photo" class="form-control" accept="image/*">
                    <?php if (!empty($v['photo_path'])): ?>
                        <div style="margin-top:8px;">
                            <img src="<?= htmlspecialchars($v['photo_path']) ?>"
                                style="max-width:120px; border-radius:8px; border:1px solid var(--gray-200);">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>เอกสารแนบ (หลายไฟล์ได้)</label>
                    <input type="file" name="documents[]" class="form-control" multiple
                        accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                    <select name="doc_category" class="form-control mt-1" style="max-width:250px;">
                        <option value="id_copy">สำเนาบัตร ปชช.</option>
                        <option value="photo">ภาพถ่าย</option>
                        <option value="permit">หนังสืออนุญาต</option>
                        <option value="other">อื่นๆ</option>
                    </select>
                </div>
            </div>

            <!-- หมายเหตุ -->
            <div class="form-group">
                <label>หมายเหตุ</label>
                <textarea name="notes" class="form-control" rows="3"
                    placeholder="บันทึกเพิ่มเติม (ถ้ามี)"><?= htmlspecialchars($v['notes'] ?? '') ?></textarea>
            </div>

            <!-- Buttons -->
            <div
                style="display:flex; gap:12px; margin-top:24px; padding-top:20px; border-top:1px solid var(--gray-100);">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-<?= $isEdit ? 'check-lg' : 'plus-lg' ?>"></i>
                    <?= $isEdit ? 'บันทึกการแก้ไข' : 'เพิ่มราษฎร' ?>
                </button>
                <a href="index.php?page=villagers" class="btn btn-secondary">ยกเลิก</a>

                <?php if ($isEdit && $_SESSION['role'] === ROLE_ADMIN): ?>
                    <form method="POST" action="index.php?page=villagers&action=delete&id=<?= $id ?>"
                        style="margin-left:auto;"
                        onsubmit="return confirmDelete('คุณต้องการลบราษฎรนี้? ข้อมูลแปลงที่ดินที่เกี่ยวข้องจะถูกลบไปด้วย')">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> ลบ</button>
                    </form>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>