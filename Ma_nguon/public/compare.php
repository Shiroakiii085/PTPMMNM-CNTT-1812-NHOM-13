<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/RunRepository.php';
require_once __DIR__ . '/../src/ProjectRepository.php';

$pageTitle = 'Đối chiếu trước & sau sửa lỗi';
$activeTab = 'compare';

$db = db();
$runRepo = new RunRepository($db);
$projectRepo = new ProjectRepository($db);
$projects = $projectRepo->listProjects();

$selectedProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : (!empty($projects) ? (int)$projects[0]['id'] : 1);
$runs = $runRepo->listForProject($selectedProjectId);

$runBeforeId = isset($_GET['before']) ? (int)$_GET['before'] : (!empty($runs) && count($runs) >= 2 ? (int)$runs[1]['id'] : (!empty($runs) ? (int)$runs[0]['id'] : 0));
$runAfterId = isset($_GET['after']) ? (int)$_GET['after'] : (!empty($runs) ? (int)$runs[0]['id'] : 0);

$comparison = [];
$runBefore = null;
$runAfter = null;

if ($runBeforeId > 0 && $runAfterId > 0) {
    $runBefore = $runRepo->findRun($runBeforeId);
    $runAfter = $runRepo->findRun($runAfterId);
    $comparison = $runRepo->compareRuns($runBeforeId, $runAfterId, $selectedProjectId);
}

