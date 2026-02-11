<?php
/**
 * รายละเอียดคำร้อง
 */
require_once __DIR__ . '/../../models/Document.php';

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT c.*, v.prefix, v.first_name, v.last_name, v.id_card_number,
                              lp.plot_code, u1.full_name as assigned_name, u2.full_name as creator_name
                       FROM cases c
                       JOIN villagers v ON c.villager_id = v.villager_id
                       LEFT JOIN land_plots lp ON c.plot_id = lp.plot_id
                       LEFT JOIN users u1 ON c.assigned_to = u1.user_id
                       LEFT JOIN users u2 ON c.created_by = u2.user_id
                       WHERE c.case_id = :id");
$stmt->execute(['id' => $id]);
$case = $stmt->fetch();

if (!$case) {
    echo '<div class="alert alert-danger">ไม่พบคำร้อง</div>';
    return;
}

$documents = Document::getByRelated('case', $id);
$c = $case;
?>

<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success" data-dismiss><i class="bi bi-check-circle-fill"></i>
        <?= $_SESSION['flash_success'] ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="d-flex justify-between align-center mb-3">
    <a href="index.php?page=cases" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> กลับ</a>
    <?php if ($_SESSION['role'] !== ROLE_VIEWER): ?>
        <a href="index.php?page=cases&action=edit&id=<?= $id ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i>
            แก้ไข</a>
    <?php endif; ?>
</div>

