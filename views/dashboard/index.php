<?php
/**
 * Dashboard — ภาพรวมระบบ
 */

try {
    $db = getDB();

    // Stat: จำนวนราษฎร
    $villagerCount = $db->query("SELECT COUNT(*) FROM villagers")->fetchColumn();

    // Stat: จำนวนแปลงที่ดิน
    $plotCount = $db->query("SELECT COUNT(*) FROM land_plots")->fetchColumn();

    // Stat: พื้นที่รวม (ไร่)
    $totalArea = $db->query("SELECT COALESCE(SUM(area_rai), 0) FROM land_plots")->fetchColumn();

    // Stat: คำร้องที่เปิดอยู่
    $openCases = $db->query("SELECT COUNT(*) FROM cases WHERE status NOT IN ('closed','rejected')")->fetchColumn();

    // Plot Status Distribution
    $plotStatusStmt = $db->query("SELECT status, COUNT(*) as cnt FROM land_plots GROUP BY status");
    $plotStatuses = $plotStatusStmt->fetchAll();

    // Land Use Distribution
    $landUseStmt = $db->query("SELECT land_use_type, COUNT(*) as cnt FROM land_plots GROUP BY land_use_type");
    $landUses = $landUseStmt->fetchAll();

    // Recent Activities
    $recentStmt = $db->query("SELECT al.*, u.full_name 
                               FROM activity_logs al 
                               JOIN users u ON al.user_id = u.user_id 
                               ORDER BY al.created_at DESC LIMIT 10");
    $recentActivities = $recentStmt->fetchAll();

} catch (PDOException $e) {
    $villagerCount = $plotCount = $totalArea = $openCases = 0;
    $plotStatuses = $landUses = $recentActivities = [];
}
?>

<!-- Stat Cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="bi bi-people-fill"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">จำนวนราษฎร</div>
            <div class="stat-value">
                <?= number_format($villagerCount) ?>
            </div>
            <div class="stat-change">คน (ในระบบ)</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="bi bi-map-fill"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">แปลงที่ดินทำกิน</div>
            <div class="stat-value">
                <?= number_format($plotCount) ?>
            </div>
            <div class="stat-change">แปลง</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="bi bi-rulers"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">พื้นที่ครอบครองรวม</div>
            <div class="stat-value">
                <?= number_format($totalArea, 2) ?>
            </div>
            <div class="stat-change">ไร่</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon red">
            <i class="bi bi-folder-fill"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">คำร้องที่เปิดอยู่</div>
            <div class="stat-value">
                <?= number_format($openCases) ?>
            </div>
            <div class="stat-change">เรื่อง</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px;">

    <!-- Chart: ประเภทการใช้ที่ดิน -->
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-pie-chart-fill"
                    style="color:var(--primary-600); margin-right:8px;"></i>ประเภทการใช้ที่ดิน</h3>
        </div>
        <div class="card-body" style="height: 300px; display:flex; align-items:center; justify-content:center;">
            <?php if (empty($landUses)): ?>
                <div class="empty-state">
                    <i class="bi bi-bar-chart"></i>
                    <p>ยังไม่มีข้อมูลแปลงที่ดิน</p>
                </div>
            <?php else: ?>
                <canvas id="landUseChart"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- Chart: สถานะแปลง -->
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-bar-chart-fill" style="color:var(--info); margin-right:8px;"></i>สถานะแปลงที่ดิน</h3>
        </div>
        <div class="card-body" style="height: 300px; display:flex; align-items:center; justify-content:center;">
            <?php if (empty($plotStatuses)): ?>
                <div class="empty-state">
                    <i class="bi bi-bar-chart"></i>
                    <p>ยังไม่มีข้อมูลแปลงที่ดิน</p>
                </div>
            <?php else: ?>
                <canvas id="plotStatusChart"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card">
    <div class="card-header">
        <h3><i class="bi bi-clock-history" style="color:var(--gray-500); margin-right:8px;"></i>กิจกรรมล่าสุด</h3>
    </div>
    <div class="card-body">
        <?php if (empty($recentActivities)): ?>
            <div class="empty-state">
                <i class="bi bi-clock"></i>
                <h4>ยังไม่มีกิจกรรม</h4>
                <p>เมื่อมีการเพิ่ม แก้ไข หรือลบข้อมูล จะแสดงที่นี่</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>เวลา</th>
                            <th>ผู้ดำเนินการ</th>
                            <th>การกระทำ</th>
                            <th>รายละเอียด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentActivities as $log): ?>
                            <tr>
                                <td style="white-space:nowrap;">
                                    <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($log['full_name']) ?>
                                </td>
                                <td>
                                    <?php
                                    $actionLabels = [
                                        'create' => ['เพิ่มข้อมูล', 'badge-success'],
                                        'update' => ['แก้ไข', 'badge-warning'],
                                        'delete' => ['ลบ', 'badge-danger'],
                                        'export' => ['ส่งออก', 'badge-info'],
                                        'login' => ['เข้าสู่ระบบ', 'badge-gray'],
                                        'logout' => ['ออกจากระบบ', 'badge-gray'],
                                    ];
                                    $label = $actionLabels[$log['action']] ?? ['อื่นๆ', 'badge-gray'];
                                    ?>
                                    <span class="badge <?= $label[1] ?>">
                                        <?= $label[0] ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($log['description'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($landUses) || !empty($plotStatuses)): ?>
    <script>
    // Land Use Pie Chart
    <?php if (!empty($landUses)): ?>
        const landUseLabels = <?= json_encode(array_map(fn($r) => LAND_USE_LABELS[$r['land_use_type']] ?? $r['land_use_type'], $landUses)) ?>;
            const landUseData = <?= json_encode(array_map(fn($r) => (int) $r['cnt'], $landUses)) ?>;

            new Chart(document.getElementById('landUseChart'), {
                type: 'doughnut',
                data: {
                    labels: landUseLabels,
                    datasets: [{
                        data: landUseData,
                        backgroundColor: ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#6b7280'],
                        borderWidth: 0,
                        hoverOffset: 8,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right', labels: { font: { family: 'Prompt', size: 13 }, padding: 16 } }
                    }
                }
            });
    <?php endif; ?>

    // Plot Status Bar Chart
    <?php if (!empty($plotStatuses)): ?>
        const statusLabels = <?= json_encode(array_map(fn($r) => PLOT_STATUS_LABELS[$r['status']] ?? $r['status'], $plotStatuses)) ?>;
            const statusData = <?= json_encode(array_map(fn($r) => (int) $r['cnt'], $plotStatuses)) ?>;
            const statusColors = <?= json_encode(array_map(fn($r) => PLOT_STATUS_COLORS[$r['status']] ?? '#6b7280', $plotStatuses)) ?>;

            new Chart(document.getElementById('plotStatusChart'), {
                type: 'bar',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        label: 'จำนวนแปลง',
                        data: statusData,
                        backgroundColor: statusColors,
                        borderRadius: 8,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { font: { family: 'Prompt' } } },
                        x: { ticks: { font: { family: 'Prompt', size: 12 } } }
                    }
                }
            });
    <?php endif; ?>
    </script>
<?php endif; ?>