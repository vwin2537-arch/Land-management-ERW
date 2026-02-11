<?php
/**
 * Template: รายงานสำรวจรายแปลง (Plot Survey)
 * One-page-per-plot layout for print
 * Uses $data as array of plot records with joined villager info
 */
if (empty($data)) {
    echo '<div class="empty-state"><i class="bi bi-inbox"></i><p>ไม่พบข้อมูลแปลง</p></div>';
    return;
}
?>

<style>
    .survey-card {
        border: 2px solid #d1d5db;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        page-break-after: always;
    }

    .survey-card:last-child {
        page-break-after: auto;
    }

    .survey-card h3 {
        font-size: 16px;
        font-weight: 700;
        color: #15803d;
        margin-bottom: 16px;
        padding-bottom: 8px;
        border-bottom: 2px solid #22c55e;
    }

    .survey-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px 20px;
    }

    .survey-field {
        margin-bottom: 8px;
    }

    .survey-field .label {
        font-size: 11px;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .survey-field .value {
        font-size: 14px;
        font-weight: 500;
        color: #1f2937;
        border-bottom: 1px dotted #d1d5db;
        padding-bottom: 2px;
        min-height: 22px;
    }

    .survey-img {
        max-width: 100%;
        max-height: 200px;
        border-radius: 8px;
        border: 1px solid #d1d5db;
    }

    @media print {
        .survey-card {
            border: 1px solid #888 !important;
        }
    }
</style>

<p style="font-size:12px; color:#666; margin-bottom:16px;">จำนวน
    <?= count($data) ?> แปลง
</p>

<?php foreach ($data as $p): ?>
    <div class="survey-card">
        <h3>🗺️ แบบฟอร์มสำรวจ —
            <?= htmlspecialchars($p['plot_code']) ?>
        </h3>

        <div class="survey-grid">
            <!-- Owner info -->
            <div>
                <div class="survey-field">
                    <div class="label">ชื่อผู้ครอบครอง</div>
                    <div class="value">
                        <?= htmlspecialchars(($p['prefix'] ?? '') . $p['first_name'] . ' ' . $p['last_name']) ?>
                    </div>
                </div>
                <div class="survey-field">
                    <div class="label">เลขบัตรประชาชน</div>
                    <div class="value" style="font-family:monospace;">
                        <?= htmlspecialchars($p['id_card_number']) ?>
                    </div>
                </div>
                <div class="survey-field">
                    <div class="label">โทรศัพท์</div>
                    <div class="value">
                        <?= htmlspecialchars($p['phone'] ?? '-') ?>
                    </div>
                </div>
                <div class="survey-field">
                    <div class="label">ที่อยู่</div>
                    <div class="value">
                        <?= htmlspecialchars(($p['address'] ?? '') . ' ' . ($p['village_name'] ?? '')) ?>
                    </div>
                </div>
            </div>

            <!-- Plot info -->
            <div>
                <div class="survey-field">
                    <div class="label">อุทยาน / โซน</div>
                    <div class="value">
                        <?= htmlspecialchars($p['park_name'] ?? '-') ?> /
                        <?= htmlspecialchars($p['zone'] ?? '-') ?>
                    </div>
                </div>
                <div class="survey-field">
                    <div class="label">ขนาดพื้นที่</div>
                    <div class="value">
                        <?= $p['area_rai'] ?> ไร่
                        <?= $p['area_ngan'] ?> งาน
                        <?= $p['area_sqwa'] ?> ตร.วา
                    </div>
                </div>
                <div class="survey-field">
                    <div class="label">ประเภทการใช้ที่ดิน</div>
                    <div class="value">
                        <?= LAND_USE_LABELS[$p['land_use_type']] ?? $p['land_use_type'] ?>
                        <?= $p['crop_type'] ? '(' . htmlspecialchars($p['crop_type']) . ')' : '' ?>
                    </div>
                </div>
                <div class="survey-field">
                    <div class="label">ครอบครองตั้งแต่ปี</div>
                    <div class="value">
                        <?= htmlspecialchars($p['occupation_since'] ?? '-') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom section -->
        <div class="survey-grid" style="margin-top:12px;">
            <div>
                <div class="survey-field">
                    <div class="label">สถานะ</div>
                    <div class="value" style="font-weight:700; color:<?= PLOT_STATUS_COLORS[$p['status']] ?? '#888' ?>;">
                        <?= PLOT_STATUS_LABELS[$p['status']] ?? $p['status'] ?>
                    </div>
                </div>
                <div class="survey-field">
                    <div class="label">เอกสารสิทธิ์</div>
                    <div class="value">
                        <?= $p['has_document'] ? '✅ มี — ' . htmlspecialchars($p['document_type'] ?? '') : '❌ ไม่มี' ?>
                    </div>
                </div>
                <div class="survey-field">
                    <div class="label">พิกัด GPS</div>
                    <div class="value" style="font-family:monospace; font-size:12px;">
                        <?= $p['latitude'] && $p['longitude'] ? $p['latitude'] . ', ' . $p['longitude'] : '-' ?>
                    </div>
                </div>
            </div>
            <div>
                <div class="survey-field">
                    <div class="label">วันที่สำรวจ</div>
                    <div class="value">
                        <?= $p['survey_date'] ? date('d/m/Y', strtotime($p['survey_date'])) : '-' ?>
                    </div>
                </div>
                <div class="survey-field">
                    <div class="label">ผู้สำรวจ</div>
                    <div class="value">
                        <?= htmlspecialchars($p['surveyor_name'] ?? '-') ?>
                    </div>
                </div>
                <div class="survey-field">
                    <div class="label">หมายเหตุ</div>
                    <div class="value">
                        <?= htmlspecialchars($p['notes'] ?? '-') ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($p['plot_image_path'])): ?>
            <div style="margin-top:12px; text-align:center;">
                <img src="<?= htmlspecialchars($p['plot_image_path']) ?>" class="survey-img" alt="รูปแปลง">
                <p style="font-size:10px; color:#9ca3af; margin-top:4px;">รูปถ่ายแปลง
                    <?= htmlspecialchars($p['plot_code']) ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Signature area -->
        <div
            style="display:grid; grid-template-columns:1fr 1fr; gap:40px; margin-top:24px; padding-top:16px; border-top:1px dashed #ccc;">
            <div style="text-align:center;">
                <div style="height:50px;"></div>
                <div style="border-top:1px solid #333; padding-top:4px; font-size:12px;">ลงชื่อผู้ครอบครอง</div>
                <p style="font-size:11px; color:#888;">(...................................)</p>
            </div>
            <div style="text-align:center;">
                <div style="height:50px;"></div>
                <div style="border-top:1px solid #333; padding-top:4px; font-size:12px;">ลงชื่อเจ้าหน้าที่สำรวจ</div>
                <p style="font-size:11px; color:#888;">(...................................)</p>
            </div>
        </div>
    </div>
<?php endforeach; ?>