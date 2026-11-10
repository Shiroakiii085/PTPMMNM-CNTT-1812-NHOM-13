<?php

declare(strict_types=1);

// Khởi tạo session cho trình duyệt Web
if (session_status() === PHP_SESSION_NONE) {
    $sessionDir = SAMPLE_PUBLIC_DIR . '/../../.local/sessions';
    if (is_dir($sessionDir) || @mkdir($sessionDir, 0777, true)) {
        session_save_path($sessionDir);
    }
    @session_start();
}

// 1. Chỉ lắng nghe trên loopback (127.0.0.1, ::1, localhost) hoặc môi trường Cloud Render/Container
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($clientIp, ['127.0.0.1', '::1', 'localhost'], true) && !getenv('PORT') && !getenv('RENDER')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'FORBIDDEN_HOST'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Kết nối CSDL PostgreSQL nếu có
$db = null;
try {
    require_once SAMPLE_PUBLIC_DIR . '/../../src/Database.php';
    $db = db();
} catch (Throwable) {}

// 3. Danh sách tài khoản thật theo Bối cảnh Y Tế (MediRecord EMR)
$mockUsers = [
    'admin_bv' => [
        'username' => 'admin_bv',
        'role' => 'admin',
        'full_name' => 'Quản trị viên Bệnh viện Bạch Mai',
        'department' => 'Phòng CNTT & An Ninh Mạng',
        'token' => 'test_token_admin_bv'
    ],
    'tester_sec' => [
        'username' => 'tester_sec',
        'role' => 'auditor',
        'full_name' => 'Kỹ sư Bảo mật Nguyễn Kiểm Thử',
        'department' => 'Ban An Toàn Thông Tin & Kiểm Toán Y Tế',
        'token' => 'test_token_tester_sec'
    ],
    'bs_minh' => [
        'username' => 'bs_minh',
        'role' => 'doctor',
        'full_name' => 'BS. CKI Lê Hoàng Minh',
        'department' => 'Khoa Tim Mạch & Nội Tiết',
        'token' => 'test_token_bs_minh'
    ],
    'bn_an' => [
        'username' => 'bn_an',
        'role' => 'member',
        'full_name' => 'Bệnh nhân Nguyễn Văn An',
        'department' => 'Bệnh nhân điều trị ngoại trú',
        'token' => 'test_token_bn_an'
    ],
    'bn_binh' => [
        'username' => 'bn_binh',
        'role' => 'member',
        'full_name' => 'Bệnh nhân Trần Thị Bình',
        'department' => 'Bệnh nhân điều trị chuyên khoa',
        'token' => 'test_token_bn_binh'
    ],
];

// Nạp thêm người dùng từ Session nếu có
if (!isset($_SESSION['custom_users'])) {
    $_SESSION['custom_users'] = [];
}
foreach ($_SESSION['custom_users'] as $uName => $uData) {
    $mockUsers[$uName] = $uData;
}

// Nạp người dùng từ CSDL nếu có
if ($db !== null) {
    try {
        $r = queryParams($db, 'SELECT username, role, full_name, department FROM sample_app.users ORDER BY id', []);
        $dbUsers = pg_fetch_all($r) ?: [];
        foreach ($dbUsers as $u) {
            if (!isset($mockUsers[$u['username']])) {
                $mockUsers[$u['username']] = [
                    'username' => $u['username'],
                    'role' => $u['role'],
                    'full_name' => $u['full_name'],
                    'department' => $u['department'] ?? 'Khoa Khám Bệnh',
                    'token' => 'test_token_' . $u['username']
                ];
            }
        }
    } catch (Throwable) {}
}

// Token mapping để hỗ trợ các bài kiểm thử tự động của AccessTester
$tokenMap = [
    'test_token_admin_bv'    => 'admin_bv',
    'test_token_tester_sec'  => 'tester_sec',
    'test_token_bs_minh'     => 'bs_minh',
    'test_token_bn_an'       => 'bn_an',
    'test_token_bn_binh'     => 'bn_binh',
    'test_token_alice_123'   => 'bn_an',
    'test_token_bob_456'     => 'bn_binh',
    'test_token_admin_789'   => 'admin_bv',
    'test_token_auditor_321' => 'tester_sec',
];

