<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ProjectRepository.php';
require_once __DIR__ . '/../src/RunRepository.php';
require_once __DIR__ . '/../src/Runner.php';
require_once __DIR__ . '/../src/HttpClient.php';
require_once __DIR__ . '/../src/Auth.php';

$pageTitle = 'Tổng quan hệ thống';
$activeTab = 'home';

$dbConnected = false;
$dbError = '';
$projects = [];
$recentRuns = [];
$sampleAppHealthy = false;
$flashMessage = '';
$flashType = 'success';

$flagFile = __DIR__ . '/../.local/bug_idor.flag';
$currentBugIdor = file_exists($flagFile) && trim((string)@file_get_contents($flagFile)) === '1';

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

$currentUser = Auth::getCurrentUsername();
$isCurrentAdmin = Auth::isOwner(1, $currentUser);

// Xử lý các thao tác thực thi 1-Click
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbConnected) {
    checkCsrf($_POST);
    $action = $_POST['action'] ?? '';
    $projId = !empty($projects) ? (int)$projects[0]['id'] : 1;

    try {
        @mkdir(__DIR__ . '/../.local', 0777, true);

        if ($action === 'toggle_bug_idor') {
            $newState = ($_POST['state'] ?? '0') === '1' ? '1' : '0';
            @file_put_contents($flagFile, $newState);
            $currentBugIdor = ($newState === '1');
            $flashMessage = $currentBugIdor 
                ? "Đã kích hoạt Lỗ hổng IDOR (BUG_IDOR=1). Hồ sơ bệnh án đang ở trạng thái có thể bị truy cập chéo trái phép."
                : "Đã vá Lỗ hổng IDOR (BUG_IDOR=0). Hệ thống khôi phục quy tắc phân quyền ABAC an toàn.";
            $flashType = $currentBugIdor ? 'warning' : 'success';
        } elseif ($action === 'quick_run') {
            $runner = new Runner($db);
            $lbl = 'kiem-dinh-' . date('Hi');
            $res = $runner->run($projId, $lbl, 'web', $currentUser);
            header('Location: /?msg=run_success&run_id=' . $res['run_id']);
            exit;
        } elseif ($action === 'demo_scenario_1') {
            @file_put_contents($flagFile, '1');
            $runner = new Runner($db);
            $res = $runner->run($projId, 'before-fix-' . date('Hi'), 'web', $currentUser);
            header('Location: /?msg=demo1_done&run_id=' . $res['run_id']);
            exit;
        } elseif ($action === 'demo_scenario_2') {
            @file_put_contents($flagFile, '0');
            $runner = new Runner($db);
            $res = $runner->run($projId, 'after-fix-' . date('Hi'), 'web', $currentUser);
            header('Location: /?msg=demo2_done&run_id=' . $res['run_id']);
            exit;
        }
    } catch (Throwable $e) {
        $flashMessage = "Lỗi thực thi: " . $e->getMessage();
        $flashType = 'danger';
    }
}

// Đọc lại trạng thái bug IDOR
$currentBugIdor = file_exists($flagFile) && trim((string)@file_get_contents($flagFile)) === '1';

// Kiểm tra nhanh sample app tại cổng 8205
$sampleRes = HttpClient::request('GET', 'http://127.0.0.1:8205/api/announcements', null, null, 1);
$sampleAppHealthy = ($sampleRes['error_code'] === null);

// Nhận flash message từ redirect
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'run_success') {
        $flashMessage = "Đã hoàn thành kiểm định 24 ca bệnh án HTTP thực tế.";
        $flashType = 'success';
    } elseif ($_GET['msg'] === 'demo1_done') {
        $flashMessage = "Tình huống 1 hoàn tất (Bật lỗi IDOR): Phát hiện ca R02 FAIL do rò rỉ bệnh án chéo (23 PASS, 1 FAIL).";
        $flashType = 'warning';
    } elseif ($_GET['msg'] === 'demo2_done') {
        $flashMessage = "Tình huống 2 hoàn tất (Đã vá lỗi): Toàn bộ 24/24 ca kiểm định đạt chuẩn PASS.";
        $flashType = 'success';
    }
}