$fixedCases = [];
$regressionCases = [];
$resolvedCases = [];
foreach ($comparison as $row) {
    $bOut = $row['before_outcome'] ?? '';
    $aOut = $row['after_outcome'] ?? '';
    if ($bOut === 'FAIL' && $aOut === 'PASS') {
        $fixedCases[] = $row['code'];
    } elseif ($bOut === 'PASS' && $aOut === 'FAIL') {
        $regressionCases[] = $row['code'];
    } elseif ($bOut === 'ERROR' && $aOut === 'PASS') {
        $resolvedCases[] = $row['code'];
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="page-header">
    <div class="page-header-info">
        <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 8px;">
            <span class="badge badge-subtle">Bệnh Viện Bạch Mai</span>
            <span class="badge badge-subtle">Xác minh vá lỗi IDOR</span>
        </div>
        <h1 class="page-title">Đối Chiếu Trước & Sau Khi Sửa Lỗi IDOR</h1>
        <p class="page-desc">Tự động so sánh hai đợt kiểm thử độc lập. Làm nổi bật ca trọng yếu <strong>R02 (Bệnh án của BN. Bình)</strong> chuyển biến từ <strong>FAIL sang PASS</strong> mà không phát sinh lỗi hồi quy.</p>
    </div>
    <div class="page-header-actions">
        <form method="GET" action="/compare.php" style="margin: 0;">
            <select name="project_id" onchange="this.form.submit()" class="form-control font-mono">
                <?php foreach ($projects as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $selectedProjectId === (int)$p['id'] ? 'selected' : '' ?>>
                        Dự án #<?= (int)$p['id'] ?>: <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<!-- Bộ chọn 2 lần chạy để đối chiếu -->
<div class="card mb-6">
    <form method="GET" action="/compare.php" style="display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="project_id" value="<?= $selectedProjectId ?>">

        <div class="form-group" style="flex: 1; min-width: 250px; margin-bottom: 0;">
            <label class="form-label">Lần chạy trước (Before - Trước khi sửa IDOR)</label>
            <select name="before" class="form-control font-mono" onchange="this.form.submit()">
                <?php foreach ($runs as $r): ?>
                    <option value="<?= (int)$r['id'] ?>" <?= $runBeforeId === (int)$r['id'] ? 'selected' : '' ?>>
                        #<?= (int)$r['id'] ?>: <?= e($r['label']) ?> (<?= (int)$r['pass_count'] ?> PASS, <?= (int)$r['fail_count'] ?> FAIL<?= (int)($r['error_count'] ?? 0) > 0 ? ', ' . (int)$r['error_count'] . ' ERR' : '' ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="flex: 1; min-width: 250px; margin-bottom: 0;">
            <label class="form-label">Lần chạy sau (After - Sau khi sửa IDOR)</label>
            <select name="after" class="form-control font-mono" onchange="this.form.submit()">
                <?php foreach ($runs as $r): ?>
                    <option value="<?= (int)$r['id'] ?>" <?= $runAfterId === (int)$r['id'] ? 'selected' : '' ?>>
                        #<?= (int)$r['id'] ?>: <?= e($r['label']) ?> (<?= (int)$r['pass_count'] ?> PASS, <?= (int)$r['fail_count'] ?> FAIL<?= (int)($r['error_count'] ?? 0) > 0 ? ', ' . (int)$r['error_count'] . ' ERR' : '' ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="height: 2.6rem;">Đối chiếu hai bản ghi</button>
    </form>
</div>

<?php if (!empty($fixedCases)): ?>
<div class="sec-banner banner-success mb-6">
    <div class="banner-title">[XÁC MINH AN NINH] ĐÃ KHẮC PHỤC THÀNH CÔNG LỖ HỔNG PHÂN QUYỀN (IDOR)</div>
    <div class="banner-body">
        Các ca thử nghiệm chuyển biến tích cực từ <strong>FAIL sang PASS</strong>: 
        <?php foreach ($fixedCases as $fc): ?>
            <code style="background: #bbf7d0; color: #166534; padding: 2px 6px; border-radius: 4px; font-weight: bold;"><?= e($fc) ?></code>
        <?php endforeach; ?>
        <br>Lỗ hổng đọc chéo dữ liệu của người dùng khác đã bị chặn đứng hoàn toàn theo chuẩn Phép thử 2.
    </div>
</div>
<?php endif; ?>

<?php if (!empty($resolvedCases) && empty($fixedCases)): ?>
<div class="sec-banner banner-info mb-6">
    <div class="banner-title">[THÔNG TIN KẾT NỐI] ĐÃ PHỤC HỒI KẾT NỐI MÁY CHỦ THÀNH CÔNG (<?= count($resolvedCases) ?> CA ĐẠT PASS)</div>
    <div class="banner-body">
        Lần chạy trước (#<?= (int)$runBeforeId ?>) gặp sự cố mất kết nối mạng (ERROR). Lần chạy sau (#<?= (int)$runAfterId ?>) đã kết nối thành công tới máy chủ mẫu và đạt chuẩn quyền hạn.
    </div>
</div>
<?php endif; ?>

<?php if (!empty($regressionCases)): ?>
<div class="sec-banner banner-danger mb-6">
    <div class="banner-title">[CẢNH BÁO REGRESSION] PHÁT SINH LỖI MỚI SAU KHI SỬA ĐỔI</div>
    <div class="banner-body">
        Các ca thử nghiệm bị thụt lùi từ <strong>PASS sang FAIL</strong>: 
        <?php foreach ($regressionCases as $rc): ?>
            <code style="background: #fecaca; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-weight: bold;"><?= e($rc) ?></code>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($runBefore && $runAfter): ?>
<!-- Thẻ so sánh chỉ số và biểu đồ chuẩn nghiên cứu -->
<div class="grid grid-cols-3 gap-6 mb-6">
    <div class="card" style="grid-column: span 1;">
        <div class="card-header">
            <h2 class="card-title">Trước sửa (#<?= (int)$runBefore['id'] ?>)</h2>
            <span class="badge badge-subtle"><?= e($runBefore['label']) ?></span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.65rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span class="text-muted">Đạt chuẩn (PASS):</span>
                <strong class="text-success font-mono"><?= (int)$runBefore['pass_count'] ?> / <?= (int)($runBefore['total'] ?: 24) ?> ca</strong>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span class="text-muted">Lỗ hổng (FAIL):</span>
                <strong class="text-danger font-mono"><?= (int)$runBefore['fail_count'] ?> ca</strong>
            </div>
            <?php if ((int)($runBefore['error_count'] ?? 0) > 0): ?>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span class="text-muted">Lỗi kết nối (ERROR):</span>
                <strong class="text-warning font-mono"><?= (int)$runBefore['error_count'] ?> ca</strong>
            </div>
            <?php endif; ?>
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--color-border-light); padding-top: 0.5rem;">
                <span class="text-muted">Mã băm SHA-256:</span>
                <code class="font-mono text-muted" style="font-size: 0.72rem;"><?= substr((string)$runBefore['config_hash'], 0, 16) ?>...</code>
            </div>
        </div>
    </div>

    <div class="card" style="grid-column: span 1;">
        <div class="card-header">
            <h2 class="card-title">Sau sửa (#<?= (int)$runAfter['id'] ?>)</h2>
            <span class="badge badge-pass"><?= e($runAfter['label']) ?></span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.65rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span class="text-muted">Đạt chuẩn (PASS):</span>
                <strong class="text-success font-mono"><?= (int)$runAfter['pass_count'] ?> / <?= (int)($runAfter['total'] ?: 24) ?> ca</strong>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span class="text-muted">Lỗ hổng (FAIL):</span>
                <strong class="text-danger font-mono"><?= (int)$runAfter['fail_count'] ?> ca</strong>
            </div>
            <?php if ((int)($runAfter['error_count'] ?? 0) > 0): ?>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span class="text-muted">Lỗi kết nối (ERROR):</span>
                <strong class="text-warning font-mono"><?= (int)$runAfter['error_count'] ?> ca</strong>
            </div>
            <?php endif; ?>
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--color-border-light); padding-top: 0.5rem;">
                <span class="text-muted">Mã băm SHA-256:</span>
                <code class="font-mono text-muted" style="font-size: 0.72rem;"><?= substr((string)$runAfter['config_hash'], 0, 16) ?>...</code>
            </div>
        </div>
    </div>

    <div class="card" style="grid-column: span 1;">
        <div class="card-header">
            <h2 class="card-title">Biểu đồ đối chiếu</h2>
            <span class="badge badge-subtle">KẾT QUẢ ĐỐI SÁT</span>
        </div>
        <div style="height: 130px; position: relative;">
            <canvas id="compareBarChart"></canvas>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Bảng đối chiếu chi tiết từng ca thử (Phân trang 8 ca / trang) -->
<div class="card mb-6" data-paginate data-page-size="8">
    <div class="card-header">
        <div>
            <h2 class="card-title">Bảng so sánh đối chiếu chi tiết</h2>
            <div class="card-subtitle">Hiển thị thay đổi mã trạng thái HTTP và kết quả thực thi</div>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <input type="text" class="form-control" style="width: 220px; font-size: 0.82rem;" placeholder="Lọc nhanh ca kiểm thử..." data-table-search>
            <?php if ($runAfterId > 0 && $runBeforeId > 0): ?>
                <a href="/report.php?id=<?= $runAfterId ?>&compare=<?= $runBeforeId ?>" class="btn btn-outline btn-sm" target="_blank">Báo cáo HTML</a>
                <a href="/report.php?id=<?= $runAfterId ?>&compare=<?= $runBeforeId ?>&format=md" class="btn btn-outline btn-sm">Tải Markdown</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table" id="compareTable">
            <thead>
                <tr>
                    <th style="width: 75px;">Mã ca</th>
                    <th style="width: 130px;">Tài khoản</th>
                    <th>Yêu cầu HTTP</th>
                    <th style="width: 80px; text-align: center;">Kỳ vọng</th>
                    <th style="width: 140px; text-align: center;">Trước sửa (#<?= (int)$runBeforeId ?>)</th>
                    <th style="width: 140px; text-align: center;">Sau sửa (#<?= (int)$runAfterId ?>)</th>
                    <th style="width: 180px; text-align: center;">Đánh giá biến chuyển</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($comparison)): ?>
                    <tr class="no-search"><td colspan="7" class="text-muted text-center" style="padding: 2.5rem;">Vui lòng chọn 2 lần chạy để so sánh đối chiếu.</td></tr>
                <?php else: ?>
                    <?php foreach ($comparison as $row): 
                        $bOut = $row['before_outcome'] ?? 'N/A';
                        $aOut = $row['after_outcome'] ?? 'N/A';
                        $isFixed = ($bOut === 'FAIL' && $aOut === 'PASS');
                        $isReg = ($bOut === 'PASS' && $aOut === 'FAIL');
                        $isResolved = ($bOut === 'ERROR' && $aOut === 'PASS');
                        $isErrToFail = ($bOut === 'ERROR' && $aOut === 'FAIL');
                        $isNewErr = (in_array($bOut, ['PASS', 'FAIL'], true) && $aOut === 'ERROR');
                        $methodClass = strtolower($row['method'] ?? 'get');
                    ?>
                    <tr class="<?= $isFixed || $isResolved ? 'row-fixed' : ($isReg || $isNewErr ? 'row-regression' : '') ?>" style="<?= $isFixed ? 'background: #f0fdf4;' : ($isReg ? 'background: #fef2f2;' : '') ?>">
                        <td><strong class="font-mono"><?= e($row['code']) ?></strong></td>
                        <td><?= e($row['account_label'] ?? '') ?></td>
                        <td>
                            <span class="method-chip method-<?= $methodClass ?>"><?= e($row['method'] ?? 'GET') ?></span>
                            <code style="margin-left: 0.25rem; font-size: 12px;"><?= e($row['path'] ?? '') ?></code>
                        </td>
                        <td style="text-align: center;"><span class="badge badge-<?= strtolower($row['expected'] ?? '') ?>"><?= e($row['expected'] ?? '') ?></span></td>
                        <td style="text-align: center;">
                            <span class="badge badge-<?= strtolower($bOut) ?>"><?= e($bOut) ?></span>
                            <small class="text-muted font-mono" style="margin-left: 4px;">HTTP <?= e((string)($row['before_status'] ?? '-')) ?></small>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge badge-<?= strtolower($aOut) ?>"><?= e($aOut) ?></span>
                            <small class="text-muted font-mono" style="margin-left: 4px;">HTTP <?= e((string)($row['after_status'] ?? '-')) ?></small>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($isFixed): ?>
                                <span class="badge badge-pass" style="font-weight: 700;">[FIXED] ĐÃ KHẮC PHỤC</span>
                            <?php elseif ($isReg): ?>
                                <span class="badge badge-fail" style="font-weight: 700;">[REGRESSION] LỖI MỚI</span>
                            <?php elseif ($isResolved): ?>
                                <span class="badge badge-pass" style="font-weight: 600;">[PHỤC HỒI] KẾT NỐI & PASS</span>
                            <?php elseif ($isErrToFail): ?>
                                <span class="badge badge-fail" style="font-weight: 600;">[LỖ HỔNG] PHÁT HIỆN FAIL</span>
                            <?php elseif ($isNewErr): ?>
                                <span class="badge badge-error" style="font-weight: 600;">[MẤT KẾT NỐI] GẶP LỖI</span>
                            <?php elseif ($bOut === 'PASS' && $aOut === 'PASS'): ?>
                                <span class="text-muted font-mono" style="font-size: 12px;">Duy trì PASS</span>
                            <?php elseif ($bOut === 'FAIL' && $aOut === 'FAIL'): ?>
                                <span class="badge badge-fail">Vẫn còn FAIL</span>
                            <?php else: ?>
                                <span class="text-muted font-mono" style="font-size: 12px;">Không đổi</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Phân trang chuẩn nghiên cứu 8 ca / trang -->
    <div class="pagination-container">
        <div class="pagination-info"></div>
        <div class="pagination-controls"></div>
    </div>
</div>

<?php if ($runBefore && $runAfter): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('compareBarChart');
    if (ctx && typeof Chart !== 'undefined') {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['PASS', 'FAIL', 'ERROR'],
                datasets: [
                    {
                        label: 'Trước (#<?= (int)$runBefore['id'] ?>)',
                        data: [<?= (int)$runBefore['pass_count'] ?>, <?= (int)$runBefore['fail_count'] ?>, <?= (int)$runBefore['error_count'] ?>],
                        backgroundColor: '#94a3b8',
                        borderRadius: 3
                    },
                    {
                        label: 'Sau (#<?= (int)$runAfter['id'] ?>)',
                        data: [<?= (int)$runAfter['pass_count'] ?>, <?= (int)$runAfter['fail_count'] ?>, <?= (int)$runAfter['error_count'] ?>],
                        backgroundColor: '#0052cc',
                        borderRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'bottom', 
                        labels: { 
                            font: { size: 11, family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif' },
                            boxWidth: 12,
                            boxHeight: 12
                        } 
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        max: 24, 
                        ticks: { stepSize: 6, font: { size: 10 } },
                        grid: { color: '#e2e8f0' }
                    },
                    x: { 
                        ticks: { font: { size: 11, family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif' } },
                        grid: { display: false }
                    }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
