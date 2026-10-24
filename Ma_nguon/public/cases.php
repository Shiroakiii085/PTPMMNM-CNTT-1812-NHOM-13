<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/CaseRepository.php';
require_once __DIR__ . '/../src/ProjectRepository.php';
require_once __DIR__ . '/../src/Auth.php';

$pageTitle = 'Bộ ca kiểm thử phân quyền';
$activeTab = 'cases';

$db = db();
$caseRepo = new CaseRepository($db);
$projectRepo = new ProjectRepository($db);
$projects = $projectRepo->listProjects();

$selectedProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : (!empty($projects) ? (int)$projects[0]['id'] : 1);
$currentUser = Auth::getCurrentUsername();
$userRole = $projectRepo->getMemberRole($selectedProjectId, $currentUser);
$canEdit = in_array($userRole, ['owner', 'editor'], true);

// Xử lý xuất JSON
if (isset($_GET['export']) && $_GET['export'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="cases-project-' . $selectedProjectId . '.json"');
    echo $caseRepo->exportJson($selectedProjectId);
    exit;
}

$message = '';
$messageType = 'success';

// Xử lý thêm / sửa / xóa ca thử
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf($_POST);
    $action = $_POST['action'] ?? '';

    try {
        if (!$canEdit) {
            throw new RuntimeException("Tài khoản '$currentUser' chỉ có vai trò '$userRole' trong dự án này nên không có quyền sửa đổi bộ ca thử (Phép thử 4).");
        }

        if ($action === 'save_case') {
            $caseRepo->saveCase($selectedProjectId, $currentUser, [
                'code' => $_POST['code'] ?? '',
                'account_id' => $_POST['account_id'] ?? 0,
                'resource_id' => $_POST['resource_id'] ?? 0,
                'action_id' => $_POST['action_id'] ?? 0,
                'method' => $_POST['method'] ?? 'GET',
                'path' => $_POST['path'] ?? '',
                'target_owner' => $_POST['target_owner'] ?? null,
                'expected' => $_POST['expected'] ?? 'DENY',
                'expected_status' => !empty($_POST['expected_status']) ? (int)$_POST['expected_status'] : null,
                'must_contain' => $_POST['must_contain'] ?? null,
                'must_not_contain' => $_POST['must_not_contain'] ?? null,
                'request_body' => $_POST['request_body'] ?? null,
            ]);
            $message = "Đã lưu ca kiểm thử " . e($_POST['code']) . " thành công.";
        }

        if ($action === 'delete_case') {
            $caseId = (int)($_POST['case_id'] ?? 0);
            $caseRepo->deleteCase($selectedProjectId, $currentUser, $caseId);
            $message = "Đã xóa ca kiểm thử thành công.";
        }
    } catch (Throwable $e) {
        $message = "Lỗi: " . $e->getMessage();
        $messageType = 'danger';
    }
}

$cases = $caseRepo->listForProject($selectedProjectId);
$accounts = $projectRepo->getAccounts($selectedProjectId);
$resources = $projectRepo->getResources($selectedProjectId);
$actions = $projectRepo->getActions($selectedProjectId);

// Bộ lọc
$filterAccount = trim($_GET['account'] ?? '');
$filterExpected = trim($_GET['expected'] ?? '');

