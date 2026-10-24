<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Runner.php';
require_once __DIR__ . '/../src/RunRepository.php';
require_once __DIR__ . '/../src/ProjectRepository.php';
require_once __DIR__ . '/../src/Auth.php';

$pageTitle = 'Thực thi & Lịch sử kiểm thử';
$activeTab = 'runs';

$db = db();
$runRepo = new RunRepository($db);
$projectRepo = new ProjectRepository($db);
$projects = $projectRepo->listProjects();

$selectedProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : (!empty($projects) ? (int)$projects[0]['id'] : 1);
$project = $projectRepo->findById($selectedProjectId);

$currentUser = Auth::getCurrentUsername();
$userRole = $projectRepo->getMemberRole($selectedProjectId, $currentUser);

$message = '';
$messageType = 'success';

$flagFile = __DIR__ . '/../.local/bug_idor.flag';
$currentBugIdor = file_exists($flagFile) && trim((string)file_get_contents($flagFile)) === '1';

// Kiểm tra máy chủ đích có đang lắng nghe không
$targetUrl = $project['base_url'] ?? 'http://127.0.0.1:8205';
$targetHost = parse_url($targetUrl, PHP_URL_HOST) ?: '127.0.0.1';
$targetPort = parse_url($targetUrl, PHP_URL_PORT) ?: 8205;
$sock = @fsockopen($targetHost, (int)$targetPort, $errCode, $errMsg, 0.2);
$isTargetAlive = ($sock !== false);
if ($isTargetAlive) {
    fclose($sock);
}

// Xử lý chạy kiểm thử mới
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf($_POST);
    
    // Cập nhật chế độ BUG_IDOR nếu được chọn
    if (isset($_POST['idor_mode'])) {
        $setMode = ($_POST['idor_mode'] === '1') ? '1' : '0';
        @mkdir(__DIR__ . '/../.local', 0777, true);
        file_put_contents($flagFile, $setMode);
        $currentBugIdor = ($setMode === '1');
    }

    $label = trim($_POST['label'] ?? '');
    if ($label === '') {
        $label = ($currentBugIdor ? 'before-fix' : 'after-fix') . '-' . date('Hi');
    }

    try {
        $runner = new Runner($db);
        $res = $runner->run($selectedProjectId, $label, 'web', $currentUser);
        header('Location: /runs.php?id=' . $res['run_id'] . '&project_id=' . $selectedProjectId . '&msg=run_success');
        exit;
    } catch (Throwable $e) {
        $message = "Lỗi khi chạy kiểm thử: " . $e->getMessage();
        $messageType = 'danger';
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'run_success') {
    $message = "Đã hoàn thành toàn bộ ca kiểm thử HTTP và lưu ảnh chụp Snapshot thành công!";
}

$runs = $runRepo->listForProject($selectedProjectId);

$viewRunId = isset($_GET['id']) ? (int)$_GET['id'] : (!empty($runs) ? (int)$runs[0]['id'] : null);
$currentRun = null;
$runResults = [];