// 4. Danh sách Hồ sơ Bệnh án mẫu (Medical Records)
$mockRecords = [
    1 => [
        'id' => 1,
        'owner_username' => 'bn_an',
        'doctor_username' => 'bs_minh',
        'doctor_name' => 'BS. CKI Lê Hoàng Minh',
        'title' => 'Bệnh án số 01: Khám chuyên khoa Tim Mạch - BN Nguyễn Văn An',
        'department' => 'Khoa Tim Mạch Can Thiệp',
        'diagnosis' => 'Rối loạn nhịp xoang độ 2, theo dõi thiếu máu cơ tim cục bộ',
        'content' => 'Bi mat cua Alice 1: Chẩn đoán rối loạn nhịp xoang độ 2. Phác đồ điều trị nội khoa bảo mật do BS Minh chỉ định. Hạn chế vận động nặng.',
        'created_at' => '2026-10-24 08:30:00'
    ],
    2 => [
        'id' => 2,
        'owner_username' => 'bn_an',
        'doctor_username' => 'bs_minh',
        'doctor_name' => 'BS. CKI Lê Hoàng Minh',
        'title' => 'Bệnh án số 02: Kết quả xét nghiệm sinh hóa máu định kỳ - BN Nguyễn Văn An',
        'department' => 'Khoa Khám Bệnh Yêu Cầu',
        'diagnosis' => 'Chỉ số đường huyết ổn định, men gan ALT/AST bình thường',
        'content' => 'Chỉ số HbA1c 5.8%, lipid máu trong giới hạn an toàn. Duy trì đơn thuốc và tái khám sau 3 tháng.',
        'created_at' => '2026-10-24 09:15:00'
    ],
    3 => [
        'id' => 3,
        'owner_username' => 'bn_binh',
        'doctor_username' => 'bs_minh',
        'doctor_name' => 'BS. CKI Lê Hoàng Minh',
        'title' => 'Bệnh án số 03: Hồ sơ chẩn đoán đặc biệt & Sinh thiết - BN Trần Thị Bình',
        'department' => 'Khoa Ung Bướu & Hóa Trị Liệu',
        'diagnosis' => 'Theo dõi u biểu mô giai đoạn 1, chỉ định hóa xạ trị nội khoa',
        'content' => 'Bi mat cua Bob 3: Kết quả sinh thiết giải phẫu bệnh lý giai đoạn 1. Phác đồ hóa xạ trị kết hợp phục hồi chức năng.',
        'created_at' => '2026-10-24 10:00:00'
    ],
    4 => [
        'id' => 4,
        'owner_username' => 'bn_binh',
        'doctor_username' => 'bs_minh',
        'doctor_name' => 'BS. CKI Lê Hoàng Minh',
        'title' => 'Bệnh án số 04: Sổ theo dõi thuốc điều trị ngoại trú - BN Trần Thị Bình',
        'department' => 'Khoa Dược Lâm Sàng',
        'diagnosis' => 'Đáp ứng thuốc tốt sau chu kỳ 1, cải thiện tổng trạng',
        'content' => 'Báo cáo chi tiết các đợt phát thuốc bảo hiểm y tế đặc biệt. Thể trạng bệnh nhân ổn định sau chu kỳ 1.',
        'created_at' => '2026-10-24 10:45:00'
    ],
];

// Nạp thêm bệnh án từ Session nếu có
if (!isset($_SESSION['custom_records'])) {
    $_SESSION['custom_records'] = [];
}
foreach ($_SESSION['custom_records'] as $cId => $cRec) {
    $mockRecords[$cId] = $cRec;
}

// Nạp danh sách bệnh án đã xóa từ Session
if (!isset($_SESSION['deleted_records'])) {
    $_SESSION['deleted_records'] = [];
}
foreach ($_SESSION['deleted_records'] as $delId) {
    unset($mockRecords[$delId]);
}


