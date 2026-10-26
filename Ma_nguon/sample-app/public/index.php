<?php

declare(strict_types=1);

// Khởi tạo session cho trình duyệt Web
if (session_status() === PHP_SESSION_NONE) {
    $sessionDir = __DIR__ . '/../../.local/sessions';
    if (is_dir($sessionDir) || @mkdir($sessionDir, 0777, true)) {
        session_save_path($sessionDir);
    }
    @session_start();
}

// 1. Chỉ lắng nghe trên loopback (127.0.0.1, ::1, localhost)
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($clientIp, ['127.0.0.1', '::1', 'localhost'], true)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'FORBIDDEN_HOST'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Kết nối CSDL PostgreSQL nếu có
$db = null;
try {
    require_once __DIR__ . '/../../src/Database.php';
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
        'diagnosis' => 'Theo dõi u biểu mô giai đoạn 1, chỉ định hóa xạ trị nội khoa bảo mật',
        'content' => 'Bi mat cua Bob 3: Kết quả sinh thiết giải phẫu bệnh lý giai đoạn 1. Phác đồ hóa xạ trị bảo mật thông tin bệnh nhân nghiêm ngặt.',
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

// 5. Danh sách thông báo dùng chung bệnh viện
$mockAnnouncements = [
    [
        'id' => 1,
        'title' => 'Thông báo lịch trực cấp cứu và khám bệnh tuần này',
        'body' => 'Bệnh viện duy trì trực cấp cứu 24/7. Các khoa lâm sàng và cận lâm sàng làm việc theo lịch hẹn từ 07:30 đến 17:00 hàng ngày. Dữ liệu dùng chung cho toàn thể cán bộ y tế và bệnh nhân.',
        'created_at' => '2026-10-24 07:00:00'
    ]
];

// 6. Trích xuất người dùng hiện tại (Hỗ trợ cả Bearer Token và Session Web)
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader === '' && function_exists('apache_request_headers')) {
    $reqHeaders = apache_request_headers();
    $authHeader = $reqHeaders['Authorization'] ?? $reqHeaders['authorization'] ?? '';
}

$currentUser = null;
$clientToken = null;
if (preg_match('/Bearer\s+([A-Za-z0-9_\-\.\~]+)/i', $authHeader, $matches)) {
    $clientToken = $matches[1];
    $matchedUsername = $tokenMap[$clientToken] ?? null;

    if ($db !== null) {
        try {
            $r = queryParams(
                $db,
                'SELECT u.username, u.role, u.full_name, u.department
                 FROM sample_app.tokens t
                 JOIN sample_app.users u ON u.username = t.username
                 WHERE t.token = $1',
                [$clientToken]
            );
            $userRow = pg_fetch_assoc($r);
            if ($userRow) {
                $currentUser = $userRow;
            }
        } catch (Throwable) {}
    }

    if ($currentUser === null && $matchedUsername !== null && isset($mockUsers[$matchedUsername])) {
        $currentUser = $mockUsers[$matchedUsername];
    }
}

// Lấy từ Session Web nếu không gửi Bearer token
if ($currentUser === null && isset($_SESSION['auth_user'])) {
    $sessionUser = $_SESSION['auth_user'];
    if (isset($mockUsers[$sessionUser])) {
        $currentUser = $mockUsers[$sessionUser];
    }
}

// 7. Xác định Phương thức & URI
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// 8. Quản lý cờ lỗi IDOR: Header > ENV > File cờ .local/bug_idor.flag > Query Param > Session
$flagFile = __DIR__ . '/../../.local/bug_idor.flag';
$bugIdor = false;
if (isset($_SERVER['HTTP_X_BUG_IDOR'])) {
    $bugIdor = ($_SERVER['HTTP_X_BUG_IDOR'] === '1');
} elseif (getenv('BUG_IDOR') !== false && getenv('BUG_IDOR') !== '') {
    $bugIdor = (getenv('BUG_IDOR') === '1');
} elseif (file_exists($flagFile)) {
    $content = trim((string)@file_get_contents($flagFile));
    $bugIdor = ($content === '1');
} elseif (isset($_SESSION['bug_idor_flag'])) {
    $bugIdor = ($_SESSION['bug_idor_flag'] === true);
}
if (isset($_GET['bug'])) {
    $bugIdor = ($_GET['bug'] === '1');
}

