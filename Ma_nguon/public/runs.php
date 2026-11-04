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

// Xử lý các thao tác POST (Chạy kiểm thử mới, xoá lần chạy, hoặc xoá toàn bộ lịch sử)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf($_POST);
    $action = $_POST['action'] ?? 'run_test';

    if ($action === 'delete_run') {
        $delId = (int)($_POST['run_id'] ?? 0);
        if ($delId > 0) {
            $runRepo->deleteRun($delId, $selectedProjectId);
            header('Location: /runs.php?project_id=' . $selectedProjectId . '&msg=delete_success');
            exit;
        }
    } elseif ($action === 'delete_all_runs') {
        $deletedCount = $runRepo->deleteAllForProject($selectedProjectId);
        header('Location: /runs.php?project_id=' . $selectedProjectId . '&msg=delete_all_success&count=' . $deletedCount);
        exit;
    } else {
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
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'run_success') {
        $message = "Đã hoàn thành toàn bộ ca kiểm thử HTTP và lưu ảnh chụp Snapshot thành công!";
        $messageType = 'success';
    } elseif ($_GET['msg'] === 'delete_success') {
        $message = "Đã xoá thành công lần chạy kiểm thử được chọn khỏi cơ sở dữ liệu.";
        $messageType = 'success';
    } elseif ($_GET['msg'] === 'delete_all_success') {
        $cCount = (int)($_GET['count'] ?? 0);
        $message = "Đã dọn dẹp sạch toàn bộ {$cCount} lần chạy lịch sử của dự án này.";
        $messageType = 'success';
    }
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
        <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 8px;">
            <span class="badge badge-subtle">Bệnh Viện Bạch Mai</span>
            <span class="badge badge-subtle"><?= count($runs) ?> đợt kiểm định</span>
        </div>
        <h1 class="page-title">Lịch Sử & Thực Thi Kiểm Định Bệnh Án</h1>
        <p class="page-desc">Gửi 24 yêu cầu HTTP thực tế tới máy chủ MediRecord EMR (Cổng 8205), lưu trữ snapshot bất biến và bảo vệ tính toàn vẹn với mã băm SHA-256.</p>
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
<div class="card mb-6">
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
                <option value="1" <?= $currentBugIdor ? 'selected' : '' ?>>Tình huống 1: Tái hiện lỗi IDOR (23 PASS, 1 FAIL)</option>
                <option value="0" <?= !$currentBugIdor ? 'selected' : '' ?>>Tình huống 2: Đã khắc phục lỗi (24 PASS, 0 FAIL)</option>
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
    <!-- Cột trái: Lịch sử các lần chạy (Có tìm kiếm, phân trang và xoá) -->
    <div class="card" style="grid-column: span 1;" id="runsHistoryContainer" data-list-paginate data-page-size="6">
        <div class="card-header" style="align-items: flex-start;">
            <div>
                <h2 class="card-title">Lịch sử thực thi</h2>
                <div class="card-subtitle">Ảnh chụp snapshot theo thứ tự thời gian</div>
            </div>
            <div style="display: flex; gap: 0.4rem; align-items: center;">
                <span class="badge badge-subtle" id="historyTotalBadge"><?= count($runs) ?> lần</span>
                <?php if (!empty($runs)): ?>
                    <form method="POST" action="/runs.php?project_id=<?= $selectedProjectId ?>" style="margin: 0;" onsubmit="return confirm('Bạn có chắc chắn muốn xoá TOÀN BỘ lịch sử thực thi của dự án này? Thao tác không thể hoàn tác.');">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                        <input type="hidden" name="action" value="delete_all_runs">
                        <button type="submit" class="btn btn-outline btn-sm" style="padding: 2px 8px; font-size: 11px; color: #dc2626; border-color: rgba(239, 68, 68, 0.3);" title="Dọn dẹp sạch toàn bộ lịch sử">
                            Xoá hết
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($runs)): ?>
            <!-- Thanh tìm kiếm nhanh lịch sử -->
            <div style="margin-bottom: 0.75rem;">
                <input type="text" id="runSearchInput" class="form-control" style="font-size: 0.82rem; padding: 0.45rem 0.75rem;" placeholder="Tìm theo nhãn, #ID, ngày, trạng thái..." autocomplete="off">
            </div>
        <?php endif; ?>

        <?php if (empty($runs)): ?>
            <p class="text-muted text-center" style="padding: 2rem 0;">Chưa có lần chạy nào cho dự án này.</p>
        <?php else: ?>
            <div id="runsList" style="display: flex; flex-direction: column; gap: 0.65rem;">
                <?php foreach ($runs as $r): 
                    $isSelected = ($viewRunId === (int)$r['id']);
                ?>
                    <div class="run-card-item" data-search-text="<?= e(strtolower($r['label'] . ' #' . $r['id'] . ' ' . $r['source'] . ' ' . $r['started_at'] . ' ' . ($r['fail_count'] > 0 ? 'fail' : 'pass'))) ?>"
                         style="position: relative; border-radius: 8px; border: 1px solid <?= $isSelected ? '#10b981' : 'rgba(0,0,0,0.08)' ?>; background: <?= $isSelected ? '#ecfdf5' : '#ffffff' ?>; transition: all 150ms ease;">
                        <a href="/runs.php?id=<?= (int)$r['id'] ?>&project_id=<?= $selectedProjectId ?>"
                           style="text-decoration: none; color: inherit; padding: 0.75rem 0.85rem; display: block;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; padding-right: 1.75rem;">
                                <strong style="color: <?= $isSelected ? '#065f46' : '#09090b' ?>; font-size: 0.92rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 170px;" title="<?= e($r['label']) ?>"><?= e($r['label']) ?></strong>
                                <span class="badge badge-subtle font-mono">#<?= (int)$r['id'] ?></span>
                            </div>
                            <div style="display: flex; gap: 0.35rem; margin-bottom: 0.35rem; flex-wrap: wrap;">
                                <span class="badge badge-pass" style="font-size: 11px; padding: 2px 6px;"><?= (int)$r['pass_count'] ?> PASS</span>
                                <?php if ((int)$r['fail_count'] > 0): ?>
                                    <span class="badge badge-fail" style="font-size: 11px; padding: 2px 6px;"><?= (int)$r['fail_count'] ?> FAIL</span>
                                <?php endif; ?>
                                <?php if ((int)$r['error_count'] > 0): ?>
                                    <span class="badge badge-error" style="font-size: 11px; padding: 2px 6px;"><?= (int)$r['error_count'] ?> ERR</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.75rem; color: #71717a; display: flex; justify-content: space-between;">
                                <span>Nguồn: <code style="font-size: 0.72rem;"><?= e(strtoupper((string)$r['source'])) ?></code></span>
                                <span class="font-mono"><?= e(substr((string)$r['started_at'], 5, 11)) ?></span>
                            </div>
                        </a>

                        <!-- Nút Xoá lần chạy riêng lẻ -->
                        <form method="POST" action="/runs.php?project_id=<?= $selectedProjectId ?>" style="position: absolute; top: 0.55rem; right: 0.55rem; margin: 0;" onsubmit="return confirm('Bạn có chắc muốn xoá lần chạy #<?= (int)$r['id'] ?> (<?= e($r['label']) ?>)?');">
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                            <input type="hidden" name="action" value="delete_run">
                            <input type="hidden" name="run_id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" title="Xoá lần chạy này" style="background: none; border: none; cursor: pointer; color: #a1a1aa; padding: 2px 6px; font-size: 14px; line-height: 1; border-radius: 4px; transition: all 150ms;" onmouseover="this.style.color='#ef4444';this.style.backgroundColor='#fee2e2';" onmouseout="this.style.color='#a1a1aa';this.style.backgroundColor='transparent';">
                                &times;
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Điều khiển phân trang cho danh sách lịch sử -->
            <div id="historyPaginationControls" style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid rgba(0,0,0,0.06);">
                <span id="historyPaginationInfo" style="font-size: 0.75rem; color: #71717a;"></span>
                <div id="historyPaginationBtns" style="display: flex; gap: 4px;"></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cột phải: Báo cáo chi tiết của lần chạy được chọn (Phân trang 8 ca / trang) -->
    <div class="card" style="grid-column: span 2;" data-paginate data-page-size="8">
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
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                    <a href="/report.php?id=<?= (int)$currentRun['id'] ?>" class="btn btn-outline btn-sm" target="_blank">Báo cáo HTML</a>
                    <a href="/report.php?id=<?= (int)$currentRun['id'] ?>&format=md" class="btn btn-outline btn-sm">Tải Markdown</a>
                    <a href="/compare.php?project_id=<?= $selectedProjectId ?>&after=<?= (int)$currentRun['id'] ?>" class="btn btn-primary btn-sm">So sánh diff</a>
                    <form method="POST" action="/runs.php?project_id=<?= $selectedProjectId ?>" style="display: inline; margin: 0;" onsubmit="return confirm('Xác nhận xoá vĩnh viễn lần chạy #<?= (int)$currentRun['id'] ?> (<?= e($currentRun['label']) ?>)?');">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                        <input type="hidden" name="action" value="delete_run">
                        <input type="hidden" name="run_id" value="<?= (int)$currentRun['id'] ?>">
                        <button type="submit" class="btn btn-outline btn-sm" style="color: #dc2626; border-color: rgba(239, 68, 68, 0.3);">
                            Xoá lần này
                        </button>
                    </form>
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
                <input type="text" class="form-control" style="max-width: 350px; font-size: 0.82rem;" placeholder="Lọc nhanh kết quả (mã ca, endpoint, trạng thái)..." data-table-search>
                <div style="font-size: 0.8125rem;" class="text-muted">
                    Tổng số: <strong><?= count($runResults) ?></strong> ca thử
                </div>
            </div>

            <div class="table-responsive">
                <table class="table" id="runResultsTable">
                    <thead>
                        <tr>
                            <th style="width: 75px;">Mã ca</th>
                            <th style="width: 140px;">Tài khoản</th>
                            <th>Yêu cầu HTTP</th>
                            <th style="width: 80px; text-align: center;">Kỳ vọng</th>
                            <th style="width: 90px; text-align: center;">Thực tế</th>
                            <th style="width: 85px; text-align: center;">Kết quả</th>
                            <th style="width: 75px; text-align: right;">Độ trễ</th>
                            <th style="width: 70px; text-align: right;">Kiểm tra</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($runResults as $idx => $res): 
                            $isFail = ($res['outcome'] === 'FAIL');
                            $isErr = ($res['outcome'] === 'ERROR');
                            $methodClass = strtolower($res['method']);
                            $detailRowId = 'res_detail_' . $idx;
                        ?>
                            <tr style="<?= $isFail ? 'background-color: #fef2f2;' : ($isErr ? 'background-color: #fffbeb;' : '') ?>">
                                <td><strong class="font-mono"><?= e($res['code']) ?></strong></td>
                                <td>
                                    <strong><?= e($res['account_label']) ?></strong><br>
                                    <span class="badge badge-subtle"><?= e((string)($res['role_name'] ?? 'anon')) ?></span>
                                </td>
                                <td>
                                    <span class="method-chip method-<?= $methodClass ?>"><?= e($res['method']) ?></span>
                                    <code style="margin-left: 0.25rem; font-size: 0.82rem;"><?= e($res['path']) ?></code>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-<?= strtolower($res['expected']) ?>"><?= e($res['expected']) ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="font-mono" style="font-size: 12px; font-weight: 600;">HTTP <?= e((string)($res['http_status'] ?? '-')) ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-<?= strtolower($res['outcome']) ?>">
                                        <?= e($res['outcome']) ?>
                                        <?= !empty($res['error_code']) ? '(' . e($res['error_code']) . ')' : '' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;"><span class="font-mono text-muted"><?= (int)$res['duration_ms'] ?> ms</span></td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn btn-outline btn-sm" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;" data-toggle-detail="<?= $detailRowId ?>">
                                        Chi tiết
                                    </button>
                                </td>
                            </tr>
                            <tr id="<?= $detailRowId ?>" style="display: none;" class="detail-row no-search">
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

            <!-- Phân trang chuẩn nghiên cứu 8 ca / trang -->
            <div class="pagination-container">
                <div class="pagination-info"></div>
                <div class="pagination-controls"></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