// 5. Danh sách thông báo bệnh viện (Phân loại: Bệnh nhân vs Nội bộ cán bộ y tế)
$mockAnnouncements = [
    1 => [
        'id' => 1,
        'title' => 'Thông báo lịch trực cấp cứu và khám bệnh tuần này',
        'body' => 'Bệnh viện duy trì trực cấp cứu 24/7. Các khoa lâm sàng và cận lâm sàng làm việc theo lịch hẹn từ 07:30 đến 17:00 hàng ngày. Thông tin công khai cho toàn thể người bệnh và thân nhân.',
        'content' => 'Bệnh viện duy trì trực cấp cứu 24/7. Các khoa lâm sàng và cận lâm sàng làm việc theo lịch hẹn từ 07:30 đến 17:00 hàng ngày. Thông tin công khai cho toàn thể người bệnh và thân nhân.',
        'author' => 'Ban Giám Đốc Bệnh Viện',
        'audience' => 'patient',
        'created_at' => '2026-10-24 07:00:00'
    ],
    2 => [
        'id' => 2,
        'title' => 'Hướng dẫn tra cứu kết quả khám bệnh trực tuyến',
        'body' => 'Người bệnh và thân nhân có thể tra cứu hồ sơ bệnh án và lịch tái khám thông qua mã bệnh án cá nhân được cấp tại quầy tiếp đón.',
        'content' => 'Người bệnh và thân nhân có thể tra cứu hồ sơ bệnh án và lịch tái khám thông qua mã bệnh án cá nhân được cấp tại quầy tiếp đón.',
        'author' => 'Phòng Công Tác Xã Hội & Tiếp Đón',
        'audience' => 'patient',
        'created_at' => '2026-10-24 08:00:00'
    ],
    3 => [
        'id' => 3,
        'title' => 'Nội bộ: Quy trình an toàn thông tin & bảo mật hồ sơ EMR',
        'body' => 'Yêu cầu toàn thể Bác sĩ, Dược sĩ và Cán bộ chuyên môn tuân thủ tiêu chuẩn ISO/IEC 27001 và Luật Khám bệnh, chữa bệnh. Tuyệt đối không chia sẻ tài khoản hoặc truy vấn hồ sơ bệnh án ngoài phạm vi phân công.',
        'content' => 'Yêu cầu toàn thể Bác sĩ, Dược sĩ và Cán bộ chuyên môn tuân thủ tiêu chuẩn ISO/IEC 27001 và Luật Khám bệnh, chữa bệnh. Tuyệt đối không chia sẻ tài khoản hoặc truy vấn hồ sơ bệnh án ngoài phạm vi phân công.',
        'author' => 'Ban Giám Đốc & Phòng An Toàn Thông Tin',
        'audience' => 'internal',
        'created_at' => '2026-10-24 08:30:00'
    ]
];

if ($db !== null) {
    try {
        @pg_query($db, "ALTER TABLE sample_app.announcements ADD COLUMN IF NOT EXISTS audience varchar(20) DEFAULT 'patient'");
        $res = pg_query($db, 'SELECT id, title, body, audience, created_at FROM sample_app.announcements ORDER BY id ASC');
        if ($res && pg_num_rows($res) > 0) {
            $mockAnnouncements = [];
            while ($row = pg_fetch_assoc($res)) {
                $aud = ($row['audience'] ?? 'patient') === 'internal' ? 'internal' : 'patient';
                $mockAnnouncements[(int)$row['id']] = [
                    'id' => (int)$row['id'],
                    'title' => $row['title'],
                    'body' => $row['body'],
                    'content' => $row['body'],
                    'audience' => $aud,
                    'author' => ($aud === 'internal') ? 'Ban Giám Đốc & Phòng An Toàn Thông Tin' : 'Ban Giám Đốc Bệnh Viện',
                    'created_at' => $row['created_at']
                ];
            }
        }
    } catch (Throwable) {}
}

if (!isset($_SESSION['custom_announcements'])) {
    $_SESSION['custom_announcements'] = [];
}
foreach ($_SESSION['custom_announcements'] as $aId => $aRec) {
    $mockAnnouncements[(int)$aId] = $aRec;
}

if (isset($_SESSION['deleted_announcements']) && is_array($_SESSION['deleted_announcements'])) {
    foreach ($_SESSION['deleted_announcements'] as $delId) {
        unset($mockAnnouncements[(int)$delId]);
    }
}
$mockAnnouncements = array_values($mockAnnouncements);