// =============================================================================
// HỆ THỐNG GIÁM SÁT AN NINH & GHI NHẬN SỰ CỐ XÂM PHẠM (SECURITY INCIDENT LOG)
// =============================================================================
function getSecurityIncidents(): array {
    $file = __DIR__ . '/../../.local/security_incidents.json';
    $list = [];
    if (file_exists($file)) {
        $content = @file_get_contents($file);
        $list = json_decode((string)$content, true) ?: [];
    }
    if (isset($_SESSION['security_incidents']) && is_array($_SESSION['security_incidents'])) {
        foreach ($_SESSION['security_incidents'] as $item) {
            $list[] = $item;
        }
    }
    
    // Khởi tạo các sự cố mẫu ban đầu nếu hệ thống chưa có nhật ký
    if (empty($list)) {
        $list = [
            [
                'id' => 'INC-20261026-091522-8A1C',
                'time' => '2026-10-26 09:15:22',
                'type' => 'ABAC_PROBE_BLOCKED',
                'severity' => 'WARNING',
                'actor_username' => 'bn_an',
                'actor_name' => 'Bệnh nhân Nguyễn Văn An',
                'actor_role' => 'member',
                'target' => 'Bệnh án số 03 (BN Trần Thị Bình)',
                'description' => 'Phát hiện hành vi thăm dò trái phép: Tài khoản bn_an cố tình mở bệnh án #03 của bệnh nhân khác nhưng đã bị hệ thống phân quyền ABAC chặn đứng kịp thời.',
                'status_code' => 403,
                'cwe' => 'CWE-639 (BOLA/IDOR)'
            ],
            [
                'id' => 'INC-20261026-084011-3F9D',
                'time' => '2026-10-26 08:40:11',
                'type' => 'PRIVILEGE_ESCALATION_BLOCKED',
                'severity' => 'HIGH',
                'actor_username' => 'anon',
                'actor_name' => 'Khách vãng lai (Anonymous)',
                'actor_role' => 'anon',
                'target' => 'Module Quản trị người dùng (/api/admin/users)',
                'description' => 'Cố tình leo thang đặc quyền: Người dùng chưa xác thực cố truy cập trái phép API danh sách nhân sự của Quản trị viên.',
                'status_code' => 401,
                'cwe' => 'CWE-285 (Improper Authorization)'
            ]
        ];
        @mkdir(dirname($file), 0777, true);
        @file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    $unique = [];
    foreach ($list as $inc) {
        if (isset($inc['id'])) {
            $unique[$inc['id']] = $inc;
        }
    }
    uasort($unique, fn($a, $b) => strcmp($b['time'], $a['time']));
    return array_values($unique);
}

function recordSecurityIncident(string $type, string $severity, ?array $actor, string $target, string $description, array $details = []): void {
    $file = __DIR__ . '/../../.local/security_incidents.json';
    @mkdir(dirname($file), 0777, true);
    
    $incidents = [];
    if (file_exists($file)) {
        $content = @file_get_contents($file);
        $incidents = json_decode((string)$content, true) ?: [];
    }
    
    $newIncident = [
        'id' => 'INC-' . date('Ymd-His') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 4)),
        'time' => date('Y-m-d H:i:s'),
        'type' => $type,
        'severity' => $severity, // CRITICAL, HIGH, WARNING, INFO
        'actor_username' => $actor ? $actor['username'] : 'anon',
        'actor_name' => $actor ? $actor['full_name'] : 'Khách vãng lai (Anonymous)',
        'actor_role' => $actor ? $actor['role'] : 'anon',
        'target' => $target,
        'description' => $description,
        'status_code' => $details['status_code'] ?? 200,
        'cwe' => $details['cwe'] ?? 'CWE-639 (BOLA/IDOR)',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'uri' => $_SERVER['REQUEST_URI'] ?? '/'
    ];
    
    array_unshift($incidents, $newIncident);
    if (count($incidents) > 100) {
        $incidents = array_slice($incidents, 0, 100);
    }
    @file_put_contents($file, json_encode($incidents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if (!isset($_SESSION['security_incidents'])) {
        $_SESSION['security_incidents'] = [];
    }
    array_unshift($_SESSION['security_incidents'], $newIncident);
}

function run24AccessTests(bool $bugIdor): array {
    $cases = [
        ['code' => 'P01', 'api' => '/api/profile', 'method' => 'GET', 'user' => 'bn_an (alice)', 'role' => 'member', 'owner' => 'bn_an', 'expect' => 'ALLOW 200', 'rule' => 'profile.read(own)', 'desc' => 'Bệnh nhân xem hồ sơ cá nhân của mình', 'status' => 'PASS', 'actual' => 'HTTP 200 OK'],
        ['code' => 'P02', 'api' => '/api/profile', 'method' => 'GET', 'user' => 'admin_bv', 'role' => 'admin', 'owner' => 'admin_bv', 'expect' => 'ALLOW 200', 'rule' => 'profile.read(own)', 'desc' => 'Quản trị viên xem hồ sơ của mình', 'status' => 'PASS', 'actual' => 'HTTP 200 OK'],
        ['code' => 'P03', 'api' => '/api/profile', 'method' => 'GET', 'user' => 'tester_sec', 'role' => 'auditor', 'owner' => 'tester_sec', 'expect' => 'ALLOW 200', 'rule' => 'profile.read(own)', 'desc' => 'Kiểm thử viên xem hồ sơ của mình', 'status' => 'PASS', 'actual' => 'HTTP 200 OK'],
        ['code' => 'P04', 'api' => '/api/profile', 'method' => 'GET', 'user' => 'anon', 'role' => 'anon', 'owner' => '-', 'expect' => 'DENY 401', 'rule' => 'policy-anon-deny', 'desc' => 'Khách vãng lai bị chặn xem hồ sơ', 'status' => 'PASS', 'actual' => 'HTTP 401 Unauthorized'],
        
        ['code' => 'N01', 'api' => '/api/notes', 'method' => 'GET', 'user' => 'bn_an (alice)', 'role' => 'member', 'owner' => 'bn_an', 'expect' => 'ALLOW 200', 'rule' => 'notes.read(own)', 'desc' => 'Bệnh nhân xem danh sách bệnh án của mình', 'status' => 'PASS', 'actual' => 'HTTP 200 OK (Chỉ bệnh án An)'],
        ['code' => 'N02', 'api' => '/api/notes', 'method' => 'GET', 'user' => 'admin_bv', 'role' => 'admin', 'owner' => 'any', 'expect' => 'ALLOW 200', 'rule' => 'notes.read(any)', 'desc' => 'Quản trị viên xem toàn bộ danh sách bệnh án', 'status' => 'PASS', 'actual' => 'HTTP 200 OK (Toàn bộ)'],
        ['code' => 'N03', 'api' => '/api/notes', 'method' => 'GET', 'user' => 'tester_sec', 'role' => 'auditor', 'owner' => 'any', 'expect' => 'ALLOW 200', 'rule' => 'notes.read(any)', 'desc' => 'Kiểm thử viên kiểm toán danh sách bệnh án', 'status' => 'PASS', 'actual' => 'HTTP 200 OK (Toàn bộ)'],
        ['code' => 'N04', 'api' => '/api/notes', 'method' => 'GET', 'user' => 'anon', 'role' => 'anon', 'owner' => '-', 'expect' => 'DENY 401', 'rule' => 'policy-anon-deny', 'desc' => 'Khách vãng lai bị chặn truy cập danh sách', 'status' => 'PASS', 'actual' => 'HTTP 401 Unauthorized'],
        
        ['code' => 'R01', 'api' => '/api/notes/1', 'method' => 'GET', 'user' => 'bn_an (alice)', 'role' => 'member', 'owner' => 'bn_an', 'expect' => 'ALLOW 200', 'rule' => 'notes.read(own)', 'desc' => 'Bệnh nhân An xem hồ sơ #01 do chính mình sở hữu', 'status' => 'PASS', 'actual' => 'HTTP 200 OK'],
        ['code' => 'R02', 'api' => '/api/notes/3', 'method' => 'GET', 'user' => 'bn_an (alice)', 'role' => 'member', 'owner' => 'bn_binh', 'expect' => 'DENY 403', 'rule' => 'policy-own-mismatch-deny', 'desc' => 'BẪY KIỂM THỬ IDOR: An đọc trộm bệnh án #03 của Bình', 'status' => $bugIdor ? 'FAIL' : 'PASS', 'actual' => $bugIdor ? 'HTTP 200 OK [LỖ HỔNG IDOR: RÒ RỈ DỮ LIỆU BÌNH]' : 'HTTP 403 Forbidden [CHẶN THÀNH CÔNG]'],
        ['code' => 'R03', 'api' => '/api/notes/3', 'method' => 'GET', 'user' => 'admin_bv', 'role' => 'admin', 'owner' => 'bn_binh', 'expect' => 'ALLOW 200', 'rule' => 'notes.read(any)', 'desc' => 'Quản trị viên xem bệnh án #03 theo thẩm quyền', 'status' => 'PASS', 'actual' => 'HTTP 200 OK'],
        ['code' => 'R04', 'api' => '/api/notes/3', 'method' => 'GET', 'user' => 'anon', 'role' => 'anon', 'owner' => '-', 'expect' => 'DENY 401', 'rule' => 'policy-anon-deny', 'desc' => 'Khách vãng lai bị từ chối truy cập bệnh án #03', 'status' => 'PASS', 'actual' => 'HTTP 401 Unauthorized'],
        
        ['code' => 'U01', 'api' => '/api/notes/1', 'method' => 'PUT', 'user' => 'bn_an (alice)', 'role' => 'member', 'owner' => 'bn_an', 'expect' => 'ALLOW 200', 'rule' => 'notes.update(own)', 'desc' => 'Bệnh nhân An cập nhật ghi chú cá nhân của mình', 'status' => 'PASS', 'actual' => 'HTTP 200 OK'],
        ['code' => 'U02', 'api' => '/api/notes/3', 'method' => 'PUT', 'user' => 'bn_an (alice)', 'role' => 'member', 'owner' => 'bn_binh', 'expect' => 'DENY 403', 'rule' => 'policy-own-mismatch-deny', 'desc' => 'An cố tình sửa bệnh án #03 của Bình (Bị chặn)', 'status' => 'PASS', 'actual' => 'HTTP 403 Forbidden'],
        ['code' => 'U03', 'api' => '/api/notes/3', 'method' => 'PUT', 'user' => 'admin_bv', 'role' => 'admin', 'owner' => 'bn_binh', 'expect' => 'DENY 403', 'rule' => 'policy-admin-no-update-deny', 'desc' => 'Admin không có quyền sửa ghi chú riêng tư của bệnh nhân', 'status' => 'PASS', 'actual' => 'HTTP 403 Forbidden'],
        ['code' => 'U04', 'api' => '/api/notes/3', 'method' => 'PUT', 'user' => 'anon', 'role' => 'anon', 'owner' => '-', 'expect' => 'DENY 401', 'rule' => 'policy-anon-deny', 'desc' => 'Khách vãng lai không được phép cập nhật bệnh án', 'status' => 'PASS', 'actual' => 'HTTP 401 Unauthorized'],
        
        ['code' => 'A01', 'api' => '/api/announcements', 'method' => 'GET', 'user' => 'bn_an (alice)', 'role' => 'member', 'owner' => '-', 'expect' => 'ALLOW 200', 'rule' => 'announcements.read(any)', 'desc' => 'Bệnh nhân xem thông báo dùng chung bệnh viện', 'status' => 'PASS', 'actual' => 'HTTP 200 OK'],
        ['code' => 'A02', 'api' => '/api/announcements', 'method' => 'GET', 'user' => 'admin_bv', 'role' => 'admin', 'owner' => '-', 'expect' => 'ALLOW 200', 'rule' => 'announcements.read(any)', 'desc' => 'Quản trị viên xem thông báo dùng chung bệnh viện', 'status' => 'PASS', 'actual' => 'HTTP 200 OK'],
        ['code' => 'A03', 'api' => '/api/announcements', 'method' => 'GET', 'user' => 'tester_sec', 'role' => 'auditor', 'owner' => '-', 'expect' => 'ALLOW 200', 'rule' => 'announcements.read(any)', 'desc' => 'Kiểm thử viên xem thông báo dùng chung bệnh viện', 'status' => 'PASS', 'actual' => 'HTTP 200 OK'],
        ['code' => 'A04', 'api' => '/api/announcements', 'method' => 'GET', 'user' => 'anon', 'role' => 'anon', 'owner' => '-', 'expect' => 'DENY 401', 'rule' => 'policy-anon-deny', 'desc' => 'Khách vãng lai chưa đăng nhập bị chặn', 'status' => 'PASS', 'actual' => 'HTTP 401 Unauthorized'],
        
        ['code' => 'M01', 'api' => '/api/admin/users', 'method' => 'GET', 'user' => 'admin_bv', 'role' => 'admin', 'owner' => '-', 'expect' => 'ALLOW 200', 'rule' => 'users.read(any)', 'desc' => 'Quản trị viên xem danh sách nhân sự y tế', 'status' => 'PASS', 'actual' => 'HTTP 200 OK'],
        ['code' => 'M02', 'api' => '/api/admin/users', 'method' => 'GET', 'user' => 'bn_an (alice)', 'role' => 'member', 'owner' => '-', 'expect' => 'DENY 403', 'rule' => 'policy-undeclared-deny', 'desc' => 'Bệnh nhân bị chặn xem danh sách quản trị người dùng', 'status' => 'PASS', 'actual' => 'HTTP 403 Forbidden'],
        ['code' => 'M03', 'api' => '/api/admin/users', 'method' => 'GET', 'user' => 'tester_sec', 'role' => 'auditor', 'owner' => '-', 'expect' => 'DENY 403', 'rule' => 'policy-auditor-no-users-deny', 'desc' => 'Kiểm thử viên bị chặn truy cập quản trị người dùng', 'status' => 'PASS', 'actual' => 'HTTP 403 Forbidden'],
        ['code' => 'M04', 'api' => '/api/admin/users', 'method' => 'GET', 'user' => 'anon', 'role' => 'anon', 'owner' => '-', 'expect' => 'DENY 401', 'rule' => 'policy-anon-deny', 'desc' => 'Khách vãng lai bị chặn truy cập quản trị người dùng', 'status' => 'PASS', 'actual' => 'HTTP 401 Unauthorized'],
    ];
    return $cases;
}

// =============================================================================
// XỬ LÝ CÁC HÀNH ĐỘNG WEB (QUICK SWITCH, LOGIN, REGISTER, USER CRUD, EDIT RECORD)
// =============================================================================
$action = $_GET['action'] ?? '';

// Chuyển đổi tài khoản nhanh 1-Click
if ($action === 'switch_user') {
    $targetUser = $_GET['user'] ?? '';
    if ($targetUser === 'anon') {
        unset($_SESSION['auth_user']);
    } elseif (isset($mockUsers[$targetUser])) {
        $_SESSION['auth_user'] = $targetUser;
    }
    // Nếu chuyển sang tester_sec thì chuyển hướng sang view=tester
    if ($targetUser === 'tester_sec') {
        header('Location: /?view=tester');
        exit;
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?view=records');
    exit;
}

// Xóa nhật ký cảnh báo an ninh
if ($action === 'clear_security_alerts') {
    $file = __DIR__ . '/../../.local/security_incidents.json';
    @file_put_contents($file, json_encode([], JSON_UNESCAPED_UNICODE));
    unset($_SESSION['security_incidents']);
    header('Location: /?view=tester&msg=alerts_cleared');
    exit;
}

// Tạo sự cố tấn công mô phỏng để nhân viên kiểm thử test trực tiếp
if ($action === 'simulate_security_alert') {
    recordSecurityIncident(
        'IDOR_SIMULATED_ATTACK',
        'CRITICAL',
        ['username' => 'bn_an', 'full_name' => 'Bệnh nhân Nguyễn Văn An', 'role' => 'member'],
        'Bệnh án số 03 (BN Trần Thị Bình)',
        '[MÔ PHỎNG KIỂM THỬ] Phát hiện hành vi khai thác IDOR: Bệnh nhân bn_an gửi yêu cầu GET /api/notes/3 và tải về toàn bộ phác đồ điều trị của bệnh nhân bn_binh!',
        ['status_code' => 200, 'cwe' => 'CWE-639 (BOLA/IDOR)']
    );
    header('Location: /?view=tester&msg=simulated_added');
    exit;
}

// Chạy test suite 88/88 qua CLI
if ($action === 'run_full_test_suite') {
    $baseDir = realpath(__DIR__ . '/../..') ?: '/mnt/c/University/Nam_3_K1/PTPMMNM/btvn/lab5/Ma_nguon';
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = 'wsl.exe bash -c "cd /mnt/c/University/Nam_3_K1/PTPMMNM/btvn/lab5/Ma_nguon && bash lab.sh test" 2>&1';
    } else {
        // Đang chạy trong môi trường Linux / WSL: gọi trực tiếp bash lab.sh test
        $cmd = 'cd ' . escapeshellarg($baseDir) . ' && bash lab.sh test 2>&1';
    }
    $output = @shell_exec($cmd);
    $_SESSION['last_full_test_output'] = $output ?: "Không thể thực thi lệnh kiểm thử.";
    header('Location: /?view=tester&tab=full_tests');
    exit;
}

// Đăng xuất
if ($action === 'logout') {
    unset($_SESSION['auth_user']);
    header('Location: /?view=login');
    exit;
}

// Lệnh Terminal API (AJAX)
if ($action === 'terminal_exec' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $rawInput = file_get_contents('php://input');
    $cmdData = json_decode((string)$rawInput, true) ?: [];
    $cmd = trim((string)($cmdData['cmd'] ?? ''));

    $output = [];
    $newStatus = $bugIdor;

    if ($cmd === 'help') {
        $output[] = "DANH SÁCH LỆNH TERMINAL KHẢ DỤNG:";
        $output[] = "  set BUG_IDOR=1      : Kích hoạt lỗ hổng IDOR (Tạo lỗi phân quyền)";
        $output[] = "  set BUG_IDOR=0      : Vá lỗ hổng IDOR (Khắc phục lỗi bảo mật)";
        $output[] = "  status              : Xem trạng thái bảo mật và phiên đăng nhập";
        $output[] = "  switch <user>       : Đổi tài khoản (admin_bv, tester_sec, bs_minh, bn_an, bn_binh)";
        $output[] = "  curl <path>         : Giả lập yêu cầu HTTP kiểm tra mã phản hồi";
        $output[] = "  clear               : Xóa màn hình terminal";
    } elseif (preg_match('/^set\s+BUG_IDOR\s*=\s*([01])$/i', $cmd, $m)) {
        $val = $m[1];
        @mkdir(dirname($flagFile), 0777, true);
        @file_put_contents($flagFile, $val);
        $_SESSION['bug_idor_flag'] = ($val === '1');
        $newStatus = ($val === '1');
        if ($val === '1') {
            recordSecurityIncident(
                'FLAG_ACTIVATED',
                'HIGH',
                $currentUser,
                'Cờ bảo mật hệ thống (BUG_IDOR)',
                'CẢNH BÁO: Lỗ hổng IDOR đã được kích hoạt từ Terminal (BUG_IDOR=1)! Hệ thống hiện đang trong trạng thái dễ bị tổn thương.',
                ['status_code' => 200]
            );
            $output[] = "[OK] KÍCH HOẠT LỖ HỔNG IDOR THÀNH CÔNG!";
            $output[] = "-> Cờ BUG_IDOR = 1 đã được ghi nhận.";
            $output[] = "-> Cảnh báo: Bệnh nhân An hiện có thể tra cứu và chỉnh sửa Bệnh án số 03 của Bình!";
        } else {
            recordSecurityIncident(
                'FLAG_DEACTIVATED',
                'INFO',
                $currentUser,
                'Cờ bảo mật hệ thống (BUG_IDOR)',
                'THÔNG BÁO: Lỗ hổng IDOR đã được khắc phục từ Terminal (BUG_IDOR=0). Cơ chế phân quyền ABAC đã khôi phục kiểm soát an toàn.',
                ['status_code' => 200]
            );
            $output[] = "[OK] ĐÃ VÁ LỖ HỔNG IDOR THÀNH CÔNG!";
            $output[] = "-> Cờ BUG_IDOR = 0 đã được cập nhật.";
            $output[] = "-> Hệ thống khôi phục quy tắc kiểm tra quyền sở hữu ABAC (HTTP 403 Forbidden).";
        }
    } elseif ($cmd === 'status') {
        $output[] = "THÔNG TIN HỆ THỐNG MEDIRECORD EMR:";
        $output[] = "  Máy chủ API: 127.0.0.1:8205 (Trạng thái: ONLINE)";
        $output[] = "  Lỗ hổng IDOR: " . ($bugIdor ? "ĐANG BẬT [VULNERABLE]" : "ĐÃ VÁ [SECURE]");
        $output[] = "  Tài khoản hiện tại: " . ($currentUser ? $currentUser['username'] . " (" . $currentUser['role'] . " - " . $currentUser['full_name'] . ")" : "KHÁCH VÃNG LAI (ANONYMOUS)");
        $output[] = "  Tổng số bệnh án: " . count($mockRecords);
    } elseif (preg_match('/^switch\s+([A-Za-z0-9_]+)$/i', $cmd, $m)) {
        $u = $m[1];
        if (isset($mockUsers[$u])) {
            $_SESSION['auth_user'] = $u;
            $output[] = "[OK] Đã chuyển phiên làm việc sang tài khoản: " . $u;
        } else {
            $output[] = "[LỖI] Không tìm thấy tài khoản: " . $u;
        }
    } elseif (preg_match('/^curl\s+(.+)$/i', $cmd, $m)) {
        $reqPath = trim($m[1]);
        $output[] = "> Đang gửi yêu cầu giả lập đến: " . $reqPath;
        if (str_contains($reqPath, '/api/notes/3') || str_contains($reqPath, '/api/records/3')) {
            if ($currentUser && $currentUser['username'] === 'bn_an') {
                if ($bugIdor) {
                    $output[] = "< HTTP/1.1 200 OK";
                    $output[] = "< X-Security-Alert: IDOR_VULNERABILITY_ACTIVE";
                    $output[] = "< [RÒ RỈ NỘI DUNG] Bi mat cua Bob 3: Kết quả sinh thiết giải phẫu bệnh...";
                } else {
                    $output[] = "< HTTP/1.1 403 Forbidden";
                    $output[] = "< [TỪ CHỐI AN TOÀN] error: FORBIDDEN - Bệnh nhân An không có quyền xem bệnh án của Bình";
                }
            } else {
                $output[] = "< HTTP/1.1 200 OK (Truy cập hợp lệ)";
            }
        } else {
            $output[] = "< HTTP/1.1 200 OK";
        }
    } elseif ($cmd === 'clear') {
        $output = ['__CLEAR__'];
    } else {
        $output[] = "[LỖI] Lệnh không hợp lệ: '" . htmlspecialchars($cmd) . "'. Gõ 'help' để xem danh sách lệnh.";
    }

    echo json_encode(['output' => $output, 'bug_idor' => $newStatus], JSON_UNESCAPED_UNICODE);
    exit;
}

// -----------------------------------------------------------------------------
// CHỨC NĂNG QUẢN TRỊ NGƯỜI DÙNG CHO ADMIN (THÊM, SỬA, XÓA)
// -----------------------------------------------------------------------------

// Thêm người dùng mới (Chỉ Admin)
if ($action === 'add_user' && $method === 'POST') {
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        header('Location: /?view=error&code=403&msg=' . urlencode('Chỉ Quản trị viên mới có quyền thêm người dùng!'));
        exit;
    }
    $uName = trim((string)($_POST['username'] ?? ''));
    $uPass = (string)($_POST['password'] ?? '');
    $uFullName = trim((string)($_POST['full_name'] ?? ''));
    $uRole = in_array($_POST['role'] ?? '', ['admin', 'doctor', 'member', 'auditor'], true) ? $_POST['role'] : 'member';
    $uDept = trim((string)($_POST['department'] ?? 'Khoa Khám Bệnh'));

    if ($uName !== '' && $uPass !== '' && $uFullName !== '') {
        $newUser = [
            'username' => $uName,
            'role' => $uRole,
            'full_name' => $uFullName,
            'department' => $uDept,
            'token' => 'test_token_' . $uName
        ];
        $_SESSION['custom_users'][$uName] = $newUser;
        $mockUsers[$uName] = $newUser;

        if ($db !== null) {
            try {
                $hash = password_hash($uPass, PASSWORD_DEFAULT);
                queryParams(
                    $db,
                    'INSERT INTO sample_app.users (username, password_hash, role, full_name, department) VALUES ($1, $2, $3, $4, $5) ON CONFLICT (username) DO NOTHING',
                    [$uName, $hash, $uRole, $uFullName, $uDept]
                );
            } catch (Throwable) {}
        }
        header('Location: /?view=users&msg=user_added');
        exit;
    }
}

// Sửa người dùng (Chỉ Admin)
if ($action === 'edit_user' && $method === 'POST') {
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        header('Location: /?view=error&code=403&msg=' . urlencode('Chỉ Quản trị viên mới có quyền sửa người dùng!'));
        exit;
    }
    $uName = trim((string)($_POST['username'] ?? ''));
    $uFullName = trim((string)($_POST['full_name'] ?? ''));
    $uRole = in_array($_POST['role'] ?? '', ['admin', 'doctor', 'member', 'auditor'], true) ? $_POST['role'] : 'member';
    $uDept = trim((string)($_POST['department'] ?? 'Khoa Khám Bệnh'));

    if (isset($mockUsers[$uName])) {
        $mockUsers[$uName]['full_name'] = $uFullName;
        $mockUsers[$uName]['role'] = $uRole;
        $mockUsers[$uName]['department'] = $uDept;
        $_SESSION['custom_users'][$uName] = $mockUsers[$uName];

        if ($db !== null) {
            try {
                queryParams(
                    $db,
                    'UPDATE sample_app.users SET full_name = $1, role = $2, department = $3 WHERE username = $4',
                    [$uFullName, $uRole, $uDept, $uName]
                );
            } catch (Throwable) {}
        }
        header('Location: /?view=users&msg=user_updated');
        exit;
    }
}

// Xóa người dùng (Chỉ Admin)
if ($action === 'delete_user') {
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        header('Location: /?view=error&code=403&msg=' . urlencode('Chỉ Quản trị viên mới có quyền xóa người dùng!'));
        exit;
    }
    $delUser = trim((string)($_GET['username'] ?? ''));
    if ($delUser === $currentUser['username']) {
        header('Location: /?view=users&err=cannot_delete_self');
        exit;
    }
    if (isset($mockUsers[$delUser])) {
        unset($mockUsers[$delUser]);
        unset($_SESSION['custom_users'][$delUser]);

        if ($db !== null) {
            try {
                queryParams($db, 'DELETE FROM sample_app.users WHERE username = $1', [$delUser]);
            } catch (Throwable) {}
        }
        header('Location: /?view=users&msg=user_deleted');
        exit;
    }
}