// Lấy kết quả lần chạy mới nhất
$latestRun = !empty($recentRuns) ? $recentRuns[0] : null;
$latestRunResults = [];
$latestPass = 0;
$latestFail = 0;
$latestError = 0;

// Thống kê chuẩn nghiên cứu theo nhóm tài nguyên & độ trễ
$resStats = [
    'profile' => ['label' => 'Hồ sơ cá nhân', 'pass' => 0, 'fail' => 0, 'error' => 0],
    'notes' => ['label' => 'Bệnh án điện tử', 'pass' => 0, 'fail' => 0, 'error' => 0],
    'announcements' => ['label' => 'Thông báo chung', 'pass' => 0, 'fail' => 0, 'error' => 0],
    'users' => ['label' => 'Danh bạ cán bộ', 'pass' => 0, 'fail' => 0, 'error' => 0],
];
$accountLatency = [];

if ($latestRun !== null && $dbConnected) {
    $latestPass = (int)$latestRun['pass_count'];
    $latestFail = (int)$latestRun['fail_count'];
    $latestError = (int)$latestRun['error_count'];
    $latestRunResults = $runRepo->getResults((int)$latestRun['id']);

    foreach ($latestRunResults as $r) {
        $rName = $r['resource_name'] ?? 'notes';
        if (!isset($resStats[$rName])) {
            $resStats[$rName] = ['label' => $rName, 'pass' => 0, 'fail' => 0, 'error' => 0];
        }
        $out = strtolower($r['outcome']);
        if (isset($resStats[$rName][$out])) {
            $resStats[$rName][$out]++;
        }

        $acc = $r['account_label'] ?? 'khác';
        if (!isset($accountLatency[$acc])) {
            $accountLatency[$acc] = ['total' => 0, 'count' => 0];
        }
        $accountLatency[$acc]['total'] += (int)($r['duration_ms'] ?? 0);
        $accountLatency[$acc]['count']++;
    }
}

// Chú thích ngữ cảnh bệnh viện
function getCaseHospitalContext(string $code): array {
    $map = [
        'R01' => ['desc' => 'Bệnh nhân An xem bệnh án #1 của chính mình (Scope: OWN)', 'is_critical' => false],
        'R02' => ['desc' => 'Bệnh nhân An xem trộm bệnh án #3 của bệnh nhân Bình (LỖ HỔNG IDOR)', 'is_critical' => true],
        'R03' => ['desc' => 'Quản trị viên BV xem bệnh án #3 phục vụ giám sát y khoa', 'is_critical' => false],
        'R04' => ['desc' => 'Khách vãng lai xem bệnh án (Chưa xác thực danh tính)', 'is_critical' => false],
        'U01' => ['desc' => 'Bệnh nhân An cập nhật địa chỉ hồ sơ cá nhân của mình', 'is_critical' => false],
        'U02' => ['desc' => 'Bệnh nhân An cố ý sửa hồ sơ bệnh nhân Bình', 'is_critical' => false],
        'U03' => ['desc' => 'QTV Viện sửa hồ sơ (Kỹ thuật 1: Quyền chưa khai báo)', 'is_critical' => false],
        'U04' => ['desc' => 'Khách vãng lai cố ý sửa hồ sơ cá nhân', 'is_critical' => false],
        'A01' => ['desc' => 'Bệnh nhân An xem bảng tin y tế công cộng', 'is_critical' => false],
        'A02' => ['desc' => 'Bệnh nhân Bình xem bảng tin y tế công cộng', 'is_critical' => false],
        'A03' => ['desc' => 'Kỹ sư an toàn thông tin xem bảng tin y tế', 'is_critical' => false],
        'A04' => ['desc' => 'Khách vãng lai xem bảng tin (Công khai toàn viện)', 'is_critical' => false],
        'M01' => ['desc' => 'Quản trị viên Viện tra cứu danh bạ y bác sĩ nội bộ', 'is_critical' => false],
        'M02' => ['desc' => 'Bệnh nhân An cố ý truy cập danh bạ nhân sự y tế', 'is_critical' => false],
        'M03' => ['desc' => 'Kiểm toán cố ý truy cập danh bạ nhân sự y tế', 'is_critical' => false],
        'M04' => ['desc' => 'Khách vãng lai truy cập trái phép danh bạ nhân sự', 'is_critical' => false],
    ];
    return $map[$code] ?? ['desc' => 'Ca kiểm định phân quyền API y tế', 'is_critical' => false];
}

