<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';

// Xử lý chuyển đổi tài khoản người dùng trực tiếp trên header nếu có POST/GET
if (isset($_GET['switch_user'])) {
    Auth::switchUser((string)$_GET['switch_user']);
}

$currentUsername = Auth::getCurrentUsername();
$usersList = Auth::listUsers();

// Tìm thông tin hiển thị của user hiện tại
$currentUserInfo = null;
foreach ($usersList as $u) {
    if ($u['username'] === $currentUsername) {
        $currentUserInfo = $u;
        break;
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' • ' : '' ?>AccessTester - Nền tảng kiểm thử tự động quyền truy cập Web</title>
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <!-- Chart.js Thư viện đồ họa phân tích bảo mật tiêu chuẩn quốc tế -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
    <header class="site-header">
        <div class="header-inner container">
            <div class="brand-group">
                <a href="/" class="brand-link">
                    <span class="brand-mark">AT</span>
                    <span class="brand-title">AccessTester</span>
                </a>
                <span class="brand-tag">SEC-TEST</span>
            </div>

            <nav class="main-nav" aria-label="Điều hướng chính">
                <a href="/" class="<?= ($activeTab ?? '') === 'home' ? 'active' : '' ?>">Tổng quan</a>
                <a href="/projects.php" class="<?= ($activeTab ?? '') === 'projects' ? 'active' : '' ?>">Dự án & Quyền</a>
                <a href="/cases.php" class="<?= ($activeTab ?? '') === 'cases' ? 'active' : '' ?>">Bộ ca kiểm thử</a>
                <a href="/runs.php" class="<?= ($activeTab ?? '') === 'runs' ? 'active' : '' ?>">Thực thi & Lịch sử</a>
                <a href="/compare.php" class="<?= ($activeTab ?? '') === 'compare' ? 'active' : '' ?>">Đối chiếu trước/sau</a>
            </nav>

            <div class="header-actions">
                <div class="user-switcher-box">
                    <span class="label">NGƯỜI DÙNG:</span>
                    <form method="GET" action="" style="margin: 0; display: inline;">
                        <?php
                        // Giữ lại các query param hiện tại ngoại trừ switch_user
                        foreach ($_GET as $gk => $gv) {
                            if ($gk !== 'switch_user') {
                                echo '<input type="hidden" name="' . htmlspecialchars($gk) . '" value="' . htmlspecialchars((string)$gv) . '">';
                            }
                        }
                        ?>
                        <select name="switch_user" onchange="this.form.submit()" title="Chuyển đổi vai trò người dùng">
                            <?php foreach ($usersList as $u): ?>
                                <option value="<?= htmlspecialchars($u['username']) ?>" <?= $currentUsername === $u['username'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['username']) ?> [<?= htmlspecialchars(strtoupper($u['display_name'])) ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
        </div>
    </header>
    <main id="main" class="container main-content" tabindex="-1">