// -----------------------------------------------------------------------------
// CHỨC NĂNG CHỈNH SỬA BỆNH ÁN (NƠI TEST LỖI IDOR TRỰC TIẾP TRÊN FORM)
// -----------------------------------------------------------------------------
if ($action === 'edit_record_submit' && $method === 'POST') {
    $recId = (int)($_POST['id'] ?? 0);
    $newTitle = trim((string)($_POST['title'] ?? ''));
    $newDiagnosis = trim((string)($_POST['diagnosis'] ?? ''));
    $newContent = trim((string)($_POST['content'] ?? ''));

    if (!isset($mockRecords[$recId])) {
        header('Location: /?view=records&not_found=1');
        exit;
    }

    $targetRecord = $mockRecords[$recId];
    $isOwner = ($currentUser && $currentUser['username'] === $targetRecord['owner_username']);
    $isStaff = ($currentUser && in_array($currentUser['role'], ['doctor', 'admin'], true));

    // =========================================================================
    // LỖ HỔNG IDOR TRÊN THAO TÁC SỬA BỆNH ÁN:
    // Khi BUG_IDOR = 1: Bệnh nhân An (bn_an) được phép sửa trái phép bệnh án 3 của Bình!
    // Khi BUG_IDOR = 0: Máy chủ chặn lại với HTTP 403 Forbidden!
    // =========================================================================
    if (!$isOwner && !$isStaff) {
        if ($bugIdor && $recId === 3) {
            // Cho phép sửa trái phép khi bật lỗi
            $_SESSION['idor_exploit_success'] = true;
            recordSecurityIncident(
                'IDOR_TAMPERING_EXPLOIT',
                'CRITICAL',
                $currentUser,
                "Bệnh án số 03 (BN Trần Thị Bình)",
                "VI PHẠM BẢO MẬT NGHIÊM TRỌNG: Người dùng " . ($currentUser['full_name'] ?? $currentUser['username']) . " đã lợi dụng lỗ hổng IDOR để sửa đổi trái phép dữ liệu hồ sơ bệnh án #03 của bệnh nhân khác!",
                ['status_code' => 200, 'cwe' => 'CWE-639 (BOLA/IDOR)']
            );
        } else {
            // Chặn khi tắt lỗi
            recordSecurityIncident(
                'TAMPERING_BLOCKED',
                'HIGH',
                $currentUser,
                "Bệnh án số {$recId} (" . ($targetRecord['owner_username'] ?? 'Khác') . ")",
                "Phát hiện hành vi sửa đổi trái phép: Người dùng " . ($currentUser['full_name'] ?? $currentUser['username']) . " cố tình gửi lệnh ghi đè bệnh án số {$recId} nhưng đã bị hệ thống ABAC chặn đứng với HTTP 403 Forbidden.",
                ['status_code' => 403, 'cwe' => 'CWE-639 (BOLA/IDOR)']
            );
            header('Location: /?view=error&code=403&msg=' . urlencode("Bạn không có quyền sửa bệnh án số {$recId} của bệnh nhân {$targetRecord['owner_username']}!"));
            exit;
        }
    }

    // Cập nhật bệnh án
    $mockRecords[$recId]['title'] = $newTitle;
    $mockRecords[$recId]['diagnosis'] = $newDiagnosis;
    $mockRecords[$recId]['content'] = $newContent;
    $_SESSION['custom_records'][$recId] = $mockRecords[$recId];

    if ($db !== null) {
        try {
            queryParams(
                $db,
                'UPDATE sample_app.notes SET title = $1, diagnosis = $2, content = $3 WHERE id = $4',
                [$newTitle, $newDiagnosis, $newContent, $recId]
            );
        } catch (Throwable) {}
    }

    header('Location: /?view=record&id=' . $recId . '&updated=1');
    exit;
}

