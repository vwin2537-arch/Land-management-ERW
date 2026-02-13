<?php
/**
 * ฟอร์มสร้าง/แก้ไข คำร้อง
 */
require_once __DIR__ . '/../../controllers/AuthController.php';
AuthController::requireRole(ROLE_ADMIN, ROLE_OFFICER);

require_once __DIR__ . '/../../models/Villager.php';

$db = getDB();
$id = $_GET['id'] ?? null;
$isEdit = ($action === 'edit' && $id);

$case = null;
if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM cases WHERE case_id = :id");
    $stmt->execute(['id' => $id]);
    $case = $stmt->fetch();
    if (!$case) {
        echo '<div class="alert alert-danger">ไม่พบคำร้อง</div>';
        return;
    }
}

$c = $case ?? [];
$villagers = Villager::getAllForSelect();
$officers = $db->query("SELECT user_id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();

// Get plots for selected villager (if editing)
$plots = [];
if (!empty($c['villager_id'])) {
    $plotStmt = $db->prepare("SELECT plot_id, plot_code FROM land_plots WHERE villager_id = :vid ORDER BY plot_code");
    $plotStmt->execute(['vid' => $c['villager_id']]);
    $plots = $plotStmt->fetchAll();
}
?>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i>
        <?= $_SESSION['flash_error'] ?>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3><i class="bi bi-<?= $isEdit ? 'pencil-square' : 'folder-plus' ?>"
                style="color:var(--primary-600); margin-right:8px;"></i>
            <?= $isEdit ? 'แก้ไขคำร้อง' : 'สร้างคำร้องใหม่' ?>
        </h3>
        <a href="index.php?page=cases" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> กลับ</a>
    </div>
    <div class="card-body">
        <form method="POST" action="index.php?page=cases&action=<?= $isEdit ? "edit&id=$id" : 'create' ?>"
            enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label>ประเภท <span class="required">*</span></label>
                    <select name="case_type" class="form-control" required>
                        <?php foreach (CASE_TYPE_LABELS as $k => $l): ?>
                            <option value="<?= $k ?>" <?= ($c['case_type'] ?? '') === $k ? 'selected' : '' ?>>
                                <?= $l ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>ความเร่งด่วน</label>
                    <select name="priority" class="form-control">
                        <option value="low" <?= ($c['priority'] ?? '') === 'low' ? 'selected' : '' ?>>ต่ำ</option>
                        <option value="medium" <?= ($c['priority'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>กลาง
                        </option>
                        <option value="high" <?= ($c['priority'] ?? '') === 'high' ? 'selected' : '' ?>>สูง</option>
                    </select>
                </div>
                <?php if ($isEdit): ?>
                    <div class="form-group">
                        <label>สถานะ</label>
                        <select name="status" class="form-control">
                            <?php foreach (CASE_STATUS_LABELS as $k => $l): ?>
                                <option value="<?= $k ?>" <?= ($c['status'] ?? '') === $k ? 'selected' : '' ?>>
                                    <?= $l ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>หัวข้อ/เรื่อง <span class="required">*</span></label>
                <input type="text" name="subject" class="form-control" required
                    value="<?= htmlspecialchars($c['subject'] ?? '') ?>"
                    placeholder="เช่น ขอต่ออายุการใช้พื้นที่ แปลง NP-012">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>ผู้ร้อง/ราษฎร <span class="required">*</span></label>
                    <select name="villager_id" class="form-control" required id="caseVillager"
                        onchange="loadPlots(this.value)">
                        <option value="">-- เลือกราษฎร --</option>
                        <?php foreach ($villagers as $vl): ?>
                            <option value="<?= $vl['villager_id'] ?>" <?= ($c['villager_id'] ?? '') == $vl['villager_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($vl['prefix'] ?? '') . $vl['first_name'] . ' ' . $vl['last_name']) ?>
                                (
                                <?= $vl['id_card_number'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>แปลงที่เกี่ยวข้อง</label>
                    <select name="plot_id" class="form-control" id="casePlot">
                        <option value="">-- ไม่ระบุ --</option>
                        <?php foreach ($plots as $pl): ?>
                            <option value="<?= $pl['plot_id'] ?>" <?= ($c['plot_id'] ?? '') == $pl['plot_id'] ? 'selected' : '' ?>>
                                <?= $pl['plot_code'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>รายละเอียด</label>
                <textarea name="description" class="form-control" rows="4"
                    placeholder="อธิบายรายละเอียดของคำร้อง..."><?= htmlspecialchars($c['description'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>มอบหมายให้</label>
                    <select name="assigned_to" class="form-control">
                        <option value="">-- ยังไม่มอบหมาย --</option>
                        <?php foreach ($officers as $o): ?>
                            <option value="<?= $o['user_id'] ?>" <?= ($c['assigned_to'] ?? '') == $o['user_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($o['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>เอกสารแนบ</label>
                    <input type="file" name="documents[]" class="form-control" multiple>
                </div>
            </div>

            <?php if ($isEdit): ?>
                <div class="form-group">
                    <label>ผลการดำเนินการ</label>
                    <textarea name="resolution" class="form-control" rows="3"
                        placeholder="สรุปผลการดำเนินการ..."><?= htmlspecialchars($c['resolution'] ?? '') ?></textarea>
                </div>
            <?php endif; ?>

            <div
                style="display:flex; gap:12px; margin-top:24px; padding-top:20px; border-top:1px solid var(--gray-100);">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-<?= $isEdit ? 'check-lg' : 'plus-lg' ?>"></i>
                    <?= $isEdit ? 'บันทึก' : 'สร้างคำร้อง' ?>
                </button>
                <a href="index.php?page=cases" class="btn btn-secondary">ยกเลิก</a>
            </div>
        </form>
    </div>
</div>

<script>
    // Load plots when villager changes
    function loadPlots(villagerId) {
        const select = document.getElementById('casePlot');
        select.innerHTML = '<option value="">-- กำลังโหลด... --</option>';
        if (!villagerId) { select.innerHTML = '<option value="">-- ไม่ระบุ --</option>'; return; }

        // Simple AJAX — fetch plots
        fetch('index.php?page=api_plots&villager_id=' + villagerId)
            .then(r => r.json())
            .then(data => {
                select.innerHTML = '<option value="">-- ไม่ระบุ --</option>';
                data.forEach(p => {
                    select.innerHTML += `<option value="${p.plot_id}">${p.plot_code}</option>`;
                });
            })
            .catch(() => { select.innerHTML = '<option value="">-- ไม่ระบุ --</option>'; });
    }
</script>