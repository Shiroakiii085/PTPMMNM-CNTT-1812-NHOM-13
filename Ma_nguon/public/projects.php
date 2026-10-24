<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ProjectRepository.php';
require_once __DIR__ . '/../src/Auth.php';

$pageTitle = 'Dự án & Ma trận phân quyền';
$activeTab = 'projects';

$db = db();
$projectRepo = new ProjectRepository($db);
$projects = $projectRepo->listProjects();

$selectedProjectId = isset($_GET['id']) ? (int)$_GET['id'] : (!empty($projects) ? (int)$projects[0]['id'] : 1);
$project = $projectRepo->findById($selectedProjectId);

$currentUser = Auth::getCurrentUsername();
$userRole = $projectRepo->getMemberRole($selectedProjectId, $currentUser);
$canEdit = in_array($userRole, ['owner', 'editor'], true);

$message = '';
$messageType = 'success';

// Xử lý các thao tác POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf($_POST);
    $action = $_POST['action'] ?? '';

    try {
        if (!$canEdit && in_array($action, ['add_member', 'add_role', 'add_resource', 'add_record', 'set_permission', 'delete_permission', 'create_project'], true)) {
            if ($action !== 'create_project') {
                throw new RuntimeException("Tài khoản '$currentUser' chỉ có vai trò '$userRole' trong dự án này nên không có quyền sửa đổi (Phép thử 4).");
            }
        }

        if ($action === 'create_project') {
            $pName = trim($_POST['name'] ?? '');
            $pBaseUrl = trim($_POST['base_url'] ?? '');
            $pDesc = trim($_POST['description'] ?? '');
            $newId = $projectRepo->create($pName, $pBaseUrl, $currentUser, $pDesc);
            header("Location: /projects.php?id=$newId&msg=project_created");
            exit;
        }

        if ($action === 'add_member') {
            $username = trim($_POST['username'] ?? '');
            $role = trim($_POST['member_role'] ?? 'viewer');
            if ($username !== '') {
                $projectRepo->addMember($selectedProjectId, $username, $role);
                $message = "Đã cập nhật thành viên '$username' với vai trò '$role'.";
            }
        }

        if ($action === 'add_role') {
            $roleName = trim($_POST['role_name'] ?? '');
            if ($roleName !== '') {
                $projectRepo->addRole($selectedProjectId, $roleName);
                $message = "Đã thêm vai trò '$roleName'.";
            }
        }

        if ($action === 'add_resource') {
            $resName = trim($_POST['resource_name'] ?? '');
            $ownerScoped = isset($_POST['owner_scoped']);
            if ($resName !== '') {
                $projectRepo->addResource($selectedProjectId, $resName, $ownerScoped);
                $message = "Đã thêm tài nguyên '$resName'.";
            }
        }

        if ($action === 'set_permission') {
            $roleId = (int)($_POST['role_id'] ?? 0);
            $resId = (int)($_POST['resource_id'] ?? 0);
            $actId = (int)($_POST['action_id'] ?? 0);
            $scope = trim($_POST['scope'] ?? 'any');
            if ($roleId > 0 && $resId > 0 && $actId > 0) {
                $projectRepo->setPermission($roleId, $resId, $actId, $scope);
                $message = "Đã cập nhật quyền thành công.";
            }
        }

        if ($action === 'delete_permission') {
            $roleId = (int)($_POST['role_id'] ?? 0);
            $resId = (int)($_POST['resource_id'] ?? 0);
            $actId = (int)($_POST['action_id'] ?? 0);
            $projectRepo->deletePermission($roleId, $resId, $actId);
            $message = "Đã xóa quyền (mặc định chuyển về DENY).";
        }

        if ($action === 'add_record') {
            $resId = (int)($_POST['resource_id'] ?? 0);
            $recordKey = trim($_POST['record_key'] ?? '');
            $ownerKey = trim($_POST['owner_key'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            if ($resId > 0 && $recordKey !== '') {
                $projectRepo->saveRecord($selectedProjectId, $resId, $recordKey, $ownerKey, $desc);
                $message = "Đã cập nhật bản ghi '$recordKey'.";
            }
        }

    } catch (Throwable $e) {
        $message = "Lỗi: " . $e->getMessage();
        $messageType = 'danger';
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'project_created') {
    $message = "Đã khởi tạo dự án kiểm thử mới thành công!";
}

$members = $projectRepo->getMembers($selectedProjectId);
$permissions = $projectRepo->getPermissions($selectedProjectId);
$roles = $projectRepo->getRoles($selectedProjectId);
$resources = $projectRepo->getResources($selectedProjectId);
$actions = $projectRepo->getActions($selectedProjectId);
$records = $projectRepo->getRecords($selectedProjectId);

require_once __DIR__ . '/../templates/header.php';
?>

<div class="page-header">
    <div class="page-header-info">
        <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.4rem;">
            <span class="badge badge-brand">CHỨC NĂNG 1</span>
            <span class="badge badge-subtle">RBAC & ABAC SECURITY MATRIX</span>
        </div>
        <h1 class="page-title">Quản lý dự án & Ma trận phân quyền</h1>
        <p class="page-desc">Định nghĩa vai trò, tài nguyên, bản ghi - chủ sở hữu và ma trận phân quyền. Mọi quyền chưa khai báo mặc định là <strong>DENY</strong>.</p>
    </div>
    <div class="page-header-actions">
        <form method="GET" action="/projects.php" style="margin: 0;">
            <select name="id" onchange="this.form.submit()" class="form-control font-mono">
                <?php foreach ($projects as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $selectedProjectId === (int)$p['id'] ? 'selected' : '' ?>>
                        #<?= (int)$p['id'] ?>: <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <button type="button" class="btn btn-secondary" onclick="const f = document.getElementById('newProjectPanel'); f.style.display = f.style.display === 'none' ? 'block' : 'none';">
            + Dự án mới
        </button>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="sec-banner <?= $messageType === 'success' ? 'banner-success' : 'banner-danger' ?>">
        <div class="banner-title"><?= $messageType === 'success' ? '[THÀNH CÔNG]' : '[LỖI]' ?></div>
        <div class="banner-body"><?= e($message) ?></div>
    </div>
<?php endif; ?>

<?php if (!$canEdit): ?>
    <div class="sec-banner banner-warning">
        <div class="banner-title">[CHẾ ĐỘ CHỈ XEM - PHÉP THỬ 4] Tài khoản không thuộc ban quản trị dự án</div>
        <div class="banner-body">
            Bạn đang đăng nhập dưới tên <strong><?= e($currentUser) ?></strong> với vai trò <strong><?= e((string)$userRole ?: 'người ngoài dự án') ?></strong>. Mọi quyền thay đổi cấu hình dự án bị khóa theo quy tắc cô lập nhóm.
        </div>
    </div>
<?php endif; ?>

<!-- Form tạo dự án mới (Ẩn/Hiện) -->
<div id="newProjectPanel" class="card mb-6" style="display: none; border-left: 4px solid var(--color-brand-accent); animation: slideDown 0.2s ease;">
    <div class="card-header">
        <h2 class="card-title">+ Khởi tạo dự án kiểm thử mới</h2>
        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('newProjectPanel').style.display='none';">Đóng</button>
    </div>
    <form method="POST" action="/projects.php">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="create_project">
        <div class="grid grid-cols-3 gap-4 mb-4">
            <div class="form-group">
                <label class="form-label">Tên dự án</label>
                <input type="text" name="name" class="form-control" placeholder="ví dụ: Dự án Cổng Thanh Toán" required>
            </div>
            <div class="form-group">
                <label class="form-label">Máy chủ đích (Base URL)</label>
                <input type="url" name="base_url" class="form-control font-mono" placeholder="http://127.0.0.1:8205" required>
                <span class="form-hint">Chỉ chấp nhận host 127.0.0.1 hoặc localhost</span>
            </div>
            <div class="form-group">
                <label class="form-label">Mô tả mục tiêu kiểm thử</label>
                <input type="text" name="description" class="form-control" placeholder="Ghi chú chi tiết">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Khởi tạo dự án</button>
    </form>
</div>

<!-- Thẻ tóm tắt thông số dự án -->
<div class="card mb-6">
    <div class="card-header">
        <div>
            <h2 class="card-title">Dự án #<?= (int)$project['id'] ?>: <?= e((string)$project['name']) ?></h2>
            <div class="card-subtitle">Máy chủ đích: <code class="font-mono"><?= e((string)$project['base_url']) ?></code></div>
        </div>
        <span class="badge <?= $canEdit ? 'badge-pass' : 'badge-subtle' ?>">
            VAI TRÒ CỦA BẠN: <?= strtoupper((string)$userRole ?: 'NONE') ?>
        </span>
    </div>
    <div class="grid grid-cols-3 gap-4 font-mono" style="font-size: 0.85rem;">
        <div><strong>Mô tả:</strong> <span class="text-muted"><?= e((string)($project['description'] ?? 'Chưa có mô tả')) ?></span></div>
        <div><strong>Ngày khởi tạo:</strong> <span class="text-muted"><?= e(substr((string)$project['created_at'], 0, 19)) ?></span></div>
        <div><strong>Chính sách mặc định:</strong> <span class="badge badge-deny">DENY (KỸ THUẬT 1)</span></div>
    </div>
</div>

<!-- Thanh chuyển đổi Tabs không gian làm việc -->
<div class="tab-wrapper">
    <div class="nav-tabs">
        <button type="button" class="nav-tab-btn active" data-tab-target="tab-matrix">
            1. Ma trận phân quyền (<?= count($permissions) ?>)
        </button>
        <button type="button" class="nav-tab-btn" data-tab-target="tab-records">
            2. Bản ghi & Chủ sở hữu (<?= count($records) ?>)
        </button>
        <button type="button" class="nav-tab-btn" data-tab-target="tab-members">
            3. Thành viên & Phân quyền công cụ (<?= count($members) ?>)
        </button>
    </div>

    <!-- TAB 1: Ma trận phân quyền RBAC/ABAC -->
    <div id="tab-matrix" class="tab-pane" style="display: block;">
        <div class="card mb-6">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Ma trận quy tắc truy cập (Permissions Matrix)</h2>
                    <div class="card-subtitle">Kỹ thuật 1: Quyền chưa được khai báo phải bị từ chối (Mặc định = DENY)</div>
                </div>
                <input type="text" class="form-control" style="max-width: 250px; font-size: 0.82rem;" placeholder="Lọc vai trò, tài nguyên..." data-table-search="#matrixTable">
            </div>

            <div class="table-responsive mb-4">
                <table class="table" id="matrixTable">
                    <thead>
                        <tr>
                            <th>Vai trò người dùng</th>
                            <th>Tài nguyên mục tiêu</th>
                            <th>Hành động</th>
                            <th>Phạm vi quyền (Scope)</th>
                            <?php if ($canEdit): ?><th style="text-align: right;">Thao tác</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($permissions)): ?>
                            <tr class="no-search"><td colspan="<?= $canEdit ? 5 : 4 ?>" class="text-muted text-center" style="padding: 2.5rem;">Chưa có quyền nào được khai báo (Toàn bộ yêu cầu đều bị DENY).</td></tr>
                        <?php else: ?>
                            <?php foreach ($permissions as $p): ?>
                            <tr>
                                <td><strong><?= e($p['role_name']) ?></strong></td>
                                <td><code class="font-mono"><?= e($p['resource_name']) ?></code></td>
                                <td><span class="badge badge-subtle"><?= e($p['action_name']) ?></span></td>
                                <td>
                                    <?php if ($p['scope'] === 'any'): ?>
                                        <span class="badge badge-allow">ANY (Mọi bản ghi)</span>
                                    <?php elseif ($p['scope'] === 'own'): ?>
                                        <span class="badge badge-pass">OWN (Chính chủ)</span>
                                    <?php else: ?>
                                        <span class="badge badge-fail">DENY</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canEdit): ?>
                                <td style="text-align: right;">
                                    <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Xác nhận xóa quyền này trong ma trận?');">
                                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                        <input type="hidden" name="action" value="delete_permission">
                                        <input type="hidden" name="role_id" value="<?= (int)$p['role_id'] ?>">
                                        <input type="hidden" name="resource_id" value="<?= (int)$p['resource_id'] ?>">
                                        <input type="hidden" name="action_id" value="<?= (int)$p['action_id'] ?>">
                                        <button type="submit" class="btn btn-outline btn-sm text-danger" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">Xóa quyền</button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($canEdit): ?>
            <div style="background: var(--color-bg-subtle); padding: 1.25rem; border-radius: 8px; border: 1px solid var(--color-border-light);">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="action" value="set_permission">
                    <div style="font-weight: 700; margin-bottom: 0.75rem; font-size: 0.95rem;">+ Thêm hoặc cập nhật quyền hạn trong ma trận</div>
                    <div class="grid grid-cols-4 gap-4" style="align-items: flex-end;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Vai trò</label>
                            <select name="role_id" class="form-control" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Tài nguyên</label>
                            <select name="resource_id" class="form-control" required>
                                <?php foreach ($resources as $res): ?>
                                    <option value="<?= (int)$res['id'] ?>"><?= e($res['name']) ?> (<?= $res['owner_scoped'] === 't' ? 'Chủ sở hữu' : 'Dùng chung' ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Hành động</label>
                            <select name="action_id" class="form-control" required>
                                <?php foreach ($actions as $act): ?>
                                    <option value="<?= (int)$act['id'] ?>"><?= e($act['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Phạm vi quyền</label>
                            <div style="display: flex; gap: 0.5rem;">
                                <select name="scope" class="form-control" required>
                                    <option value="any">any (Mọi bản ghi)</option>
                                    <option value="own">own (Chỉ chính chủ)</option>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">Lưu</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 2: Bản ghi & Quyền sở hữu -->
    <div id="tab-records" class="tab-pane" style="display: none;">
        <div class="card mb-6">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Bản ghi & Chủ sở hữu dữ liệu (Records & Ownership)</h2>
                    <div class="card-subtitle">Phục vụ cơ chế Policy Oracle đối soát quyền hạn ABAC</div>
                </div>
                <span class="badge badge-subtle"><?= count($records) ?> bản ghi</span>
            </div>

            <div class="table-responsive mb-4">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tài nguyên</th>
                            <th>Mã định danh bản ghi</th>
                            <th>Chủ sở hữu thực sự</th>
                            <th>Mô tả bản ghi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $rec): ?>
                        <tr>
                            <td><code class="font-mono"><?= e($rec['resource_name']) ?></code></td>
                            <td><strong class="font-mono">#<?= e($rec['record_key']) ?></strong></td>
                            <td>
                                <?php if (!empty($rec['owner_key'])): ?>
                                    <span class="badge badge-pass font-mono"><?= e($rec['owner_key']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-subtle">Dùng chung</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= e((string)($rec['description'] ?? '-')) ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($canEdit): ?>
            <div style="background: var(--color-bg-subtle); padding: 1.25rem; border-radius: 8px; border: 1px solid var(--color-border-light);">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="action" value="add_record">
                    <div style="font-weight: 700; margin-bottom: 0.75rem; font-size: 0.95rem;">+ Khai báo bản ghi & chủ sở hữu mới</div>
                    <div class="grid grid-cols-4 gap-4">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Tài nguyên</label>
                            <select name="resource_id" class="form-control" required>
                                <?php foreach ($resources as $res): ?>
                                    <option value="<?= (int)$res['id'] ?>"><?= e($res['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Khóa bản ghi (ID / Key)</label>
                            <input type="text" name="record_key" class="form-control font-mono" placeholder="ví dụ: 1 hoặc username" required>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Chủ sở hữu (owner_key)</label>
                            <input type="text" name="owner_key" class="form-control font-mono" placeholder="ví dụ: alice (để trống nếu chung)">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Mô tả</label>
                            <div style="display: flex; gap: 0.5rem;">
                                <input type="text" name="description" class="form-control" placeholder="Ghi chú chi tiết">
                                <button type="submit" class="btn btn-primary btn-sm">Lưu</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 3: Thành viên & Quyền hạn công cụ (Phép thử 4) -->
    <div id="tab-members" class="tab-pane" style="display: none;">
        <div class="card mb-6">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Thành viên & Phân quyền công cụ (Phép thử 4)</h2>
                    <div class="card-subtitle">Kiểm soát an ninh: Tài khoản chỉ được xem (Viewer) không thể sửa đổi cấu hình của nhóm khác</div>
                </div>
            </div>

            <div class="table-responsive mb-4">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tài khoản</th>
                            <th>Tên hiển thị</th>
                            <th>Vai trò trong công cụ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m): ?>
                        <tr>
                            <td><strong class="font-mono"><?= e($m['username']) ?></strong></td>
                            <td><?= e($m['display_name']) ?></td>
                            <td>
                                <span class="badge <?= $m['member_role'] === 'owner' ? 'badge-pass' : ($m['member_role'] === 'editor' ? 'badge-allow' : 'badge-subtle') ?>">
                                    <?= e(strtoupper($m['member_role'])) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($canEdit): ?>
            <div style="background: var(--color-bg-subtle); padding: 1.25rem; border-radius: 8px; border: 1px solid var(--color-border-light);">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="action" value="add_member">
                    <div style="font-weight: 700; margin-bottom: 0.75rem; font-size: 0.95rem;">+ Gán hoặc cập nhật vai trò thành viên</div>
                    <div class="grid grid-cols-3 gap-4" style="align-items: flex-end;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Tên người dùng (Username)</label>
                            <input type="text" name="username" class="form-control font-mono" placeholder="ví dụ: dev2 hoặc auditor_user" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Vai trò công cụ</label>
                            <select name="member_role" class="form-control">
                                <option value="viewer">viewer (Chỉ xem báo cáo)</option>
                                <option value="editor">editor (Chạy & Sửa ca)</option>
                                <option value="owner">owner (Toàn quyền quản trị)</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="height: 2.6rem;">Cập nhật vai trò</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
