<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';

$error = '';
$info = '';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    Auth::logout();
    header('Location: /login.php?msg=logged_out');
    exit;
}

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $info = 'Bạn đã đăng xuất khỏi phiên làm việc an toàn.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf($_POST);
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // Kiểm tra đăng nhập nhanh từ nút 1-click
    if (isset($_POST['quick_user'])) {
        $username = trim((string)$_POST['quick_user']);
        $password = 'AccessTest@2026';
    }

    if (!Auth::isAuthorizedToolUser($username)) {
        $error = "Truy cập bị từ chối: Tài khoản '" . htmlspecialchars($username) . "' không thuộc danh sách được phân quyền. Chỉ Quản trị viên (admin_bv) và Nhân viên kiểm thử (tester_sec) được phép đăng nhập vào công cụ kiểm định.";
    } else {
        if (Auth::login($username, $password)) {
            header('Location: /');
            exit;
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không chính xác. Mật khẩu mẫu mặc định: AccessTest@2026';
        }
    }
}

// Nếu đã đăng nhập thì chuyển hướng vào trang chủ
if (isset($_SESSION['auth_user']) && Auth::isAuthorizedToolUser((string)$_SESSION['auth_user']) && !isset($_GET['force'])) {
    header('Location: /');
    exit;
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập • MediSec Audit - Bệnh viện Bạch Mai</title>
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        body {
            background-color: var(--color-bg-app);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }
        .floria-login-card {
            background: #ffffff;
            border: 1px solid var(--color-border-light);
            border-radius: var(--radius-bento);
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.05);
            max-width: 440px;
            width: 100%;
            padding: 44px 36px;
        }
        .floria-login-brand {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            font-size: 11px;
            font-weight: 600;
            color: #065f46;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }
        .floria-login-title {
            font-size: 1.85rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #09090b;
            margin-bottom: 0.4rem;
        }
        .floria-login-desc {
            font-size: 0.9rem;
            color: #71717a;
            line-height: 1.5;
            margin-bottom: 1.75rem;
        }
        .floria-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #27272a;
            margin-bottom: 0.4rem;
            display: block;
        }
        .floria-input {
            width: 100%;
            padding: 0.65rem 1rem;
            font-size: 0.9rem;
            border-radius: var(--radius-pill);
            border: 1px solid var(--color-border-light);
            background: #ffffff;
            color: #09090b;
            outline: none;
            transition: all var(--transition-fast);
            box-sizing: border-box;
        }
        .floria-input:focus {
            border-color: #09090b;
            box-shadow: 0 0 0 2px rgba(9, 9, 11, 0.08);
        }
        .floria-divider {
            height: 1px;
            background: #e4e4e7;
            margin: 28px 0;
            position: relative;
            text-align: center;
        }
        .floria-divider-text {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: #ffffff;
            padding: 0 12px;
            font-size: 11px;
            font-weight: 600;
            color: #a1a1aa;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .floria-quick-btn {
            width: 100%;
            background: #fafafa;
            color: #09090b;
            border: 1px solid #e4e4e7;
            border-radius: var(--radius-lg);
            padding: 12px 16px;
            font-size: 13px;
            cursor: pointer;
            transition: all 150ms ease;
            text-align: left;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .floria-quick-btn:hover {
            background: #f4f4f5;
            border-color: #a1a1aa;
        }
    </style>
</head>
<body>
    <div class="floria-login-card">
        <div style="text-align: center;">
            <div class="floria-login-brand">
                <span class="brand-dot"></span>
                Studio &amp; Archive • Bạch Mai
            </div>
            <h1 class="floria-login-title">MediSec<span class="brand-dot"></span></h1>
            <p class="floria-login-desc">Hệ thống kiểm định an ninh phân quyền hồ sơ bệnh án MediRecord EMR</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="sec-banner banner-danger" style="margin-bottom: 1.5rem; padding: 12px 14px;">
                <div class="banner-body"><?= $error ?></div>
            </div>
        <?php endif; ?>

        <?php if ($info !== ''): ?>
            <div class="sec-banner banner-success" style="margin-bottom: 1.5rem; padding: 12px 14px;">
                <div class="banner-body"><?= htmlspecialchars($info) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login.php">
            <?= csrfField() ?>
            <div style="margin-bottom: 14px;">
                <label class="floria-label" for="username">Tài khoản kiểm định</label>
                <input type="text" id="username" name="username" class="floria-input" placeholder="admin_bv hoặc tester_sec" autocomplete="username" required>
            </div>

            <div style="margin-bottom: 18px;">
                <label class="floria-label" for="password">Mật khẩu</label>
                <input type="password" id="password" name="password" class="floria-input" placeholder="Nhập mật khẩu" autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn-floria-dark" style="width: 100%;">Đăng nhập</button>
        </form>

        <div class="floria-divider">
            <span class="floria-divider-text">Đăng nhập nhanh theo vai trò</span>
        </div>

        <form method="POST" action="/login.php">
            <?= csrfField() ?>
            <input type="hidden" name="quick_user" value="admin_bv">
            <button type="submit" class="floria-quick-btn">
                <div>
                    <div style="font-weight: 700; color: #09090b;">Quản trị viên (admin_bv)</div>
                    <div style="font-size: 11px; color: #71717a; margin-top: 2px;">Toàn quyền hệ thống &amp; quản trị nhân sự</div>
                </div>
                <span class="badge badge-pass" style="font-size: 10px;">Toàn quyền</span>
            </button>
        </form>

        <form method="POST" action="/login.php">
            <?= csrfField() ?>
            <input type="hidden" name="quick_user" value="tester_sec">
            <button type="submit" class="floria-quick-btn">
                <div>
                    <div style="font-weight: 700; color: #09090b;">Nhân viên kiểm thử (tester_sec)</div>
                    <div style="font-size: 11px; color: #71717a; margin-top: 2px;">Toàn quyền kiểm thử, không quản trị nhân sự</div>
                </div>
                <span class="badge badge-subtle" style="font-size: 10px;">Kiểm thử</span>
            </button>
        </form>

        <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: var(--radius-md); padding: 12px 14px; margin-top: 20px; font-size: 11.5px; color: #71717a; line-height: 1.45;">
            <strong>Chính sách phân quyền:</strong> Chỉ Quản trị viên (admin_bv) và Nhân viên kiểm thử (tester_sec) được phép truy cập. Các tài khoản bệnh nhân (bn_an, bn_binh) và bác sĩ (bs_minh) bên Cổng 8205 bị từ chối truy cập công cụ này.
        </div>
    </div>
</body>
</html>