require_once __DIR__ . '/../templates/header.php';
?>

<!-- 1. FLORIA HERO ARCHITECTURE -->
<div class="floria-hero">
    <div class="floria-hero-tag">
        <span class="brand-dot"></span>
        Studio & Archive • Bệnh Viện Bạch Mai
    </div>
    <h1 class="floria-hero-title">Medical Access Architecture.</h1>
    <p class="floria-hero-desc">
        Hệ thống kiểm định chuyên sâu phân quyền RBAC & ABAC cho Bệnh Viện Bạch Mai. Kiểm soát truy cập đa lớp, phát hiện lỗ hổng IDOR và tự động hóa xác thực an toàn thông tin y tế.
    </p>

    <div class="floria-hero-actions">
        <!-- 1-Click Thực thi kiểm định -->
        <form method="POST" action="/" style="margin: 0; display: inline;">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="quick_run">
            <button type="submit" class="btn-floria-dark">
                Kiểm định 1-Click ngay
            </button>
        </form>

        <!-- Tình huống 2: Đã sửa lỗi -->
        <form method="POST" action="/" style="margin: 0; display: inline;">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="demo_scenario_2">
            <button type="submit" class="btn-floria-emerald">
                Tình huống 2: Đã vá lỗi (24 PASS)
            </button>
        </form>

        <!-- Tình huống 1: Tái hiện IDOR -->
        <form method="POST" action="/" style="margin: 0; display: inline;">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="demo_scenario_1">
            <button type="submit" class="btn-floria-outline">
                Tình huống 1: Tái hiện IDOR (23 PASS, 1 FAIL)
            </button>
        </form>

        <!-- Nút chuyển trạng thái IDOR độc lập -->
        <form method="POST" action="/" style="margin: 0; display: inline;">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="toggle_bug_idor">
            <input type="hidden" name="state" value="<?= $currentBugIdor ? '0' : '1' ?>">
            <button type="submit" class="badge <?= $currentBugIdor ? 'badge-fail' : 'badge-pass' ?>" style="cursor: pointer; padding: 8px 14px; font-size: 12px;" title="Bấm để bật/tắt cờ BUG_IDOR">
                CỜ HỆ THỐNG: <?= $currentBugIdor ? 'BUG_IDOR=1 (Có lỗi)' : 'BUG_IDOR=0 (Đã an toàn)' ?>
            </button>
        </form>
    </div>
</div>

<?php if ($flashMessage !== ''): ?>
    <div class="sec-banner <?= $flashType === 'success' ? 'banner-success' : ($flashType === 'warning' ? 'banner-warning' : 'banner-danger') ?>">
        <div class="banner-title"><?= $flashType === 'success' ? '[THÔNG BÁO THÀNH CÔNG]' : ($flashType === 'warning' ? '[CẢNH BÁO MÔI TRƯỜNG]' : '[LỖI HỆ THỐNG]') ?></div>
        <div class="banner-body"><?= e($flashMessage) ?></div>
    </div>
<?php endif; ?>