if ($filterAccount !== '') {
    $cases = array_filter($cases, fn($c) => strcasecmp($c['account_label'], $filterAccount) === 0);
}
if ($filterExpected !== '') {
    $cases = array_filter($cases, fn($c) => strcasecmp($c['expected'], $filterExpected) === 0);
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="page-header">
    <div class="page-header-info">
        <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.4rem;">
            <span class="badge badge-brand">CHỨC NĂNG 2</span>
            <span class="badge badge-subtle"><?= count($cases) ?> CA HIỆN TẠI</span>
        </div>
        <h1 class="page-title">Bộ ca kiểm thử tự động quyền truy cập</h1>
        <p class="page-desc">Khai báo tài khoản, phương thức HTTP, đích truy cập, quyền mong đợi và điều kiện kiểm tra dữ liệu phản hồi.</p>
    </div>
    <div class="page-header-actions">
        <?php if ($canEdit): ?>
            <button type="button" class="btn btn-secondary" onclick="const p = document.getElementById('addCaseDrawer'); p.style.display = p.style.display === 'none' ? 'block' : 'none';">
                + Thêm ca thử mới
            </button>
        <?php endif; ?>
        <a href="/cases.php?project_id=<?= $selectedProjectId ?>&export=json" class="btn btn-outline">Xuất file JSON</a>
        <a href="/runs.php?project_id=<?= $selectedProjectId ?>" class="btn btn-primary">Chạy kiểm thử ngay</a>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="sec-banner <?= $messageType === 'success' ? 'banner-success' : 'banner-danger' ?>">
        <div class="banner-title"><?= $messageType === 'success' ? '[THÔNG BÁO THÀNH CÔNG]' : '[LỖI THỰC THI]' ?></div>
        <div class="banner-body"><?= e($message) ?></div>
    </div>
<?php endif; ?>

<?php if (!$canEdit): ?>
    <div class="sec-banner banner-warning">
        <div class="banner-title">[CHẾ ĐỘ CHỈ XEM - PHÉP THỬ 4] Tài khoản không có quyền chỉnh sửa bộ ca</div>
        <div class="banner-body">
            Bạn đang đăng nhập bằng <strong><?= e($currentUser) ?></strong> với vai trò <strong><?= e((string)$userRole ?: 'người ngoài dự án') ?></strong>. Theo quy tắc an ninh, chỉ thành viên nhóm dự án (Owner/Editor) mới được cấu hình ca thử. Bạn chỉ có quyền xem báo cáo.
        </div>
    </div>
<?php endif; ?>

<!-- Thanh tìm kiếm & Bộ lọc trực tiếp -->
<div class="filter-bar">
    <div class="filter-group" style="flex: 2; min-width: 250px;">
        <label for="tableSearchInput">TÌM NHANH:</label>
        <input type="text" id="tableSearchInput" class="form-control" style="width: 100%;" placeholder="Nhập mã ca (R02, U01), endpoint, role..." data-table-search="#casesTable">
    </div>

    <form method="GET" action="/cases.php" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin: 0;">
        <div class="filter-group">
            <label>DỰ ÁN:</label>
            <select name="project_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($projects as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $selectedProjectId === (int)$p['id'] ? 'selected' : '' ?>>
                        #<?= (int)$p['id'] ?>: <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>TÀI KHOẢN:</label>
            <select name="account" class="form-control" onchange="this.form.submit()">
                <option value="">-- Tất cả --</option>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?= e($acc['label']) ?>" <?= $filterAccount === $acc['label'] ? 'selected' : '' ?>>
                        <?= e($acc['label']) ?> (<?= e($acc['role_name'] ?? 'anon') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>KỲ VỌNG:</label>
            <select name="expected" class="form-control" onchange="this.form.submit()">
                <option value="">-- Tất cả --</option>
                <option value="ALLOW" <?= $filterExpected === 'ALLOW' ? 'selected' : '' ?>>ALLOW (Cho phép)</option>
                <option value="DENY" <?= $filterExpected === 'DENY' ? 'selected' : '' ?>>DENY (Từ chối)</option>
            </select>
        </div>

        <?php if ($filterAccount !== '' || $filterExpected !== ''): ?>
            <a href="/cases.php?project_id=<?= $selectedProjectId ?>" class="btn btn-outline btn-sm">Bỏ lọc</a>
        <?php endif; ?>
    </form>
    <div style="margin-left: auto;">
        <span class="badge badge-subtle" data-search-count></span>
    </div>
</div>

<?php if ($canEdit): ?>
<!-- Form khai báo ca thử mới (Thu gọn / Mở rộng) -->
<div id="addCaseDrawer" class="card mb-6" style="display: none; border-left: 4px solid var(--color-brand-accent); animation: slideDown 0.2s ease;">
    <div class="card-header">
        <div>
            <h2 class="card-title">+ Khai báo ca kiểm thử mới (Chức năng 2)</h2>
            <div class="card-subtitle">Định nghĩa yêu cầu HTTP và điều kiện kiểm chứng quyền tự động</div>
        </div>
        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('addCaseDrawer').style.display='none';">Đóng</button>
    </div>
    <form method="POST" action="/cases.php?project_id=<?= $selectedProjectId ?>">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="save_case">

        <div class="grid grid-cols-4 gap-4 mb-4">
            <div class="form-group">
                <label class="form-label">Mã ca thử</label>
                <input type="text" name="code" class="form-control" placeholder="ví dụ: X01" required>
            </div>
            <div class="form-group">
                <label class="form-label">Tài khoản thực hiện</label>
                <select name="account_id" class="form-control" required>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>"><?= e($acc['label']) ?> (<?= e($acc['role_name'] ?? 'anon') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Tài nguyên</label>
                <select name="resource_id" class="form-control" required>
                    <?php foreach ($resources as $res): ?>
                        <option value="<?= (int)$res['id'] ?>"><?= e($res['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Hành động</label>
                <select name="action_id" class="form-control" required>
                    <?php foreach ($actions as $act): ?>
                        <option value="<?= (int)$act['id'] ?>"><?= e($act['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-4 gap-4 mb-4">
            <div class="form-group">
                <label class="form-label">Phương thức HTTP</label>
                <select name="method" class="form-control" required>
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="DELETE">DELETE</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Đường dẫn Endpoint</label>
                <input type="text" name="path" class="form-control font-mono" placeholder="/api/registrations/1" required>
            </div>
            <div class="form-group">
                <label class="form-label">Chủ sở hữu đích (target_owner)</label>
                <input type="text" name="target_owner" class="form-control font-mono" placeholder="alice hoặc bob (nếu có)">
            </div>
            <div class="form-group">
                <label class="form-label">Quyền mong đợi</label>
                <select name="expected" class="form-control" required>
                    <option value="ALLOW">ALLOW (Cho phép)</option>
                    <option value="DENY">DENY (Từ chối)</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-4">
            <div class="form-group">
                <label class="form-label">Mã HTTP mong đợi</label>
                <input type="number" name="expected_status" class="form-control" placeholder="200, 401, 403...">
                <span class="form-hint">Để trống nếu muốn tự động suy diễn theo ALLOW/DENY</span>
            </div>
            <div class="form-group">
                <label class="form-label">Cấm xuất hiện (Data Leak Check)</label>
                <input type="text" name="must_not_contain" class="form-control" placeholder="Chuỗi bí mật cấm rò rỉ">
            </div>
            <div class="form-group">
                <label class="form-label">Bắt buộc phải chứa</label>
                <input type="text" name="must_contain" class="form-control" placeholder="Chuỗi xác nhận nội dung">
            </div>
        </div>

        <div class="form-group mb-4">
            <label class="form-label">Request Body (JSON nếu có)</label>
            <textarea name="request_body" class="form-control font-mono" rows="2" placeholder='{"title": "Bản ghi thử nghiệm"}'></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Lưu ca kiểm thử vào CSDL</button>
    </form>
</div>
<?php endif; ?>

<!-- Bảng danh sách ca thử -->
<div class="card mb-6">
    <div class="table-responsive">
        <table class="table" id="casesTable">
            <thead>
                <tr>
                    <th>Mã ca</th>
                    <th>Tài khoản</th>
                    <th>Yêu cầu HTTP</th>
                    <th>Chủ sở hữu</th>
                    <th>Kỳ vọng</th>
                    <th>HTTP Code</th>
                    <th>Kiểm tra nội dung (Data Leak)</th>
                    <th>Đặc điểm kiểm thử</th>
                    <?php if ($canEdit): ?><th style="text-align: right;">Thao tác</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cases)): ?>
                    <tr class="no-search"><td colspan="<?= $canEdit ? 9 : 8 ?>" class="text-muted text-center" style="padding: 2.5rem;">Không tìm thấy ca kiểm thử nào phù hợp.</td></tr>
                <?php else: ?>
                    <?php foreach ($cases as $c): 
                        $isBugTrigger = ($c['code'] === 'R02');
                        $isUndeclared = in_array($c['code'], ['U03', 'U04', 'M02', 'M03'], true);
                        $methodClass = strtolower($c['method']);
                    ?>
                    <tr style="<?= $isBugTrigger ? 'background-color: rgba(254, 243, 199, 0.4);' : '' ?>">
                        <td><strong class="font-mono"><?= e($c['code']) ?></strong></td>
                        <td>
                            <strong><?= e($c['account_label']) ?></strong><br>
                            <span class="badge badge-subtle"><?= e($c['role_name'] ?? 'anon') ?></span>
                        </td>
                        <td>
                            <span class="method-chip method-<?= $methodClass ?>"><?= e($c['method']) ?></span>
                            <code style="margin-left: 0.35rem;"><?= e($c['path']) ?></code>
                        </td>
                        <td>
                            <?php if (!empty($c['target_owner'])): ?>
                                <span class="badge badge-subtle font-mono"><?= e($c['target_owner']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= strtolower($c['expected']) ?>">
                                <?= e($c['expected']) ?>
                            </span>
                        </td>
                        <td>
                            <?= !empty($c['expected_status']) ? '<span class="font-mono">HTTP ' . (int)$c['expected_status'] . '</span>' : '<span class="text-muted">Tự động</span>' ?>
                        </td>
                        <td>
                            <?php if (!empty($c['must_not_contain'])): ?>
                                <div><small class="text-danger"><strong>[CẤM]:</strong> <code><?= e($c['must_not_contain']) ?></code></small></div>
                            <?php endif; ?>
                            <?php if (!empty($c['must_contain'])): ?>
                                <div><small class="text-success"><strong>[CẦN]:</strong> <code><?= e($c['must_contain']) ?></code></small></div>
                            <?php endif; ?>
                            <?php if (empty($c['must_not_contain']) && empty($c['must_contain'])): ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isBugTrigger): ?>
                                <span class="badge badge-fail">[PHÉP THỬ 1] IDOR</span>
                            <?php elseif ($isUndeclared): ?>
                                <span class="badge badge-subtle">[KỸ THUẬT 1] CHƯA KHAI BÁO</span>
                            <?php else: ?>
                                <span class="text-muted">Ca chuẩn</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($canEdit): ?>
                        <td style="text-align: right;">
                            <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Xác nhận xóa ca <?= e($c['code']) ?>?');">
                                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                <input type="hidden" name="action" value="delete_case">
                                <input type="hidden" name="case_id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm text-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Xóa</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
