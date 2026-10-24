<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ProjectRepository.php';
require_once __DIR__ . '/../src/RunRepository.php';
require_once __DIR__ . '/../src/HttpClient.php';

$pageTitle = 'Tổng quan hệ thống';
$activeTab = 'home';

$dbConnected = false;
$dbError = '';
$projects = [];
$recentRuns = [];
$sampleAppHealthy = false;

try {
    $db = db();
    $dbConnected = true;
    $projectRepo = new ProjectRepository($db);
    $projects = $projectRepo->listProjects();

    $runRepo = new RunRepository($db);
    if (!empty($projects)) {
        $recentRuns = $runRepo->listForProject((int)$projects[0]['id']);
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

// Kiểm tra nhanh sample app
$sampleRes = HttpClient::request('GET', 'http://127.0.0.1:8205/api/announcements', null, null, 1);
$sampleAppHealthy = ($sampleRes['error_code'] === null);

// Dữ liệu biểu đồ từ lần chạy gần nhất nếu có
$latestPass = 0;
$latestFail = 0;
$latestError = 0;
if (!empty($recentRuns)) {
    $latestPass = (int)$recentRuns[0]['pass_count'];
    $latestFail = (int)$recentRuns[0]['fail_count'];
    $latestError = (int)$recentRuns[0]['error_count'];
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="page-header">
    <div class="page-header-info">
        <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.4rem;">
            <span class="badge badge-brand">BÀI TẬP LỚN • ĐỀ TÀI 4</span>
            <span class="badge badge-subtle">ACCESSTESTER v2.0</span>
        </div>
        <h1 class="page-title">Bảng điều khiển kiểm thử phân quyền Web</h1>
        <p class="page-desc">Hệ thống gửi yêu cầu HTTP thực tế đến ứng dụng mẫu, đối chiếu quy tắc RBAC/ABAC và phát hiện lỗ hổng IDOR.</p>
    </div>
    <div class="page-header-actions">
        <a href="/compare.php" class="btn btn-outline">Đối chiếu trước/sau</a>
        <a href="/runs.php" class="btn btn-primary">Bắt đầu kiểm thử HTTP</a>
    </div>
</div>

<!-- Thẻ chỉ số tổng quan 4 cột -->
<div class="grid grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Cơ sở dữ liệu</span>
            <span class="stat-indicator <?= $dbConnected ? 'ready' : 'danger' ?>"></span>
        </div>
        <div class="stat-value">
            <?= $dbConnected ? 'SẴN SÀNG' : 'LỖI' ?>
        </div>
        <div class="stat-footer">
            <span class="font-mono">PostgreSQL (accesstest_lab)</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Ứng dụng mẫu (Cổng 8205)</span>
            <span class="stat-indicator <?= $sampleAppHealthy ? 'ready' : 'warning' ?>"></span>
        </div>
        <div class="stat-value">
            <?= $sampleAppHealthy ? 'HOẠT ĐỘNG' : 'CHƯA MỞ' ?>
        </div>
        <div class="stat-footer">
            <span class="font-mono">http://127.0.0.1:8205</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Ca kiểm thử chuẩn</span>
            <span class="badge badge-subtle">RBAC/ABAC</span>
        </div>
        <div class="stat-value">24</div>
        <div class="stat-footer">
            <span>6 tài nguyên x 4 ca chuẩn</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Lần chạy đã lưu</span>
            <span class="badge badge-subtle">SNAPSHOT</span>
        </div>
        <div class="stat-value"><?= count($recentRuns) ?></div>
        <div class="stat-footer">
            <span>Toàn bộ kết quả gắn hash SHA-256</span>
        </div>
    </div>
</div>

<?php if (!$dbConnected): ?>
<div class="sec-banner banner-danger mb-6">
    <div class="banner-title">[LỖI HỆ THỐNG] Chưa kết nối được CSDL PostgreSQL</div>
    <div class="banner-body">
        Chi tiết: <code><?= e($dbError) ?></code>.<br>
        Vui lòng chạy: <code>bash lab.sh setup</code> hoặc thiết lập thông tin với <code>bash lab.sh db-config</code>.
    </div>
</div>
<?php endif; ?>

<?php if (!$sampleAppHealthy): ?>
<div class="sec-banner banner-warning mb-6">
    <div class="banner-title">[CẢNH BÁO MÔI TRƯỜNG] Ứng dụng mẫu tại cổng 8205 chưa khởi động</div>
    <div class="banner-body">
        Công cụ kiểm thử cần ứng dụng mẫu đang chạy để gửi các gói tin HTTP thực tế.<br>
        Mở một cửa sổ dòng lệnh và chạy: <code>bash lab.sh sample</code> (hoặc <code>BUG_IDOR=1 bash lab.sh sample</code> để tái hiện lỗi).
    </div>
</div>
<?php endif; ?>

<!-- Biểu đồ phân tích bảo mật & Thao tác nhanh -->
<div class="grid grid-cols-3 gap-6 mb-6">
    <!-- Cột trái (2 phần): Biểu đồ phân tích bảo mật Chart.js -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Phân tích kết quả kiểm thử đợt gần nhất</h2>
                <div class="card-subtitle">Thống kê tỷ lệ PASS / FAIL / ERROR từ lần chạy mới nhất</div>
            </div>
            <?php if (!empty($recentRuns)): ?>
                <span class="badge badge-subtle">Lần chạy #<?= (int)$recentRuns[0]['id'] ?> (<?= e($recentRuns[0]['label']) ?>)</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($recentRuns)): ?>
            <div style="display: flex; align-items: center; justify-content: space-around; gap: 2rem; padding: 1rem 0; flex-wrap: wrap;">
                <div style="width: 240px; height: 240px; position: relative;">
                    <canvas id="securityPostureChart"></canvas>
                </div>
                <div style="flex: 1; min-width: 260px;">
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div style="background: var(--color-pass-bg); border-left: 3px solid var(--color-pass-accent); padding: 0.75rem 1rem; border-radius: 4px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong style="color: var(--color-pass-text);">PASS (Tuân thủ quyền)</strong>
                                <span class="badge badge-pass" style="font-size: 0.85rem;"><?= $latestPass ?> ca</span>
                            </div>
                            <small class="text-muted">Quyền truy cập thực tế khớp hoàn toàn với chính sách.</small>
                        </div>

                        <div style="background: var(--color-fail-bg); border-left: 3px solid var(--color-fail-accent); padding: 0.75rem 1rem; border-radius: 4px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong style="color: var(--color-fail-text);">FAIL (Sai lệch / Lỗ hổng)</strong>
                                <span class="badge badge-fail" style="font-size: 0.85rem;"><?= $latestFail ?> ca</span>
                            </div>
                            <small class="text-muted">Phát hiện truy cập trái quyền hoặc rò rỉ dữ liệu IDOR.</small>
                        </div>

                        <div style="background: var(--color-warn-bg); border-left: 3px solid var(--color-warn-accent); padding: 0.75rem 1rem; border-radius: 4px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong style="color: var(--color-warn-text);">ERROR (Lỗi kết nối / Máy chủ)</strong>
                                <span class="badge badge-error" style="font-size: 0.85rem;"><?= $latestError ?> ca</span>
                            </div>
                            <small class="text-muted">Cổng đóng, lỗi 500 hoặc máy chủ không phản hồi.</small>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <p class="text-muted text-center" style="padding: 3rem 0;">Chưa có lần chạy nào. Bấm nút "Bắt đầu kiểm thử HTTP" để thực thi lần chạy đầu tiên.</p>
        <?php endif; ?>
    </div>

    <!-- Cột phải (1 phần): Quy chuẩn an ninh & Allowlist -->
    <div class="card" style="grid-column: span 1;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Cơ chế bảo vệ tích hợp</h2>
                <div class="card-subtitle">Tuân thủ tiêu chuẩn an toàn kiểm thử</div>
            </div>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.85rem; font-size: 0.875rem;">
            <div style="padding: 0.75rem; background: var(--color-bg-subtle); border-radius: 6px;">
                <div style="font-weight: 700; color: var(--color-text-main); margin-bottom: 0.2rem;">Target Allowlist</div>
                <div class="text-muted" style="font-size: 0.8rem;">Chỉ cho phép gửi yêu cầu tới 127.0.0.1 và localhost. Mọi IP ngoài danh sách bị chặn tuyệt đối.</div>
            </div>
            <div style="padding: 0.75rem; background: var(--color-bg-subtle); border-radius: 6px;">
                <div style="font-weight: 700; color: var(--color-text-main); margin-bottom: 0.2rem;">Policy Oracle Engine</div>
                <div class="text-muted" style="font-size: 0.8rem;">Quy tắc DENY mặc định. Tự động kiểm tra quyền sở hữu bản ghi (owner-match) cho mọi yêu cầu.</div>
            </div>
            <div style="padding: 0.75rem; background: var(--color-bg-subtle); border-radius: 6px;">
                <div style="font-weight: 700; color: var(--color-text-main); margin-bottom: 0.2rem;">Khử định danh (Redactor)</div>
                <div class="text-muted" style="font-size: 0.8rem;">Tự động che giấu Bearer Token, mật khẩu CSDL trong mọi báo cáo và nhật ký audit.</div>
            </div>
        </div>
    </div>
</div>

<!-- Lưới 2 phần: Dự án được bảo vệ & Lịch sử chạy -->
<div class="grid grid-cols-2 gap-6">
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Dự án được bảo vệ</h2>
                <div class="card-subtitle">Danh sách dự án và đích kiểm thử đã khai báo</div>
            </div>
            <a href="/projects.php" class="btn btn-outline btn-sm">Quản lý dự án</a>
        </div>
        <?php if (empty($projects)): ?>
            <p class="text-muted">Chưa có dự án nào trong CSDL.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã dự án</th>
                            <th>Tên dự án</th>
                            <th>Máy chủ đích</th>
                            <th>Ca thử</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $p): ?>
                        <tr>
                            <td><code>#<?= (int)$p['id'] ?></code></td>
                            <td><strong><?= e($p['name']) ?></strong></td>
                            <td><code><?= e($p['base_url']) ?></code></td>
                            <td><span class="badge badge-subtle"><?= (int)$p['case_count'] ?> ca</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Lần chạy gần nhất</h2>
                <div class="card-subtitle">Lịch sử thực thi kiểm thử tự động</div>
            </div>
            <a href="/runs.php" class="btn btn-outline btn-sm">Lịch sử đầy đủ</a>
        </div>
        <?php if (empty($recentRuns)): ?>
            <p class="text-muted">Chưa có lần chạy nào được ghi nhận.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã chạy</th>
                            <th>Nhãn đợt thử</th>
                            <th>Kết quả</th>
                            <th>Thời điểm</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($recentRuns, 0, 5) as $r): ?>
                        <tr>
                            <td><code>#<?= (int)$r['id'] ?></code></td>
                            <td><strong><?= e($r['label']) ?></strong></td>
                            <td>
                                <span class="badge badge-pass"><?= (int)$r['pass_count'] ?> PASS</span>
                                <?php if ((int)$r['fail_count'] > 0): ?>
                                    <span class="badge badge-fail"><?= (int)$r['fail_count'] ?> FAIL</span>
                                <?php endif; ?>
                                <?php if ((int)$r['error_count'] > 0): ?>
                                    <span class="badge badge-error"><?= (int)$r['error_count'] ?> ERR</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= e(substr((string)$r['started_at'], 0, 16)) ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($recentRuns)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('securityPostureChart');
    if (ctx && typeof Chart !== 'undefined') {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['PASS', 'FAIL', 'ERROR'],
                datasets: [{
                    data: [<?= $latestPass ?>, <?= $latestFail ?>, <?= $latestError ?>],
                    backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { family: 'Inter', size: 11, weight: 'bold' },
                            padding: 12
                        }
                    }
                },
                cutout: '72%'
            }
        });
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>