<!-- Case Header -->
<div class="card mb-3"
    style="border-left: 5px solid <?= match ($c['priority']) { 'high' => '#dc3545', 'medium' => '#ffc107', default => '#17a2b8'} ?>;">
    <div class="card-body" style="padding:20px 24px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
            <div>
                <p style="font-size:13px; color:var(--gray-500); margin-bottom:4px;">
                    <?= htmlspecialchars($c['case_number']) ?>
                </p>
                <h2 style="font-size:20px; margin-bottom:8px;">
                    <?= htmlspecialchars($c['subject']) ?>
                </h2>
                <div class="d-flex gap-1" style="flex-wrap:wrap;">
                    <span class="badge badge-gray">
                        <?= CASE_TYPE_LABELS[$c['case_type']] ?? $c['case_type'] ?>
                    </span>
                    <?php $priBadge = match ($c['priority']) { 'high' => 'badge-danger', 'medium' => 'badge-warning', default => 'badge-info'}; ?>
                    <span class="badge <?= $priBadge ?>">ความเร่งด่วน:
                        <?= $c['priority'] === 'high' ? 'สูง' : ($c['priority'] === 'medium' ? 'กลาง' : 'ต่ำ') ?>
                    </span>
                    <?php $csBadge = match ($c['status']) { 'new' => 'badge-info', 'in_progress' => 'badge-warning', 'awaiting_approval' => 'badge-orange', 'closed' => 'badge-success', 'rejected' => 'badge-danger', default => 'badge-gray'}; ?>
                    <span class="badge <?= $csBadge ?>">
                        <?= CASE_STATUS_LABELS[$c['status']] ?? $c['status'] ?>
                    </span>
                </div>
            </div>
            <div style="text-align:right; font-size:13px; color:var(--gray-500);">
                <p>สร้างเมื่อ:
                    <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
                </p>
                <p>โดย:
                    <?= htmlspecialchars($c['creator_name'] ?? '-') ?>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="case-detail-grid">
    <!-- Left: Details -->
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-file-text" style="color:var(--info); margin-right:8px;"></i>รายละเอียด</h3>
        </div>
        <div class="card-body">
            <table style="width:100%;">
                <tr>
                    <td style="padding:8px 0; color:var(--gray-500); width:130px;">ผู้ร้อง</td>
                    <td>
                        <a href="index.php?page=villagers&action=view&id=<?= $c['villager_id'] ?>"
                            style="color:var(--primary-600); font-weight:500;">
                            <?= htmlspecialchars(($c['prefix'] ?? '') . $c['first_name'] . ' ' . $c['last_name']) ?>
                        </a>
                        <br><small style="color:var(--gray-400); font-family:monospace;">
                            <?= $c['id_card_number'] ?>
                        </small>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0; color:var(--gray-500);">แปลงที่เกี่ยวข้อง</td>
                    <td>
                        <?php if ($c['plot_code']): ?>
                            <a href="index.php?page=plots&action=view&id=<?= $c['plot_id'] ?>"
                                style="color:var(--primary-600);">
                                <?= $c['plot_code'] ?>
                            </a>
                        <?php else: ?>-
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0; color:var(--gray-500);">ผู้รับผิดชอบ</td>
                    <td>
                        <?= htmlspecialchars($c['assigned_name'] ?? 'ยังไม่ได้มอบหมาย') ?>
                    </td>
                </tr>
            </table>

            <?php if ($c['description']): ?>
                <div style="margin-top:16px; padding:14px; background:var(--gray-50); border-radius:8px;">
                    <strong style="font-size:13px; color:var(--gray-500);">รายละเอียด:</strong>
                    <p style="margin-top:6px; white-space:pre-wrap;">
                        <?= htmlspecialchars($c['description']) ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($c['resolution']): ?>
                <div
                    style="margin-top:16px; padding:14px; background:#f0fdf4; border-radius:8px; border-left:4px solid #22c55e;">
                    <strong style="font-size:13px; color:var(--success);">ผลการดำเนินการ:</strong>
                    <p style="margin-top:6px; white-space:pre-wrap;">
                        <?= htmlspecialchars($c['resolution']) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Documents -->
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-folder-fill" style="color:var(--warning); margin-right:8px;"></i>เอกสาร
                <span class="badge badge-warning" style="margin-left:8px;">
                    <?= count($documents) ?>
                </span>
            </h3>
            <?php if ($_SESSION['role'] !== ROLE_VIEWER): ?>
                <button class="btn btn-primary btn-sm"
                    onclick="document.getElementById('uploadModal').classList.add('show')">
                    <i class="bi bi-upload"></i> อัปโหลด
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($documents)): ?>
                <div class="empty-state" style="padding:30px;"><i class="bi bi-folder2-open"></i>
                    <p>ยังไม่มีเอกสาร</p>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                    <div
                        style="display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--gray-100);">
                        <i class="bi bi-file-earmark-<?= in_array($doc['file_type'], ['jpg', 'jpeg', 'png']) ? 'image' : ($doc['file_type'] === 'pdf' ? 'pdf' : 'text') ?>"
                            style="font-size:24px; color:var(--gray-400);"></i>
                        <div style="flex:1;">
                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" style="font-weight:500;">
                                <?= htmlspecialchars($doc['file_name']) ?>
                            </a>
                            <p style="font-size:11px; color:var(--gray-400);">
                                <?= date('d/m/Y H:i', strtotime($doc['uploaded_at'])) ?>
                            </p>
                        </div>
                        <?php if ($_SESSION['role'] !== ROLE_VIEWER): ?>
                            <form method="POST" action="index.php?page=documents&action=delete&id=<?= $doc['doc_id'] ?>"
                                onsubmit="return confirmDelete('ลบเอกสาร?')">
                                <button class="btn btn-danger btn-sm" style="padding:4px 8px;"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal">
        <div class="modal-header">
            <h3>อัปโหลดเอกสาร</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('show')"><i
                    class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" action="index.php?page=documents&action=upload" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="related_type" value="case">
                <input type="hidden" name="related_id" value="<?= $id ?>">
                <div class="form-group">
                    <label>เลือกไฟล์</label>
                    <input type="file" name="file" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>หมวดหมู่</label>
                    <select name="doc_category" class="form-control">
                        <option value="complaint_doc">เอกสารร้องเรียน</option>
                        <option value="evidence">หลักฐาน</option>
                        <option value="photo">ภาพถ่าย</option>
                        <option value="other">อื่นๆ</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>คำอธิบาย</label>
                    <input type="text" name="description" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    onclick="this.closest('.modal-overlay').classList.remove('show')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> อัปโหลด</button>
            </div>
        </form>
    </div>
</div>