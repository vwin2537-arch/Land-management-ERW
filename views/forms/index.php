<?php
/**
 * หน้าเลือกแบบฟอร์มราชการ — Export Forms
 */
require_once __DIR__ . '/../../controllers/FormExportController.php';
$options = FormExportController::getFilterOptions();
?>

<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success" data-dismiss><i class="bi bi-check-circle-fill"></i>
        <?= $_SESSION['flash_success'] ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="d-flex justify-between align-center mb-3">
    <h2><i class="bi bi-file-earmark-text" style="color:var(--primary-600); margin-right:8px;"></i>แบบฟอร์มราชการ</h2>
</div>

<!-- Filter Section -->
<div class="card mb-3">
    <div class="card-header">
        <h3><i class="bi bi-funnel" style="color:var(--info); margin-right:8px;"></i>กรองข้อมูล</h3>
    </div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group" style="flex:2;">
                <label>หมู่บ้านสำรวจ (<?= count($options['villages']) ?> หมู่บ้าน)</label>
                <select id="filterVillage" class="form-control" onchange="onVillageChange()">
                    <option value="">-- ทั้งหมด --</option>
                    <?php foreach ($options['villages'] as $v):
                        $ban = $v['par_ban'];
                        $prefix = mb_strpos($ban, 'บ้าน') === 0 ? '' : 'บ้าน';
                    ?>
                        <option value="<?= htmlspecialchars($ban) ?>">
                            <?= $prefix ?><?= htmlspecialchars($ban) ?> รหัส <?= htmlspecialchars($v['ban_e'] ?? '-') ?> · ม.<?= htmlspecialchars($v['par_moo'] ?? '-') ?> ต.<?= htmlspecialchars($v['par_tam'] ?? '-') ?> อ.<?= htmlspecialchars($v['par_amp'] ?? '-') ?> จ.<?= htmlspecialchars($v['par_prov'] ?? '-') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1;">
                <label>เขตโครงการฯ ที่</label>
                <select id="filterApar" class="form-control">
                    <option value="">-- ทั้งหมด --</option>
                    <?php foreach ($options['aparNos'] as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Form Cards -->
<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:16px;">

    <!-- อส.6-1 -->
    <div class="card">
        <div class="card-body" style="padding:20px;">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                <div style="width:48px; height:48px; background:linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius:12px; display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-people-fill" style="color:#fff; font-size:20px;"></i>
                </div>
                <div>
                    <h3 style="font-size:16px; margin:0;">แบบ อส.6-1</h3>
                    <p style="font-size:12px; color:var(--gray-500); margin:2px 0 0;">บัญชีผู้ครอบครองที่ดิน</p>
                </div>
            </div>
            <p style="font-size:13px; color:var(--gray-600); margin-bottom:16px;">
                รายชื่อผู้ครอบครองพร้อมเลขที่ดิน เนื้อที่ แยกตามเขตโครงการฯ
            </p>
            <button class="btn btn-primary" style="width:100%;" onclick="openForm('form61')">
                <i class="bi bi-printer"></i> พิมพ์แบบฟอร์ม
            </button>
        </div>
    </div>

    <!-- อส.6-2 -->
    <div class="card">
        <div class="card-body" style="padding:20px;">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                <div style="width:48px; height:48px; background:linear-gradient(135deg, #8b5cf6, #6d28d9); border-radius:12px; display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-house-heart-fill" style="color:#fff; font-size:20px;"></i>
                </div>
                <div>
                    <h3 style="font-size:16px; margin:0;">แบบ อส.6-2</h3>
                    <p style="font-size:12px; color:var(--gray-500); margin:2px 0 0;">บัญชีสมาชิกครอบครัว/ครัวเรือน</p>
                </div>
            </div>
            <p style="font-size:13px; color:var(--gray-600); margin-bottom:16px;">
                รายชื่อสมาชิกในครัวเรือน ผูกกับผู้ครอบครองที่ดิน
            </p>
            <button class="btn btn-primary" style="width:100%;" onclick="openForm('form62')">
                <i class="bi bi-printer"></i> พิมพ์แบบฟอร์ม
            </button>
        </div>
    </div>

    <!-- อส.6-3 -->
    <div class="card">
        <div class="card-body" style="padding:20px;">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                <div style="width:48px; height:48px; background:linear-gradient(135deg, #ef4444, #b91c1c); border-radius:12px; display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-person-x-fill" style="color:#fff; font-size:20px;"></i>
                </div>
                <div>
                    <h3 style="font-size:16px; margin:0;">แบบ อส.6-3</h3>
                    <p style="font-size:12px; color:var(--gray-500); margin:2px 0 0;">ผู้ไม่ผ่านตรวจสอบคุณสมบัติ</p>
                </div>
            </div>
            <p style="font-size:13px; color:var(--gray-600); margin-bottom:16px;">
                บัญชีรายชื่อผู้ครอบครองที่ดินที่ไม่ผ่านการตรวจสอบคุณสมบัติ
            </p>
            <button class="btn btn-danger" style="width:100%;" onclick="openForm('form63')">
                <i class="bi bi-printer"></i> พิมพ์แบบฟอร์ม
            </button>
        </div>
    </div>

    <!-- บัญชี 1-1 -->
    <div class="card">
        <div class="card-body" style="padding:20px;">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                <div style="width:48px; height:48px; background:linear-gradient(135deg, #f59e0b, #d97706); border-radius:12px; display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-list-ol" style="color:#fff; font-size:20px;"></i>
                </div>
                <div>
                    <h3 style="font-size:16px; margin:0;">บัญชี 1-1</h3>
                    <p style="font-size:12px; color:var(--gray-500); margin:2px 0 0;">รายชื่อราษฎรพร้อมจำนวนที่ดิน</p>
                </div>
            </div>
            <p style="font-size:13px; color:var(--gray-600); margin-bottom:16px;">
                บัญชีรายชื่อราษฎรพร้อมจำนวนที่ดิน แยกรายหมู่บ้าน
            </p>
            <button class="btn btn-warning" style="width:100%; color:#000;" onclick="openForm('account11')">
                <i class="bi bi-printer"></i> พิมพ์แบบฟอร์ม
            </button>
        </div>
    </div>

    <!-- บัญชี 1-2 -->
    <div class="card">
        <div class="card-body" style="padding:20px;">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                <div style="width:48px; height:48px; background:linear-gradient(135deg, #14b8a6, #0d9488); border-radius:12px; display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-building" style="color:#fff; font-size:20px;"></i>
                </div>
                <div>
                    <h3 style="font-size:16px; margin:0;">บัญชี 1-2</h3>
                    <p style="font-size:12px; color:var(--gray-500); margin:2px 0 0;">แปลงที่ดินประเภทอื่นๆ</p>
                </div>
            </div>
            <p style="font-size:13px; color:var(--gray-600); margin-bottom:16px;">
                วัด โรงเรียน ที่ราชพัสดุ แปลงที่ดินรวม กลุ่มชาติพันธุ์ ฯลฯ
            </p>
            <button class="btn btn-info" style="width:100%;" onclick="openForm('account12')">
                <i class="bi bi-printer"></i> พิมพ์แบบฟอร์ม
            </button>
        </div>
    </div>

    <!-- หนังสือรับรองตนเอง -->
    <div class="card">
        <div class="card-body" style="padding:20px;">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                <div style="width:48px; height:48px; background:linear-gradient(135deg, #64748b, #475569); border-radius:12px; display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-shield-check" style="color:#fff; font-size:20px;"></i>
                </div>
                <div>
                    <h3 style="font-size:16px; margin:0;">หนังสือรับรองตนเอง</h3>
                    <p style="font-size:12px; color:var(--gray-500); margin:2px 0 0;">รับรองคุณสมบัติ 6 ข้อ</p>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label style="font-size:13px;">เลือกราษฎร</label>
                <select id="certVillager" class="form-control">
                    <option value="">-- เลือกราษฎร --</option>
                    <?php foreach ($options['villagerList'] as $vl): ?>
                        <option value="<?= $vl['villager_id'] ?>">
                            <?= htmlspecialchars(($vl['prefix'] ?? '') . $vl['first_name'] . ' ' . $vl['last_name']) ?>
                            (<?= $vl['id_card_number'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-secondary" style="width:100%;" onclick="openCert()">
                <i class="bi bi-printer"></i> พิมพ์หนังสือ
            </button>
        </div>
    </div>

</div>

<script>
const VILLAGE_APAR_MAP = <?= json_encode($options['villageAparMap'], JSON_UNESCAPED_UNICODE) ?>;

function onVillageChange() {
    const village = document.getElementById('filterVillage').value;
    const aparSel = document.getElementById('filterApar');
    const currentVal = aparSel.value;
    aparSel.innerHTML = '<option value="">-- ทั้งหมด --</option>';

    let list = [];
    if (village && VILLAGE_APAR_MAP[village]) {
        list = VILLAGE_APAR_MAP[village];
    } else {
        list = <?= json_encode(array_values($options['aparNos']), JSON_UNESCAPED_UNICODE) ?>;
    }
    list.forEach(a => {
        const opt = document.createElement('option');
        opt.value = a;
        opt.textContent = a;
        if (a === currentVal) opt.selected = true;
        aparSel.appendChild(opt);
    });
}

function buildQuery() {
    const village = document.getElementById('filterVillage').value;
    const apar = document.getElementById('filterApar').value;
    let q = '';
    if (village) q += '&par_ban=' + encodeURIComponent(village);
    if (apar) q += '&apar_no=' + encodeURIComponent(apar);
    return q;
}

function openForm(type) {
    const q = buildQuery();
    window.open('index.php?page=forms&action=print&type=' + type + q, '_blank');
}

function openCert() {
    const vid = document.getElementById('certVillager').value;
    if (!vid) { alert('กรุณาเลือกราษฎร'); return; }
    window.open('index.php?page=forms&action=print&type=self_cert&villager_id=' + vid, '_blank');
}

// Auto-select village from URL query param (e.g. from dashboard click)
(function() {
    const params = new URLSearchParams(window.location.search);
    const parBan = params.get('par_ban');
    if (parBan) {
        const sel = document.getElementById('filterVillage');
        for (let i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value === parBan) {
                sel.selectedIndex = i;
                onVillageChange();
                break;
            }
        }
    }
})();
</script>
