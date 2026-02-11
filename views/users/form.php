<?php
/**
 * ฟอร์มเพิ่ม/แก้ไข ผู้ใช้งาน (Admin only)
 */
require_once __DIR__ . '/../../controllers/AuthController.php';
AuthController::requireRole(ROLE_ADMIN);

$db = getDB();
$id = $_GET['id'] ?? null;
$isEdit = ($action === 'edit' && $id);

$user = null;
if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :id");
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();
    if (!$user) { echo '<div class="alert alert-danger">ไม่พบผู้ใช้</div>'; return; }
}

$u = $user ?? [];
?>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="card" style="max-width:700px;">
    <div class="card-header">
        <h3><i class="bi bi-<?= $isEdit ? 'pencil-square' : 'person-plus-fill' ?>" style="color:var(--primary-600); margin-right:8px;"></i>
            <?= $isEdit ? 'แก้ไขผู้ใช้งาน' : 'เพิ่มผู้ใช้งาน' ?>
        </h3>
        <a href="index.php?page=users" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> กลับ</a>
    </div>
    <div class="card-body">
        <form method="POST" action="index.php?page=users&action=<?= $isEdit ? "edit&id=$id" : 'create' ?>">

            <div class="form-row">
                <div class="form-group">
                    <label>Username <span class="required">*</span></label>
                    <input type="text" name="username" class="form-control" required 
                           value="<?= htmlspecialchars($u['username'] ?? '') ?>" 
                           <?= $isEdit ? 'readonly style="background:var(--gray-100);"' : '' ?>
                           placeholder="ชื่อเข้าระบบ">
                </div>
                <div class="form-group">
                    <label><?= $isEdit ? 'รหัสผ่านใหม่ (เว้นว่างถ้าไม่เปลี่ยน)' : 'รหัสผ่าน <span class="required">*</span>' ?></label>
                    <input type="password" name="password" class="form-control" 
                           <?= $isEdit ? '' : 'required' ?> minlength="4" placeholder="รหัสผ่าน">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>ชื่อ-นามสกุล <span class="required">*</span></label>
                    <input type="text" name="full_name" class="form-control" required 
                           value="<?= htmlspecialchars($u['full_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>โทรศัพท์</label>
                    <input type="tel" name="phone" class="form-control" 
                           value="<?= htmlspecialchars($u['phone'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>บทบาท</label>
                    <select name="role" class="form-control">
                        <?php foreach (ROLE_LABELS as $k => $l): ?>
                            <option value="<?= $k ?>" <?= ($u['role'] ?? 'officer') === $k ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:10px; padding-top:24px;">
                    <input type="checkbox" name="is_active" id="is_active" value="1" <?= ($u['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label for="is_active" style="margin:0;">เปิดใช้งาน</label>
                </div>
            </div>

            <div style="display:flex; gap:12px; margin-top:24px; padding-top:20px; border-top:1px solid var(--gray-100);">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-<?= $isEdit ? 'check-lg' : 'plus-lg' ?>"></i>
                    <?= $isEdit ? 'บันทึก' : 'เพิ่มผู้ใช้' ?>
                </button>
                <a href="index.php?page=users" class="btn btn-secondary">ยกเลิก</a>
            </div>
        </form>
    </div>
</div>
