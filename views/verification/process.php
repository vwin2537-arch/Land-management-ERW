<?php
/**
 * จัดสรรที่ดิน — รองรับ partial split จากแปลงใหญ่
 * Step 1: ผู้ครอบครองเลือกแปลง/ใส่เนื้อที่บางส่วน (≤20 ไร่)
 * Step 2: จัดสรรส่วนที่เหลือให้ทายาท (≤20 ไร่ รวม) — แบ่งทายาทหลายคนได้
 * Step 3: ส่วนเกิน 40 ไร่ → ม.19 อัตโนมัติ
 * + แผนที่ Leaflet แสดงรูปแปลง
 */
require_once __DIR__ . '/../../controllers/VerificationController.php';

$villagerId = (int)($_GET['id'] ?? 0);
if (!$villagerId) {
    echo '<div class="card"><div class="card-body" style="text-align:center; padding:40px; color:#dc2626;">ไม่พบข้อมูลราษฎร</div></div>';
    return;
}

$v = VerificationController::getVillagerById($villagerId);
if (!$v) {
    echo '<div class="card"><div class="card-body" style="text-align:center; padding:40px; color:#dc2626;">ไม่พบข้อมูลราษฎร ID: ' . $villagerId . '</div></div>';
    return;
}

$plots = $v['plots'];
$area = $v['area_summary'];
$members = $v['household_members'];
$fullName = ($v['prefix'] ?? '') . ($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? '');
$isVerified = ($v['verification_status'] ?? 'pending') === 'verified';

// โหลด allocations ที่บันทึกไว้
$existingAllocs = VerificationController::getAllocations($villagerId);

// สร้าง plots JSON สำหรับ JS (รวม polygon_coords สำหรับแผนที่)
$plotsJson = json_encode(array_map(function($p) {
    $poly = $p['polygon_coords'] ? json_decode($p['polygon_coords'], true) : null;
    return [
        'plot_id' => (int)$p['plot_id'],
        'num_apar' => $p['num_apar'] ?? '',
        'spar_no' => $p['spar_no'] ?? '',
        'area_rai' => (int)($p['area_rai'] ?? 0),
        'area_ngan' => (int)($p['area_ngan'] ?? 0),
        'area_sqwa' => (int)($p['area_sqwa'] ?? 0),
        'total_rai' => round(VerificationController::toRai((int)$p['area_rai'], (int)$p['area_ngan'], (int)$p['area_sqwa']), 4),
        'par_ban' => $p['par_ban'] ?? '',
        'ptype' => $p['ptype'] ?? '',
        'polygon' => $poly,
        'lat' => (float)($p['latitude'] ?? 0),
        'lng' => (float)($p['longitude'] ?? 0),
    ];
}, $plots), JSON_UNESCAPED_UNICODE);

$membersJson = json_encode($members, JSON_UNESCAPED_UNICODE);
$existingAllocsJson = json_encode($existingAllocs, JSON_UNESCAPED_UNICODE);
?>

<!-- Breadcrumb -->
<div style="margin-bottom:16px;">
    <a href="index.php?page=verification&id_card=<?= urlencode($v['id_card_number']) ?>" style="color:#166534; text-decoration:none;">
        <i class="bi bi-arrow-left"></i> กลับหน้าค้นหา
    </a>
</div>

<!-- Villager Header -->
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h2 style="margin:0;"><?= htmlspecialchars($fullName) ?></h2>
            <div style="color:#666; font-family:monospace; font-size:16px;"><?= htmlspecialchars($v['id_card_number']) ?></div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:28px; font-weight:700; color:#b45309;">
                <?= number_format($area['total_in_rai'], 2) ?> ไร่
            </div>
            <div style="color:#666;"><?= $area['plot_count'] ?> แปลง — <?= $area['status_label'] ?></div>
        </div>
    </div>
</div>

