<?php
/**
 * แปลงที่ดินทำกิน — รายการ
 */

$db = getDB();
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$page_num = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page_num - 1) * ITEMS_PER_PAGE;

// Build query
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(lp.plot_code LIKE :s1 OR v.first_name LIKE :s2 OR v.last_name LIKE :s3 OR v.id_card_number LIKE :s4)";
    $params['s1'] = "%$search%";
    $params['s2'] = "%$search%";
    $params['s3'] = "%$search%";
    $params['s4'] = "%$search%";
}

if ($statusFilter !== '') {
    $where[] = "lp.status = :status";
    $params['status'] = $statusFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM land_plots lp JOIN villagers v ON lp.villager_id = v.villager_id $whereClause");
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalItems / ITEMS_PER_PAGE));

// Fetch
$sql = "SELECT lp.*, v.first_name, v.last_name, v.prefix, v.id_card_number,
               u.full_name as surveyor_name
        FROM land_plots lp
        JOIN villagers v ON lp.villager_id = v.villager_id
        LEFT JOIN users u ON lp.surveyed_by = u.user_id
        $whereClause
        ORDER BY lp.created_at DESC
        LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$plots = $stmt->fetchAll();
?>

<!-- Page Header -->
<div class="d-flex justify-between align-center mb-3">
    <p class="text-muted">ทั้งหมด
        <?= number_format($totalItems) ?> แปลง
    </p>
    <div class="d-flex gap-1">
        <?php if ($_SESSION['role'] !== ROLE_VIEWER): ?>
            <a href="index.php?page=plots&action=create" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> เพิ่มแปลงที่ดิน
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" class="d-flex gap-1 align-center" style="flex-wrap:wrap;">
            <input type="hidden" name="page" value="plots">
            <div class="search-bar" style="flex:1; min-width:250px; max-width:100%;">
                <i class="bi bi-search"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" data-live-search="true"
                    placeholder="ค้นหาด้วย รหัสแปลง / ชื่อเจ้าของ / เลขบัตร ปชช...">
            </div>
            <select name="status" class="form-control" style="width:auto; min-width:160px;">
                <option value="">-- สถานะทั้งหมด --</option>
                <?php foreach (PLOT_STATUS_LABELS as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $statusFilter === $key ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">ค้นหา</button>
            <?php if ($search || $statusFilter): ?>
                <a href="index.php?page=plots" class="btn btn-secondary btn-sm">ล้าง</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($plots)): ?>
            <div class="empty-state">
                <i class="bi bi-map"></i>
                <h4>ไม่พบข้อมูลแปลงที่ดิน</h4>
                <p>
                    <?= $search ? 'ลองค้นหาด้วยคำอื่น' : 'เริ่มต้นโดยเพิ่มข้อมูลแปลงที่ดินแปลงแรก' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>รหัสแปลง</th>
                            <th>เจ้าของ/ผู้ครอบครอง</th>
                            <th>พื้นที่</th>
                            <th>ประเภทการใช้</th>
                            <th>โซน</th>
                            <th>สถานะ</th>
                            <th>วันที่สำรวจ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plots as $i => $p): ?>
                            <tr>
                                <td>
                                    <?= $offset + $i + 1 ?>
                                </td>
                                <td><strong style="color:var(--primary-700);">
                                        <?= htmlspecialchars($p['plot_code']) ?>
                                    </strong></td>
                                <td>
                                    <?= htmlspecialchars(($p['prefix'] ?? '') . $p['first_name'] . ' ' . $p['last_name']) ?>
                                    <br><small class="text-muted" style="font-family:monospace;">
                                        <?= $p['id_card_number'] ?>
                                    </small>
                                </td>
                                <td style="white-space:nowrap;">
                                    <?= number_format($p['area_rai']) ?> ไร่
                                    <?= number_format($p['area_ngan']) ?> งาน
                                    <?= number_format($p['area_sqwa']) ?> วา
                                </td>
                                <td>
                                    <?= LAND_USE_LABELS[$p['land_use_type']] ?? $p['land_use_type'] ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($p['zone'] ?? '-') ?>
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = match ($p['status']) {
                                        'surveyed' => 'badge-success',
                                        'pending_review' => 'badge-warning',
                                        'temporary_permit' => 'badge-info',
                                        'must_relocate' => 'badge-danger',
                                        'disputed' => 'badge-orange',
                                        default => 'badge-gray',
                                    };
                                    ?>
                                    <span class="badge <?= $statusBadge ?>">
                                        <?= PLOT_STATUS_LABELS[$p['status']] ?? $p['status'] ?>
                                    </span>
                                    <?php if (!empty($p['data_issues'])): ?>
                                        <i class="bi bi-exclamation-triangle-fill" style="color:#ef4444; font-size:14px;" title="พบประเด็นข้อมูล: <?= htmlspecialchars(str_replace("\n", " ", $p['data_issues'])) ?>"></i>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space:nowrap;">
                                    <?= $p['survey_date'] ? date('d/m/Y', strtotime($p['survey_date'])) : '-' ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="index.php?page=plots&action=view&id=<?= $p['plot_id'] ?>"
                                            class="btn btn-secondary btn-sm" title="ดู">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($p['latitude'] && $p['longitude']): ?>
                                            <a href="index.php?page=map&lat=<?= $p['latitude'] ?>&lng=<?= $p['longitude'] ?>&plot=<?= $p['plot_id'] ?>"
                                                class="btn btn-info btn-sm" title="ดูแผนที่">
                                                <i class="bi bi-geo-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($_SESSION['role'] !== ROLE_VIEWER): ?>
                                            <a href="index.php?page=plots&action=edit&id=<?= $p['plot_id'] ?>"
                                                class="btn btn-warning btn-sm" title="แก้ไข">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <div class="pagination">
                        <?php if ($page_num > 1): ?>
                            <a
                                href="index.php?page=plots&p=<?= $page_num - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        <?php for ($pg = max(1, $page_num - 2); $pg <= min($totalPages, $page_num + 2); $pg++): ?>
                            <?php if ($pg == $page_num): ?>
                                <span class="active">
                                    <?= $pg ?>
                                </span>
                            <?php else: ?>
                                <a
                                    href="index.php?page=plots&p=<?= $pg ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">
                                    <?= $pg ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($page_num < $totalPages): ?>
                            <a
                                href="index.php?page=plots&p=<?= $page_num + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>