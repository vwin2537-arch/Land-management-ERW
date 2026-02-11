<?php
/**
 * Template: รายงานสรุปผู้บริหาร (Executive Summary)
 * Uses $data as associative array of stats
 */
if (empty($data)) {
    echo '<div class="empty-state"><i class="bi bi-inbox"></i><p>ไม่พบข้อมูล</p></div>';
    return;
}
$s = $data;
?>

<style>
    .exec-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .exec-stat {
        text-align: center;
        padding: 20px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
    }

    .exec-stat .num {
        font-size: 32px;
        font-weight: 700;
        color: #15803d;
    }

    .exec-stat .lbl {
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
    }

    .exec-section {
        margin-bottom: 24px;
    }

    .exec-section h4 {
        font-size: 14px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 10px;
        padding-bottom: 6px;
        border-bottom: 2px solid #e5e7eb;
    }

    @media print {
        .exec-grid {
            grid-template-columns: repeat(4, 1fr) !important;
        }

        .exec-stat {
            border: 1px solid #ccc !important;
        }
    }
</style>

<!-- Summary Stats -->
<div class="exec-grid">
    <div class="exec-stat">
        <div class="num">
            <?= number_format($s['villager_count']) ?>
        </div>
        <div class="lbl">ราษฎรทั้งหมด (ราย)</div>
    </div>
    <div class="exec-stat">
        <div class="num">
            <?= number_format($s['plot_count']) ?>
        </div>
        <div class="lbl">แปลงที่ดินทั้งหมด</div>
    </div>
    <div class="exec-stat">
        <div class="num" style="color:#2563eb;">
            <?= number_format($s['total_area'], 1) ?>
        </div>
        <div class="lbl">พื้นที่ครอบครองรวม (ไร่)</div>
    </div>
    <div class="exec-stat">
        <div class="num" style="color:#dc2626;">
            <?= number_format($s['open_cases']) ?>
        </div>
        <div class="lbl">คำร้องที่เปิดอยู่</div>
    </div>
</div>

<!-- Plot Status -->
<div class="exec-section">
    <h4>📊 สถานะแปลงที่ดิน</h4>
    <table class="print-table">
        <thead>
            <tr>
                <th>สถานะ</th>
                <th style="text-align:right;">จำนวน (แปลง)</th>
                <th style="text-align:right;">สัดส่วน</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($s['plot_status'] as $ps):
                $pct = $s['plot_count'] > 0 ? round($ps['cnt'] / $s['plot_count'] * 100, 1) : 0; ?>
                <tr>
                    <td>
                        <?= PLOT_STATUS_LABELS[$ps['status']] ?? $ps['status'] ?>
                    </td>
                    <td style="text-align:right; font-weight:600;">
                        <?= number_format($ps['cnt']) ?>
                    </td>
                    <td style="text-align:right;">
                        <?= $pct ?>%
                        <div style="background:#e5e7eb; height:6px; border-radius:3px; margin-top:2px;">
                            <div
                                style="background:<?= PLOT_STATUS_COLORS[$ps['status']] ?? '#888' ?>; width:<?= $pct ?>%; height:100%; border-radius:3px;">
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Land Use -->
<div class="exec-section">
    <h4>🌾 ประเภทการใช้ที่ดิน</h4>
    <table class="print-table">
        <thead>
            <tr>
                <th>ประเภท</th>
                <th style="text-align:right;">จำนวน (แปลง)</th>
                <th style="text-align:right;">พื้นที่รวม (ไร่)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($s['land_use'] as $lu): ?>
                <tr>
                    <td>
                        <?= LAND_USE_LABELS[$lu['land_use_type']] ?? $lu['land_use_type'] ?>
                    </td>
                    <td style="text-align:right; font-weight:600;">
                        <?= number_format($lu['cnt']) ?>
                    </td>
                    <td style="text-align:right;">
                        <?= number_format($lu['total_rai'], 1) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Case Types -->
<div class="exec-section">
    <h4>📋 สถิติคำร้อง (ทั้งหมด
        <?= number_format($s['case_count']) ?> เรื่อง)
    </h4>
    <table class="print-table">
        <thead>
            <tr>
                <th>ประเภทคำร้อง</th>
                <th style="text-align:right;">จำนวน</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($s['case_types'] as $ct): ?>
                <tr>
                    <td>
                        <?= CASE_TYPE_LABELS[$ct['case_type']] ?? $ct['case_type'] ?>
                    </td>
                    <td style="text-align:right; font-weight:600;">
                        <?= number_format($ct['cnt']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>