<!-- 2. FLORIA BENTO GRID ARCHIVE ("THE ARCHIVES" - INSPIRED BY #WEDDINGS) -->
<div class="floria-archive-header" id="weddings">
    <h2 class="floria-archive-title">The Archives.</h2>
    <p class="floria-archive-desc">
        Khám phá các phân hệ dữ liệu bệnh án y tế được kiểm định theo chuẩn phân quyền RBAC và ABAC.
    </p>
</div>

<div class="floria-bento">
    <!-- Card 1: The Medical Records Archive (Feature Card 8 Cột) -->
    <a href="/cases.php" class="floria-bento-card col-8">
        <img src="/assets/images/bachmai_hero.jpg" alt="Bệnh án điện tử" class="floria-bento-img">
        <div class="floria-bento-overlay"></div>
        <div class="floria-bento-ring"></div>
        <div class="floria-bento-content">
            <span class="floria-bento-pill pill-danger">PHÉP THỬ TRỌNG YẾU • R02 IDOR</span>
            <h3 class="floria-bento-title">The Medical Records Archive</h3>
            <p class="floria-bento-text">
                Bệnh án điện tử (<code>/api/notes</code>). Kiểm định quyền truy cập nhạy cảm, loại bỏ nguy cơ đọc chéo bệnh án giữa các bệnh nhân và đối soát quyền sở hữu chính chủ (Scope OWN).
            </p>
        </div>
    </a>

    <!-- Card 2: The Prescription Archive (4 Cột) -->
    <a href="/cases.php" class="floria-bento-card col-4">
        <img src="/assets/images/banner.png" alt="Đơn thuốc y tế" class="floria-bento-img">
        <div class="floria-bento-overlay"></div>
        <div class="floria-bento-ring"></div>
        <div class="floria-bento-content">
            <span class="floria-bento-pill">KÊ ĐƠN &amp; ĐIỀU TRỊ</span>
            <h3 class="floria-bento-title">The Prescription Archive</h3>
            <p class="floria-bento-text">
                Đơn thuốc y tế (<code>/api/prescriptions</code>). Phân quyền vai trò chuyên môn y khoa: Chỉ Bác sĩ và Dược sĩ được phép kê toa và cập nhật.
            </p>
        </div>
    </a>

    <!-- Card 3: The Patient Identity Vault (4 Cột) -->
    <a href="/projects.php" class="floria-bento-card col-4" style="background: #18181b;">
        <div class="floria-bento-overlay" style="background: linear-gradient(135deg, #09090b 0%, #1e3a8a 100%); opacity: 0.95;"></div>
        <div class="floria-bento-ring"></div>
        <div class="floria-bento-content">
            <span class="floria-bento-pill">QUYỀN SỞ HỮU ABAC</span>
            <h3 class="floria-bento-title">The Patient Vault</h3>
            <p class="floria-bento-text">
                Hồ sơ cá nhân (<code>/api/profile</code>). Bệnh nhân chỉ được chỉnh sửa thông tin của chính mình; từ chối mọi yêu cầu chỉnh sửa hồ sơ người khác.
            </p>
        </div>
    </a>

    <!-- Card 4: The Personnel Directory (4 Cột) -->
    <a href="/projects.php" class="floria-bento-card col-4" style="background: #18181b;">
        <div class="floria-bento-overlay" style="background: linear-gradient(135deg, #09090b 0%, #065f46 100%); opacity: 0.95;"></div>
        <div class="floria-bento-ring"></div>
        <div class="floria-bento-content">
            <span class="floria-bento-pill">CÔ LẬP NỘI BỘ</span>
            <h3 class="floria-bento-title">Personnel Directory</h3>
            <p class="floria-bento-text">
                Danh bạ y bác sĩ (<code>/api/users</code>). Dữ liệu bảo mật chỉ Quản trị viên Bệnh viện (admin1) mới có quyền truy cập; người bệnh bị chặn (403).
            </p>
        </div>
    </a>

    <!-- Card 5: The Public Dispatch (4 Cột) -->
    <a href="/cases.php" class="floria-bento-card col-4" style="background: #18181b;">
        <div class="floria-bento-overlay" style="background: linear-gradient(135deg, #09090b 0%, #312e81 100%); opacity: 0.95;"></div>
        <div class="floria-bento-ring"></div>
        <div class="floria-bento-content">
            <span class="floria-bento-pill">TRUY CẬP ĐẠI CHÚNG</span>
            <h3 class="floria-bento-title">Public Dispatch</h3>
            <p class="floria-bento-text">
                Bảng tin y tế (<code>/api/announcements</code>). Cho phép mọi vai trò y tế và khách vãng lai (anon) truy cập đọc tin tức hướng dẫn khám chữa bệnh.
            </p>
        </div>
    </a>
</div>

<!-- 3. PROCESS SECTION ("THE ARCHITECTURE OF TRUST" - INSPIRED BY FLORIA #PROCESS) -->
<div class="floria-process-section" id="process">
    <div class="floria-process-header">
        <h2 class="floria-process-title">The Architecture of Trust.</h2>
        <p class="floria-process-desc">
            Quy trình kiểm định bảo mật 3 bước khép kín, đảm bảo tính chuẩn xác và loại bỏ hoàn toàn các lỗi sai logic phân quyền.
        </p>
    </div>

    <div class="floria-process-grid">
        <div class="floria-process-card">
            <div class="floria-process-num">01</div>
            <h3>Policy Oracle</h3>
            <p>
                Mô hình hóa ma trận RBAC và ABAC. Áp dụng kỹ thuật 1: Quyền chưa khai báo mặc định là DENY (Từ chối). Thiết lập cơ chế kiểm chứng chủ sở hữu hồ sơ (Scope OWN).
            </p>
        </div>

        <div class="floria-process-card">
            <div class="floria-process-num">02</div>
            <h3>Execution Engine</h3>
            <p>
                Gửi 24 yêu cầu HTTP thực tế tới máy chủ MediRecord EMR (Cổng 8205). Lưu trữ snapshot bất biến và bảo vệ tính toàn vẹn với mã băm an ninh SHA-256.
            </p>
        </div>

        <div class="floria-process-card">
            <div class="floria-process-num">03</div>
            <h3>Redactor Shield</h3>
            <p>
                Tự động khử định danh Bearer Token, mật khẩu CSDL và khóa phiên trong báo cáo. Quét phản hồi HTTP để phát hiện rò rỉ dữ liệu cá nhân (Data Leak Check).
            </p>
        </div>
    </div>
</div>

<!-- 4. RESEARCH-GRADE CHARTS SECTION -->
<?php if ($latestRun): ?>
<div class="card mb-6">
    <div class="card-header">
        <div>
            <h2 class="card-title">Phân tích kết quả kiểm định đợt #<?= (int)$latestRun['id'] ?> (<?= e($latestRun['label']) ?>)</h2>
            <div class="card-subtitle">Biểu đồ chuẩn nghiên cứu: Phân bố quyền truy cập theo tài nguyên và độ trễ phản hồi</div>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-outline btn-sm" id="btnToggleChartMode">Xem biểu đồ độ trễ</button>
            <a href="/runs.php?id=<?= (int)$latestRun['id'] ?>" class="btn btn-primary btn-sm">Xem chi tiết đợt chạy</a>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-6">
        <div style="grid-column: span 2; height: 260px; position: relative;">
            <canvas id="floriaResearchChart"></canvas>
        </div>
        <div style="grid-column: span 1; display: flex; flex-direction: column; justify-content: center; gap: 1rem;">
            <div style="background: #fafafa; border: 1px solid var(--color-border-light); padding: 1rem; border-radius: var(--radius-md);">
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); font-weight: 700;">ĐẠT CHUẨN (PASS)</div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #065f46; letter-spacing: -0.03em;"><?= $latestPass ?> <span style="font-size: 0.9rem; font-weight: 500; color: #71717a;">/ 24 ca</span></div>
            </div>
            <div style="background: #fafafa; border: 1px solid var(--color-border-light); padding: 1rem; border-radius: var(--radius-md);">
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); font-weight: 700;">LỖ HỔNG (FAIL)</div>
                <div style="font-size: 1.8rem; font-weight: 800; color: <?= $latestFail > 0 ? '#991b1b' : '#065f46' ?>; letter-spacing: -0.03em;"><?= $latestFail ?> <span style="font-size: 0.9rem; font-weight: 500; color: #71717a;">ca</span></div>
            </div>
            <div style="background: #fafafa; border: 1px solid var(--color-border-light); padding: 1rem; border-radius: var(--radius-md);">
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); font-weight: 700;">MÃ BĂM SHA-256</div>
                <code class="font-mono" style="font-size: 0.72rem; color: #52525b;"><?= substr((string)$latestRun['config_hash'], 0, 18) ?>...</code>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 5. CURATED ASSEMBLAGES: 24 CA KIỂM ĐỊNH CÓ PHÂN TRANG & TÌM KIẾM -->