<?php if ($isVerified): ?>
<div class="card" style="border:2px solid #22c55e; margin-bottom:16px;">
    <div class="card-body" style="text-align:center; padding:24px; color:#166534;">
        <i class="bi bi-check-circle-fill" style="font-size:32px;"></i>
        <h3>ราษฎรนี้ได้รับการตรวจสอบและจัดสรรแล้ว</h3>
        <p>เมื่อ <?= $v['verified_at'] ?? '-' ?></p>
        <div style="margin-top:16px; display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
            <a href="index.php?page=forms&action=print&type=self_cert&villager_id=<?= $villagerId ?>" target="_blank" class="btn btn-primary">
                <i class="bi bi-printer"></i> พิมพ์หนังสือรับรอง (ผู้ครอบครอง)
            </a>
            <a href="index.php?page=forms&action=print&type=self_cert_heir&villager_id=<?= $villagerId ?>" target="_blank" class="btn btn-primary" style="background:#7c3aed;">
                <i class="bi bi-printer"></i> พิมพ์หนังสือรับรอง (ทายาท)
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Budget Overview -->
<div class="card" style="margin-bottom:16px;">
    <div class="card-body">
        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; text-align:center;">
            <div style="background:#f0fdf4; border-radius:8px; padding:12px;">
                <div style="color:#166534; font-size:12px; font-weight:600;">ผู้ครอบครอง</div>
                <div style="font-size:24px; font-weight:700; color:#166534;" id="budgetOwner">0.00</div>
                <div style="color:#999; font-size:12px;">/ 20.00 ไร่</div>
                <div style="height:6px; background:#dcfce7; border-radius:3px; margin-top:6px;">
                    <div id="barOwner" style="height:100%; width:0%; background:#22c55e; border-radius:3px; transition:width 0.3s;"></div>
                </div>
            </div>
            <div style="background:#eff6ff; border-radius:8px; padding:12px;">
                <div style="color:#1e40af; font-size:12px; font-weight:600;">ทายาท/ครัวเรือน</div>
                <div style="font-size:24px; font-weight:700; color:#1e40af;" id="budgetHeir">0.00</div>
                <div style="color:#999; font-size:12px;">/ 20.00 ไร่</div>
                <div style="height:6px; background:#dbeafe; border-radius:3px; margin-top:6px;">
                    <div id="barHeir" style="height:100%; width:0%; background:#3b82f6; border-radius:3px; transition:width 0.3s;"></div>
                </div>
            </div>
            <div style="background:#fef2f2; border-radius:8px; padding:12px;">
                <div style="color:#dc2626; font-size:12px; font-weight:600;">ม.19 (คืนรัฐ)</div>
                <div style="font-size:24px; font-weight:700; color:#dc2626;" id="budgetS19">0.00</div>
                <div style="color:#999; font-size:12px;">ไร่</div>
                <div style="height:6px; background:#fee2e2; border-radius:3px; margin-top:6px;">
                    <div id="barS19" style="height:100%; width:0%; background:#ef4444; border-radius:3px; transition:width 0.3s;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Map -->
<div class="card" style="margin-bottom:16px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 style="margin:0;"><i class="bi bi-map"></i> แผนที่แปลงที่ดิน</h3>
        <div style="display:flex; gap:12px; font-size:13px;">
            <span><span style="display:inline-block; width:12px; height:12px; background:#22c55e; border-radius:2px;"></span> ผู้ครอบครอง</span>
            <span><span style="display:inline-block; width:12px; height:12px; background:#3b82f6; border-radius:2px;"></span> ทายาท</span>
            <span><span style="display:inline-block; width:12px; height:12px; background:#ef4444; border-radius:2px;"></span> ม.19</span>
            <span><span style="display:inline-block; width:12px; height:12px; background:#9ca3af; border-radius:2px;"></span> ยังไม่จัดสรร</span>
        </div>
    </div>
    <div id="allocMap" style="height:400px; border-radius:0 0 12px 12px;"></div>
</div>

