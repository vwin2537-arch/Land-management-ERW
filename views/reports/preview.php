<?php
/**
 * Report Preview — ดูตัวอย่างรายงาน + กรองข้อมูล + พิมพ์ PDF + Export Excel
 */
require_once __DIR__ . '/../../controllers/ReportController.php';

$db = getDB();
$code = $_GET['code'] ?? '';

// Load template info
$tplStmt = $db->prepare("SELECT * FROM report_templates WHERE template_code = :code AND is_active = 1");
$tplStmt->execute(['code' => $code]);
$template = $tplStmt->fetch();

if (!$template) {
    echo '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> ไม่พบรายงานที่เลือก</div>';
    echo '<a href="index.php?page=reports" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> กลับ</a>';
    return;
}

// Collect filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'land_use_type' => $_GET['land_use_type'] ?? '',
    'zone' => $_GET['zone'] ?? '',
    'case_type' => $_GET['case_type'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'plot_id' => $_GET['plot_id'] ?? '',
    'case_id' => $_GET['case_id'] ?? '',
    'province' => $_GET['province'] ?? '',
];

// Get report data
$data = ReportController::getData($code, $filters);
$isExecutive = ($code === 'RPT_EXECUTIVE');
$isSurvey = ($code === 'RPT_PLOT_SURVEY');
?>