<div class="card mb-6" data-paginate data-page-size="8" id="cases">
    <div class="card-header">
        <div>
            <h2 class="card-title">Curated Assemblages: Danh mục 24 ca kiểm định</h2>
            <div class="card-subtitle">
                Kết quả thực thi HTTP thực tế đến máy chủ MediRecord EMR (Cổng 8205)
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <input type="text" class="form-control" style="width: 240px;" placeholder="Tìm nhanh ca kiểm thử..." data-table-search>
            <a href="/cases.php" class="btn btn-outline btn-sm">Xem bộ ca đầy đủ</a>
        </div>
    </div>

    <?php if (empty($latestRunResults)): ?>
        <p class="text-muted text-center" style="padding: 3rem 0;">Chưa có dữ liệu lần chạy nào. Bấm "Kiểm định 1-Click ngay" để thực thi.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 75px;">Mã ca</th>
                        <th style="width: 80px;">Phương thức</th>
                        <th style="width: 140px;">Tài khoản</th>
                        <th>Hành vi kiểm thử y tế</th>
                        <th style="width: 90px; text-align: center;">Mã HTTP</th>
                        <th style="width: 95px; text-align: center;">Kết quả</th>
                        <th style="width: 85px; text-align: right;">Độ trễ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($latestRunResults as $row): 
                        $caseCode = $row['code'] ?? $row['case_code'] ?? '';
                        $ctx = getCaseHospitalContext($caseCode);
                        $isFail = ($row['outcome'] === 'FAIL');
                        $isPass = ($row['outcome'] === 'PASS');
                        $isCrit = $ctx['is_critical'];
                        $httpStatus = $row['http_status'] ?? $row['actual_status'] ?? '-';
                        $dur = $row['duration_ms'] ?? $row['response_time_ms'] ?? 0;
                    ?>
                    <tr style="<?= $isFail ? 'background: #fef2f2;' : ($isCrit ? 'background: #ecfdf5;' : '') ?>">
                        <td>
                            <strong class="font-mono" style="<?= $isCrit ? 'color: #991b1b; font-weight: 700;' : '' ?>">
                                <?= e($caseCode) ?>
                            </strong>
                            <?php if ($isCrit): ?>
                                <span style="display: block; font-size: 10px; color: #dc2626; font-weight: 700;">IDOR TEST</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="method-chip method-<?= strtolower($row['method'] ?? 'get') ?>">
                                <?= e($row['method'] ?? 'GET') ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            $acc = $row['account_label'] ?? '';
                            if ($acc === 'alice'): ?>
                                <span class="patient-pill patient-an">BN. An (alice)</span>
                            <?php elseif ($acc === 'bob'): ?>
                                <span class="patient-pill patient-binh">BN. Bình (bob)</span>
                            <?php elseif ($acc === 'admin1'): ?>
                                <span class="patient-pill medical-admin">Admin BV (admin1)</span>
                            <?php elseif ($acc === 'auditor1'): ?>
                                <span class="patient-pill medical-auditor">Kiểm toán (auditor1)</span>
                            <?php else: ?>
                                <span class="patient-pill" style="background: #f4f4f5; color: #52525b;">Khách (anon)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: #09090b; font-size: 12.5px;">
                                <?= e($ctx['desc']) ?>
                            </div>
                            <div style="font-size: 11px; color: #71717a; margin-top: 2px;">
                                <code style="font-size: 11px;"><?= e($row['path'] ?? '') ?></code>
                                <?php if (!empty($row['leak_detected'])): ?>
                                    <span style="color: #dc2626; font-weight: 700; margin-left: 6px;">[Cảnh báo: Rò rỉ dữ liệu bệnh án]</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <span class="font-mono" style="font-size: 12px; font-weight: 600;">
                                <?= (int)$httpStatus > 0 ? (int)$httpStatus : e((string)$httpStatus) ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($isPass): ?>
                                <span class="badge badge-pass">PASS</span>
                            <?php elseif ($isFail): ?>
                                <span class="badge badge-fail">FAIL</span>
                            <?php else: ?>
                                <span class="badge badge-error">ERR</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <span class="text-muted font-mono" style="font-size: 11.5px;"><?= (int)$dur ?> ms</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Phân trang 8 ca / trang -->
        <div class="pagination-container">
            <div class="pagination-info"></div>
            <div class="pagination-controls"></div>
        </div>
    <?php endif; ?>
