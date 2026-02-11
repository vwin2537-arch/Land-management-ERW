<?php
/**
 * ทะเบียนราษฎร — รายชื่อ
 */

$db = getDB();
require_once __DIR__ . '/../../models/Villager.php';
$search = trim($_GET['search'] ?? '');
$page_num = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page_num - 1) * ITEMS_PER_PAGE;

// Build query
$where = '';
$params = [];

if ($search !== '') {
    $where = "WHERE v.id_card_number LIKE :s1 OR v.first_name LIKE :s2 OR v.last_name LIKE :s3 OR v.village_name LIKE :s4";
    $params = ['s1' => "%$search%", 's2' => "%$search%", 's3' => "%$search%", 's4' => "%$search%"];
}

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) FROM villagers v $where");
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalItems / ITEMS_PER_PAGE));

// Fetch data
$sortBy = $_GET['sort'] ?? 'recent';
$villagers = Villager::getAll($search, $sortBy, ITEMS_PER_PAGE, $offset);
?>

<!-- Page Header -->
<div class="d-flex justify-between align-center mb-3">
    <div>
        <p class="text-muted">ทั้งหมด
            <?= number_format($totalItems) ?> คน
        </p>
    </div>
    <div class="d-flex gap-1">
        <?php if ($_SESSION['role'] !== ROLE_VIEWER): ?>
            <a href="index.php?page=villagers&action=create" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> เพิ่มราษฎร
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Search & Sort -->
<div class="card mb-3">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" class="d-flex gap-1 align-center wrap-mobile">
            <input type="hidden" name="page" value="villagers">
            <div class="search-bar" style="flex:1;">
                <i class="bi bi-search"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                    placeholder="ค้นหาด้วย เลขบัตร ปชช. / ชื่อ / นามสกุล / หมู่บ้าน...">
            </div>
            
            <select name="sort" class="form-control" style="width:auto; min-width:150px;" onchange="this.form.submit()">
                <option value="recent" <?= $sortBy == 'recent' ? 'selected' : '' ?>>ล่าสุด</option>
                <option value="name_asc" <?= $sortBy == 'name_asc' ? 'selected' : '' ?>>ชื่อ (ก-ฮ)</option>
                <option value="plots_desc" <?= $sortBy == 'plots_desc' ? 'selected' : '' ?>>จำนวนแปลง (มากสุด)</option>
                <option value="area_desc" <?= $sortBy == 'area_desc' ? 'selected' : '' ?>>เนื้อที่ (มากสุด)</option>
            </select>

            <button type="submit" class="btn btn-primary btn-sm">ค้นหา</button>
            <?php if ($search || $sortBy !== 'recent'): ?>
                <a href="index.php?page=villagers" class="btn btn-secondary btn-sm">ล้าง</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($villagers)): ?>
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <h4>ไม่พบข้อมูลราษฎร</h4>
                <p>
                    <?= $search ? 'ลองค้นหาด้วยคำอื่น' : 'เริ่มต้นโดยเพิ่มข้อมูลราษฎรคนแรก' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>เลขบัตร ปชช.</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>หมู่บ้าน</th>
                            <th>ตำบล/อำเภอ</th>
                            <th>โทรศัพท์</th>
                            <th>แปลงที่ดิน</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($villagers as $i => $v): ?>
                            <tr>
                                <td>
                                    <?= $offset + $i + 1 ?>
                                </td>
                                <td style="font-family:monospace; letter-spacing:1px;">
                                    <?= htmlspecialchars($v['id_card_number']) ?>
                                </td>
                                <td>
                                    <strong>
                                        <?= htmlspecialchars(($v['prefix'] ?? '') . $v['first_name'] . ' ' . $v['last_name']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?= htmlspecialchars($v['village_name'] ?? '-') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars(($v['sub_district'] ?? '') . ' / ' . ($v['district'] ?? '')) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($v['phone'] ?? '-') ?>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        <?= $v['plot_count'] ?> แปลง
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="index.php?page=villagers&action=view&id=<?= $v['villager_id'] ?>"
                                            class="btn btn-secondary btn-sm" title="ดู">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($_SESSION['role'] !== ROLE_VIEWER): ?>
                                            <a href="index.php?page=villagers&action=edit&id=<?= $v['villager_id'] ?>"
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
                            <a href="index.php?page=villagers&p=<?= $page_num - 1 ?>&search=<?= urlencode($search) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($p = max(1, $page_num - 2); $p <= min($totalPages, $page_num + 2); $p++): ?>
                            <?php if ($p == $page_num): ?>
                                <span class="active">
                                    <?= $p ?>
                                </span>
                            <?php else: ?>
                                <a href="index.php?page=villagers&p=<?= $p ?>&search=<?= urlencode($search) ?>">
                                    <?= $p ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page_num < $totalPages): ?>
                            <a href="index.php?page=villagers&p=<?= $page_num + 1 ?>&search=<?= urlencode($search) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>