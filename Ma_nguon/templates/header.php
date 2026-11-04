<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';

// Bảo vệ đăng nhập: Chuyển hướng về login.php nếu chưa xác thực
Auth::requireLogin();

// Thiết lập Security Headers tiêu chuẩn
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com; img-src 'self' data: https:;");
}

// Xử lý chuyển đổi tài khoản người dùng trực tiếp nếu có query switch_user
if (isset($_GET['switch_user'])) {
    Auth::switchUser((string)$_GET['switch_user']);
}

$currentUsername = Auth::getCurrentUsername();
$isCurrentAdmin = ($currentUsername === 'admin_bv' || $currentUsername === 'admin1' || $currentUsername === 'phamle');
$usersList = Auth::listUsers();
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' • ' : '' ?>MediSec Audit - Bệnh Viện Bạch Mai</title>
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <!-- Chart.js Thư viện đồ họa phân tích bảo mật -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
    <!-- Floria Minimalist Floating Capsule Header -->
    <header class="site-header">
        <div class="header-inner">
            <!-- Brand Section -->
            <div class="brand-group">
                <a href="/" class="brand-link" aria-label="MediSec Audit">
                    <span class="brand-title">MediSec<span class="brand-dot-char">.</span></span>
                </a>
                <span class="brand-tag">Bạch Mai</span>
            </div>

            <!-- Navigation Links -->
            <nav class="main-nav" aria-label="Điều hướng">
                <a href="/" class="<?= ($activeTab ?? '') === 'home' ? 'active' : '' ?>">Tổng quan</a>
                <a href="/runs.php" class="<?= ($activeTab ?? '') === 'runs' ? 'active' : '' ?>">Thực thi</a>
                <a href="/compare.php" class="<?= ($activeTab ?? '') === 'compare' ? 'active' : '' ?>">Đối chiếu</a>
                <a href="/cases.php" class="<?= ($activeTab ?? '') === 'cases' ? 'active' : '' ?>">24 Ca thử</a>
                <a href="/projects.php" class="<?= ($activeTab ?? '') === 'projects' ? 'active' : '' ?>">Ma trận quyền</a>
                <a href="http://127.0.0.1:8205" target="_blank" class="nav-external" title="Mở MediRecord EMR tại Cổng 8205">Cổng 8205</a>
            </nav>

            <!-- User Capsule -->
            <div class="header-user-capsule">
                <form method="GET" action="" class="user-switch-form" style="margin: 0; display: inline-flex;">
                    <?php
                    foreach ($_GET as $gk => $gv) {
                        if ($gk !== 'switch_user') {
                            echo '<input type="hidden" name="' . htmlspecialchars($gk) . '" value="' . htmlspecialchars((string)$gv) . '">';
                        }
                    }
                    ?>
                    <select name="switch_user" onchange="this.form.submit()" class="nav-user-select" title="Chuyển đổi tài khoản">
                        <option value="admin_bv" <?= $currentUsername === 'admin_bv' ? 'selected' : '' ?>>admin_bv</option>
                        <option value="tester_sec" <?= $currentUsername === 'tester_sec' ? 'selected' : '' ?>>tester_sec</option>
                        <?php if ($currentUsername !== 'admin_bv' && $currentUsername !== 'tester_sec'): ?>
                            <option value="<?= htmlspecialchars($currentUsername) ?>" selected><?= htmlspecialchars($currentUsername) ?></option>
                        <?php endif; ?>
                    </select>
                </form>

                <span class="nav-role-badge <?= $isCurrentAdmin ? 'role-admin' : 'role-tester' ?>">
                    <?= $isCurrentAdmin ? 'Admin' : 'Tester' ?>
                </span>

                <a href="/login.php?action=logout" class="nav-logout-btn" title="Đăng xuất khỏi phiên làm việc">Thoát</a>
            </div>
        </div>
    </header>
    <main id="main" class="container main-content" tabindex="-1">