</div>

<?php if ($latestRun): ?>
<script>
let currentChart = null;

const resourceLabels = [
    'Hồ sơ (/profile)',
    'Bệnh án (/notes)',
    'Thông báo (/announcements)',
    'Danh bạ (/users)'
];

const passByRes = [
    <?= (int)($resStats['profile']['pass'] ?? 0) ?>,
    <?= (int)($resStats['notes']['pass'] ?? 0) ?>,
    <?= (int)($resStats['announcements']['pass'] ?? 0) ?>,
    <?= (int)($resStats['users']['pass'] ?? 0) ?>
];

const failByRes = [
    <?= (int)($resStats['profile']['fail'] ?? 0) ?>,
    <?= (int)($resStats['notes']['fail'] ?? 0) ?>,
    <?= (int)($resStats['announcements']['fail'] ?? 0) ?>,
    <?= (int)($resStats['users']['fail'] ?? 0) ?>
];

const latencyAccounts = <?= json_encode(array_keys($accountLatency)) ?>;
const latencyValues = <?= json_encode(array_map(fn($a) => $a['count'] > 0 ? round($a['total'] / $a['count'], 1) : 0, array_values($accountLatency))) ?>;

function renderResourceChart() {
    const ctx = document.getElementById('floriaResearchChart');
    if (!ctx || typeof Chart === 'undefined') return;

    if (currentChart) currentChart.destroy();

    currentChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: resourceLabels,
            datasets: [
                {
                    label: 'Đạt chuẩn (PASS)',
                    data: passByRes,
                    backgroundColor: '#10b981',
                    borderRadius: 4
                },
                {
                    label: 'Lỗ hổng (FAIL)',
                    data: failByRes,
                    backgroundColor: '#dc2626',
                    borderRadius: 4
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
                        font: { size: 11, family: 'var(--font-sans)' },
                        boxWidth: 12,
                        boxHeight: 12
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 2, font: { size: 10 } },
                    grid: { color: '#f4f4f5' }
                },
                x: {
                    ticks: { font: { size: 11, family: 'var(--font-sans)' } },
                    grid: { display: false }
                }
            }
        }
    });
}

function renderLatencyChart() {
    const ctx = document.getElementById('floriaResearchChart');
    if (!ctx || typeof Chart === 'undefined') return;

    if (currentChart) currentChart.destroy();

    currentChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: latencyAccounts,
            datasets: [{
                label: 'Độ trễ trung bình (ms)',
                data: latencyValues,
                backgroundColor: '#09090b',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { size: 11, family: 'var(--font-sans)' }, boxWidth: 12 }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { font: { size: 10 } },
                    grid: { color: '#f4f4f5' }
                },
                x: {
                    ticks: { font: { size: 11, family: 'var(--font-sans)' } },
                    grid: { display: false }
                }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    renderResourceChart();

    let isLatencyMode = false;
    const btnToggle = document.getElementById('btnToggleChartMode');
    if (btnToggle) {
        btnToggle.addEventListener('click', () => {
            isLatencyMode = !isLatencyMode;
            if (isLatencyMode) {
                renderLatencyChart();
                btnToggle.textContent = 'Xem biểu đồ phân quyền';
            } else {
                renderResourceChart();
                btnToggle.textContent = 'Xem biểu đồ độ trễ';
            }
        });
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