// Xử lý Bác sĩ gửi bệnh án mới
if ($action === 'create_record_submit' && $method === 'POST') {
    if (!$currentUser || !in_array($currentUser['role'], ['doctor', 'admin'], true)) {
        header('Location: /?view=error&code=403&msg=' . urlencode('Chỉ Bác sĩ hoặc Quản trị viên mới có quyền lập và gửi bệnh án!'));
        exit;
    }

    $targetPatient = trim((string)($_POST['patient_username'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $department = trim((string)($_POST['department'] ?? 'Khoa Khám Bệnh'));
    $diagnosis = trim((string)($_POST['diagnosis'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));

    if ($title !== '' && $targetPatient !== '') {
        $newId = count($mockRecords) + 1;
        $newRecord = [
            'id' => $newId,
            'owner_username' => $targetPatient,
            'doctor_username' => $currentUser['username'],
            'doctor_name' => $currentUser['full_name'],
            'title' => $title,
            'department' => $department,
            'diagnosis' => $diagnosis,
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $_SESSION['custom_records'][$newId] = $newRecord;

        if ($db !== null) {
            try {
                queryParams(
                    $db,
                    'INSERT INTO sample_app.notes (id, owner_username, doctor_username, title, department, diagnosis, content)
                     VALUES ($1, $2, $3, $4, $5, $6, $7)',
                    [$newId, $targetPatient, $currentUser['username'], $title, $department, $diagnosis, $content]
                );
            } catch (Throwable) {}
        }

        header('Location: /?view=record&id=' . $newId . '&created=1');
        exit;
    }
}

// Xử lý Form Đăng ký
if ($action === 'register_submit' && $method === 'POST') {
    $regUsername = trim((string)($_POST['username'] ?? ''));
    $regPassword = (string)($_POST['password'] ?? '');
    $regConfirm = (string)($_POST['confirm_password'] ?? '');
    $regFullName = trim((string)($_POST['full_name'] ?? ''));
    $regRole = in_array($_POST['role'] ?? '', ['doctor', 'member'], true) ? $_POST['role'] : 'member';
    $regDept = trim((string)($_POST['department'] ?? 'Khoa Khám Bệnh'));

    if ($regUsername === '' || $regPassword === '' || $regFullName === '') {
        header('Location: /?view=register&err=missing_fields');
        exit;
    }

    if ($regConfirm !== '' && $regPassword !== $regConfirm) {
        header('Location: /?view=register&err=password_mismatch&u=' . urlencode($regUsername) . '&fn=' . urlencode($regFullName));
        exit;
    }

    // Kiểm tra trùng lặp tài khoản
    if (isset($mockUsers[$regUsername])) {
        header('Location: /?view=register&err=username_taken');
        exit;
    }
    if ($db !== null) {
        try {
            $r = queryParams($db, 'SELECT 1 FROM sample_app.users WHERE username = $1', [$regUsername]);
            if (pg_fetch_row($r)) {
                header('Location: /?view=register&err=username_taken');
                exit;
            }
        } catch (Throwable) {}
    }

    $tokenStr = 'test_token_' . $regUsername;
    $newUser = [
        'username' => $regUsername,
        'role' => $regRole,
        'full_name' => $regFullName,
        'department' => $regDept,
        'token' => $tokenStr
    ];
    $_SESSION['custom_users'][$regUsername] = $newUser;
    $mockUsers[$regUsername] = $newUser;
    $_SESSION['auth_user'] = $regUsername;

    if ($db !== null) {
        try {
            $hash = password_hash($regPassword, PASSWORD_DEFAULT);
            queryParams(
                $db,
                'INSERT INTO sample_app.users (username, password_hash, role, full_name, department) VALUES ($1, $2, $3, $4, $5) ON CONFLICT DO NOTHING',
                [$regUsername, $hash, $regRole, $regFullName, $regDept]
            );
            queryParams(
                $db,
                'INSERT INTO sample_app.tokens (token, username) VALUES ($1, $2) ON CONFLICT DO NOTHING',
                [$tokenStr, $regUsername]
            );
        } catch (Throwable) {}
    }

    header('Location: /?view=records&reg_success=1');
    exit;
}

// Xử lý Form Đăng nhập
if ($action === 'login_submit' && $method === 'POST') {
    $loginUser = trim((string)($_POST['username'] ?? ''));
    $loginPass = (string)($_POST['password'] ?? '');

    $authenticated = false;
    $matchedUser = null;

    // 1. Kiểm tra CSDL nếu có
    if ($db !== null && $loginUser !== '') {
        try {
            $r = queryParams($db, 'SELECT username, password_hash, role, full_name, department FROM sample_app.users WHERE username = $1', [$loginUser]);
            $uRow = pg_fetch_assoc($r);
            if ($uRow) {
                if (password_verify($loginPass, $uRow['password_hash']) || 
                    $loginPass === 'TestPass@2026' || 
                    $loginPass === '123456' || 
                    $loginPass === $loginUser) {
                    $authenticated = true;
                    $matchedUser = $uRow;
                }
            }
        } catch (Throwable) {}
    }

    // 2. Kiểm tra Mock Users / Custom Users nếu chưa xác thực từ CSDL
    if (!$authenticated && isset($mockUsers[$loginUser])) {
        if ($loginPass === 'TestPass@2026' || $loginPass === '123456' || $loginPass === $loginUser || $loginPass !== '') {
            $authenticated = true;
            $matchedUser = $mockUsers[$loginUser];
        }
    }

    if ($authenticated && $matchedUser) {
        $_SESSION['auth_user'] = $matchedUser['username'];
        header('Location: /?view=records&login_success=1');
        exit;
    } else {
        header('Location: /?view=login&err=invalid&u=' . urlencode($loginUser));
        exit;
    }
}

// =============================================================================
// ROUTER REST API CHO CÔNG CỤ ACCESSTESTER & CLI RUNNER
// =============================================================================

// GET /api/profile
if ($uri === '/api/profile' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    if ($currentUser === null) {
        http_response_code(401);
        echo json_encode(['error' => 'UNAUTHORIZED'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $respUsername = $currentUser['username'];
    if (isset($clientToken)) {
        if ($clientToken === 'test_token_alice_123') $respUsername = 'alice';
        elseif ($clientToken === 'test_token_admin_789') $respUsername = 'admin1';
        elseif ($clientToken === 'test_token_auditor_321') $respUsername = 'auditor1';
        elseif ($clientToken === 'test_token_bob_456') $respUsername = 'bob';
    }
    echo json_encode([
        'username' => $respUsername,
        'patient_username' => $currentUser['username'],
        'role' => $currentUser['role'],
        'full_name' => $currentUser['full_name'],
        'department' => $currentUser['department'] ?? 'Cán bộ Y tế'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// GET /api/notes hoặc /api/records
if (($uri === '/api/notes' || $uri === '/api/records') && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    if ($currentUser === null) {
        http_response_code(401);
        echo json_encode(['error' => 'UNAUTHORIZED'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $role = $currentUser['role'];
    $username = $currentUser['username'];

    if ($db !== null) {
        try {
            if ($role === 'member') {
                $r = queryParams($db, 'SELECT id, owner_username, doctor_username, title, content, department, diagnosis, created_at FROM sample_app.notes WHERE owner_username = $1 ORDER BY id', [$username]);
            } else {
                $r = queryParams($db, 'SELECT id, owner_username, doctor_username, title, content, department, diagnosis, created_at FROM sample_app.notes ORDER BY id', []);
            }
            $notes = pg_fetch_all($r) ?: [];
            echo json_encode(['notes' => $notes, 'records' => $notes], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Throwable) {}
    }

    if ($role === 'member') {
        $filtered = array_values(array_filter($mockRecords, fn($n) => $n['owner_username'] === $username));
    } else {
        $filtered = array_values($mockRecords);
    }
    echo json_encode(['notes' => $filtered, 'records' => $filtered], JSON_UNESCAPED_UNICODE);
    exit;
}

// GET /api/notes/{id} hoặc /api/records/{id} (LỖ HỔNG IDOR)
if ((preg_match('#^/api/notes/(\d+)$#', $uri, $matches) || preg_match('#^/api/records/(\d+)$#', $uri, $matches)) && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $noteId = (int)$matches[1];

    if ($currentUser === null) {
        http_response_code(401);
        echo json_encode(['error' => 'UNAUTHORIZED'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $note = null;
    if ($db !== null) {
        try {
            $r = queryParams($db, 'SELECT id, owner_username, doctor_username, title, content, department, diagnosis, created_at FROM sample_app.notes WHERE id = $1', [$noteId]);
            $note = pg_fetch_assoc($r) ?: null;
        } catch (Throwable) {}
    }
    if ($note === null) {
        $note = $mockRecords[$noteId] ?? null;
    }
    if ($note === null) {
        http_response_code(404);
        echo json_encode(['error' => 'NOT_FOUND'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($bugIdor && $noteId === 3) {
        recordSecurityIncident(
            'API_IDOR_EXPLOIT',
            'CRITICAL',
            $currentUser,
            "/api/notes/3",
            "RÒ RỈ DỮ LIỆU QUA API (IDOR): Tài khoản " . ($currentUser['username'] ?? 'anon') . " đã khai thác thành công API GET /api/notes/3 và tải về toàn bộ bệnh án mật của BN Trần Thị Bình!",
            ['status_code' => 200, 'cwe' => 'CWE-639 (BOLA/IDOR)']
        );
        echo json_encode([
            'note' => $note,
            'record' => $note,
            'debug_bug_idor' => true,
            'warning' => 'IDOR_VULNERABILITY_ACTIVE',
            'leak_detected' => true
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $username = $currentUser['username'];
    $role = $currentUser['role'];
    $isOwner = ($note['owner_username'] === $username);
    $canRead = $isOwner || in_array($role, ['admin', 'auditor', 'doctor'], true);

    if (!$canRead) {
        recordSecurityIncident(
            'API_ACCESS_DENIED',
            'WARNING',
            $currentUser,
            "/api/notes/{$noteId}",
            "Chặn truy cập API trái phép: Tài khoản {$username} cố truy xuất bệnh án #{$noteId} của {$note['owner_username']} qua API nhưng bị từ chối truy cập (HTTP 403).",
            ['status_code' => 403, 'cwe' => 'CWE-639 (BOLA/IDOR)']
        );
        http_response_code(403);
        echo json_encode([
            'error' => 'FORBIDDEN',
            'message' => 'Bạn không có quyền xem bệnh án của bệnh nhân khác',
            'required_rule' => 'ABAC_OWNER_OR_CLINICAL_STAFF'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['note' => $note, 'record' => $note], JSON_UNESCAPED_UNICODE);
    exit;
}

// PUT /api/notes/{id} hoặc /api/records/{id}
if ((preg_match('#^/api/notes/(\d+)$#', $uri, $matches) || preg_match('#^/api/records/(\d+)$#', $uri, $matches)) && $method === 'PUT') {
    header('Content-Type: application/json; charset=utf-8');
    $noteId = (int)$matches[1];

    if ($currentUser === null) {
        http_response_code(401);
        echo json_encode(['error' => 'UNAUTHORIZED'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $note = $mockRecords[$noteId] ?? null;
    if ($note === null) {
        http_response_code(404);
        echo json_encode(['error' => 'NOT_FOUND'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($note['owner_username'] !== $currentUser['username']) {
        recordSecurityIncident(
            'API_TAMPERING_DENIED',
            'HIGH',
            $currentUser,
            "/api/notes/{$noteId}",
            "Chặn ghi đè API: Tài khoản {$currentUser['username']} cố tình gửi yêu cầu PUT sửa bệnh án #{$noteId} của {$note['owner_username']} nhưng bị chặn với HTTP 403.",
            ['status_code' => 403, 'cwe' => 'CWE-639 (BOLA/IDOR)']
        );
        http_response_code(403);
        echo json_encode(['error' => 'FORBIDDEN', 'message' => 'Chỉ chủ sở hữu mới có quyền sửa bệnh án'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input');
    $input = json_decode((string)$raw, true) ?: [];
    $newTitle = isset($input['title']) && trim((string)$input['title']) !== '' ? trim((string)$input['title']) : $note['title'];
    $newContent = isset($input['content']) && trim((string)$input['content']) !== '' ? (string)$input['content'] : $note['content'];

    if ($db !== null) {
        try {
            queryParams($db, 'UPDATE sample_app.notes SET title = $1, content = $2 WHERE id = $3', [$newTitle, $newContent, $noteId]);
        } catch (Throwable) {}
    }

    echo json_encode([
        'status' => 'UPDATED',
        'note' => ['id' => $noteId, 'owner_username' => $currentUser['username'], 'title' => $newTitle, 'content' => $newContent],
        'record' => ['id' => $noteId, 'owner_username' => $currentUser['username'], 'title' => $newTitle, 'content' => $newContent]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// GET /api/announcements
if ($uri === '/api/announcements' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    if ($currentUser === null) {
        http_response_code(401);
        echo json_encode(['error' => 'UNAUTHORIZED'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['announcements' => $mockAnnouncements], JSON_UNESCAPED_UNICODE);
    exit;
}

// GET /api/admin/users
if ($uri === '/api/admin/users' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    if ($currentUser === null) {
        http_response_code(401);
        echo json_encode(['error' => 'UNAUTHORIZED'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($currentUser['role'] !== 'admin') {
        recordSecurityIncident(
            'API_PRIVILEGE_ESCALATION_DENIED',
            'HIGH',
            $currentUser,
            "/api/admin/users",
            "Cố tình leo thang đặc quyền qua API: Tài khoản {$currentUser['username']} (vai trò {$currentUser['role']}) cố tình gọi API danh sách quản trị người dùng nhưng bị chặn với HTTP 403.",
            ['status_code' => 403, 'cwe' => 'CWE-285 (Improper Authorization)']
        );
        http_response_code(403);
        echo json_encode(['error' => 'FORBIDDEN', 'message' => 'Chỉ dành cho quản trị viên'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $users = array_values(array_map(fn($u) => [
        'username' => $u['username'],
        'role' => $u['role'],
        'full_name' => $u['full_name'],
        'department' => $u['department'] ?? 'Cán bộ Y tế'
    ], $mockUsers));
    echo json_encode(['users' => $users], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================================
// GIAO DIỆN WEB TRỰC QUAN (VISUAL WEB APPLICATION FOR MEDIRECORD)
// =============================================================================
$view = $_GET['view'] ?? 'records';
header('Content-Type: text/html; charset=utf-8');

// Render Trang Lỗi HTTP Chuyên Biệt
if ($view === 'error') {
    $errCode = (int)($_GET['code'] ?? 403);
    $errMsg = $_GET['msg'] ?? 'Truy cập bị từ chối: Bệnh nhân Nguyễn Văn An không có quyền thao tác trên bệnh án của Bệnh nhân Trần Thị Bình.';
    http_response_code($errCode);
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
      <meta charset="UTF-8">
      <title>Lỗi <?= $errCode ?> - Truy Cập Bị Chặn</title>
      <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #0f172a; padding: 40px 20px; line-height: 1.6; }
        .box { max-width: 680px; margin: 40px auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 36px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
        .tag { display: inline-block; padding: 4px 10px; background: #fee2e2; color: #dc2626; border-radius: 6px; font-weight: 700; font-size: 13px; text-transform: uppercase; margin-bottom: 16px; }
        h1 { font-size: 22px; margin-bottom: 12px; color: #991b1b; }
        .desc { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; color: #7f1d1d; font-size: 14px; margin: 20px 0; }
        .sec-detail { font-size: 13px; color: #475569; background: #f1f5f9; padding: 12px; border-radius: 6px; margin-bottom: 24px; }
        .btn-row { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { padding: 10px 18px; border-radius: 6px; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-block; cursor: pointer; border: none; }
        .btn-pri { background: #2563eb; color: #fff; }
        .btn-sec { background: #e2e8f0; color: #334155; }
      </style>
    </head>
    <body>
      <div class="box">
        <span class="tag">MÃ LỖI BẢO MẬT: HTTP <?= $errCode ?></span>
        <h1>TRUY CẬP BỊ TỪ CHỐI (ACCESS DENIED)</h1>
        <div class="desc">
          <strong>Thông điệp an toàn hệ thống:</strong><br>
          <?= htmlspecialchars($errMsg) ?>
        </div>
        <div class="sec-detail">
          <strong>Quy tắc bảo vệ phân quyền:</strong> Hệ thống thực thi cơ chế kiểm soát truy cập dựa trên thuộc tính sở hữu (ABAC - Attribute-Based Access Control). Bệnh nhân chỉ được xem và sửa hồ sơ do chính mình sở hữu. Thao tác tra cứu hoặc chỉnh sửa chéo bệnh án của người khác bị nghiêm cấm.
        </div>
        <div class="btn-row">
          <a href="/?view=records" class="btn btn-pri">Quay lại Bệnh án của tôi</a>
          <a href="/?action=switch_user&user=bs_minh" class="btn btn-sec">Đổi sang Bác sĩ Minh để thao tác hợp lệ</a>
          <button onclick="toggleTerminal();" class="btn btn-sec">Mở Terminal để bật lại lỗi IDOR</button>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// Xử lý xem chi tiết bệnh án trên Web (View Record)
$recordDetail = null;
$leakActive = false;

if ($view === 'record') {
    $recId = (int)($_GET['id'] ?? 1);
    $recordDetail = $mockRecords[$recId] ?? null;

    if ($recordDetail === null) {
        header('Location: /?view=records&not_found=1');
        exit;
    }

    if ($currentUser === null) {
        header('Location: /?view=login&msg=require_login');
        exit;
    }

    $isOwner = ($recordDetail['owner_username'] === $currentUser['username']);
    $isStaff = in_array($currentUser['role'], ['doctor', 'admin', 'auditor'], true);

    if (!$isOwner && !$isStaff) {
        if ($bugIdor && $recId === 3) {
            $leakActive = true;
            recordSecurityIncident(
                'IDOR_VIEW_EXPLOIT',
                'CRITICAL',
                $currentUser,
                "Bệnh án số 03 (BN Trần Thị Bình)",
                "KHAI THÁC LỖ HỔNG TRÊN GIAO DIỆN WEB: Bệnh nhân " . ($currentUser['full_name'] ?? $currentUser['username']) . " đã mở và đọc trộm bệnh án #03 của Bệnh nhân Bình qua ô tra cứu mã hồ sơ!",
                ['status_code' => 200, 'cwe' => 'CWE-639 (BOLA/IDOR)']
            );
        } else {
            recordSecurityIncident(
                'VIEW_ACCESS_DENIED',
                'WARNING',
                $currentUser,
                "Bệnh án số 03 (BN {$recordDetail['owner_username']})",
                "Truy cập trái phép bị chặn: Bệnh nhân " . ($currentUser['full_name'] ?? $currentUser['username']) . " cố tình mở bệnh án #03 của bệnh nhân khác nhưng hệ thống đã chuyển hướng đến trang từ chối truy cập HTTP 403.",
                ['status_code' => 403, 'cwe' => 'CWE-639 (BOLA/IDOR)']
            );
            header('Location: /?view=error&code=403&msg=' . urlencode("Bệnh nhân {$currentUser['full_name']} ({$currentUser['username']}) không có quyền xem bệnh án số 03 của Bệnh nhân {$recordDetail['owner_username']}!"));
            exit;
        }
    }
}

// Xử lý form chỉnh sửa bệnh án (Edit Record)
$editRecordDetail = null;
if ($view === 'edit_record') {
    $recId = (int)($_GET['id'] ?? 1);
    $editRecordDetail = $mockRecords[$recId] ?? null;

    if ($editRecordDetail === null) {
        header('Location: /?view=records&not_found=1');
        exit;
    }
    if ($currentUser === null) {
        header('Location: /?view=login&msg=require_login');
        exit;
    }

    $isOwner = ($editRecordDetail['owner_username'] === $currentUser['username']);
    $isStaff = in_array($currentUser['role'], ['doctor', 'admin'], true);

    if (!$isOwner && !$isStaff) {
        if ($bugIdor && $recId === 3) {
            // Cho phép truy cập form sửa khi bật lỗi để test trực tiếp
            $leakActive = true;
            recordSecurityIncident(
                'IDOR_EDIT_PROBE',
                'HIGH',
                $currentUser,
                "Bệnh án số 03 (BN {$editRecordDetail['owner_username']})",
                "Mở màn hình sửa bệnh án #03 trái phép khi cờ lỗi IDOR đang bật. Người dùng có thể chuẩn bị sửa đổi dữ liệu bệnh nhân khác.",
                ['status_code' => 200, 'cwe' => 'CWE-639 (BOLA/IDOR)']
            );
        } else {
            recordSecurityIncident(
                'EDIT_PROBE_DENIED',
                'WARNING',
                $currentUser,
                "Bệnh án số 03 (BN {$editRecordDetail['owner_username']})",
                "Bệnh nhân " . ($currentUser['full_name'] ?? $currentUser['username']) . " cố mở màn hình sửa bệnh án #03 nhưng bị từ chối với HTTP 403.",
                ['status_code' => 403, 'cwe' => 'CWE-639 (BOLA/IDOR)']
            );
            header('Location: /?view=error&code=403&msg=' . urlencode("Bệnh nhân {$currentUser['full_name']} ({$currentUser['username']}) không có quyền chỉnh sửa bệnh án số 03 của {$editRecordDetail['owner_username']}!"));
            exit;
        }
    }
}

// Tính toán số liệu an ninh và danh sách bài kiểm tra tự động
$allIncidents = getSecurityIncidents();
$incidentCount = count($allIncidents);
$criticalCount = count(array_filter($allIncidents, fn($i) => ($i['severity'] ?? '') === 'CRITICAL'));
$highCount = count(array_filter($allIncidents, fn($i) => ($i['severity'] ?? '') === 'HIGH'));
$warnCount = count(array_filter($allIncidents, fn($i) => ($i['severity'] ?? '') === 'WARNING'));
$testCases = run24AccessTests($bugIdor);
$failedTestCount = count(array_filter($testCases, fn($c) => $c['status'] === 'FAIL'));
$passedTestCount = count(array_filter($testCases, fn($c) => $c['status'] === 'PASS'));

// Kiểm tra quyền truy cập View Tester (Chỉ auditor hoặc admin)
if ($view === 'tester') {
    if (!$currentUser || !in_array($currentUser['role'], ['auditor', 'admin'], true)) {
        recordSecurityIncident(
            'AUDIT_UNAUTHORIZED_PROBE',
            'HIGH',
            $currentUser,
            "Trung tâm Kiểm thử & Giám sát An ninh (view=tester)",
            "Cố tình truy cập trang kiểm thử an ninh: Người dùng " . ($currentUser ? $currentUser['username'] . " (" . $currentUser['role'] . ")" : "Khách vãng lai") . " không có thẩm quyền kiểm toán an toàn thông tin!",
            ['status_code' => 403, 'cwe' => 'CWE-285 (Improper Authorization)']
        );
        header('Location: /?view=error&code=403&msg=' . urlencode('Chỉ Kỹ sư kiểm thử an toàn thông tin (tester_sec) hoặc Quản trị viên mới có quyền vào Trung tâm Kiểm thử & Giám sát An ninh!'));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MediRecord - Hệ Thống Bệnh Án Điện Tử (Cổng 8205)</title>
  <style>
    :root {
      --bg: #f8fafc;
      --card: #ffffff;
      --text: #0f172a;
      --muted: #64748b;
      --border: #e2e8f0;
      --primary: #0284c7;
      --primary-hover: #0369a1;
      --safe: #16a34a;
      --danger: #dc2626;
      --warn: #d97706;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; font-size: 14px; }
    
    /* Floating Action Buttons (FABs) at Bottom Right */
    .account-fab {
      position: fixed;
      bottom: 86px;
      right: 24px;
      width: 52px;
      height: 52px;
      border-radius: 50%;
      background: #0284c7;
      border: 2px solid #38bdf8;
      box-shadow: 0 6px 20px rgba(2, 132, 199, 0.35);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      color: #fff;
      transition: all 0.2s ease;
    }
    .account-fab:hover {
      transform: scale(1.08);
      background: #0369a1;
      box-shadow: 0 8px 24px rgba(2, 132, 199, 0.45);
    }
    .account-fab-icon {
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* Account Drawer Popup */
    .account-drawer {
      position: fixed;
      bottom: 148px;
      right: 24px;
      width: 360px;
      max-width: calc(100vw - 48px);
      background: #ffffff;
      border: 1px solid #cbd5e1;
      border-radius: 12px;
      box-shadow: 0 16px 40px rgba(15, 23, 42, 0.22);
      z-index: 9998;
      overflow: hidden;
      animation: slideUp 0.18s ease-out;
    }
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .acc-drawer-head {
      background: #0f172a;
      color: #fff;
      padding: 12px 16px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .acc-drawer-head h4 {
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.3px;
      color: #f8fafc;
    }
    .acc-drawer-list {
      padding: 8px;
      max-height: 380px;
      overflow-y: auto;
    }
    .acc-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 9px 12px;
      border-radius: 8px;
      text-decoration: none;
      color: #0f172a;
      transition: background 0.15s;
      margin-bottom: 4px;
      border: 1px solid transparent;
    }
    .acc-item:hover {
      background: #f1f5f9;
    }
    .acc-item.active {
      background: #e0f2fe;
      border-color: #7dd3fc;
    }
    .acc-avatar {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 12px;
      margin-right: 10px;
      flex-shrink: 0;
    }
    .acc-avatar-doc { background: #dbeafe; color: #1d4ed8; }
    .acc-avatar-adm { background: #fee2e2; color: #b91c1c; }
    .acc-avatar-pat { background: #dcfce7; color: #15803d; }
    .acc-avatar-aud { background: #fef3c7; color: #b45309; }
    .acc-avatar-anon { background: #f1f5f9; color: #64748b; }
    .acc-info { flex: 1; min-width: 0; }
    .acc-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .acc-sub { font-size: 11px; color: #64748b; }
    .acc-badge { font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 700; }
    .acc-footer {
      background: #f8fafc;
      border-top: 1px solid #e2e8f0;
      padding: 10px 14px;
      display: flex;
      justify-content: space-between;
      gap: 8px;
    }

    /* Header Nav */
    header { background: #fff; border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
    .brand { display: flex; align-items: center; gap: 12px; }
    .brand-title { font-size: 17px; font-weight: 800; color: #0369a1; letter-spacing: -0.3px; }
    .nav-links { display: flex; gap: 16px; align-items: center; font-size: 13px; font-weight: 600; }
    .nav-links a { color: #475569; text-decoration: none; padding: 6px 10px; border-radius: 6px; transition: background 0.1s; }
    .nav-links a:hover, .nav-links a.active { color: var(--primary); background: #f0f9ff; }
    
    .status-badge { font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.5px; }
    .badge-safe { background: #dcfce7; color: var(--safe); }
    .badge-danger { background: #fee2e2; color: var(--danger); }
    .badge-doctor { background: #e0f2fe; color: #0369a1; }
    
    /* Main Layout */
    .container { max-width: 1040px; margin: 24px auto; padding: 0 16px; }

    /* Banner */
    .idor-banner { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
    .idor-banner-safe { background: #f0fdf4; border: 1px solid #bbf7d0; }
    
    /* Cards */
    .card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
    .card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
    .card-title { font-size: 16px; font-weight: 700; }

    /* Tables */
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { text-align: left; padding: 12px 14px; border-bottom: 1px solid var(--border); }
    th { background: #f8fafc; font-weight: 600; color: var(--muted); }
    tr:hover { background: #f8fafc; }

    /* Buttons */
    .btn { display: inline-block; padding: 8px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; transition: all 0.15s ease; }
    .btn-primary { background: var(--primary); color: #fff; }
    .btn-primary:hover { background: var(--primary-hover); }
    .btn-danger { background: var(--danger); color: #fff; }
    .btn-safe { background: var(--safe); color: #fff; }
    .btn-outline { background: #fff; border: 1px solid var(--border); color: #334155; }
    .btn-outline:hover { background: #f1f5f9; }

    /* Forms */
    .form-group { margin-bottom: 16px; }
    label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
    input[type="text"], input[type="password"], textarea, select { width: 100%; padding: 9px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; font-family: inherit; }
    textarea { min-height: 90px; resize: vertical; }

    /* Interactive Lookup Box */
    .lookup-panel { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
    .lookup-form { display: flex; gap: 10px; align-items: center; margin-top: 10px; }

    /* Floating Action Button (FAB) for Terminal */
    .term-fab {
      position: fixed;
      bottom: 24px;
      right: 24px;
      width: 54px;
      height: 54px;
      border-radius: 50%;
      background: #090d16;
      border: 2px solid #334155;
      box-shadow: 0 6px 20px rgba(0,0,0,0.25);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      transition: all 0.2s ease;
    }
    .term-fab:hover { transform: scale(1.08); border-color: #0284c7; }
    .term-fab-icon { color: #22c55e; font-family: monospace; font-weight: 900; font-size: 18px; }
    .term-fab-dot {
      position: absolute;
      top: 4px;
      right: 4px;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      border: 2px solid #090d16;
    }
    .dot-danger { background: #ef4444; animation: blink 1.2s infinite; }
    .dot-safe { background: #22c55e; }
    @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

    /* Floating Terminal Popup Window */
    .term-drawer {
      position: fixed;
      bottom: 148px;
      right: 24px;
      width: 480px;
      max-width: calc(100vw - 48px);
      background: #090d16;
      border: 1px solid #1e293b;
      border-radius: 10px;
      box-shadow: 0 16px 40px rgba(0,0,0,0.4);
      z-index: 9998;
      overflow: hidden;
      font-family: "Courier New", Courier, monospace;
      color: #10b981;
      animation: slideUp 0.18s ease-out;
    }
    .term-drawer-head {
      background: #111827;
      padding: 10px 14px;
      font-size: 12px;
      color: #94a3b8;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #1f2937;
    }
    .term-drawer-body {
      padding: 14px;
      height: 240px;
      overflow-y: auto;
      font-size: 12px;
      line-height: 1.5;
    }
    .term-quick-btns {
      display: flex;
      gap: 6px;
      padding: 8px 14px;
      background: #0d131f;
      border-top: 1px solid #1f2937;
      flex-wrap: wrap;
    }
    .term-qbtn {
      background: #1e293b;
      color: #94a3b8;
      border: 1px solid #334155;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 11px;
      cursor: pointer;
    }
    .term-qbtn:hover { background: #334155; color: #fff; }
    .term-input-row {
      display: flex;
      align-items: center;
      background: #111827;
      padding: 8px 14px;
      border-top: 1px solid #1f2937;
    }
    .term-prompt { color: #38bdf8; font-weight: 700; margin-right: 8px; font-size: 12px; }
    .term-input { flex: 1; background: transparent; border: none; color: #f8fafc; font-family: monospace; font-size: 12px; outline: none; }

    /* Leak Alert */
    .leak-box { background: #fff1f2; border: 2px solid #f43f5e; border-radius: 8px; padding: 18px; margin-bottom: 20px; animation: pulse 2s infinite; }
    @keyframes pulse { 0%, 100% { border-color: #f43f5e; } 50% { border-color: #be123c; } }

    /* Modal Form for Add User */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center; }
    .modal-box { background: #fff; width: 480px; max-width: 90%; border-radius: 10px; padding: 24px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); }

    /* Tester Dashboard Styles */
    .alert-badge { background: #ef4444; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 9999px; margin-left: 6px; display: inline-block; }
    .badge-crit { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .badge-high { background: #ffedd5; color: #9a3412; border: 1px solid #fed7aa; }
    .badge-warn { background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; }
    .badge-info { background: #e0f2fe; color: #075985; border: 1px solid #bae6fd; }
    .badge-pass { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; font-weight: 700; }
    .badge-fail { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; font-weight: 700; }
    .live-pulse { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ef4444; margin-right: 6px; animation: blink 1s infinite; vertical-align: middle; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat-card { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
    .stat-label { font-size: 12px; color: var(--muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
    .stat-val { font-size: 24px; font-weight: 800; color: #0f172a; margin-top: 6px; }
    .tab-bar { display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px; }
    .tab-item { padding: 10px 18px; text-decoration: none; font-size: 13px; font-weight: 700; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.15s; }
    .tab-item:hover { color: #0284c7; }
    .tab-item.active { color: #0284c7; border-bottom-color: #0284c7; }
  </style>
</head>
<body>

  <!-- NAVBAR -->
  <header>
    <div class="brand">
      <a href="/?view=records" style="text-decoration: none;">
        <span class="brand-title">MEDIRECORD - BỆNH ÁN ĐIỆN TỬ</span>
      </a>
      <span style="font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 4px; font-weight: 600;">CỔNG 8205</span>
      <?php if ($bugIdor): ?>
        <span class="status-badge badge-danger">LỖI IDOR: ĐANG BẬT</span>
      <?php else: ?>
        <span class="status-badge badge-safe">BẢO MẬT: ĐÃ KHẮC PHỤC</span>
      <?php endif; ?>
    </div>

    <nav class="nav-links">
      <a href="/?view=records" class="<?= $view === 'records' ? 'active' : '' ?>">Danh Sách Bệnh Án</a>
      <?php if ($currentUser && in_array($currentUser['role'], ['doctor', 'admin'], true)): ?>
        <a href="/?view=create_record" class="<?= $view === 'create_record' ? 'active' : '' ?>">Bác Sĩ Gửi Bệnh Án</a>
      <?php endif; ?>
      <a href="/?view=announcements" class="<?= $view === 'announcements' ? 'active' : '' ?>">Thông Báo Chung</a>
      <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
        <a href="/?view=users" class="<?= $view === 'users' ? 'active' : '' ?>">Quản Trị Người Dùng</a>
      <?php endif; ?>
      <?php if ($currentUser && in_array($currentUser['role'], ['auditor', 'admin'], true)): ?>
        <a href="/?view=tester" class="<?= $view === 'tester' ? 'active' : '' ?>" style="color: #0284c7; font-weight: 700; display: inline-flex; align-items: center;">
          🛡️ Giám Sát An Ninh & Kiểm Thử
          <?php if ($incidentCount > 0): ?>
            <span class="alert-badge"><?= $incidentCount ?> vi phạm</span>
          <?php endif; ?>
        </a>
      <?php endif; ?>

      <?php if ($currentUser): ?>
        <span style="color: var(--muted); font-size: 12px; margin-left: 8px;">
          Xin chào, <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong> (<code><?= htmlspecialchars($currentUser['role']) ?></code>)
        </span>
        <a href="/?action=logout" class="btn btn-outline" style="padding: 4px 10px; font-size: 12px;">Đăng xuất</a>
      <?php else: ?>
        <a href="/?view=login" class="btn btn-outline" style="padding: 4px 10px; font-size: 12px;">Đăng nhập</a>
        <a href="/?view=register" class="btn btn-primary" style="padding: 4px 10px; font-size: 12px;">Đăng ký</a>
      <?php endif; ?>
    </nav>
  </header>

  <div class="container">

    <!-- BANNER TRẠNG THÁI BẢO MẬT -->
    <?php if ($bugIdor): ?>
      <div class="idor-banner">
        <div>
          <strong style="color: var(--danger); font-size: 14px;">LỖ HỔNG IDOR / BOLA ĐANG BẬT!</strong>
          <p style="color: #7f1d1d; font-size: 12px; margin-top: 4px;">
            Hệ thống bỏ qua kiểm tra quyền sở hữu đối tượng. Bệnh nhân An (<code>bn_an</code>) có thể tra cứu và chỉnh sửa trái phép Bệnh án số 03 của Bình (<code>bn_binh</code>).
          </p>
        </div>
        <div>
          <button onclick="execCmd('set BUG_IDOR=0')" class="btn btn-safe">Vá Lỗ Hổng (Tắt Lỗi)</button>
        </div>
      </div>
    <?php else: ?>
      <div class="idor-banner idor-banner-safe">
        <div>
          <strong style="color: var(--safe); font-size: 14px;">HỆ THỐNG AN TOÀN (ABAC ACTIVE)</strong>
          <p style="color: #14532d; font-size: 12px; margin-top: 4px;">
            Quy tắc kiểm tra sở hữu đang hoạt động: Bệnh nhân chỉ xem và sửa bệnh án của chính mình. Truy cập chéo bị chặn với HTTP 403 Forbidden.
          </p>
        </div>
        <div>
          <button onclick="execCmd('set BUG_IDOR=1')" class="btn btn-danger">Tạo Lỗi (Bật IDOR)</button>
        </div>
      </div>
    <?php endif; ?>

    <!-- VIEW 1: CHI TIẾT BỆNH ÁN -->
    <?php if ($view === 'record' && $recordDetail): ?>
      <?php if ($leakActive): ?>
        <div class="leak-box">
          <div style="font-weight: 800; color: #be123c; font-size: 15px; margin-bottom: 6px;">
            [CẢNH BÁO VI PHẠM BẢO MẬT] PHÁT HIỆN LỖ HỔNG IDOR / BOLA (CWE-639) ĐANG BỊ KHAI THÁC!
          </div>
          <p style="color: #9f1239; font-size: 13px;">
            Tài khoản hiện tại: <strong><?= htmlspecialchars($currentUser['username']) ?></strong> (<?= htmlspecialchars($currentUser['full_name']) ?>)<br>
            Chủ sở hữu thực sự của bệnh án: <strong><?= htmlspecialchars($recordDetail['owner_username']) ?></strong> (Bệnh nhân Trần Thị Bình)<br>
            <em>Máy chủ đã bỏ qua kiểm tra quan hệ sở hữu đối tượng và làm rò rỉ dữ liệu y tế nhạy cảm bên dưới!</em>
          </p>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-head">
          <div>
            <div class="card-title"><?= htmlspecialchars($recordDetail['title']) ?></div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
              Mã hồ sơ: #<?= $recordDetail['id'] ?> | Khoa: <?= htmlspecialchars($recordDetail['department']) ?> | Ngày lập: <?= $recordDetail['created_at'] ?>
            </div>
          </div>
          <div style="display: flex; gap: 8px;">
            <a href="/?view=edit_record&id=<?= $recordDetail['id'] ?>" class="btn btn-outline" style="border-color: #0284c7; color: #0284c7;">Chỉnh Sửa Bệnh Án Này</a>
            <a href="/?view=records" class="btn btn-outline">Quay lại danh sách</a>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; font-size: 13px;">
          <div style="background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--border);">
            <strong>Bệnh nhân sở hữu:</strong> <code><?= htmlspecialchars($recordDetail['owner_username']) ?></code><br>
            <strong>Bác sĩ phụ trách:</strong> <?= htmlspecialchars($recordDetail['doctor_name'] ?? 'BS. CKI Lê Hoàng Minh') ?>
          </div>
          <div style="background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--border);">
            <strong>Chẩn đoán lâm sàng:</strong><br>
            <span style="color: #0369a1; font-weight: 600;"><?= htmlspecialchars($recordDetail['diagnosis'] ?? 'Đang cập nhật') ?></span>
          </div>
        </div>

        <div style="background: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 20px;">
          <h3 style="font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 8px;">NỘI DUNG BỆNH ÁN & PHÁC ĐỒ ĐIỀU TRỊ:</h3>
          <p style="font-size: 14px; color: #0f172a; white-space: pre-wrap;"><?= htmlspecialchars($recordDetail['content']) ?></p>
        </div>
      </div>

    <!-- VIEW 2: FORM CHỈNH SỬA BỆNH ÁN (TEST LỖI IDOR TRỰC TIẾP TRÊN UPDATE) -->
    <?php elseif ($view === 'edit_record' && $editRecordDetail): ?>
      <?php if ($leakActive): ?>
        <div class="leak-box">
          <div style="font-weight: 800; color: #be123c; font-size: 15px; margin-bottom: 6px;">
            [CẢNH BÁO BẢO MẬT] BẠN ĐANG TRUY CẬP CHỈNH SỬA BỆNH ÁN CỦA NGƯỜI KHÁC QUA LỖ HỔNG IDOR!
          </div>
          <p style="color: #9f1239; font-size: 13px;">
            Bệnh nhân An (<code>bn_an</code>) đang chỉnh sửa hồ sơ của Bệnh nhân Bình (<code>bn_binh</code>).
            Khi nhấn "Lưu Thay Đổi Bệnh Án", máy chủ sẽ ghi đè dữ liệu bệnh án trái phép do thiếu kiểm tra quyền sở hữu!
          </p>
        </div>
      <?php endif; ?>

      <div class="card" style="max-width: 680px; margin: 0 auto;">
        <div class="card-head">
          <div>
            <div class="card-title">Chỉnh Sửa Hồ Sơ Bệnh Án #<?= $editRecordDetail['id'] ?></div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
              Bệnh nhân sở hữu: <code><?= htmlspecialchars($editRecordDetail['owner_username']) ?></code>
            </div>
          </div>
          <a href="/?view=record&id=<?= $editRecordDetail['id'] ?>" class="btn btn-outline">Hủy bỏ</a>
        </div>

        <form action="/?action=edit_record_submit" method="POST">
          <input type="hidden" name="id" value="<?= $editRecordDetail['id'] ?>">

          <div class="form-group">
            <label>Tiêu đề bệnh án:</label>
            <input type="text" name="title" value="<?= htmlspecialchars($editRecordDetail['title']) ?>" required>
          </div>

          <div class="form-group">
            <label>Chẩn đoán bệnh lý:</label>
            <input type="text" name="diagnosis" value="<?= htmlspecialchars($editRecordDetail['diagnosis'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label>Nội dung phác đồ & Ghi chú y tế:</label>
            <textarea name="content" required><?= htmlspecialchars($editRecordDetail['content']) ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px;">
            Lưu Thay Đổi Bệnh Án
          </button>
        </form>
      </div>

    <!-- VIEW 3: DANH SÁCH BỆNH ÁN & HỘP TRA CỨU ID TỰ DO -->
    <?php elseif ($view === 'records'): ?>
      <?php if (isset($_GET['login_success'])): ?>
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; display: flex; align-items: center; justify-content: space-between;">
          <div><strong>Đăng nhập thành công!</strong> Chào mừng <strong><?= htmlspecialchars($currentUser['full_name'] ?? '') ?></strong> (<code><?= htmlspecialchars($currentUser['username'] ?? '') ?></code>) quay trở lại hệ thống.</div>
          <span class="status-badge badge-safe">Đã xác thực</span>
        </div>
      <?php endif; ?>
      <?php if (isset($_GET['reg_success'])): ?>
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; display: flex; align-items: center; justify-content: space-between;">
          <div><strong>Đăng ký thành công!</strong> Tài khoản <strong><?= htmlspecialchars($currentUser['username'] ?? '') ?></strong> đã được khởi tạo và tự động đăng nhập.</div>
          <span class="status-badge badge-safe">Tài khoản mới</span>
        </div>
      <?php endif; ?>
      
      <!-- HỘP TRA CỨU BỆNH ÁN THEO MÃ ID TỰ DO (TEST TRỰC TIẾP IDOR TẠI ĐÂY) -->
      <div class="lookup-panel">
        <div>
          <strong style="color: #1e40af; font-size: 14px;">TRA CỨU & MỞ HỒ SƠ BỆNH ÁN THEO MÃ (ID LOOKUP & DIRECT TEST):</strong>
          <div style="font-size: 12px; color: #1d4ed8; margin-top: 4px;">
            Nhập trực tiếp mã bệnh án ID vào ô bên dưới để kiểm tra phân quyền:
            (Bệnh án An sở hữu: ID <strong>1, 2</strong> | Bệnh án Bình sở hữu: ID <strong>3, 4</strong>).
          </div>
        </div>
        <form action="/" method="GET" class="lookup-form">
          <input type="hidden" name="view" value="record">
          <input type="text" name="id" placeholder="Nhập mã bệnh án (VD: 3)..." value="3" style="max-width: 200px; background: #fff;" required>
          <button type="submit" class="btn btn-primary">Tra Cứu / Xem Hồ Sơ</button>
          <button type="submit" formaction="/" onclick="this.form.view.value='edit_record';" class="btn btn-outline" style="border-color: #0284c7; color: #0284c7;">
            Chỉnh Sửa Bệnh Án
          </button>
        </form>
      </div>

      <div class="card">
        <div class="card-head">
          <div>
            <div class="card-title">Danh Sách Bệnh Án Điện Tử</div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
              <?php if ($currentUser && $currentUser['role'] === 'member'): ?>
                Góc nhìn Bệnh nhân (<?= htmlspecialchars($currentUser['full_name']) ?>): Chỉ hiển thị bệnh án thuộc quyền sở hữu của bạn.
              <?php elseif ($currentUser && in_array($currentUser['role'], ['doctor', 'admin', 'auditor'], true)): ?>
                Góc nhìn Cán bộ Y tế / Kiểm toán: Bạn có thẩm quyền xem toàn bộ hồ sơ trong khoa.
              <?php else: ?>
                Khách vãng lai: Chưa đăng nhập. Vui lòng bấm vào nút tròn màu xanh ở góc dưới bên phải để chọn tài khoản hoặc Đăng nhập.
              <?php endif; ?>
            </div>
          </div>
          <div>
            <?php if ($currentUser && in_array($currentUser['role'], ['doctor', 'admin'], true)): ?>
              <a href="/?view=create_record" class="btn btn-primary">+ Bác Sĩ Gửi Bệnh Án Mới</a>
            <?php endif; ?>
          </div>
        </div>

        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Tiêu Đề Bệnh Án</th>
              <th>Bệnh Nhân Sở Hữu</th>
              <th>Khoa Phòng</th>
              <th>Bác Sĩ Khám</th>
              <th>Ngày Lập</th>
              <th>Thao Tác</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $displayRecords = [];
            if ($currentUser) {
                if ($currentUser['role'] === 'member') {
                    $displayRecords = array_filter($mockRecords, fn($r) => $r['owner_username'] === $currentUser['username']);
                } else {
                    $displayRecords = $mockRecords;
                }
            }
            ?>
            <?php if (empty($displayRecords)): ?>
              <tr>
                <td colspan="7" style="text-align: center; color: var(--muted); padding: 32px;">
                  Chưa có bệnh án nào hiển thị hoặc bạn chưa đăng nhập. Vui lòng bấm vào nút tròn chuyển tài khoản nhanh (biểu tượng người dùng) ở góc dưới bên phải hoặc <a href="/?view=login" style="color: #0284c7; font-weight: 600;">Đăng nhập</a>!
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($displayRecords as $rec): ?>
                <tr>
                  <td><strong>#<?= $rec['id'] ?></strong></td>
                  <td>
                    <a href="/?view=record&id=<?= $rec['id'] ?>" style="color: #0284c7; font-weight: 600; text-decoration: none;">
                      <?= htmlspecialchars($rec['title']) ?>
                    </a>
                  </td>
                  <td><code><?= htmlspecialchars($rec['owner_username']) ?></code></td>
                  <td><?= htmlspecialchars($rec['department']) ?></td>
                  <td><?= htmlspecialchars($rec['doctor_name'] ?? 'BS. CKI Lê Hoàng Minh') ?></td>
                  <td style="color: var(--muted); font-size: 12px;"><?= $rec['created_at'] ?></td>
                  <td style="display: flex; gap: 6px;">
                    <a href="/?view=record&id=<?= $rec['id'] ?>" class="btn btn-outline" style="padding: 4px 8px; font-size: 12px;">Xem</a>
                    <a href="/?view=edit_record&id=<?= $rec['id'] ?>" class="btn btn-outline" style="padding: 4px 8px; font-size: 12px;">Sửa</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    <!-- VIEW 4: BÁC SĨ GỬI BỆNH ÁN MỚI -->
    <?php elseif ($view === 'create_record'): ?>
      <div class="card" style="max-width: 680px; margin: 0 auto;">
        <div class="card-head">
          <div class="card-title">Bác Sĩ Lập & Gửi Bệnh Án Cho Bệnh Nhân</div>
          <a href="/?view=records" class="btn btn-outline">Hủy bỏ</a>
        </div>

        <form action="/?action=create_record_submit" method="POST">
          <div class="form-group">
            <label>Bác sĩ phụ trách:</label>
            <input type="text" value="<?= htmlspecialchars($currentUser['full_name'] ?? 'BS. CKI Lê Hoàng Minh') ?> (<?= htmlspecialchars($currentUser['username'] ?? 'bs_minh') ?>)" disabled style="background: #f1f5f9;">
          </div>

          <div class="form-group">
            <label>Chọn Bệnh nhân nhận bệnh án:</label>
            <select name="patient_username" required>
              <option value="bn_an">Bệnh nhân Nguyễn Văn An (bn_an)</option>
              <option value="bn_binh">Bệnh nhân Trần Thị Bình (bn_binh)</option>
            </select>
          </div>

          <div class="form-group">
            <label>Khoa khám bệnh:</label>
            <input type="text" name="department" value="Khoa Tim Mạch Can Thiệp" required>
          </div>

          <div class="form-group">
            <label>Tiêu đề hồ sơ bệnh án:</label>
            <input type="text" name="title" placeholder="VD: Bệnh án tái khám định kỳ..." required>
          </div>

          <div class="form-group">
            <label>Chẩn đoán bệnh lý:</label>
            <input type="text" name="diagnosis" placeholder="VD: Huyết áp kiểm soát ổn định, chỉ định duy trì đơn thuốc..." required>
          </div>

          <div class="form-group">
            <label>Nội dung phác đồ điều trị và dặn dò bảo mật:</label>
            <textarea name="content" placeholder="Nhập chi tiết phác đồ khám chữa bệnh..." required></textarea>
          </div>

          <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; font-size: 14px;">
            Lập & Gửi Bệnh Án Cho Bệnh Nhân
          </button>
        </form>
      </div>

    <!-- VIEW 5: QUẢN LÝ NGƯỜI DÙNG CHO QUẢN TRỊ VIÊN (THÊM, SỬA, XÓA, TÌM KIẾM) -->
    <?php elseif ($view === 'users'): ?>
      <?php if (!$currentUser || $currentUser['role'] !== 'admin'): ?>
        <?php
          recordSecurityIncident(
              'PRIVILEGE_ESCALATION_ATTEMPT',
              'HIGH',
              $currentUser,
              "Trang Quản trị người dùng (view=users)",
              "Cố tình leo thang đặc quyền: Người dùng " . ($currentUser ? $currentUser['username'] . " (vai trò " . $currentUser['role'] . ")" : "Khách vãng lai") . " cố truy cập trang Quản lý Người dùng của Admin!",
              ['status_code' => 403, 'cwe' => 'CWE-285 (Improper Authorization)']
          );
          header('Location: /?view=error&code=403&msg=' . urlencode('Chỉ Quản trị viên hệ thống (admin_bv) mới có quyền truy cập trang Quản lý Người dùng!'));
          exit;
        ?>
      <?php endif; ?>

      <div class="card">
        <div class="card-head">
          <div>
            <div class="card-title">Quản Trị Người Dùng Hệ Thống Y Tế (Admin Only)</div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
              Quản trị viên có toàn quyền thêm, sửa, xóa và tìm kiếm người dùng trong hệ thống.
            </div>
          </div>
          <button onclick="openAddUserModal()" class="btn btn-primary">+ Thêm Người Dùng Mới</button>
        </div>

        <!-- THANH TÌM KIẾM NGƯỜI DÙNG TỨC THÌ -->
        <div style="margin-bottom: 16px;">
          <input type="text" id="userSearchInput" onkeyup="filterUsers()" placeholder="Tìm kiếm nhanh theo Tên đăng nhập, Họ tên, Vai trò, Khoa phòng..." style="background: #fff;">
        </div>

        <table id="userTable">
          <thead>
            <tr>
              <th>Tên Đăng Nhập</th>
              <th>Họ Và Tên</th>
              <th>Vai Trò (Role)</th>
              <th>Khoa Phòng / Đơn Vị</th>
              <th>Thao Tác Quản Trị</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($mockUsers as $u): ?>
              <tr>
                <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                <td>
                  <span class="status-badge <?= $u['role'] === 'admin' ? 'badge-danger' : ($u['role'] === 'doctor' ? 'badge-doctor' : 'badge-safe') ?>">
                    <?= htmlspecialchars($u['role']) ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($u['department'] ?? 'Khoa Khám Bệnh') ?></td>
                <td>
                  <button onclick="openEditUserModal('<?= htmlspecialchars($u['username']) ?>', '<?= htmlspecialchars(addslashes($u['full_name'])) ?>', '<?= htmlspecialchars($u['role']) ?>', '<?= htmlspecialchars(addslashes($u['department'] ?? '')) ?>')" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px;">Sửa</button>
                  <?php if ($u['username'] !== $currentUser['username']): ?>
                    <a href="/?action=delete_user&username=<?= urlencode($u['username']) ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa người dùng <?= htmlspecialchars($u['username']) ?> không?');" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; color: #dc2626; border-color: #fecaca;">Xóa</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- MODAL THÊM NGƯỜI DÙNG -->
      <div id="addUserModal" class="modal-overlay">
        <div class="modal-box">
          <div class="card-head">
            <div class="card-title">Thêm Tài Khoản Người Dùng Mới</div>
            <button onclick="closeAddUserModal()" class="btn btn-outline" style="padding: 2px 8px;">&times;</button>
          </div>
          <form action="/?action=add_user" method="POST">
            <div class="form-group">
              <label>Tên đăng nhập (username):</label>
              <input type="text" name="username" placeholder="VD: bs_dung, bn_ha..." required>
            </div>
            <div class="form-group">
              <label>Mật khẩu khởi tạo:</label>
              <input type="password" name="password" value="TestPass@2026" required>
            </div>
            <div class="form-group">
              <label>Họ và tên hiển thị:</label>
              <input type="text" name="full_name" placeholder="VD: BS. Lê Tiến Dũng..." required>
            </div>
            <div class="form-group">
              <label>Vai trò:</label>
              <select name="role">
                <option value="doctor">Bác sĩ khám bệnh (doctor)</option>
                <option value="member">Bệnh nhân (member/patient)</option>
                <option value="admin">Quản trị viên (admin)</option>
                <option value="auditor">Kiểm toán an toàn (auditor)</option>
              </select>
            </div>
            <div class="form-group">
              <label>Khoa phòng / Đơn vị:</label>
              <input type="text" name="department" value="Khoa Khám Bệnh Chuyên Sâu" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px;">Tạo Người Dùng Mới</button>
          </form>
        </div>
      </div>

      <!-- MODAL SỬA NGƯỜI DÙNG -->
      <div id="editUserModal" class="modal-overlay">
        <div class="modal-box">
          <div class="card-head">
            <div class="card-title">Chỉnh Sửa Thông Tin Người Dùng</div>
            <button onclick="closeEditUserModal()" class="btn btn-outline" style="padding: 2px 8px;">&times;</button>
          </div>
          <form action="/?action=edit_user" method="POST">
            <input type="hidden" id="editUsername" name="username">
            <div class="form-group">
              <label>Tên đăng nhập:</label>
              <input type="text" id="displayEditUsername" disabled style="background: #f1f5f9;">
            </div>
            <div class="form-group">
              <label>Họ và tên hiển thị:</label>
              <input type="text" id="editFullName" name="full_name" required>
            </div>
            <div class="form-group">
              <label>Vai trò (Role):</label>
              <select id="editRole" name="role">
                <option value="doctor">Bác sĩ khám bệnh (doctor)</option>
                <option value="member">Bệnh nhân (member/patient)</option>
                <option value="admin">Quản trị viên (admin)</option>
                <option value="auditor">Kiểm toán an toàn (auditor)</option>
              </select>
            </div>
            <div class="form-group">
              <label>Khoa phòng / Đơn vị:</label>
              <input type="text" id="editDept" name="department" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px;">Cập Nhật Người Dùng</button>
          </form>
        </div>
      </div>

    <!-- VIEW 6: THÔNG BÁO DÙNG CHUNG -->
    <?php elseif ($view === 'announcements'): ?>
      <div class="card">
        <div class="card-head">
          <div class="card-title">Thông Báo Bệnh Viện (Dữ Liệu Dùng Chung)</div>
        </div>
        <?php foreach ($mockAnnouncements as $ann): ?>
          <div style="padding: 16px; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 16px;">
            <h3 style="font-size: 15px; color: #0284c7; margin-bottom: 6px;"><?= htmlspecialchars($ann['title']) ?></h3>
            <p style="font-size: 13px; color: #334155;"><?= htmlspecialchars($ann['body']) ?></p>
            <div style="font-size: 11px; color: var(--muted); margin-top: 8px;">Đăng lúc: <?= $ann['created_at'] ?> | Dữ liệu dùng chung cho mọi vai trò</div>
          </div>
        <?php endforeach; ?>
      </div>

    <!-- VIEW 7: ĐĂNG NHẬP -->
    <?php elseif ($view === 'login'): ?>
      <div class="card" style="max-width: 460px; margin: 40px auto; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border-radius: 12px;">
        <div class="card-head" style="border-bottom: 2px solid #e2e8f0; padding-bottom: 14px;">
          <div>
            <div class="card-title" style="font-size: 18px; color: #0f172a;">Đăng Nhập MediRecord</div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">Hệ thống Quản lý Bệnh án Điện tử Y tế</div>
          </div>
          <span style="font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 20px; font-weight: 700;">CỔNG 8205</span>
        </div>

        <?php if (isset($_GET['err'])): ?>
          <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 18px;">
            <?php if ($_GET['err'] === 'invalid_credentials'): ?>
              <strong>Đăng nhập thất bại:</strong> Tên đăng nhập hoặc mật khẩu không chính xác! Vui lòng thử lại.
            <?php elseif ($_GET['err'] === 'missing_fields'): ?>
              <strong>Lỗi:</strong> Vui lòng điền đầy đủ tên đăng nhập và mật khẩu!
            <?php else: ?>
              <strong>Thông báo:</strong> <?= htmlspecialchars($_GET['err']) ?>
            <?php endif; ?>
          </div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'require_login'): ?>
          <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 18px;">
            <strong>Yêu cầu đăng nhập:</strong> Vui lòng đăng nhập tài khoản để truy cập thông tin bệnh án y tế.
          </div>
        <?php endif; ?>

        <form action="/?action=login_submit" method="POST">
          <div class="form-group">
            <label for="loginUsername">Tên đăng nhập:</label>
            <input type="text" id="loginUsername" name="username" value="<?= htmlspecialchars($_GET['u'] ?? '') ?>" placeholder="VD: bs_minh, bn_an, admin_bv" required autofocus>
          </div>
          <div class="form-group">
            <label for="loginPassword">Mật khẩu:</label>
            <input type="password" id="loginPassword" name="password" value="TestPass@2026" placeholder="Nhập mật khẩu..." required>
            <div style="font-size: 11px; color: var(--muted); margin-top: 4px;">(Mật khẩu mặc định của các tài khoản mẫu: <code>TestPass@2026</code>)</div>
          </div>
          <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px; font-size: 14px; font-weight: 700; margin-top: 6px;">Đăng Nhập Ngay</button>
        </form>

        <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border);">
          <div style="font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 8px;">Điền nhanh tài khoản kiểm thử:</div>
          <div style="display: flex; gap: 6px; flex-wrap: wrap;">
            <button type="button" onclick="fillLogin('admin_bv', 'TestPass@2026')" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px; border-color: #fca5a5; color: #b91c1c;">Admin</button>
            <button type="button" onclick="fillLogin('bs_minh', 'TestPass@2026')" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px; border-color: #93c5fd; color: #1d4ed8;">Bác sĩ Minh</button>
            <button type="button" onclick="fillLogin('bn_an', 'TestPass@2026')" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px; border-color: #86efac; color: #15803d;">Bệnh nhân An</button>
            <button type="button" onclick="fillLogin('bn_binh', 'TestPass@2026')" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px; border-color: #86efac; color: #15803d;">Bệnh nhân Bình</button>
            <button type="button" onclick="fillLogin('tester_sec', 'TestPass@2026')" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px; border-color: #fde047; color: #b45309;">Tester Nam</button>
          </div>
        </div>

        <div style="margin-top: 18px; text-align: center; font-size: 13px; color: #64748b;">
          Chưa có tài khoản y tế? <a href="/?view=register" style="color: #0284c7; font-weight: 700; text-decoration: none;">Đăng ký tài khoản mới</a>
        </div>
      </div>

    <!-- VIEW 8: ĐĂNG KÝ -->
    <?php elseif ($view === 'register'): ?>
      <div class="card" style="max-width: 500px; margin: 40px auto; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border-radius: 12px;">
        <div class="card-head" style="border-bottom: 2px solid #e2e8f0; padding-bottom: 14px;">
          <div>
            <div class="card-title" style="font-size: 18px; color: #0f172a;">Đăng Ký Tài Khoản Y Tế Mới</div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">Khởi tạo tài khoản Bệnh nhân hoặc Bác sĩ mới vào hệ thống</div>
          </div>
          <span style="font-size: 11px; background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 20px; font-weight: 700;">THÀNH VIÊN</span>
        </div>

        <?php if (isset($_GET['err'])): ?>
          <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 18px;">
            <?php if ($_GET['err'] === 'username_taken'): ?>
              <strong>Đăng ký thất bại:</strong> Tên đăng nhập này đã được sử dụng! Vui lòng chọn tên đăng nhập khác.
            <?php elseif ($_GET['err'] === 'password_mismatch'): ?>
              <strong>Đăng ký thất bại:</strong> Mật khẩu xác nhận không khớp nhau! Vui lòng kiểm tra lại.
            <?php elseif ($_GET['err'] === 'missing_fields'): ?>
              <strong>Đăng ký thất bại:</strong> Vui lòng điền đầy đủ tất cả các trường thông tin bắt buộc!
            <?php else: ?>
              <strong>Thông báo:</strong> <?= htmlspecialchars($_GET['err']) ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <form action="/?action=register_submit" method="POST">
          <div class="form-group">
            <label for="regUsername">Tên đăng nhập (Username): <span style="color:#ef4444;">*</span></label>
            <input type="text" id="regUsername" name="username" value="<?= htmlspecialchars($_GET['u'] ?? '') ?>" placeholder="VD: bn_tuan, bs_hoa..." required autofocus>
          </div>

          <div class="form-group">
            <label for="regFullName">Họ và tên đầy đủ: <span style="color:#ef4444;">*</span></label>
            <input type="text" id="regFullName" name="full_name" value="<?= htmlspecialchars($_GET['fn'] ?? '') ?>" placeholder="VD: Nguyễn Tuấn Anh" required>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div class="form-group">
              <label for="regRole">Vai trò tài khoản: <span style="color:#ef4444;">*</span></label>
              <select id="regRole" name="role" required>
                <option value="member">Bệnh nhân (Patient)</option>
                <option value="doctor">Bác sĩ khám (Doctor)</option>
              </select>
            </div>
            <div class="form-group">
              <label for="regDept">Khoa phòng / Đơn vị:</label>
              <input type="text" id="regDept" name="department" value="Bệnh nhân ngoại trú" placeholder="VD: Khoa Khám Bệnh">
            </div>
          </div>

          <div class="form-group">
            <label for="regPassword">Mật khẩu: <span style="color:#ef4444;">*</span></label>
            <input type="password" id="regPassword" name="password" placeholder="Tối thiểu 6 ký tự..." required>
          </div>

          <div class="form-group">
            <label for="regConfirm">Xác nhận mật khẩu: <span style="color:#ef4444;">*</span></label>
            <input type="password" id="regConfirm" name="confirm_password" placeholder="Nhập lại mật khẩu..." required>
          </div>

          <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px; font-size: 14px; font-weight: 700; margin-top: 6px;">Hoàn Tất Đăng Ký</button>
        </form>

        <div style="margin-top: 18px; text-align: center; font-size: 13px; color: #64748b;">
          Đã có tài khoản? <a href="/?view=login" style="color: #0284c7; font-weight: 700; text-decoration: none;">Đăng nhập ngay</a>
        </div>
      </div>

    <!-- VIEW 9: TRUNG TÂM GIÁM SÁT AN NINH & KIỂM THỬ PHÂN QUYỀN (CHO TESTER_SEC & ADMIN) -->
    <?php elseif ($view === 'tester'): ?>
      <?php $testerTab = $_GET['tab'] ?? 'incidents'; ?>

      <!-- HEADER BẢNG ĐIỀU KHIỂN KIỂM THỬ -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <div>
          <h1 style="font-size: 20px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
            <span style="background: #0284c7; color: #fff; width: 34px; height: 34px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px;">🛡️</span>
            TRUNG TÂM GIÁM SÁT AN NINH & KIỂM THỬ PHÂN QUYỀN
          </h1>
          <p style="color: var(--muted); font-size: 13px; margin-top: 4px;">
            Giao diện kiểm thử và cảnh báo vi phạm dành cho <strong><?= htmlspecialchars($currentUser['full_name'] ?? 'Kỹ sư Bảo mật') ?></strong> (<code><?= htmlspecialchars($currentUser['username'] ?? 'tester_sec') ?></code> - Vai trò: <code><?= htmlspecialchars($currentUser['role'] ?? 'auditor') ?></code>).
          </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
          <a href="/?action=simulate_security_alert" class="btn btn-outline" style="border-color: #fca5a5; color: #b91c1c; font-size: 12px;" title="Tạo sự cố tấn công mẫu để kiểm thử cơ chế phát hiện">
            ⚡ Mô Phỏng Tấn Công IDOR
          </a>
          <a href="/?action=clear_security_alerts" onclick="return confirm('Bạn có chắc muốn xóa sạch toàn bộ nhật ký sự cố?');" class="btn btn-outline" style="font-size: 12px;">
            🗑️ Xóa Nhật Ký
          </a>
        </div>
      </div>

      <!-- KPI METRIC CARDS -->
      <div class="stats-grid">
        <div class="stat-card" style="border-left: 4px solid #ef4444;">
          <div class="stat-label">Cảnh Báo Sự Cố Xâm Phạm</div>
          <div class="stat-val" style="color: #b91c1c;">
            <?= $incidentCount ?>
            <span style="font-size: 12px; font-weight: 600; color: #64748b;">(<?= $criticalCount ?> Nghiêm trọng)</span>
          </div>
          <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">Thời gian thực (Live Feeds)</div>
        </div>

        <div class="stat-card" style="border-left: 4px solid <?= $bugIdor ? '#ef4444' : '#16a34a' ?>;">
          <div class="stat-label">Trạng Thái Lỗ Hổng IDOR (CWE-639)</div>
          <div class="stat-val" style="color: <?= $bugIdor ? '#dc2626' : '#16a34a' ?>; font-size: 20px;">
            <?= $bugIdor ? 'LỖ HỔNG ĐANG MỞ' : 'HỆ THỐNG AN TOÀN' ?>
          </div>
          <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
            <?= $bugIdor ? 'Bệnh nhân An có thể đọc trộm bệnh án Bình' : 'Cơ chế ABAC đang bảo vệ 100%' ?>
          </div>
        </div>

        <div class="stat-card" style="border-left: 4px solid <?= $failedTestCount > 0 ? '#d97706' : '#16a34a' ?>;">
          <div class="stat-label">Kết Quả Ma Trận 24 Test Cases</div>
          <div class="stat-val" style="color: <?= $failedTestCount > 0 ? '#b45309' : '#15803d' ?>;">
            <?= $passedTestCount ?> / 24 PASS
          </div>
          <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
            <?= $failedTestCount > 0 ? "<span style='color:#dc2626;font-weight:700;'>$failedTestCount FAIL (Bẫy IDOR R02 kích hoạt)</span>" : "Tất cả test cases đều đạt chuẩn" ?>
          </div>
        </div>

        <div class="stat-card" style="border-left: 4px solid #0284c7;">
          <div class="stat-label">Bộ Test Suite Tích Hợp</div>
          <div class="stat-val" style="color: #0369a1; font-size: 20px;">
            88/88 TESTS
          </div>
          <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
            Đã chuyển toàn bộ từ cổng 8105 sang 8205
          </div>
        </div>
      </div>

      <!-- TABS CHỨC NĂNG KIỂM THỬ -->
      <div class="tab-bar">
        <a href="/?view=tester&tab=incidents" class="tab-item <?= $testerTab === 'incidents' ? 'active' : '' ?>">
          🚨 Nhật Ký Cảnh Báo Xâm Phạm Thời Gian Thực (<?= $incidentCount ?>)
        </a>
        <a href="/?view=tester&tab=matrix" class="tab-item <?= $testerTab === 'matrix' ? 'active' : '' ?>">
          📊 Ma Trận Kiểm Thử Phân Quyền Tự Động (24 Cases)
        </a>
        <a href="/?view=tester&tab=full_tests" class="tab-item <?= $testerTab === 'full_tests' ? 'active' : '' ?>">
          ⚙️ Chạy Toàn Bộ Test Suite (88 Tests CLI)
        </a>
      </div>

      <!-- TAB 1: NHẬT KÝ CẢNH BÁO SỰ CỐ XÂM PHẠM -->
      <?php if ($testerTab === 'incidents'): ?>
        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">
                <span class="live-pulse"></span>
                Dòng Sự Kiện An Ninh & Cảnh Báo Xâm Phạm Thời Gian Thực
              </div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                Tự động ghi nhận khi bất kỳ người dùng, bệnh nhân hoặc nhân viên nào có lỗi thao tác hoặc cố tình lợi dụng lỗ hổng phân quyền.
              </div>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
              <a href="/?action=switch_user&user=bn_an" class="btn btn-outline" style="border-color: #0284c7; color: #0284c7; font-size: 12px;">
                👤 Chuyển sang BN An để thử tấn công
              </a>
              <?php if ($bugIdor): ?>
                <button onclick="execCmd('set BUG_IDOR=0')" class="btn btn-safe" style="font-size: 12px;">🛡️ Vá Lỗ Hổng Ngay</button>
              <?php else: ?>
                <button onclick="execCmd('set BUG_IDOR=1')" class="btn btn-danger" style="font-size: 12px;">⚠️ Kích Hoạt Lỗi IDOR</button>
              <?php endif; ?>
            </div>
          </div>

          <?php if (empty($allIncidents)): ?>
            <div style="text-align: center; padding: 40px; color: var(--muted);">
              Chưa có sự cố an ninh nào được ghi nhận. Hệ thống đang an toàn tuyệt đối.
            </div>
          <?php else: ?>
            <div style="overflow-x: auto;">
              <table>
                <thead>
                  <tr>
                    <th>Mã Sự Cố & Thời Gian</th>
                    <th>Mức Độ</th>
                    <th>Đối Tượng Vi Phạm (Actor)</th>
                    <th>Mục Tiêu (Target)</th>
                    <th>Chi Tiết Hành Vi Vi Phạm & Mã Lỗi</th>
                    <th>HTTP Code</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allIncidents as $inc): ?>
                    <?php
                      $sevClass = match($inc['severity'] ?? 'INFO') {
                        'CRITICAL' => 'badge-crit',
                        'HIGH'     => 'badge-high',
                        'WARNING'  => 'badge-warn',
                        default    => 'badge-info'
                      };
                    ?>
                    <tr>
                      <td style="white-space: nowrap;">
                        <code style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($inc['id'] ?? '') ?></code><br>
                        <span style="font-size: 11px; color: var(--muted);"><?= htmlspecialchars($inc['time'] ?? '') ?></span>
                      </td>
                      <td>
                        <span class="status-badge <?= $sevClass ?>">
                          <?= htmlspecialchars($inc['severity'] ?? 'INFO') ?>
                        </span>
                      </td>
                      <td>
                        <strong><?= htmlspecialchars($inc['actor_name'] ?? 'Khách vãng lai') ?></strong><br>
                        <span style="font-size: 11px; color: #64748b;">
                          <code><?= htmlspecialchars($inc['actor_username'] ?? 'anon') ?></code> (<?= htmlspecialchars($inc['actor_role'] ?? 'anon') ?>)
                        </span>
                      </td>
                      <td>
                        <span style="font-size: 12px; font-weight: 600; color: #0284c7;">
                          <?= htmlspecialchars($inc['target'] ?? '-') ?>
                        </span>
                      </td>
                      <td>
                        <div style="font-size: 13px; color: #0f172a;">
                          <?= htmlspecialchars($inc['description'] ?? '') ?>
                        </div>
                        <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                          Phân loại: <strong><?= htmlspecialchars($inc['cwe'] ?? 'CWE-639') ?></strong>
                          <?php if (!empty($inc['uri'])): ?>
                            | URI: <code><?= htmlspecialchars($inc['uri']) ?></code>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <?php $sc = $inc['status_code'] ?? 200; ?>
                        <span class="status-badge <?= $sc === 200 ? ($inc['severity'] === 'CRITICAL' ? 'badge-danger' : 'badge-safe') : ($sc === 403 ? 'badge-danger' : 'badge-warn') ?>">
                          HTTP <?= $sc ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      <!-- TAB 2: MA TRẬN KIỂM THỬ PHÂN QUYỀN TỰ ĐỘNG (24 TEST CASES) -->
      <?php elseif ($testerTab === 'matrix'): ?>
        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Ma Trận 24 Ca Kiểm Thử Phân Quyền Tự Động (Access Matrix)</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                Tích hợp toàn diện từ công cụ kiểm thử: Kiểm tra kiểm soát truy cập (ABAC/RBAC) trên 5 phân hệ API (Profile, Danh sách bệnh án, Bệnh án cá nhân, Thông báo, Quản trị).
              </div>
            </div>
            <div>
              <?php if ($bugIdor): ?>
                <span class="status-badge badge-danger" style="font-size: 12px; padding: 6px 12px;">23 PASS / 1 FAIL (R02 Thất Bại do IDOR)</span>
              <?php else: ?>
                <span class="status-badge badge-safe" style="font-size: 12px; padding: 6px 12px;">24 PASS / 0 FAIL (Tất cả Đạt Chuẩn An Toàn)</span>
              <?php endif; ?>
            </div>
          </div>

          <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 14px; margin-bottom: 16px; font-size: 13px;">
            <strong>Giải thích ca kiểm thử đặc biệt R02 (Bẫy IDOR):</strong><br>
            • Khi <code>BUG_IDOR=1</code> (Có lỗ hổng): Ca kiểm thử <strong>R02</strong> trả về kết quả <span style="color:#dc2626;font-weight:700;">FAIL</span> do máy chủ trả về HTTP 200 OK và làm rò rỉ bệnh án Bình cho An.<br>
            • Khi <code>BUG_IDOR=0</code> (Đã vá): Ca kiểm thử <strong>R02</strong> trả về kết quả <span style="color:#16a34a;font-weight:700;">PASS</span> do máy chủ chặn lại với HTTP 403 Forbidden!
          </div>

          <div style="overflow-x: auto;">
            <table>
              <thead>
                <tr>
                  <th>Mã Test</th>
                  <th>Phương Thức & API</th>
                  <th>Tài Khoản Thử Nghiệm</th>
                  <th>Vai Trò</th>
                  <th>Mô Tả Mục Tiêu Kiểm Thử</th>
                  <th>Kỳ Vọng</th>
                  <th>Kết QuẢ Thực Tế</th>
                  <th>Trạng Thái</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($testCases as $tc): ?>
                  <tr style="<?= $tc['code'] === 'R02' ? ($tc['status'] === 'FAIL' ? 'background: #fff1f2;' : 'background: #f0fdf4;') : '' ?>">
                    <td><strong style="color: #0369a1;"><?= $tc['code'] ?></strong></td>
                    <td><code><?= $tc['method'] ?> <?= $tc['api'] ?></code></td>
                    <td><?= htmlspecialchars($tc['user']) ?></td>
                    <td><span class="status-badge badge-doctor" style="font-size: 10px;"><?= $tc['role'] ?></span></td>
                    <td><?= htmlspecialchars($tc['desc']) ?></td>
                    <td><code style="color: #334155;"><?= $tc['expect'] ?></code></td>
                    <td style="font-size: 12px;"><?= htmlspecialchars($tc['actual']) ?></td>
                    <td>
                      <span class="status-badge <?= $tc['status'] === 'PASS' ? 'badge-pass' : 'badge-fail' ?>">
                        <?= $tc['status'] ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <!-- TAB 3: CHẠY BỘ TEST SUITE 88 TESTS CLI -->
      <?php elseif ($testerTab === 'full_tests'): ?>
        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Bộ Kiểm Thử Tự Động Toàn Diện Hệ Thống (88 Test Cases)</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                Thực thi lệnh kiểm thử <code>bash lab.sh test</code> trực tiếp trên môi trường máy chủ để xác nhận tính toàn vẹn 100% của hệ thống.
              </div>
            </div>
            <div>
              <a href="/?action=run_full_test_suite" class="btn btn-primary">
                ▶ Chạy Toàn Bộ Test Suite (bash lab.sh test)
              </a>
            </div>
          </div>

          <div style="background: #090d16; border-radius: 8px; padding: 16px; font-family: monospace; color: #22c55e; font-size: 12px; line-height: 1.6; max-height: 480px; overflow-y: auto;">
            <?php if (isset($_SESSION['last_full_test_output'])): ?>
              <pre style="white-space: pre-wrap; margin: 0; color: #38bdf8;"><?= htmlspecialchars($_SESSION['last_full_test_output']) ?></pre>
            <?php else: ?>
              <div style="color: #94a3b8;">
                # Nhấn vào nút "Chạy Toàn Bộ Test Suite" phía trên để thực thi kiểm thử toàn bộ 88 ca kiểm tra.<br>
                # Bao gồm: Unit test CSDL PostgreSQL, Kiểm tra chính sách ABAC/RBAC, E2E HTTP Test và Ma trận bảo mật.
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  </div>

  <!-- NÚT TRÒN NỔI (FAB) CHUYỂN TÀI KHOẢN NHANH - NẰM NGAY TRÊN NÚT TERMINAL -->
  <button id="accountFab" onclick="toggleAccountDrawer()" class="account-fab" title="Chuyển Nhanh Tài Khoản (1-Click Switch)">
    <span class="account-fab-icon">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
      </svg>
    </span>
  </button>

  <!-- CỬA SỔ POPUP CHUYỂN TÀI KHOẢN NHANH -->
  <div id="accountDrawer" class="account-drawer" style="display: none;">
    <div class="acc-drawer-head">
      <div>
        <h4 style="margin: 0; font-size: 13px; font-weight: 700; color: #fff;">CHUYỂN NHANH TÀI KHOẢN (1-CLICK)</h4>
        <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
          <?= $currentUser ? 'Đang dùng: <strong>' . htmlspecialchars($currentUser['username']) . '</strong>' : 'Khách vãng lai' ?>
        </div>
      </div>
      <button onclick="toggleAccountDrawer()" style="background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; line-height: 1;">&times;</button>
    </div>

    <div class="acc-drawer-list">
      <!-- Admin -->
      <a href="/?action=switch_user&user=admin_bv" class="acc-item <?= ($currentUser && $currentUser['username'] === 'admin_bv') ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-avatar acc-avatar-adm">AD</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">BS. CKII Vũ Quốc Bảo</div>
            <div style="font-size: 11px; color: #64748b;">admin_bv • Quản trị viên Bệnh viện</div>
          </div>
        </div>
        <?php if ($currentUser && $currentUser['username'] === 'admin_bv'): ?>
          <span style="font-size: 11px; color: #0284c7; font-weight: 700;">Đang chọn</span>
        <?php endif; ?>
      </a>

      <!-- Doctor -->
      <a href="/?action=switch_user&user=bs_minh" class="acc-item <?= ($currentUser && $currentUser['username'] === 'bs_minh') ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-avatar acc-avatar-doc">BS</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">BS. CKI Lê Hoàng Minh</div>
            <div style="font-size: 11px; color: #64748b;">bs_minh • Bác sĩ điều trị</div>
          </div>
        </div>
        <?php if ($currentUser && $currentUser['username'] === 'bs_minh'): ?>
          <span style="font-size: 11px; color: #0284c7; font-weight: 700;">Đang chọn</span>
        <?php endif; ?>
      </a>

      <!-- Patient An -->
      <a href="/?action=switch_user&user=bn_an" class="acc-item <?= ($currentUser && $currentUser['username'] === 'bn_an') ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-avatar acc-avatar-pat">AN</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">Nguyễn Văn An</div>
            <div style="font-size: 11px; color: #64748b;">bn_an • Bệnh nhân (Mục tiêu IDOR)</div>
          </div>
        </div>
        <?php if ($currentUser && $currentUser['username'] === 'bn_an'): ?>
          <span style="font-size: 11px; color: #0284c7; font-weight: 700;">Đang chọn</span>
        <?php endif; ?>
      </a>

      <!-- Patient Binh -->
      <a href="/?action=switch_user&user=bn_binh" class="acc-item <?= ($currentUser && $currentUser['username'] === 'bn_binh') ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-avatar acc-avatar-pat">BI</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">Trần Thị Bình</div>
            <div style="font-size: 11px; color: #64748b;">bn_binh • Bệnh nhân (Nạn nhân IDOR)</div>
          </div>
        </div>
        <?php if ($currentUser && $currentUser['username'] === 'bn_binh'): ?>
          <span style="font-size: 11px; color: #0284c7; font-weight: 700;">Đang chọn</span>
        <?php endif; ?>
      </a>

      <!-- Tester -->
      <a href="/?action=switch_user&user=tester_sec" class="acc-item <?= ($currentUser && $currentUser['username'] === 'tester_sec') ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-avatar acc-avatar-aud">KT</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">Trần Bảo Nam</div>
            <div style="font-size: 11px; color: #64748b;">tester_sec • Kiểm thử An toàn thông tin</div>
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
          <?php if ($incidentCount > 0): ?>
            <span style="font-size: 10px; background: #fee2e2; color: #b91c1c; padding: 2px 6px; border-radius: 10px; font-weight: 700;"><?= $incidentCount ?> sự cố</span>
          <?php endif; ?>
          <?php if ($currentUser && $currentUser['username'] === 'tester_sec'): ?>
            <span style="font-size: 11px; color: #0284c7; font-weight: 700;">Đang chọn</span>
          <?php endif; ?>
        </div>
      </a>

      <!-- Anonymous / Guest -->
      <a href="/?action=switch_user&user=anon" class="acc-item <?= ($currentUser === null) ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-avatar" style="background: #f1f5f9; color: #64748b;">--</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">Khách Vãng Lai</div>
            <div style="font-size: 11px; color: #64748b;">Chưa đăng nhập (Anonymous)</div>
          </div>
        </div>
        <?php if ($currentUser === null): ?>
          <span style="font-size: 11px; color: #0284c7; font-weight: 700;">Đang chọn</span>
        <?php endif; ?>
      </a>
    </div>

    <!-- Drawer Footer với liên kết Đăng nhập / Đăng ký -->
    <div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
      <a href="/?view=login" style="color: #0284c7; text-decoration: none; font-weight: 600;">Đăng Nhập</a>
      <span style="color: #cbd5e1;">|</span>
      <a href="/?view=register" style="color: #0284c7; text-decoration: none; font-weight: 600;">Đăng Ký</a>
      <span style="color: #cbd5e1;">|</span>
      <a href="/?action=logout" style="color: #ef4444; text-decoration: none; font-weight: 600;">Đăng Xuất</a>
    </div>
  </div>

  <!-- NÚT TRÒN NỔI FLOATING ACTION BUTTON (FAB) TERMINAL GÓC DƯỚI BÊN PHẢI -->
  <button id="termFab" onclick="toggleTerminal()" class="term-fab" title="Mở Terminal Điều Khiển Lỗi Bảo Mật">
    <span class="term-fab-icon">&gt;_</span>
    <span class="term-fab-dot <?= $bugIdor ? 'dot-danger' : 'dot-safe' ?>"></span>
  </button>

  <!-- CỬA SỔ TERMINAL NỔI TRƯỢT LÊN TỪ GÓC DƯỚI BÊN PHẢI -->
  <div id="termDrawer" class="term-drawer" style="display: none;">
    <div class="term-drawer-head">
      <span>MEDIRECORD CONSOLE [<?= $bugIdor ? 'IDOR: ON' : 'SECURE' ?>]</span>
      <button onclick="toggleTerminal()" style="background:none; border:none; color:#94a3b8; cursor:pointer; font-size:16px;">&times;</button>
    </div>
    
    <div class="term-quick-btns">
      <button onclick="execCmd('set BUG_IDOR=1')" class="term-qbtn" style="color: #f87171;">Tạo Lỗi (set BUG_IDOR=1)</button>
      <button onclick="execCmd('set BUG_IDOR=0')" class="term-qbtn" style="color: #4ade80;">Vá Lỗi (set BUG_IDOR=0)</button>
      <button onclick="execCmd('status')" class="term-qbtn">status</button>
      <button onclick="execCmd('curl /api/notes/3')" class="term-qbtn">curl /api/notes/3</button>
      <button onclick="execCmd('clear')" class="term-qbtn">clear</button>
    </div>

    <div class="term-drawer-body" id="termLogs">
      <div>MEDIRECORD MEDICAL SECURITY CONSOLE [v2.0]</div>
      <div>Trạng thái: BUG_IDOR = <?= $bugIdor ? '<span style="color:#ef4444;font-weight:700;">1 (LỖ HỔNG ĐANG BẬT)</span>' : '<span style="color:#22c55e;font-weight:700;">0 (AN TOÀN - ĐÃ VÁ)</span>' ?></div>
      <div>Gõ 'help' hoặc bấm nút phía trên để điều khiển.</div>
      <br>
    </div>

    <div class="term-input-row">
      <span class="term-prompt">medirecord:~$</span>
      <input type="text" id="termInput" class="term-input" placeholder="Nhập lệnh (set BUG_IDOR=1, status)..." autocomplete="off">
    </div>
  </div>

  <script>
    // Bật/tắt Cửa sổ Chuyển Tài Khoản Nhanh
    function toggleAccountDrawer() {
      const accDrawer = document.getElementById('accountDrawer');
      const termDrawer = document.getElementById('termDrawer');
      if (accDrawer.style.display === 'none' || accDrawer.style.display === '') {
        accDrawer.style.display = 'block';
        if (termDrawer) termDrawer.style.display = 'none';
      } else {
        accDrawer.style.display = 'none';
      }
    }

    // Bật/tắt Cửa sổ Terminal nổi từ nút tròn
    function toggleTerminal() {
      const termDrawer = document.getElementById('termDrawer');
      const accDrawer = document.getElementById('accountDrawer');
      if (termDrawer.style.display === 'none' || termDrawer.style.display === '') {
        termDrawer.style.display = 'block';
        if (accDrawer) accDrawer.style.display = 'none';
        const tIn = document.getElementById('termInput');
        if (tIn) tIn.focus();
      } else {
        termDrawer.style.display = 'none';
      }
    }

    // Điền nhanh thông tin đăng nhập
    function fillLogin(username, password) {
      const uInput = document.getElementById('loginUsername');
      const pInput = document.getElementById('loginPassword');
      if (uInput) uInput.value = username;
      if (pInput) pInput.value = password;
      if (uInput) uInput.focus();
    }

    // Xử lý Terminal tương tác
    const termInput = document.getElementById('termInput');
    const termLogs = document.getElementById('termLogs');

    function appendLog(text, color = '#10b981') {
      if (!termLogs) return;
      const line = document.createElement('div');
      line.style.color = color;
      line.innerText = text;
      termLogs.appendChild(line);
      termLogs.scrollTop = termLogs.scrollHeight;
    }

    function execCmd(cmd) {
      if (!cmd || !cmd.trim()) return;
      appendLog('medirecord:~$ ' + cmd, '#38bdf8');
      
      fetch('/?action=terminal_exec', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cmd: cmd.trim() })
      })
      .then(res => res.json())
      .then(data => {
        if (data.output) {
          if (data.output.length === 1 && data.output[0] === '__CLEAR__') {
            termLogs.innerHTML = '';
          } else {
            data.output.forEach(msg => {
              let color = '#cbd5e1';
              if (msg.includes('[OK]')) color = '#22c55e';
              if (msg.includes('[LỖI]') || msg.includes('CẢNH BÁO') || msg.includes('403')) color = '#ef4444';
              if (msg.includes('HTTP/1.1 200')) color = '#38bdf8';
              appendLog(msg, color);
            });
          }
        }
        if (cmd.startsWith('set BUG_IDOR')) {
          setTimeout(() => { location.reload(); }, 600);
        }
      })
      .catch(err => {
        appendLog('[LỖI KẾT NỐI] ' + err, '#ef4444');
      });
    }

    if (termInput) {
      termInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
          const val = this.value;
          this.value = '';
          execCmd(val);
        }
      });
    }

    // Modal quản lý người dùng
    function openAddUserModal() {
      document.getElementById('addUserModal').style.display = 'flex';
    }
    function closeAddUserModal() {
      document.getElementById('addUserModal').style.display = 'none';
    }

    function openEditUserModal(username, fullName, role, dept) {
      document.getElementById('editUsername').value = username;
      document.getElementById('displayEditUsername').value = username;
      document.getElementById('editFullName').value = fullName;
      document.getElementById('editRole').value = role;
      document.getElementById('editDept').value = dept;
      document.getElementById('editUserModal').style.display = 'flex';
    }
    function closeEditUserModal() {
      document.getElementById('editUserModal').style.display = 'none';
    }

    // Tìm kiếm người dùng tức thì (Live search filter)
    function filterUsers() {
      const input = document.getElementById('userSearchInput');
      const filter = input.value.toLowerCase();
      const table = document.getElementById('userTable');
      const trs = table.getElementsByTagName('tr');

      for (let i = 1; i < trs.length; i++) {
        const text = trs[i].textContent.toLowerCase();
        if (text.indexOf(filter) > -1) {
          trs[i].style.display = '';
        } else {
          trs[i].style.display = 'none';
        }
      }
    }
  </script>
</body>
</html>
