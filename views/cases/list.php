<?php
/**
 * คำร้อง/เรื่องร้องเรียน — รายการ
 */

$db = getDB();
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$page_num = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page_num - 1) * ITEMS_PER_PAGE;

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(c.case_number LIKE :s1 OR c.subject LIKE :s2 OR v.first_name LIKE :s3 OR v.last_name LIKE :s4)";
    $params['s1'] = "%$search%";
    $params['s2'] = "%$search%";
    $params['s3'] = "%$search%";
    $params['s4'] = "%$search%";
}
if ($statusFilter !== '') {
    $where[] = "c.status = :status";
    $params['status'] = $statusFilter;
}
if ($typeFilter !== '') {
    $where[] = "c.case_type = :type";
    $params['type'] = $typeFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM cases c JOIN villagers v ON c.villager_id = v.villager_id $whereClause");
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalItems / ITEMS_PER_PAGE));

$sql = "SELECT c.*, v.first_name, v.last_name, v.prefix,
               lp.plot_code, u.full_name as assigned_name
        FROM cases c
        JOIN villagers v ON c.villager_id = v.villager_id
        LEFT JOIN land_plots lp ON c.plot_id = lp.plot_id
        LEFT JOIN users u ON c.assigned_to = u.user_id
        $whereClause ORDER BY c.created_at DESC
        LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$cases = $stmt->fetchAll();
?>

<div class="d-flex justify-between align-center mb-3">
    <p class="text-muted">ทั้งหมด
        <?= number_format($totalItems) ?> เรื่อง
    </p>
    <?php if ($_SESSION['role'] !== ROLE_VIEWER): ?>
        <a href="index.php?page=cases&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> สร้างเรื่องใหม่
        </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" class="d-flex gap-1 align-center" style="flex-wrap:wrap;">
            <input type="hidden" name="page" value="cases">
            <div class="search-bar" style="flex:1; min-width:200px; max-width:100%;">
                <i class="bi bi-search"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" data-live-search="true"
                    placeholder="เลขที่เรื่อง / หัวข้อ / ชื่อผู้ร้อง...">
            </div>
            <select name="status" class="form-control" style="width:auto; min-width:150px;">
                <option value="">-- สถานะ --</option>
                <?php foreach (CASE_STATUS_LABELS as $k => $l): ?>
                    <option value="<?= $k ?>" <?= $statusFilter === $k ? 'selected' : '' ?>>
                        <?= $l ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="type" class="form-control" style="width:auto; min-width:150px;">
                <option value="">-- ประเภท --</option>
                <?php foreach (CASE_TYPE_LABELS as $k => $l): ?>
                    <option value="<?= $k ?>" <?= $typeFilter === $k ? 'selected' : '' ?>>
                        <?= $l ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">ค้นหา</button>
            <?php if ($search || $statusFilter || $typeFilter): ?>
                <a href="index.php?page=cases" class="btn btn-secondary btn-sm">ล้าง</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($cases)): ?>
            <div class="empty-state">
                <i class="bi bi-folder"></i>
                <h4>ไม่พบคำร้อง</h4>
                <p>ยังไม่มีเรื่องร้องเรียนในระบบ</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>เลขที่</th>
                            <th>ประเภท</th>
                            <th>หัวข้อ</th>
                            <th>ผู้ร้อง</th>
                            <th>แปลง</th>
                            <th>ความเร่งด่วน</th>
                            <th>สถานะ</th>
                            <th>ผู้รับผิดชอบ</th>
                            <th>วันที่</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cases as $c): ?>
                            <tr>
                                <td><strong>
                                        <?= htmlspecialchars($c['case_number']) ?>
                                    </strong></td>
                                <td><span class="badge badge-gray">
                                        <?= CASE_TYPE_LABELS[$c['case_type']] ?? $c['case_type'] ?>
                                    </span></td>
                                <td>
                                    <?= htmlspecialchars(mb_substr($c['subject'], 0, 40)) ?>
                                    <?= mb_strlen($c['subject']) > 40 ? '...' : '' ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars(($c['prefix'] ?? '') . $c['first_name'] . ' ' . $c['last_name']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($c['plot_code'] ?? '-') ?>
                                </td>
                                <td>
                                    <?php $priBadge = match ($c['priority']) { 'high' => 'badge-danger', 'medium' => 'badge-warning', 'low' => 'badge-info', default => 'badge-gray'}; ?>
                                    <span class="badge <?= $priBadge ?>">
                                        <?= $c['priority'] === 'high' ? 'สูง' : ($c['priority'] === 'medium' ? 'กลาง' : 'ต่ำ') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php $csBadge = match ($c['status']) { 'new' => 'badge-info', 'in_progress' => 'badge-warning', 'awaiting_approval' => 'badge-orange', 'closed' => 'badge-success', 'rejected' => 'badge-danger', default => 'badge-gray'}; ?>
                                    <span class="badge <?= $csBadge ?>">
                                        <?= CASE_STATUS_LABELS[$c['status']] ?? $c['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($c['assigned_name'] ?? '-') ?>
                                </td>
                                <td style="white-space:nowrap;">
                                    <?= date('d/m/Y', strtotime($c['created_at'])) ?>
                                </td>
                                <td>
                                    <a href="index.php?page=cases&action=view&id=<?= $c['case_id'] ?>"
                                        class="btn btn-secondary btn-sm">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>