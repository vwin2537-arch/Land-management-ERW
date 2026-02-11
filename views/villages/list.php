<?php
/**
 * หมู่บ้านสำรวจ — รายการ 12 หมู่บ้าน (Card Grid)
 */

$db = getDB();

// ดึงข้อมูลสรุปรายหมู่บ้าน
$sql = "SELECT 
    MAX(ban_e) as ban_e,
    par_ban,
    MAX(par_moo) as par_moo,
    MAX(par_tam) as par_tam,
    MAX(par_amp) as par_amp,
    MAX(par_prov) as par_prov,
    COUNT(*) as total_plots,
    COUNT(DISTINCT villager_id) as total_villagers,
    ROUND(SUM(area_rai + area_ngan/4 + area_sqwa/400), 2) as total_area_rai,
    SUM(CASE WHEN status = 'surveyed' THEN 1 ELSE 0 END) as surveyed_count,
    SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN data_issues IS NOT NULL AND data_issues != '' THEN 1 ELSE 0 END) as issues_count,
    AVG(latitude) as center_lat,
    AVG(longitude) as center_lng
FROM land_plots
WHERE par_ban IS NOT NULL AND par_ban != ''
GROUP BY par_ban
ORDER BY total_plots DESC";

$villages = $db->query($sql)->fetchAll();
$totalPlots = array_sum(array_column($villages, 'total_plots'));
$totalVillagers = $db->query("SELECT COUNT(DISTINCT villager_id) FROM land_plots WHERE ban_e IS NOT NULL")->fetchColumn();

// สีประจำหมู่บ้าน
$villageColors = [
    'BKR' => ['#059669', '#d1fae5'], // เขียวเข้ม
    'PDS' => ['#2563eb', '#dbeafe'], // น้ำเงิน
    'TSL' => ['#7c3aed', '#ede9fe'], // ม่วง
    'CSD' => ['#dc2626', '#fee2e2'], // แดง
    'TKT' => ['#ea580c', '#ffedd5'], // ส้ม
    'TKY' => ['#0891b2', '#cffafe'], // ฟ้า
    'BPT' => ['#16a34a', '#dcfce7'], // เขียวอ่อน
    'BMT' => ['#9333ea', '#f3e8ff'], // ม่วงเข้ม
    'BPM' => ['#0284c7', '#e0f2fe'], // ฟ้าเข้ม
    'BKK' => ['#b45309', '#fef3c7'], // น้ำตาล
    'POK' => ['#be185d', '#fce7f3'], // ชมพู
    'BTS' => ['#4f46e5', '#e0e7ff'], // คราม
];
?>

<!-- Summary Stats -->
<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:24px;">
    <div class="card" style="border-left:4px solid var(--primary-600);">
        <div class="card-body" style="padding:20px; display:flex; align-items:center; gap:16px;">
            <div style="width:48px; height:48px; border-radius:12px; background:var(--primary-50); display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-houses-fill" style="font-size:22px; color:var(--primary-600);"></i>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:var(--gray-800);"><?= count($villages) ?></div>
                <div style="font-size:13px; color:var(--gray-500);">หมู่บ้านสำรวจ</div>
            </div>
        </div>
    </div>
    <div class="card" style="border-left:4px solid var(--success);">
        <div class="card-body" style="padding:20px; display:flex; align-items:center; gap:16px;">
            <div style="width:48px; height:48px; border-radius:12px; background:#dcfce7; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-map-fill" style="font-size:22px; color:var(--success);"></i>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:var(--gray-800);"><?= number_format($totalPlots) ?></div>
                <div style="font-size:13px; color:var(--gray-500);">แปลงทั้งหมด</div>
            </div>
        </div>
    </div>
    <div class="card" style="border-left:4px solid var(--info);">
        <div class="card-body" style="padding:20px; display:flex; align-items:center; gap:16px;">
            <div style="width:48px; height:48px; border-radius:12px; background:#dbeafe; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-people-fill" style="font-size:22px; color:var(--info);"></i>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:var(--gray-800);"><?= number_format($totalVillagers) ?></div>
                <div style="font-size:13px; color:var(--gray-500);">ราษฎรทั้งหมด</div>
            </div>
        </div>
    </div>
</div>

<!-- Village Cards Grid -->
<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:20px;">
    <?php foreach ($villages as $v):
        $code = $v['ban_e'];
        $color = $villageColors[$code] ?? ['#6b7280', '#f3f4f6'];
        $pct = $v['total_plots'] > 0 ? round($v['surveyed_count'] / $v['total_plots'] * 100) : 0;
        $issuesPct = $v['total_plots'] > 0 ? round($v['issues_count'] / $v['total_plots'] * 100) : 0;
    ?>
        <div class="card" style="overflow:hidden; transition:transform 0.2s, box-shadow 0.2s; cursor:pointer;"
            onclick="location.href='index.php?page=villages&action=view&ban_e=<?= urlencode($code) ?>'"
            onmouseenter="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(0,0,0,0.12)';"
            onmouseleave="this.style.transform=''; this.style.boxShadow='';">

            <!-- Header Bar -->
            <div style="background:<?= $color[0] ?>; padding:16px 20px; color:white;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h3 style="margin:0; font-size:18px; font-weight:600;">
                            🏘️ บ้าน<?= htmlspecialchars($v['par_ban']) ?>
                        </h3>
                        <p style="margin:4px 0 0; opacity:0.85; font-size:13px;">
                            รหัส <?= htmlspecialchars($code) ?> · ม.<?= htmlspecialchars($v['par_moo']) ?>
                            ต.<?= htmlspecialchars($v['par_tam']) ?>
                        </p>
                    </div>
                    <div style="background:rgba(255,255,255,0.2); padding:8px 12px; border-radius:10px; text-align:center;">
                        <div style="font-size:22px; font-weight:700;"><?= $v['total_plots'] ?></div>
                        <div style="font-size:10px; opacity:0.9;">แปลง</div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="card-body" style="padding:16px 20px;">
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div style="text-align:center;">
                        <div style="font-size:20px; font-weight:700; color:<?= $color[0] ?>;"><?= $v['total_villagers'] ?></div>
                        <div style="font-size:11px; color:var(--gray-500);">ราษฎร</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:20px; font-weight:700; color:<?= $color[0] ?>;"><?= number_format($v['total_area_rai'], 1) ?></div>
                        <div style="font-size:11px; color:var(--gray-500);">ไร่</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:20px; font-weight:700; color:<?= $color[0] ?>;"><?= $pct ?>%</div>
                        <div style="font-size:11px; color:var(--gray-500);">สำรวจแล้ว</div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; font-size:11px; color:var(--gray-500); margin-bottom:4px;">
                        <span>สำรวจแล้ว <?= $v['surveyed_count'] ?> / <?= $v['total_plots'] ?></span>
                        <?php if ($v['issues_count'] > 0): ?>
                            <span style="color:#ef4444;">⚠ <?= $v['issues_count'] ?> ปัญหา</span>
                        <?php endif; ?>
                    </div>
                    <div style="height:6px; background:var(--gray-100); border-radius:3px; overflow:hidden;">
                        <div style="height:100%; width:<?= $pct ?>%; background:<?= $color[0] ?>; border-radius:3px; transition:width 0.5s;"></div>
                    </div>
                </div>

                <!-- Footer -->
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:12px; color:var(--gray-400);">
                        อ.<?= htmlspecialchars($v['par_amp']) ?> จ.<?= htmlspecialchars($v['par_prov']) ?>
                    </span>
                    <span style="font-size:12px; color:<?= $color[0] ?>; font-weight:500;">
                        ดูรายละเอียด →
                    </span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