<!-- Allocation Form -->
<form method="POST" action="index.php?page=verification&action=save_allocation" id="allocationForm">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <input type="hidden" name="villager_id" value="<?= $villagerId ?>">

    <!-- Step 1: ผู้ครอบครอง (≤20 ไร่) -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header" style="background:#f0fdf4;">
            <h3 style="color:#166534; margin:0;"><i class="bi bi-1-circle-fill"></i> เลือกแปลงผู้ครอบครอง (ไม่เกิน 20 ไร่)</h3>
            <p style="color:#666; margin:4px 0 0; font-size:13px;">ติ๊กเลือกแปลง แล้วกำหนดเนื้อที่ที่ต้องการ (ไม่จำเป็นต้องเอาทั้งแปลง)</p>
        </div>
        <div class="card-body" style="padding:0; overflow-x:auto;">
            <table class="data-table" style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:40px;">เลือก</th>
                        <th>ที่ดินเลขที่</th>
                        <th>เขตสำรวจ</th>
                        <th>เนื้อที่ทั้งแปลง</th>
                        <th style="width:140px;">ใช้สิทธิ์ (ไร่)</th>
                        <th>เหลือ (ไร่)</th>
                        <th>บ้าน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plots as $p): ?>
                        <?php $plotRai = VerificationController::toRai((int)$p['area_rai'], (int)$p['area_ngan'], (int)$p['area_sqwa']); ?>
                        <tr id="row-owner-<?= $p['plot_id'] ?>">
                            <td>
                                <input type="checkbox" class="owner-check"
                                       data-plotid="<?= $p['plot_id'] ?>"
                                       data-totalrai="<?= round($plotRai, 4) ?>"
                                       onchange="onOwnerCheck(this)"
                                       <?= $isVerified ? 'disabled' : '' ?>>
                            </td>
                            <td style="font-weight:600;"><?= htmlspecialchars($p['num_apar'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($p['spar_no'] ?? '-') ?></td>
                            <td><?= (int)$p['area_rai'] ?>-<?= (int)$p['area_ngan'] ?>-<?= (int)$p['area_sqwa'] ?> (<?= number_format($plotRai, 2) ?>)</td>
                            <td>
                                <input type="number" class="form-control owner-area"
                                       id="ownerArea-<?= $p['plot_id'] ?>"
                                       data-plotid="<?= $p['plot_id'] ?>"
                                       step="0.01" min="0.01" max="<?= round($plotRai, 4) ?>"
                                       value="<?= round($plotRai, 2) ?>"
                                       disabled
                                       onchange="onOwnerAreaChange(this)"
                                       style="width:120px; text-align:right;">
                            </td>
                            <td class="owner-remainder" id="ownerRem-<?= $p['plot_id'] ?>" style="color:#b45309;">—</td>
                            <td><?= htmlspecialchars($p['par_ban'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-body" style="border-top:1px solid #e5e7eb; background:#f9fafb;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <div>เลือกแล้ว: <strong id="ownerCount">0</strong> แปลง</div>
                <div style="font-size:18px;">
                    รวม: <strong id="ownerTotal" style="color:#166534;">0.00</strong> / 20.00 ไร่
                </div>
            </div>
            <div id="ownerWarning" style="color:#dc2626; font-weight:600; margin-top:8px; display:none;">
                <i class="bi bi-exclamation-triangle-fill"></i> เกิน 20 ไร่! กรุณาลดเนื้อที่หรือเอาแปลงออก
            </div>
        </div>
    </div>

    <!-- Step 2: ทายาท/ครัวเรือน (≤20 ไร่ รวม) -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header" style="background:#eff6ff;">
            <h3 style="color:#1e40af; margin:0;"><i class="bi bi-2-circle-fill"></i> จัดสรรให้ทายาท/ครัวเรือน (ไม่เกิน 20 ไร่ รวม)</h3>
            <p style="color:#666; margin:4px 0 0; font-size:13px;">แปลงที่ไม่ได้เลือกหรือส่วนที่เหลือจากขั้นตอนที่ 1 — กำหนดเนื้อที่และเลือกทายาทได้</p>
        </div>
        <div class="card-body" id="heirPoolContainer">
            <div id="heirPool">
                <p style="color:#999; text-align:center; padding:20px;">เลือกแปลงผู้ครอบครองก่อน (ขั้นตอนที่ 1)</p>
            </div>
        </div>
        <div class="card-body" style="border-top:1px solid #e5e7eb; background:#f0f9ff;">
            <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                <div>
                    ทายาท: <strong id="heirTotal" style="color:#2563eb;">0.00</strong> / 20.00 ไร่
                </div>
                <div>
                    ม.19: <strong id="s19Total" style="color:#dc2626;">0.00</strong> ไร่
                </div>
            </div>
            <div id="heirWarning" style="color:#dc2626; font-weight:600; margin-top:8px; display:none;">
                <i class="bi bi-exclamation-triangle-fill"></i> เกินงบทายาท 20 ไร่!
            </div>
        </div>
    </div>

    <!-- Step 3: ข้อมูลสมาชิกครัวเรือน/ทายาท -->
    <div class="card" style="margin-bottom:16px;" id="memberSection">
        <div class="card-header" style="background:#faf5ff;">
            <h3 style="color:#7c3aed; margin:0;"><i class="bi bi-3-circle-fill"></i> ข้อมูลสมาชิกครัวเรือน/ทายาท (อส.6-2)</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($members)): ?>
                <p style="color:#666; margin-bottom:12px;">สมาชิกที่มีในระบบ:</p>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
                    <?php foreach ($members as $m): ?>
                        <span style="background:#f3e8ff; color:#7c3aed; padding:4px 12px; border-radius:16px; font-size:14px;">
                            <?= htmlspecialchars(($m['prefix'] ?? '') . $m['first_name'] . ' ' . $m['last_name']) ?>
                            (<?= htmlspecialchars($m['relationship'] ?? '-') ?>)
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div id="newMembersContainer">
                <h4 style="margin-bottom:12px;">เพิ่มทายาทใหม่</h4>
                <div id="newMembersList"></div>
                <button type="button" class="btn btn-secondary" onclick="addNewMemberRow()" style="margin-top:8px;">
                    <i class="bi bi-plus-circle"></i> เพิ่มทายาท
                </button>
            </div>
        </div>
    </div>

    <!-- Section 19 Summary -->
    <div class="card" style="margin-bottom:16px; border-left:4px solid #ef4444;" id="s19Section">
        <div class="card-header" style="background:#fef2f2;">
            <h3 style="color:#dc2626; margin:0;"><i class="bi bi-exclamation-triangle-fill"></i> ม.19 — ส่วนเกินคืนให้รัฐ</h3>
        </div>
        <div class="card-body" id="s19Details">
            <p style="color:#999;">จะแสดงเมื่อมีส่วนเกินจากการจัดสรร</p>
        </div>
    </div>

    <!-- Submit -->
    <?php if (!$isVerified): ?>
    <div class="card">
        <div class="card-body" style="display:flex; gap:12px; justify-content:flex-end; flex-wrap:wrap;">
            <a href="index.php?page=verification&id_card=<?= urlencode($v['id_card_number']) ?>" class="btn btn-secondary">
                <i class="bi bi-x-lg"></i> ยกเลิก
            </a>
            <button type="submit" class="btn btn-primary" id="submitBtn" style="padding:12px 32px; font-size:16px;">
                <i class="bi bi-check-lg"></i> บันทึกการจัดสรร
            </button>
        </div>
    </div>
    <?php endif; ?>
</form>

<script>
// ===== Data =====
const PLOTS = <?= $plotsJson ?>;
const MEMBERS = <?= $membersJson ?>;
const EXISTING = <?= $existingAllocsJson ?>;
const MAX_OWNER = 20;
const MAX_HEIR = 20;
const TOTAL_ALL = PLOTS.reduce((s, p) => s + p.total_rai, 0);

// ===== State =====
let ownerClaims = {}; // plot_id -> area (rai)
let heirRows = [];     // [{id, plot_id, area, member_id}]
let newMemberCount = 0;
let mapLayers = {};    // plot_id -> L.layer

// ===== Owner Step =====
function onOwnerCheck(cb) {
    const pid = parseInt(cb.dataset.plotid);
    const plotTotal = parseFloat(cb.dataset.totalrai);
    const areaInput = document.getElementById('ownerArea-' + pid);

    if (cb.checked) {
        const budgetLeft = MAX_OWNER - getOwnerTotal();
        const claimArea = Math.min(plotTotal, Math.max(0.01, budgetLeft));
        areaInput.value = claimArea.toFixed(2);
        areaInput.disabled = false;
        ownerClaims[pid] = claimArea;
    } else {
        areaInput.disabled = true;
        delete ownerClaims[pid];
    }
    recalcAll();
}

function onOwnerAreaChange(input) {
    const pid = parseInt(input.dataset.plotid);
    const plotTotal = parseFloat(document.querySelector(`.owner-check[data-plotid="${pid}"]`).dataset.totalrai);
    let val = parseFloat(input.value) || 0;
    val = Math.max(0.01, Math.min(plotTotal, val));
    input.value = val.toFixed(2);
    ownerClaims[pid] = val;
    recalcAll();
}

function getOwnerTotal() {
    return Object.values(ownerClaims).reduce((s, a) => s + a, 0);
}

// ===== Heir Step =====
function buildHeirPool() {
    const container = document.getElementById('heirPool');
    const availableItems = []; // [{plot_id, num_apar, available_rai, source}]

    PLOTS.forEach(p => {
        const ownerUsed = ownerClaims[p.plot_id] || 0;
        const remaining = Math.round((p.total_rai - ownerUsed) * 10000) / 10000;
        if (remaining > 0.001) {
            availableItems.push({
                plot_id: p.plot_id,
                num_apar: p.num_apar,
                available_rai: remaining,
                source: ownerUsed > 0 ? 'เหลือจากผู้ครอบครอง' : 'ทั้งแปลง',
            });
        }
    });

    if (availableItems.length === 0) {
        container.innerHTML = '<p style="color:#22c55e; text-align:center; padding:20px;">ทุกแปลงถูกจัดสรรให้ผู้ครอบครองแล้ว</p>';
        heirRows = [];
        return;
    }

    // Rebuild heir rows based on available items
    // Keep existing heir rows that are still valid, remove invalid ones
    const validRows = [];
    heirRows.forEach(hr => {
        const item = availableItems.find(a => a.plot_id === hr.plot_id);
        if (item) validRows.push(hr);
    });

    // For available items without any heir row, add a default row
    availableItems.forEach(item => {
        const hasRow = validRows.some(r => r.plot_id === item.plot_id);
        if (!hasRow) {
            validRows.push({
                id: 'hr_' + Date.now() + '_' + item.plot_id,
                plot_id: item.plot_id,
                area: 0,
                member_id: '',
            });
        }
    });
    heirRows = validRows;

    // Render
    let html = '';
    availableItems.forEach(item => {
        const plotRows = heirRows.filter(r => r.plot_id === item.plot_id);
        const totalHeirForPlot = plotRows.reduce((s, r) => s + (r.area || 0), 0);
        const s19ForPlot = Math.max(0, item.available_rai - totalHeirForPlot);

        html += `<div class="heir-item" style="border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-bottom:12px; background:#fafbff;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <div>
                    <strong style="color:#1e40af;">แปลง ${item.num_apar || item.plot_id}</strong>
                    <span style="color:#666; font-size:13px; margin-left:8px;">(${item.source})</span>
                </div>
                <div style="font-size:15px;">
                    พร้อมจัดสรร: <strong>${item.available_rai.toFixed(2)}</strong> ไร่
                </div>
            </div>`;

        plotRows.forEach((row, idx) => {
            html += `<div class="heir-row" style="display:flex; gap:8px; align-items:center; margin-bottom:6px; flex-wrap:wrap;" data-rowid="${row.id}">
                <label style="color:#3b82f6; font-size:13px; min-width:80px;">ทายาท ${idx + 1}:</label>
                <input type="number" class="form-control heir-area-input"
                       data-rowid="${row.id}" data-plotid="${item.plot_id}"
                       value="${(row.area || 0).toFixed(2)}"
                       step="0.01" min="0" max="${item.available_rai.toFixed(4)}"
                       onchange="onHeirAreaChange(this)"
                       style="width:110px; text-align:right;">
                <span style="color:#666; font-size:13px;">ไร่ →</span>
                <select class="form-control heir-member-select"
                        data-rowid="${row.id}"
                        onchange="onHeirMemberChange(this)"
                        style="width:220px; font-size:13px;">
                    ${getMemberOptions(row.member_id)}
                </select>
                ${idx > 0 ? `<button type="button" class="btn btn-secondary" onclick="removeHeirRow('${row.id}')" style="padding:4px 8px; font-size:12px;" title="ลบ"><i class="bi bi-x-lg"></i></button>` : ''}
            </div>`;
        });

        html += `<div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                <button type="button" class="btn btn-secondary" onclick="addHeirRow(${item.plot_id})" style="font-size:12px; padding:4px 12px;">
                    <i class="bi bi-plus-circle"></i> แบ่งทายาทเพิ่ม
                </button>
                <span style="color:${s19ForPlot > 0 ? '#dc2626' : '#22c55e'}; font-size:13px; font-weight:600;">
                    ${s19ForPlot > 0.001 ? '→ ม.19: ' + s19ForPlot.toFixed(2) + ' ไร่' : '✓ จัดสรรครบ'}
                </span>
            </div>
        </div>`;
    });

    container.innerHTML = html;
}

function onHeirAreaChange(input) {
    const rowId = input.dataset.rowid;
    const plotId = parseInt(input.dataset.plotid);
    let val = parseFloat(input.value) || 0;

    // Limit to available for this plot
    const plot = PLOTS.find(p => p.plot_id === plotId);
    const ownerUsed = ownerClaims[plotId] || 0;
    const plotAvailable = plot.total_rai - ownerUsed;
    const otherHeirForPlot = heirRows.filter(r => r.plot_id === plotId && r.id !== rowId).reduce((s, r) => s + (r.area || 0), 0);
    val = Math.max(0, Math.min(plotAvailable - otherHeirForPlot, val));

    // Limit to heir budget
    const currentHeirTotal = heirRows.filter(r => r.id !== rowId).reduce((s, r) => s + (r.area || 0), 0);
    val = Math.min(val, MAX_HEIR - currentHeirTotal);
    val = Math.max(0, val);

    input.value = val.toFixed(2);
    const row = heirRows.find(r => r.id === rowId);
    if (row) row.area = val;
    recalcAll();
}

function onHeirMemberChange(select) {
    const rowId = select.dataset.rowid;
    const row = heirRows.find(r => r.id === rowId);
    if (row) row.member_id = select.value;
}

function addHeirRow(plotId) {
    const newRow = {
        id: 'hr_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5),
        plot_id: plotId,
        area: 0,
        member_id: '',
    };
    heirRows.push(newRow);
    buildHeirPool();
    recalcBudgets();
}

function removeHeirRow(rowId) {
    heirRows = heirRows.filter(r => r.id !== rowId);
    buildHeirPool();
    recalcBudgets();
}

function getHeirTotal() {
    return heirRows.reduce((s, r) => s + (r.area || 0), 0);
}

function getMemberOptions(selectedId) {
    let opts = '<option value="">-- เลือกทายาท --</option>';
    MEMBERS.forEach(m => {
        const sel = (selectedId && selectedId == m.member_id) ? 'selected' : '';
        opts += `<option value="${m.member_id}" ${sel}>${(m.prefix||'')}${m.first_name} ${m.last_name} (${m.relationship||'-'})</option>`;
    });
    // Add new members (dynamically added)
    document.querySelectorAll('.new-member-row').forEach((row, idx) => {
        const fname = row.querySelector('.nm-fname')?.value || '';
        const lname = row.querySelector('.nm-lname')?.value || '';
        if (fname || lname) {
            const nmId = 'new_' + (idx + 1);
            const sel = (selectedId === nmId) ? 'selected' : '';
            opts += `<option value="${nmId}" ${sel}>★ ${fname} ${lname} (ใหม่)</option>`;
        }
    });
    return opts;
}

// ===== New Member Management =====
function addNewMemberRow() {
    newMemberCount++;
    const container = document.getElementById('newMembersList');
    const div = document.createElement('div');
    div.className = 'new-member-row';
    div.id = 'newMember_' + newMemberCount;
    div.style.cssText = 'display:grid; grid-template-columns: 90px 1fr 1fr 140px 130px 40px; gap:8px; align-items:end; margin-bottom:8px; background:#faf5ff; border:1px solid #e9d5ff; border-radius:8px; padding:10px;';
    div.innerHTML = `
        <div class="form-group" style="margin:0;">
            <label style="font-size:12px;">คำนำหน้า</label>
            <select name="new_members[${newMemberCount}][prefix]" class="form-control nm-prefix" style="font-size:13px;">
                <option value="นาย">นาย</option><option value="นาง">นาง</option><option value="นางสาว">นางสาว</option>
                <option value="เด็กชาย">ด.ช.</option><option value="เด็กหญิง">ด.ญ.</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size:12px;">ชื่อ</label>
            <input type="text" name="new_members[${newMemberCount}][first_name]" class="form-control nm-fname" placeholder="ชื่อ" onchange="refreshHeirOptions()">
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size:12px;">นามสกุล</label>
            <input type="text" name="new_members[${newMemberCount}][last_name]" class="form-control nm-lname" placeholder="นามสกุล" onchange="refreshHeirOptions()">
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size:12px;">เลขบัตร ปชช.</label>
            <input type="text" name="new_members[${newMemberCount}][id_card]" class="form-control" placeholder="13 หลัก" maxlength="13">
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size:12px;">ความสัมพันธ์</label>
            <select name="new_members[${newMemberCount}][relationship]" class="form-control" style="font-size:13px;">
                <option value="บุตร">บุตร</option><option value="คู่สมรส">คู่สมรส</option>
                <option value="บิดา">บิดา</option><option value="มารดา">มารดา</option>
                <option value="พี่น้อง">พี่น้อง</option><option value="ญาติ">ญาติ</option><option value="อื่นๆ">อื่นๆ</option>
            </select>
        </div>
        <button type="button" class="btn btn-secondary" onclick="removeNewMember(${newMemberCount})" style="padding:6px 8px;" title="ลบ"><i class="bi bi-trash"></i></button>
    `;
    container.appendChild(div);
    refreshHeirOptions();
}

function removeNewMember(idx) {
    const row = document.getElementById('newMember_' + idx);
    if (row) row.remove();
    refreshHeirOptions();
}

function refreshHeirOptions() {
    // Rebuild all heir dropdowns preserving selection
    document.querySelectorAll('.heir-member-select').forEach(sel => {
        const rowId = sel.dataset.rowid;
        const row = heirRows.find(r => r.id === rowId);
        const currentVal = row ? row.member_id : sel.value;
        sel.innerHTML = getMemberOptions(currentVal);
        if (currentVal) sel.value = currentVal;
    });
}

// ===== Recalculate Everything =====
function recalcAll() {
    recalcOwnerDisplay();
    buildHeirPool();
    recalcBudgets();
    updateMapColors();
}

function recalcOwnerDisplay() {
    const ownerTotal = getOwnerTotal();
    const count = Object.keys(ownerClaims).length;

    document.getElementById('ownerCount').textContent = count;
    document.getElementById('ownerTotal').textContent = ownerTotal.toFixed(2);
    document.getElementById('ownerTotal').style.color = ownerTotal > MAX_OWNER ? '#dc2626' : '#166534';
    document.getElementById('ownerWarning').style.display = ownerTotal > MAX_OWNER ? 'block' : 'none';

    // Update remainder cells
    PLOTS.forEach(p => {
        const remEl = document.getElementById('ownerRem-' + p.plot_id);
        if (!remEl) return;
        const used = ownerClaims[p.plot_id] || 0;
        if (used > 0) {
            const rem = Math.max(0, p.total_rai - used);
            remEl.textContent = rem > 0.001 ? rem.toFixed(2) + ' → ขั้นตอน 2' : '—';
            remEl.style.color = rem > 0.001 ? '#b45309' : '#999';
        } else {
            remEl.textContent = '—';
            remEl.style.color = '#999';
        }
    });
}

function recalcBudgets() {
    const ownerTotal = getOwnerTotal();
    const heirTotal = getHeirTotal();

    // Calc section 19
    let s19Total = 0;
    PLOTS.forEach(p => {
        const ownerUsed = ownerClaims[p.plot_id] || 0;
        const heirUsed = heirRows.filter(r => r.plot_id === p.plot_id).reduce((s, r) => s + (r.area || 0), 0);
        const remaining = p.total_rai - ownerUsed - heirUsed;
        if (remaining > 0.001) s19Total += remaining;
    });

    // Budget display
    document.getElementById('budgetOwner').textContent = ownerTotal.toFixed(2);
    document.getElementById('budgetHeir').textContent = heirTotal.toFixed(2);
    document.getElementById('budgetS19').textContent = s19Total.toFixed(2);

    // Bars
    const ownerPct = Math.min(100, (ownerTotal / MAX_OWNER) * 100);
    document.getElementById('barOwner').style.width = ownerPct + '%';
    document.getElementById('barOwner').style.background = ownerTotal > MAX_OWNER ? '#dc2626' : '#22c55e';

    const heirPct = Math.min(100, (heirTotal / MAX_HEIR) * 100);
    document.getElementById('barHeir').style.width = heirPct + '%';
    document.getElementById('barHeir').style.background = heirTotal > MAX_HEIR ? '#dc2626' : '#3b82f6';

    const s19Pct = TOTAL_ALL > 0 ? Math.min(100, (s19Total / TOTAL_ALL) * 100) : 0;
    document.getElementById('barS19').style.width = s19Pct + '%';

    // Heir totals
    document.getElementById('heirTotal').textContent = heirTotal.toFixed(2);
    document.getElementById('heirTotal').style.color = heirTotal > MAX_HEIR ? '#dc2626' : '#2563eb';
    document.getElementById('heirWarning').style.display = heirTotal > MAX_HEIR ? 'block' : 'none';
    document.getElementById('s19Total').textContent = s19Total.toFixed(2);

    // Section 19 detail
    buildS19Summary(s19Total);

    // Show/hide member section
    document.getElementById('memberSection').style.display = heirTotal > 0 ? 'block' : 'none';
    document.getElementById('s19Section').style.display = s19Total > 0.001 ? 'block' : 'none';
}

function buildS19Summary(s19Total) {
    const container = document.getElementById('s19Details');
    if (s19Total <= 0.001) {
        container.innerHTML = '<p style="color:#22c55e;">ไม่มีส่วนเกิน — ไม่ต้องดำเนินการ ม.19</p>';
        return;
    }

    let html = '<table style="width:100%; font-size:14px;"><thead><tr><th style="text-align:left;">แปลง</th><th style="text-align:right;">เนื้อที่ ม.19 (ไร่)</th></tr></thead><tbody>';
    PLOTS.forEach(p => {
        const ownerUsed = ownerClaims[p.plot_id] || 0;
        const heirUsed = heirRows.filter(r => r.plot_id === p.plot_id).reduce((s, r) => s + (r.area || 0), 0);
        const rem = p.total_rai - ownerUsed - heirUsed;
        if (rem > 0.001) {
            html += `<tr><td>${p.num_apar || p.plot_id}</td><td style="text-align:right; color:#dc2626; font-weight:600;">${rem.toFixed(2)}</td></tr>`;
        }
    });
    html += `</tbody><tfoot><tr style="font-weight:700; border-top:2px solid #dc2626;"><td>รวม ม.19</td><td style="text-align:right; color:#dc2626;">${s19Total.toFixed(2)} ไร่</td></tr></tfoot></table>`;
    container.innerHTML = html;
}

// ===== Map =====
function initMap() {
    const mapEl = document.getElementById('allocMap');
    if (!mapEl || typeof L === 'undefined') return;

    const map = L.map('allocMap', { scrollWheelZoom: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OSM',
        maxZoom: 19
    }).addTo(map);

    // Satellite layer option
    const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '© Esri'
    });
    L.control.layers({ 'แผนที่': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'), 'ดาวเทียม': satellite }).addTo(map);

    const bounds = [];
    PLOTS.forEach(p => {
        const color = '#9ca3af';
        if (p.polygon && Array.isArray(p.polygon) && p.polygon.length > 2) {
            const poly = L.polygon(p.polygon, {
                color: color, weight: 2, fillOpacity: 0.25, fillColor: color
            }).addTo(map);
            poly.bindTooltip(`${p.num_apar || p.plot_id}<br>${p.total_rai.toFixed(2)} ไร่`, { permanent: false });
            poly.on('click', () => highlightPlotInTable(p.plot_id));
            mapLayers[p.plot_id] = poly;
            bounds.push(...p.polygon);
        } else if (p.lat && p.lng) {
            const marker = L.circleMarker([p.lat, p.lng], {
                radius: 8, color: color, fillOpacity: 0.5, fillColor: color
            }).addTo(map);
            marker.bindTooltip(`${p.num_apar || p.plot_id}<br>${p.total_rai.toFixed(2)} ไร่`);
            marker.on('click', () => highlightPlotInTable(p.plot_id));
            mapLayers[p.plot_id] = marker;
            bounds.push([p.lat, p.lng]);
        }
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [30, 30] });
    } else {
        map.setView([14.5, 99.2], 10);
    }

    window._allocMap = map;
}

function updateMapColors() {
    PLOTS.forEach(p => {
        const layer = mapLayers[p.plot_id];
        if (!layer) return;

        const ownerUsed = ownerClaims[p.plot_id] || 0;
        const heirUsed = heirRows.filter(r => r.plot_id === p.plot_id).reduce((s, r) => s + (r.area || 0), 0);
        const s19 = p.total_rai - ownerUsed - heirUsed;

        let color = '#9ca3af'; // unallocated
        if (ownerUsed > 0 && heirUsed === 0 && s19 <= 0.001) {
            color = '#22c55e'; // all owner
        } else if (ownerUsed === 0 && heirUsed > 0 && s19 <= 0.001) {
            color = '#3b82f6'; // all heir
        } else if (ownerUsed === 0 && heirUsed === 0) {
            if (s19 > 0.001) color = '#ef4444'; // all section19
            else color = '#9ca3af';
        } else {
            // Mixed — use gradient approach via stripes or dominant color
            if (ownerUsed >= heirUsed && ownerUsed >= s19) color = '#22c55e';
            else if (heirUsed >= ownerUsed && heirUsed >= s19) color = '#3b82f6';
            else color = '#ef4444';
        }

        if (layer.setStyle) {
            layer.setStyle({ color: color, fillColor: color });
        } else if (layer.setRadius) {
            layer.setStyle({ color: color, fillColor: color });
        }
    });
}

function highlightPlotInTable(plotId) {
    const row = document.getElementById('row-owner-' + plotId);
    if (row) {
        row.style.transition = 'background 0.3s';
        row.style.background = '#fef3c7';
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => { row.style.background = ''; }, 2000);
    }
}

// ===== Form Submission =====
document.getElementById('allocationForm').addEventListener('submit', function(e) {
    const ownerTotal = getOwnerTotal();
    if (ownerTotal > MAX_OWNER) {
        e.preventDefault();
        alert('ผู้ครอบครองเกิน 20 ไร่! กรุณาลดเนื้อที่');
        return;
    }

    // Remove old hidden inputs
    this.querySelectorAll('.alloc-hidden').forEach(h => h.remove());

    let allocIndex = 0;

    // Owner allocations
    PLOTS.forEach(p => {
        const ownerArea = ownerClaims[p.plot_id] || 0;
        if (ownerArea > 0) {
            appendHidden(this, `allocations[${allocIndex}][plot_id]`, p.plot_id);
            appendHidden(this, `allocations[${allocIndex}][type]`, 'owner');
            appendHidden(this, `allocations[${allocIndex}][area]`, ownerArea.toFixed(4));
            appendHidden(this, `allocations[${allocIndex}][member_id]`, '');
            allocIndex++;
        }
    });

    // Heir allocations
    heirRows.forEach(row => {
        if (row.area > 0) {
            appendHidden(this, `allocations[${allocIndex}][plot_id]`, row.plot_id);
            appendHidden(this, `allocations[${allocIndex}][type]`, 'heir');
            appendHidden(this, `allocations[${allocIndex}][area]`, row.area.toFixed(4));
            appendHidden(this, `allocations[${allocIndex}][member_id]`, row.member_id || '');
            allocIndex++;
        }
    });

    // Section 19 allocations (auto-calculated remainder)
    PLOTS.forEach(p => {
        const ownerUsed = ownerClaims[p.plot_id] || 0;
        const heirUsed = heirRows.filter(r => r.plot_id === p.plot_id).reduce((s, r) => s + (r.area || 0), 0);
        const s19 = p.total_rai - ownerUsed - heirUsed;
        if (s19 > 0.001) {
            appendHidden(this, `allocations[${allocIndex}][plot_id]`, p.plot_id);
            appendHidden(this, `allocations[${allocIndex}][type]`, 'section19');
            appendHidden(this, `allocations[${allocIndex}][area]`, s19.toFixed(4));
            appendHidden(this, `allocations[${allocIndex}][member_id]`, '');
            allocIndex++;
        }
    });
});

function appendHidden(form, name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    input.className = 'alloc-hidden';
    form.appendChild(input);
}

// ===== Load Existing Allocations =====
function loadExisting() {
    if (!EXISTING || EXISTING.length === 0) return;

    // Group by plot_id and type
    EXISTING.forEach(a => {
        const pid = parseInt(a.plot_id);
        const type = a.allocation_type;
        const area = parseFloat(a.allocated_area_rai) || 0;

        if (type === 'owner' && area > 0) {
            // Check the owner checkbox and set area
            const cb = document.querySelector(`.owner-check[data-plotid="${pid}"]`);
            if (cb) {
                cb.checked = true;
                const areaInput = document.getElementById('ownerArea-' + pid);
                if (areaInput) {
                    areaInput.disabled = false;
                    areaInput.value = area.toFixed(2);
                }
                ownerClaims[pid] = area;
            }
        } else if (type === 'heir' && area > 0) {
            heirRows.push({
                id: 'hr_existing_' + a.id,
                plot_id: pid,
                area: area,
                member_id: a.member_id ? String(a.member_id) : '',
            });
        }
    });

    recalcAll();
}

// ===== Init =====
initMap();
loadExisting();
if (EXISTING.length === 0) recalcAll();
</script>
