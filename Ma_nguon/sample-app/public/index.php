<?php

declare(strict_types=1);

/**
 * MediRecord EMR — Bệnh Viện Bạch Mai (Sample Application Front Controller)
 * Kiến trúc phân tách Module theo chức năng (Bootstrap -> Services -> Controllers -> API -> Views)
 */
define('SAMPLE_PUBLIC_DIR', __DIR__);
define('SAMPLE_APP_DIR', dirname(__DIR__) . '/app');

// 1. Khởi tạo hệ thống, dữ liệu mẫu, phiên đăng nhập và cờ bảo mật BUG_IDOR
require_once SAMPLE_APP_DIR . '/bootstrap/helpers.php';
require_once SAMPLE_APP_DIR . '/bootstrap/init_data.php';
require_once SAMPLE_APP_DIR . '/bootstrap/auth_and_flags.php';

// 2. Nạp các dịch vụ nghiệp vụ (Giám sát an ninh, Kiểm thử 24 ca, Trợ lý AI MediBot)
require_once SAMPLE_APP_DIR . '/services/security_service.php';
require_once SAMPLE_APP_DIR . '/services/test_runner_service.php';
require_once SAMPLE_APP_DIR . '/services/ai_assistant_service.php';

// 3. Xử lý các hành động Web ($action) theo từng phân hệ
require SAMPLE_APP_DIR . '/controllers/ai_controller.php';
require SAMPLE_APP_DIR . '/controllers/tester_controller.php';
require SAMPLE_APP_DIR . '/controllers/auth_controller.php';
require SAMPLE_APP_DIR . '/controllers/user_controller.php';
require SAMPLE_APP_DIR . '/controllers/record_controller.php';
require SAMPLE_APP_DIR . '/controllers/announcement_controller.php';

// 4. Định tuyến REST API (/api/profile, /api/notes, /api/records, /api/announcements, /api/admin/users)
require SAMPLE_APP_DIR . '/api/api_routes.php';

// 5. Chuẩn bị dữ liệu hiển thị cho các View & kiểm tra phân quyền truy cập trang
require SAMPLE_APP_DIR . '/views/prepare_view.php';

// 6. Render khung giao diện HTML (Head & Thanh điều hướng)
require SAMPLE_APP_DIR . '/views/layouts/head.php';
require SAMPLE_APP_DIR . '/views/layouts/navbar.php';

// 7. Render nội dung trang chính theo $view
if ($view === 'landing') {
    require SAMPLE_APP_DIR . '/views/pages/landing.php';
} else {
    echo '    <div class="container">' . "\n";
    require SAMPLE_APP_DIR . '/views/layouts/idor_banner.php';

    if ($view === 'record' && $recordDetail) {
        require SAMPLE_APP_DIR . '/views/pages/record_detail.php';
    } elseif ($view === 'edit_record' && $editRecordDetail) {
        require SAMPLE_APP_DIR . '/views/pages/record_edit.php';
    } elseif ($view === 'create_record') {
        require SAMPLE_APP_DIR . '/views/pages/record_create.php';
    } elseif ($view === 'announcements') {
        require SAMPLE_APP_DIR . '/views/pages/announcements.php';
    } elseif ($view === 'users' && $currentUser && $currentUser['role'] === 'admin') {
        require SAMPLE_APP_DIR . '/views/pages/users.php';
    } elseif ($view === 'tester' && $currentUser && in_array($currentUser['role'], ['auditor', 'admin'], true)) {
        require SAMPLE_APP_DIR . '/views/tester/index.php';
    } elseif ($view === 'profile' && $currentUser) {
        require SAMPLE_APP_DIR . '/views/pages/profile.php';
    } elseif ($view === 'login') {
        require SAMPLE_APP_DIR . '/views/pages/login.php';
    } elseif ($view === 'register') {
        require SAMPLE_APP_DIR . '/views/pages/register.php';
    } else {
        require SAMPLE_APP_DIR . '/views/pages/records_list.php';
    }

    echo '    </div> <!-- /container -->' . "\n";
}

// 8. Render các tiện ích nổi toàn cục (3D Robot MediBot, Chuyển tài khoản nhanh, Hold-to-Confirm Modal)
require SAMPLE_APP_DIR . '/views/partials/floating_widgets.php';
?>
  <script>
    window.EMR_USER = {
      role: <?= json_encode($currentUser['role'] ?? 'guest', JSON_UNESCAPED_UNICODE) ?>,
      name: <?= json_encode($currentUser['full_name'] ?? 'Quý khách', JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <script src="/assets/js/app.js?v=2.0"></script>
</body>
</html>