<style>
    .report-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }

    .filter-bar {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: flex-end;
        margin-bottom: 16px;
    }

    .filter-bar .form-group {
        margin-bottom: 0;
        min-width: 140px;
    }

    .filter-bar .form-group label {
        font-size: 12px;
        margin-bottom: 4px;
    }

    .filter-bar .form-control {
        padding: 8px 10px;
        font-size: 13px;
    }

    /* Print-specific styles */
    .print-area {
        background: white;
        padding: 30px;
        border-radius: var(--border-radius-lg);
    }

    .print-header {
        text-align: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid #333;
    }

    .print-header h2 {
        font-size: 18px;
        font-weight: 700;
    }

    .print-header p {
        font-size: 13px;
        color: var(--gray-500);
    }

    .print-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .print-table th {
        background: #f3f4f6;
        padding: 8px 10px;
        border: 1px solid #d1d5db;
        font-size: 11px;
        text-align: left;
        white-space: nowrap;
    }

    .print-table td {
        padding: 7px 10px;
        border: 1px solid #e5e7eb;
    }

    .print-table tbody tr:nth-child(even) {
        background: #fafafa;
    }

    .print-footer {
        margin-top: 24px;
        padding-top: 12px;
        border-top: 1px solid #ddd;
        font-size: 11px;
        color: #888;
        display: flex;
        justify-content: space-between;
    }

    @media print {

        .no-print,
        .sidebar,
        .topbar {
            display: none !important;
        }

        .main-content {
            margin-left: 0 !important;
        }

        .page-content {
            padding: 0 !important;
        }

        .print-area {
            padding: 10px;
            box-shadow: none !important;
            border: none !important;
        }

        .print-table {
            font-size: 10px;
        }

        .print-table th {
            background: #eee !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<!-- Toolbar -->
<div class="report-toolbar no-print">
    <div class="d-flex gap-1 align-center">
        <a href="index.php?page=reports" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> กลับ</a>
        <h3 style="margin:0; font-size:16px;">
            <?= htmlspecialchars($template['template_name']) ?>
        </h3>
    </div>
    <div class="d-flex gap-1">
        <button class="btn btn-primary btn-sm" onclick="window.print()">
            <i class="bi bi-printer"></i> พิมพ์ / PDF
        </button>
        <?php if (!$isExecutive && !$isSurvey): ?>
            <a href="index.php?page=reports&action=export&code=<?= $code ?>&<?= http_build_query($filters) ?>"
                class="btn btn-info btn-sm">
                <i class="bi bi-file-earmark-excel"></i> Excel
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3 no-print">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" action="index.php">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="action" value="preview">
            <input type="hidden" name="code" value="<?= $code ?>">
            <div class="filter-bar">
                <?php if (in_array($code, ['RPT_VILLAGER_LIST'])): ?>
                    <div class="form-group">
                        <label>ค้นหา</label>
                        <input type="text" name="search" class="form-control"
                            value="<?= htmlspecialchars($filters['search']) ?>" placeholder="ชื่อ, เลขบัตร...">
                    </div>
                    <div class="form-group">
                        <label>จังหวัด</label>
                        <input type="text" name="province" class="form-control"
                            value="<?= htmlspecialchars($filters['province']) ?>" placeholder="จังหวัด">
                    </div>
                <?php endif; ?>

                <?php if (in_array($code, ['RPT_PLOT_REGISTRY', 'RPT_PLOT_SURVEY'])): ?>
                    <div class="form-group">
                        <label>สถานะ</label>
                        <select name="status" class="form-control">
                            <option value="">ทั้งหมด</option>
                            <?php foreach (PLOT_STATUS_LABELS as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>>
                                    <?= $v ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>การใช้ที่ดิน</label>
                        <select name="land_use_type" class="form-control">
                            <option value="">ทั้งหมด</option>
                            <?php foreach (LAND_USE_LABELS as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $filters['land_use_type'] === $k ? 'selected' : '' ?>>
                                    <?= $v ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>โซน</label>
                        <input type="text" name="zone" class="form-control"
                            value="<?= htmlspecialchars($filters['zone']) ?>" placeholder="โซน">
                    </div>
                <?php endif; ?>

                <?php if (in_array($code, ['RPT_CASE_STATUS'])): ?>
                    <div class="form-group">
                        <label>สถานะ</label>
                        <select name="status" class="form-control">
                            <option value="">ทั้งหมด</option>
                            <?php foreach (CASE_STATUS_LABELS as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>>
                                    <?= $v ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>ประเภท</label>
                        <select name="case_type" class="form-control">
                            <option value="">ทั้งหมด</option>
                            <?php foreach (CASE_TYPE_LABELS as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $filters['case_type'] === $k ? 'selected' : '' ?>>
                                    <?= $v ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if (in_array($code, ['RPT_CASE_STATUS', 'RPT_ACTIVITY_LOG'])): ?>
                    <div class="form-group">
                        <label>ตั้งแต่</label>
                        <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?>">
                    </div>
                    <div class="form-group">
                        <label>ถึง</label>
                        <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?>">
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> กรอง</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Print Area -->
<div class="print-area">
    <!-- Header -->
    <div class="print-header">
        <h2>ระบบจัดการที่ดินทำกิน — อุทยานแห่งชาติ</h2>
        <h3 style="font-size:16px; margin-top:4px;">
            <?= htmlspecialchars($template['template_name']) ?>
        </h3>
        <p style="margin-top:4px;">วันที่พิมพ์:
            <?= date('d/m/') . (date('Y') + 543) ?> เวลา
            <?= date('H:i') ?> น.
        </p>
    </div>

    <?php if ($isExecutive): ?>
        <!-- Executive Summary — special layout -->
        <?php include __DIR__ . '/templates/executive_summary.php'; ?>
    <?php elseif ($isSurvey): ?>
        <!-- Plot Survey — card-per-plot layout -->
        <?php include __DIR__ . '/templates/plot_survey.php'; ?>
    <?php else: ?>
        <!-- Standard Table Report -->
        <?php if (empty($data)): ?>
            <div class="empty-state" style="padding:40px;">
                <i class="bi bi-inbox"></i>
                <p>ไม่พบข้อมูลตามเงื่อนไขที่กำหนด</p>
            </div>
        <?php else: ?>
            <p style="font-size:12px; color:#666; margin-bottom:12px;">จำนวน
                <?= number_format(count($data)) ?> รายการ
            </p>
            <table class="print-table">
                <thead>
                    <tr>
                        <?php foreach (array_keys($data[0]) as $col): ?>
                            <th>
                                <?= htmlspecialchars($col) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <?php foreach ($row as $val): ?>
                                <td>
                                    <?= htmlspecialchars($val ?? '-') ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Footer -->
    <div class="print-footer">
        <span>ระบบจัดการที่ดินทำกิน v
            <?= APP_VERSION ?>
        </span>
        <span>พิมพ์โดย:
            <?= htmlspecialchars($_SESSION['full_name'] ?? 'ระบบ') ?>
        </span>
    </div>
</div>