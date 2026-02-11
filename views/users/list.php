<?php
/**
 * จัดการผู้ใช้งาน — รายชื่อ (Admin เท่านั้น)
 */

require_once __DIR__ . '/../../controllers/AuthController.php';
AuthController::requireRole(ROLE_ADMIN);

$db = getDB();
$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>

<div class="d-flex justify-between align-center mb-3">
    <p class="text-muted">ผู้ใช้งานทั้งหมด
        <?= count($users) ?> คน
    </p>
    <a href="index.php?page=users&action=create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> เพิ่มผู้ใช้งาน
    </a>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>บทบาท</th>
                        <th>โทรศัพท์</th>
                        <th>สถานะ</th>
                        <th>เข้าสู่ระบบล่าสุด</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                        <tr>
                            <td>
                                <?= $i + 1 ?>
                            </td>
                            <td><strong>
                                    <?= htmlspecialchars($u['username']) ?>
                                </strong></td>
                            <td>
                                <?= htmlspecialchars($u['full_name']) ?>
                            </td>
                            <td>
                                <?php $roleBadge = match ($u['role']) { 'admin' => 'badge-danger', 'officer' => 'badge-info', 'viewer' => 'badge-gray', default => 'badge-gray'}; ?>
                                <span class="badge <?= $roleBadge ?>">
                                    <?= ROLE_LABELS[$u['role']] ?? $u['role'] ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($u['phone'] ?? '-') ?>
                            </td>
                            <td>
                                <?php if ($u['is_active']): ?>
                                    <span class="badge badge-success">ใช้งาน</span>
                                <?php else: ?>
                                    <span class="badge badge-gray">ปิดใช้งาน</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : 'ยังไม่เคย' ?>
                            </td>
                            <td>
                                <a href="index.php?page=users&action=edit&id=<?= $u['user_id'] ?>"
                                    class="btn btn-warning btn-sm">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>