<?php
/**
 * รายงาน — เลือกประเภทรายงาน
 */

$db = getDB();

// Load report templates
$templatesStmt = $db->query("SELECT * FROM report_templates WHERE is_active = 1 ORDER BY template_id");
$templates = $templatesStmt->fetchAll();

// Group by type
$reportGroups = [
    'ข้อมูลราษฎร' => ['RPT_VILLAGER_LIST', 'RPT_DOCUMENT_LIST'],
    'ข้อมูลที่ดิน' => ['RPT_PLOT_REGISTRY', 'RPT_PLOT_SURVEY', 'RPT_ZONE_SUMMARY', 'RPT_LANDUSE_SUMMARY'],
    'คำร้อง' => ['RPT_CASE_STATUS', 'RPT_CASE_DETAIL'],
    'สรุปภาพรวม' => ['RPT_EXECUTIVE', 'RPT_ACTIVITY_LOG'],
];

$icons = [
    'RPT_VILLAGER_LIST' => 'bi-people-fill',
    'RPT_PLOT_REGISTRY' => 'bi-map-fill',
    'RPT_PLOT_SURVEY' => 'bi-clipboard-data-fill',
    'RPT_ZONE_SUMMARY' => 'bi-pie-chart-fill',
    'RPT_LANDUSE_SUMMARY' => 'bi-bar-chart-fill',
    'RPT_CASE_STATUS' => 'bi-folder-fill',
    'RPT_CASE_DETAIL' => 'bi-file-text-fill',
    'RPT_EXECUTIVE' => 'bi-graph-up-arrow',
    'RPT_DOCUMENT_LIST' => 'bi-file-earmark-check-fill',
    'RPT_ACTIVITY_LOG' => 'bi-clock-history',
];

$colors = [
    'RPT_VILLAGER_LIST' => 'green',
    'RPT_PLOT_REGISTRY' => 'blue',
    'RPT_PLOT_SURVEY' => 'blue',
    'RPT_ZONE_SUMMARY' => 'orange',
    'RPT_LANDUSE_SUMMARY' => 'orange',
    'RPT_CASE_STATUS' => 'red',
    'RPT_CASE_DETAIL' => 'red',
    'RPT_EXECUTIVE' => 'purple',
    'RPT_DOCUMENT_LIST' => 'green',
    'RPT_ACTIVITY_LOG' => 'purple',
];
?>

<style>
    .report-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
    }

    .report-card {
        background: white;
        border-radius: var(--border-radius-lg);
        padding: 24px;
        border: 1px solid var(--gray-200);
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        cursor: pointer;
        display: flex;
        gap: 16px;
        align-items: flex-start;
    }

    .report-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-300);
    }

    .report-card .rpt-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
    }

    .rpt-icon.green {
        background: var(--primary-100);
        color: var(--primary-700);
    }

    .rpt-icon.blue {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .rpt-icon.orange {
        background: #ffedd5;
        color: #c2410c;
    }

    .rpt-icon.red {
        background: #fee2e2;
        color: #dc2626;
    }

    .rpt-icon.purple {
        background: #ede9fe;
        color: #6d28d9;
    }

    .report-card .rpt-info h4 {
        font-size: 15px;
        font-weight: 600;
        color: var(--gray-800);
    }

    .report-card .rpt-info p {
        font-size: 13px;
        color: var(--gray-500);
        margin-top: 4px;
    }

    .report-card .rpt-actions {
        margin-top: 12px;
        display: flex;
        gap: 8px;
    }

    .group-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--gray-600);
        margin: 28px 0 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--gray-200);
    }

    .group-title:first-child {
        margin-top: 0;
    }
</style>

<?php foreach ($reportGroups as $groupName => $codes): ?>
    <div class="group-title"><i class="bi bi-folder2-open" style="margin-right:6px;"></i>
        <?= $groupName ?>
    </div>
    <div class="report-grid">
        <?php foreach ($templates as $tpl): ?>
            <?php if (in_array($tpl['template_code'], $codes)): ?>
                <div class="report-card" onclick="selectReport('<?= $tpl['template_code'] ?>')">
                    <div class="rpt-icon <?= $colors[$tpl['template_code']] ?? 'green' ?>">
                        <i class="bi <?= $icons[$tpl['template_code']] ?? 'bi-file-earmark' ?>"></i>
                    </div>
                    <div class="rpt-info">
                        <h4>
                            <?= htmlspecialchars($tpl['template_name']) ?>
                        </h4>
                        <p>
                            <?= htmlspecialchars($tpl['description'] ?? '') ?>
                        </p>
                        <div class="rpt-actions">
                            <span class="badge badge-info"><i class="bi bi-filetype-pdf"></i> PDF</span>
                            <span class="badge badge-success"><i class="bi bi-filetype-xlsx"></i> Excel</span>
                            <span class="badge badge-gray">
                                <?= $tpl['orientation'] === 'landscape' ? 'แนวนอน' : 'แนวตั้ง' ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<script>
    function selectReport(code) {
        window.location.href = 'index.php?page=reports&action=preview&code=' + encodeURIComponent(code);
    }
</script>