if ($viewRunId !== null) {
    $currentRun = $runRepo->findRun($viewRunId);
    if ($currentRun) {
        $runResults = $runRepo->getResults($viewRunId);
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="page-header">
    <div class="page-header-info">
        <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.4rem;">
            <span class="badge badge-brand">CHỨC NĂNG 4 & 5</span>
            <span class="badge badge-subtle"><?= count($runs) ?> LẦN CHẠY</span>
        </div>
        <h1 class="page-title">Quản lý thực thi & Lịch sử kiểm thử</h1>
        <p class="page-desc">Gửi gói tin HTTP thực tế đến máy chủ ứng dụng mẫu, lưu trữ ảnh chụp trạng thái và tính toán mã băm SHA-256.</p>
    </div>
    <div class="page-header-actions">
        <form method="GET" action="/runs.php" style="margin: 0;">
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

<?php if ($message !== ''): ?>
    <div class="sec-banner <?= $messageType === 'success' ? 'banner-success' : 'banner-danger' ?>">
        <div class="banner-title"><?= $messageType === 'success' ? '[THÔNG BÁO HOÀN THÀNH]' : '[LỖI HỆ THỐNG]' ?></div>
        <div class="banner-body"><?= e($message) ?></div>
    </div>
<?php endif; ?>

<?php if (!$isTargetAlive): ?>
    <div class="sec-banner banner-warning">
        <div class="banner-title">[CẢNH BÁO MÔI TRƯỜNG] Máy chủ ứng dụng mẫu đang tắt tại <?= e($targetUrl) ?></div>
        <div class="banner-body">
            Nếu bấm kiểm thử ngay, tất cả các ca sẽ nhận phán quyết <code>CONNECT_FAILED (ERROR)</code>.<br>
            Vui lòng mở một cửa sổ terminal mới và khởi chạy ứng dụng mẫu:<br>
            • Tái hiện lỗ hổng IDOR: <code>BUG_IDOR=1 bash lab.sh sample</code><br>
            • Phiên bản đã sửa lỗi: <code>bash lab.sh sample</code>
        </div>
    </div>
<?php endif; ?>

<!-- Khung điều khiển thực thi kiểm thử mới -->
<div class="card mb-6" style="border-top: 3px solid var(--color-brand-accent);">
    <div class="card-header">
        <div>
            <h2 class="card-title">Khởi chạy đợt kiểm thử HTTP thực tế</h2>
            <div class="card-subtitle">Máy chủ đích được cấp phép: <code class="font-mono"><?= e($targetUrl) ?></code></div>
        </div>
        <div>
            <?php if ($isTargetAlive): ?>
                <span class="badge badge-pass">KẾT NỐI: <?= $currentBugIdor ? 'BUG_IDOR=1 (Có lỗi)' : 'BUG_IDOR=0 (Đã sửa)' ?></span>
            <?php else: ?>
                <span class="badge badge-error">CỔNG <?= (int)$targetPort ?> CHƯA MỞ</span>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" action="/runs.php?project_id=<?= $selectedProjectId ?>" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

        <div class="form-group" style="flex: 1; min-width: 250px; margin-bottom: 0;">
            <label class="form-label">Kịch bản thử nghiệm</label>
            <select name="idor_mode" class="form-control" onchange="document.getElementById('run_label').value = (this.value === '1' ? 'before-fix' : 'after-fix')">
                <option value="1" <?= $currentBugIdor ? 'selected' : '' ?>>Tình huống 1: Tái hiện lỗi IDOR (Kỳ vọng: 23 PASS, 1 FAIL)</option>
                <option value="0" <?= !$currentBugIdor ? 'selected' : '' ?>>Tình huống 2: Bản đã khắc phục lỗi (Kỳ vọng: 24 PASS, 0 FAIL)</option>
            </select>
        </div>

        <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
            <label class="form-label">Nhãn nhận diện lần chạy</label>
            <input type="text" id="run_label" name="label" class="form-control font-mono" placeholder="ví dụ: before-fix hoặc after-fix" value="<?= $currentBugIdor ? 'before-fix' : 'after-fix' ?>">
        </div>

        <button type="submit" class="btn btn-primary" style="height: 2.6rem;" <?= !$isTargetAlive ? 'onclick="return confirm(\'Máy chủ ứng dụng mẫu đang tắt, kết quả sẽ nhận ERROR. Bạn có muốn tiếp tục chạy thử?\');"' : '' ?>>
            Bắt đầu kiểm thử HTTP thật
        </button>
    </form>
</div>

<!-- Lưới hiển thị: Danh sách lịch sử và Kết quả chi tiết -->
<div class="grid grid-cols-3 gap-6">
    <!-- Cột trái: Lịch sử các lần chạy -->
    <div class="card" style="grid-column: span 1;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Lịch sử thực thi</h2>
                <div class="card-subtitle">Ảnh chụp snapshot theo thứ tự thời gian</div>
            </div>
            <span class="badge badge-subtle"><?= count($runs) ?> lần</span>
        </div>

        <?php if (empty($runs)): ?>
            <p class="text-muted text-center" style="padding: 2rem 0;">Chưa có lần chạy nào cho dự án này.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.65rem; max-height: 650px; overflow-y: auto; padding-right: 0.25rem;">
                <?php foreach ($runs as $r): 
                    $isSelected = ($viewRunId === (int)$r['id']);
                ?>
                    <a href="/runs.php?id=<?= (int)$r['id'] ?>&project_id=<?= $selectedProjectId ?>"
                       style="text-decoration: none; color: inherit; padding: 0.85rem 1rem; border-radius: 8px; border: 1px solid <?= $isSelected ? 'var(--color-brand-accent)' : 'var(--color-border-light)' ?>; background: <?= $isSelected ? 'var(--color-brand-light)' : '#ffffff' ?>; display: block; transition: all var(--transition-fast);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                            <strong style="color: <?= $isSelected ? 'var(--color-brand-primary)' : 'var(--color-text-main)' ?>; font-size: 0.95rem;"><?= e($r['label']) ?></strong>
                            <span class="badge badge-subtle font-mono">#<?= (int)$r['id'] ?></span>
                        </div>
                        <div style="display: flex; gap: 0.4rem; margin-bottom: 0.4rem;">
                            <span class="badge badge-pass"><?= (int)$r['pass_count'] ?> PASS</span>
                            <?php if ((int)$r['fail_count'] > 0): ?>
                                <span class="badge badge-fail"><?= (int)$r['fail_count'] ?> FAIL</span>
                            <?php endif; ?>
                            <?php if ((int)$r['error_count'] > 0): ?>
                                <span class="badge badge-error"><?= (int)$r['error_count'] ?> ERR</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.78rem; color: var(--color-text-muted); display: flex; justify-content: space-between;">
                            <span>Nguồn: <code><?= e(strtoupper((string)$r['source'])) ?></code></span>
                            <span class="font-mono"><?= e(substr((string)$r['started_at'], 5, 11)) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cột phải: Báo cáo chi tiết của lần chạy được chọn -->
    <div class="card" style="grid-column: span 2;">
        <?php if ($currentRun === null): ?>
            <p class="text-muted text-center" style="padding: 4rem 1rem;">Chọn một lần chạy ở danh sách bên trái để kiểm tra chi tiết.</p>
        <?php else: ?>
            <div class="card-header">
                <div>
                    <h2 class="card-title">Lần chạy #<?= (int)$currentRun['id'] ?>: <?= e($currentRun['label']) ?></h2>
                    <div class="card-subtitle">
                        Thời gian: <?= e((string)$currentRun['started_at']) ?> • Nguồn: <code class="font-mono"><?= e(strtoupper((string)$currentRun['source'])) ?></code> • Thực hiện: <strong><?= e((string)($currentRun['started_by'] ?? 'cli')) ?></strong>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="/report.php?id=<?= (int)$currentRun['id'] ?>" class="btn btn-outline btn-sm" target="_blank">Báo cáo HTML</a>
                    <a href="/report.php?id=<?= (int)$currentRun['id'] ?>&format=md" class="btn btn-outline btn-sm">Tải Markdown</a>
                    <a href="/compare.php?project_id=<?= $selectedProjectId ?>&after=<?= (int)$currentRun['id'] ?>" class="btn btn-primary btn-sm">So sánh diff</a>
                </div>
            </div>

            <!-- Khung thông tin SHA-256 Hash an ninh -->
            <div style="background: var(--color-bg-subtle); border: 1px solid var(--color-border-light); padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; font-size: 0.8125rem;">
                <div>
                    <span class="text-muted">MÃ BĂM CẤU HÌNH (SHA-256):</span>
                    <code class="font-mono" style="margin-left: 0.4rem; color: var(--color-brand-primary); font-weight: 600;"><?= e((string)$currentRun['config_hash']) ?></code>
                </div>
                <button type="button" class="btn btn-outline btn-sm" data-copy-text="<?= e((string)$currentRun['config_hash']) ?>" style="font-size: 0.75rem; padding: 0.2rem 0.5rem;">
                    Sao chép Hash
                </button>
            </div>

            <!-- Thanh tìm kiếm nhanh trong bảng kết quả -->
            <div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
                <input type="text" class="form-control" style="max-width: 350px; font-size: 0.82rem;" placeholder="Lọc nhanh kết quả (mã ca, endpoint, trạng thái)..." data-table-search="#runResultsTable">
                <div style="font-size: 0.8125rem;" class="text-muted">
                    Hiển thị <strong><?= count($runResults) ?></strong> ca thử
                </div>
            </div>

            <div class="table-responsive">
                <table class="table" id="runResultsTable">
                    <thead>
                        <tr>
                            <th>Mã ca</th>
                            <th>Tài khoản</th>
                            <th>Yêu cầu HTTP</th>
                            <th>Kỳ vọng</th>
                            <th>Thực tế</th>
                            <th>Kết quả</th>
                            <th>Độ trễ</th>
                            <th style="text-align: right;">Kiểm tra</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($runResults as $idx => $res): 
                            $isFail = ($res['outcome'] === 'FAIL');
                            $isErr = ($res['outcome'] === 'ERROR');
                            $methodClass = strtolower($res['method']);
                            $detailRowId = 'res_detail_' . $idx;
                        ?>
                            <tr style="<?= $isFail ? 'background-color: rgba(254, 242, 242, 0.6);' : ($isErr ? 'background-color: rgba(254, 243, 199, 0.4);' : '') ?>">
                                <td><strong class="font-mono"><?= e($res['code']) ?></strong></td>
                                <td>
                                    <strong><?= e($res['account_label']) ?></strong><br>
                                    <span class="badge badge-subtle"><?= e((string)($res['role_name'] ?? 'anon')) ?></span>
                                </td>
                                <td>
                                    <span class="method-chip method-<?= $methodClass ?>"><?= e($res['method']) ?></span>
                                    <code style="margin-left: 0.25rem; font-size: 0.82rem;"><?= e($res['path']) ?></code>
                                </td>
                                <td>
                                    <span class="badge badge-<?= strtolower($res['expected']) ?>"><?= e($res['expected']) ?></span>
                                </td>
                                <td>
                                    <span class="font-mono">HTTP <?= e((string)($res['http_status'] ?? '-')) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= strtolower($res['outcome']) ?>">
                                        <?= e($res['outcome']) ?>
                                        <?= !empty($res['error_code']) ? '(' . e($res['error_code']) . ')' : '' ?>
                                    </span>
                                </td>
                                <td><span class="font-mono text-muted"><?= (int)$res['duration_ms'] ?> ms</span></td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn btn-outline btn-sm" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;" data-toggle-detail="<?= $detailRowId ?>">
                                        Chi tiết
                                    </button>
                                </td>
                            </tr>
                            <tr id="<?= $detailRowId ?>" style="display: none;" class="no-search">
                                <td colspan="8" style="background: var(--color-bg-subtle); padding: 0.85rem 1.25rem;">
                                    <div class="case-detail-box">
                                        <div style="margin-bottom: 0.35rem;">
                                            <strong>Chủ sở hữu mục tiêu:</strong> <?= e($res['target_owner'] ?? 'Không (Chung)') ?>
                                        </div>
                                        <div style="margin-bottom: 0.35rem;">
                                            <strong>Đánh giá phân quyền:</strong> <?= e((string)($res['detail'] ?? 'Hợp lệ theo chính sách RBAC/ABAC')) ?>
                                        </div>
                                        <?php if (!empty($res['leak_detected'])): ?>
                                            <div style="color: var(--color-fail-text); font-weight: bold; margin-bottom: 0.35rem;">
                                                [PHÁT HIỆN RÒ RỈ BẢO MẬT]: Nội dung phản hồi chứa chuỗi cấm (IDOR Data Leak)!
                                            </div>
                                        <?php endif; ?>
                                        <div class="text-muted" style="font-size: 0.75rem;">
                                            Đợt kiểm thử: Run #<?= (int)$currentRun['id'] ?> • Project #<?= (int)$selectedProjectId ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>