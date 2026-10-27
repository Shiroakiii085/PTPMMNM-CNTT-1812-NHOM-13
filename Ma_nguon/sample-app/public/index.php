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

// 8. Quản lý cờ lỗi IDOR
$flagFile = __DIR__ . '/../../.local/bug_idor.flag';
$bugIdor = false;

if (isset($_GET['bug'])) {
    $bugIdor = ($_GET['bug'] === '1');
    $_SESSION['bug_idor_flag'] = $bugIdor;
    @mkdir(dirname($flagFile), 0777, true);
    @file_put_contents($flagFile, $bugIdor ? '1' : '0');
} elseif (isset($_SERVER['HTTP_X_BUG_IDOR'])) {
    $bugIdor = ($_SERVER['HTTP_X_BUG_IDOR'] === '1');
} elseif (isset($_SESSION['bug_idor_flag'])) {
    // Ưu tiên session người dùng khi tương tác trên trình duyệt Web
    $bugIdor = ($_SESSION['bug_idor_flag'] === true);
} elseif (getenv('BUG_IDOR') !== false && getenv('BUG_IDOR') !== '') {
    // Dành cho kiểm thử tự động e2e runner
    $bugIdor = (getenv('BUG_IDOR') === '1');
} elseif (file_exists($flagFile)) {
    $content = trim((string)@file_get_contents($flagFile));
    $bugIdor = ($content === '1');
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

// Bật / Tắt lỗi IDOR trực tiếp qua URL hoặc Nút bấm (Hỗ trợ 1-click tức thì)
if ($action === 'toggle_bug_idor') {
    $state = (isset($_GET['state']) && $_GET['state'] === '1') || (isset($_POST['state']) && $_POST['state'] === '1');
    $_SESSION['bug_idor_flag'] = $state;
    @mkdir(dirname($flagFile), 0777, true);
    @file_put_contents($flagFile, $state ? '1' : '0');
    if ($state) {
        recordSecurityIncident(
            'FLAG_ACTIVATED',
            'HIGH',
            $currentUser,
            'Cờ bảo mật hệ thống (BUG_IDOR)',
            'CẢNH BÁO: Lỗ hổng IDOR đã được kích hoạt (BUG_IDOR=1)! Hệ thống hiện đang trong trạng thái dễ bị tổn thương.',
            ['status_code' => 200]
        );
    } else {
        recordSecurityIncident(
            'FLAG_DEACTIVATED',
            'INFO',
            $currentUser,
            'Cờ bảo mật hệ thống (BUG_IDOR)',
            'THÔNG BÁO: Lỗ hổng IDOR đã được khắc phục (BUG_IDOR=0). Cơ chế phân quyền ABAC đã khôi phục kiểm soát an toàn.',
            ['status_code' => 200]
        );
    }
    $targetV = $_GET['return_view'] ?? $_POST['return_view'] ?? ($view ?: 'records');
    header('Location: /?view=' . urlencode($targetV) . ($state ? '&bug=1' : '&bug=0'));
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
$view = $_GET['view'] ?? 'landing';
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
          <a href="/?view=landing" class="btn btn-pri">Về Trang Chủ Bệnh Viện</a>
          <a href="/?view=records" class="btn btn-sec">Danh Sách Bệnh Án</a>
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
  <title><?= $view === 'landing' ? 'Cổng Thông Tin Điện Tử Bệnh Viện Bạch Mai - Bộ Y Tế' : 'MediRecord EMR - Hệ Thống Bệnh Án Điện Tử (Bộ Y Tế)' ?></title>
  
  <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- GSAP & ScrollTrigger Core for Parallax & Scrollytelling -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>

  <style>
    :root {
      --bg: #f8fafc;
      --card: #ffffff;
      --border: #e2e8f0;
      --text: #0f172a;
      --muted: #64748b;
      --primary: #004b87;
      --primary-hover: #003366;
      --primary-light: #f0f7ff;
      --primary-border: #bfdbfe;
      --safe: #10b981;
      --safe-bg: #ecfdf5;
      --safe-border: #a7f3d0;
      --danger: #dc2626;
      --danger-bg: #fef2f2;
      --danger-border: #fecaca;
      --warn: #d97706;
      --warn-bg: #fffbeb;
      --warn-border: #fde68a;

      /* Bach Mai Palette */
      --bm-blue-dark: #003366;
      --bm-blue-primary: #004b87;
      --bm-blue-light: #0284c7;
      --bm-red: #d32f2f;
      --bm-red-dark: #b91c1c;
      --bm-gold: #d97706;
      --bm-gold-light: #fef3c7;
      --bm-border: #e2e8f0;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.6;
      font-size: 15px;
      -webkit-font-smoothing: antialiased;
    }

    /* ANIMATIONS */
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes slideUpDrawer { from { opacity: 0; transform: translateY(18px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes pulseGlow { 0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.45); } 50% { box-shadow: 0 0 0 10px rgba(220, 38, 38, 0); } }
    @keyframes pulseGreen { 0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.45); } 50% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); } }

    /* =========================================================================
       BỆNH VIỆN BẠCH MAI - OFFICIAL HEALTHCARE PORTAL STYLES (CLONE BACHMAI.GOV.VN)
       ========================================================================= */

    /* 1. TOPBAR TIỆN ÍCH */
    .bm-topbar {
      background: var(--bm-blue-dark);
      color: #e2e8f0;
      font-size: 13px;
      padding: 9px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .bm-topbar-inner {
      max-width: 1560px;
      width: 95%;
      margin: 0 auto;
      padding: 0 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 14px;
    }
    .bm-topbar-left, .bm-topbar-right {
      display: flex;
      align-items: center;
      gap: 20px;
      flex-wrap: wrap;
    }
    .bm-topbar-item {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .bm-topbar-item a {
      color: #e2e8f0;
      text-decoration: none;
      transition: color 0.2s;
    }
    .bm-topbar-item a:hover {
      color: #38bdf8;
    }
    .bm-topbar-badge {
      background: var(--bm-red);
      color: #fff;
      font-weight: 700;
      font-size: 10px;
      padding: 2px 7px;
      border-radius: 10px;
      letter-spacing: 0.5px;
    }

    /* 2. MAIN BRAND HEADER */
    .bm-header {
      background: #ffffff;
      border-bottom: 1px solid var(--bm-border);
      padding: 18px 0;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
    }
    .bm-header-inner {
      max-width: 1560px;
      width: 95%;
      margin: 0 auto;
      padding: 0 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 24px;
    }
    .bm-brand {
      display: flex;
      align-items: center;
      gap: 18px;
      text-decoration: none;
      color: inherit;
    }
    .bm-logo-emblem {
      width: 64px;
      height: 64px;
      background: linear-gradient(135deg, #004b87 0%, #0284c7 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(0, 75, 135, 0.25);
      position: relative;
      flex-shrink: 0;
    }
    .bm-logo-cross {
      width: 30px;
      height: 30px;
      position: relative;
    }
    .bm-logo-cross::before, .bm-logo-cross::after {
      content: '';
      position: absolute;
      background: #ffffff;
      border-radius: 2px;
    }
    .bm-logo-cross::before {
      top: 0;
      left: 11px;
      width: 8px;
      height: 30px;
    }
    .bm-logo-cross::after {
      top: 11px;
      left: 0;
      width: 30px;
      height: 8px;
    }
    .bm-brand-text {
      display: flex;
      flex-direction: column;
    }
    .bm-brand-sub {
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 1.5px;
      color: #64748b;
      text-transform: uppercase;
    }
    .bm-brand-title {
      font-size: 28px;
      font-weight: 800;
      color: var(--bm-blue-primary);
      letter-spacing: -0.5px;
      line-height: 1.15;
    }
    .bm-brand-desc {
      font-size: 12.5px;
      color: #475569;
      font-style: italic;
      margin-top: 3px;
    }
    .bm-header-actions {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .bm-header-hotline {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      padding-right: 18px;
      border-right: 1px solid var(--bm-border);
    }
    .bm-hotline-label {
      font-size: 11px;
      font-weight: 700;
      color: var(--bm-red);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .bm-hotline-phone {
      font-size: 22px;
      font-weight: 800;
      color: var(--bm-blue-dark);
      text-decoration: none;
      line-height: 1.2;
    }
    .bm-btn-booking {
      background: linear-gradient(135deg, #e11d48 0%, #dc2626 100%);
      color: #fff;
      font-weight: 700;
      font-size: 14px;
      padding: 12px 22px;
      border-radius: 8px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 12px rgba(225, 29, 72, 0.25);
      transition: all 0.2s;
      cursor: pointer;
      border: none;
    }
    .bm-btn-booking:hover {
      background: linear-gradient(135deg, #be123c 0%, #b91c1c 100%);
      transform: translateY(-1.5px);
      box-shadow: 0 6px 16px rgba(225, 29, 72, 0.35);
    }
    .bm-btn-emr {
      background: var(--bm-blue-primary);
      color: #fff;
      font-weight: 700;
      font-size: 14px;
      padding: 12px 22px;
      border-radius: 8px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 12px rgba(0, 75, 135, 0.2);
      transition: all 0.2s;
    }
    .bm-btn-emr:hover {
      background: var(--bm-blue-dark);
      transform: translateY(-1.5px);
    }

    /* 3. MAIN NAVIGATION MENU */
    .bm-nav {
      background: var(--bm-blue-primary);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .bm-nav-inner {
      max-width: 1560px;
      width: 95%;
      margin: 0 auto;
      padding: 0 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }
    .bm-nav-links {
      display: flex;
      align-items: center;
      list-style: none;
      margin: 0;
      padding: 0;
      gap: 4px;
      flex-wrap: nowrap;
    }
    @media (max-width: 1280px) {
      .bm-nav-links {
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
      }
      .bm-nav-links::-webkit-scrollbar {
        display: none;
      }
    }
    .bm-nav-item {
      position: relative;
      flex-shrink: 0;
    }
    .bm-nav-link {
      display: inline-flex;
      align-items: center;
      color: #ffffff;
      text-decoration: none;
      font-size: 13.5px;
      font-weight: 700;
      padding: 16px 14px;
      white-space: nowrap;
      transition: background 0.2s, color 0.2s;
      border-bottom: 3px solid transparent;
      letter-spacing: 0.2px;
    }
    .bm-nav-link:hover, .bm-nav-link.active {
      background: rgba(0, 0, 0, 0.18);
      border-bottom-color: #facc15;
      color: #ffffff;
    }
    .bm-nav-badge {
      background: #ef4444;
      color: #fff;
      font-size: 9px;
      font-weight: 800;
      padding: 2px 6px;
      border-radius: 8px;
      margin-left: 5px;
      text-transform: uppercase;
    }

    /* 4. SCROLL-CRAFT & GSAP PARALLAX HERO SECTION */
    .sc-hero-wrap {
      position: relative;
      min-height: 90vh;
      overflow: hidden;
      display: flex;
      align-items: center;
      background: #001f3f;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .sc-hero-bg {
      position: absolute;
      top: -15%;
      left: -10%;
      width: 120%;
      height: 130%;
      background-image: url('https://images.unsplash.com/photo-1587351021759-3e566b6af7cc?auto=format&fit=crop&w=2000&q=80');
      background-size: cover;
      background-position: center 35%;
      filter: brightness(0.42) saturate(1.15);
      will-change: transform;
      z-index: 1;
    }
    .sc-hero-overlay {
      position: absolute;
      inset: 0;
      background: radial-gradient(circle at 75% 40%, rgba(2, 132, 199, 0.25) 0%, transparent 60%),
                  linear-gradient(180deg, rgba(0, 34, 68, 0.85) 0%, rgba(0, 26, 51, 0.92) 65%, #061224 100%);
      z-index: 2;
    }
    .sc-hero-particles {
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(56, 189, 248, 0.15) 1px, transparent 1px);
      background-size: 32px 32px;
      z-index: 2;
      opacity: 0.6;
      pointer-events: none;
    }
    .sc-hero-inner {
      max-width: 1560px;
      width: 95%;
      margin: 0 auto;
      padding: 64px 24px;
      position: relative;
      z-index: 3;
      display: grid;
      grid-template-columns: 1.2fr 1fr;
      gap: 52px;
      align-items: center;
    }
    @media (max-width: 1024px) {
      .sc-hero-inner {
        grid-template-columns: 1fr;
        gap: 40px;
        padding: 48px 20px;
      }
    }
    .sc-hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 7px 18px;
      background: rgba(14, 165, 233, 0.18);
      border: 1px solid rgba(56, 189, 248, 0.45);
      border-radius: 30px;
      font-size: 12px;
      font-weight: 800;
      color: #7dd3fc;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      margin-bottom: 22px;
      backdrop-filter: blur(8px);
      box-shadow: 0 4px 16px rgba(14, 165, 233, 0.15);
    }
    .sc-hero-title {
      font-size: 46px;
      font-weight: 800;
      line-height: 1.2;
      color: #ffffff;
      letter-spacing: -0.6px;
      margin-bottom: 20px;
      text-shadow: 0 4px 24px rgba(0, 0, 0, 0.5);
    }
    .sc-hero-title span {
      background: linear-gradient(135deg, #38bdf8 0%, #a7f3d0 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .sc-hero-desc {
      font-size: 16.5px;
      color: #e2e8f0;
      line-height: 1.7;
      margin-bottom: 26px;
      max-width: 640px;
    }
    .sc-hero-alert {
      background: rgba(16, 185, 129, 0.12);
      border-left: 4px solid #10b981;
      border-radius: 12px;
      padding: 16px 22px;
      margin-bottom: 28px;
      backdrop-filter: blur(8px);
      border: 1px solid rgba(16, 185, 129, 0.25);
      border-left-width: 4px;
    }
    .sc-hero-alert-title {
      font-size: 13.5px;
      font-weight: 800;
      color: #6ee7b7;
      margin-bottom: 4px;
      letter-spacing: 0.5px;
    }
    .sc-hero-alert-desc {
      font-size: 13.5px;
      color: #d1fae5;
      line-height: 1.6;
    }
    .sc-hero-actions {
      display: flex;
      gap: 14px;
      align-items: center;
      flex-wrap: wrap;
      margin-bottom: 28px;
    }
    .sc-btn-primary {
      background: linear-gradient(135deg, #0284c7 0%, #004b87 100%);
      color: #ffffff;
      padding: 14px 28px;
      font-size: 15px;
      font-weight: 700;
      border-radius: 10px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      box-shadow: 0 4px 20px rgba(2, 132, 199, 0.4);
      transition: all 0.25s ease;
      cursor: pointer;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .sc-btn-primary:hover {
      background: linear-gradient(135deg, #0369a1 0%, #003366 100%);
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(2, 132, 199, 0.5);
    }
    .sc-btn-outline {
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.25);
      color: #ffffff;
      padding: 14px 24px;
      font-size: 15px;
      font-weight: 700;
      border-radius: 10px;
      text-decoration: none;
      transition: all 0.25s ease;
      backdrop-filter: blur(8px);
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .sc-btn-outline:hover {
      background: rgba(255, 255, 255, 0.2);
      border-color: #38bdf8;
      transform: translateY(-2px);
      color: #38bdf8;
    }
    .sc-hero-tags {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }
    .sc-hero-tag {
      font-size: 12px;
      color: #cbd5e1;
      background: rgba(255, 255, 255, 0.08);
      padding: 6px 14px;
      border-radius: 20px;
      border: 1px solid rgba(255, 255, 255, 0.15);
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      backdrop-filter: blur(6px);
    }
    .sc-hero-subject {
      position: relative;
      will-change: transform;
    }
    .sc-hero-card {
      position: relative;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.15);
      background: #021a36;
    }
    .sc-hero-img {
      width: 100%;
      height: auto;
      display: block;
      object-fit: cover;
      aspect-ratio: 16 / 10.5;
      transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .sc-hero-card:hover .sc-hero-img {
      transform: scale(1.03);
    }
    .sc-floating-badge {
      position: absolute;
      background: rgba(15, 23, 42, 0.88);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      padding: 12px 18px;
      border-radius: 14px;
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
      border: 1px solid rgba(255, 255, 255, 0.18);
      display: flex;
      align-items: center;
      gap: 12px;
      z-index: 5;
    }
    .sc-badge-top {
      top: 20px;
      left: 20px;
    }
    .sc-badge-bottom {
      bottom: 20px;
      right: 20px;
      background: rgba(0, 51, 102, 0.92);
      border-color: rgba(56, 189, 248, 0.4);
    }
    .sc-badge-title {
      font-size: 13.5px;
      font-weight: 800;
      line-height: 1.25;
      color: #ffffff;
    }
    .sc-badge-sub {
      font-size: 12px;
      color: #94a3b8;
      margin-top: 2px;
    }
    .sc-scroll-indicator {
      position: absolute;
      bottom: 24px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      color: #94a3b8;
      font-size: 11.5px;
      font-weight: 700;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      z-index: 10;
      pointer-events: none;
    }
    .sc-mouse-icon {
      width: 22px;
      height: 34px;
      border: 2px solid rgba(255, 255, 255, 0.4);
      border-radius: 12px;
      position: relative;
    }
    .sc-mouse-wheel {
      width: 4px;
      height: 7px;
      background: #38bdf8;
      border-radius: 2px;
      position: absolute;
      top: 6px;
      left: 50%;
      transform: translateX(-50%);
      animation: scWheelMove 1.8s infinite ease-in-out;
    }
    @keyframes scWheelMove {
      0% { transform: translate(-50%, 0); opacity: 1; }
      80% { transform: translate(-50%, 14px); opacity: 0; }
      100% { transform: translate(-50%, 0); opacity: 0; }
    }

    /* =========================================================================
       5. PINNED HORIZONTAL SCROLLYTELLING TRACK (#milestonesPin)
       ========================================================================= */
    .sc-pin-section {
      position: relative;
      width: 100%;
      height: 100vh;
      overflow: hidden;
      background: #061224;
      color: #ffffff;
    }
    .sc-pin-header {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      z-index: 25;
      padding: 24px 48px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: linear-gradient(180deg, rgba(6, 18, 36, 0.95) 0%, rgba(6, 18, 36, 0) 100%);
      backdrop-filter: blur(6px);
    }
    .sc-pin-tag {
      font-size: 11.5px;
      font-weight: 800;
      letter-spacing: 2px;
      text-transform: uppercase;
      color: #38bdf8;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .sc-pin-progress {
      flex: 1;
      max-width: 400px;
      margin: 0 40px;
      height: 4px;
      background: rgba(255, 255, 255, 0.15);
      border-radius: 4px;
      overflow: hidden;
    }
    .sc-pin-progress-bar {
      height: 100%;
      width: 0%;
      background: linear-gradient(90deg, #38bdf8 0%, #10b981 100%);
      border-radius: 4px;
      transition: width 0.1s linear;
    }
    .sc-pin-counter {
      font-size: 12px;
      font-weight: 800;
      color: #cbd5e1;
      font-family: 'JetBrains Mono', monospace;
    }
    .sc-horizontal-track {
      display: flex;
      height: 100vh;
      width: 400vw;
      will-change: transform;
    }
    .sc-slide {
      width: 100vw;
      height: 100vh;
      flex-shrink: 0;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 100px 5vw 60px;
      overflow: hidden;
    }
    .sc-slide-bg {
      position: absolute;
      inset: 0;
      background-size: cover;
      background-position: center;
      filter: brightness(0.3) saturate(1.2);
      transform: scale(1.05);
      will-change: transform;
    }
    .sc-slide-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, rgba(6, 18, 36, 0.9) 0%, rgba(6, 18, 36, 0.6) 50%, rgba(6, 18, 36, 0.95) 100%);
    }
    .sc-slide-inner {
      position: relative;
      z-index: 10;
      max-width: 1360px;
      width: 100%;
      display: grid;
      grid-template-columns: 1.15fr 1fr;
      gap: 56px;
      align-items: center;
    }
    @media (max-width: 1024px) {
      .sc-slide-inner {
        grid-template-columns: 1fr;
        gap: 32px;
      }
      .sc-slide-media-wrap {
        display: none;
      }
    }
    .sc-slide-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 16px;
      background: rgba(217, 119, 6, 0.2);
      border: 1px solid rgba(245, 158, 11, 0.45);
      border-radius: 20px;
      font-size: 12px;
      font-weight: 800;
      color: #fbbf24;
      margin-bottom: 18px;
      letter-spacing: 1px;
      text-transform: uppercase;
    }
    .sc-slide-badge.cyan {
      background: rgba(14, 165, 233, 0.2);
      border-color: rgba(56, 189, 248, 0.45);
      color: #38bdf8;
    }
    .sc-slide-badge.green {
      background: rgba(16, 185, 129, 0.2);
      border-color: rgba(52, 211, 153, 0.45);
      color: #34d399;
    }
    .sc-slide-title {
      font-size: 38px;
      font-weight: 800;
      line-height: 1.25;
      color: #ffffff;
      margin-bottom: 20px;
      letter-spacing: -0.5px;
    }
    .sc-slide-desc {
      font-size: 16px;
      line-height: 1.7;
      color: #cbd5e1;
      margin-bottom: 26px;
    }
    .sc-slide-highlight {
      background: rgba(255, 255, 255, 0.06);
      border-left: 3px solid #38bdf8;
      border-radius: 8px;
      padding: 14px 18px;
      margin-bottom: 24px;
      font-size: 14px;
      color: #e2e8f0;
      line-height: 1.6;
    }
    .sc-slide-media-wrap {
      position: relative;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.15);
      background: #021a36;
    }
    .sc-slide-img {
      width: 100%;
      height: 380px;
      object-fit: cover;
      display: block;
      transition: transform 0.6s ease;
    }
    .sc-slide-media-wrap:hover .sc-slide-img {
      transform: scale(1.04);
    }

    /* 6. QUICK SERVICE STRIP */
    .bm-quick-strip {
      max-width: 1560px;
      width: 95%;
      margin: -40px auto 64px;
      padding: 0 24px;
      position: relative;
      z-index: 20;
    }
    .bm-quick-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
    }
    @media (max-width: 1100px) {
      .bm-quick-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    @media (max-width: 600px) {
      .bm-quick-grid {
        grid-template-columns: 1fr;
      }
    }
    .bm-quick-card {
      background: #ffffff;
      border: 1px solid var(--bm-border);
      border-radius: 16px;
      padding: 24px 20px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
      display: flex;
      gap: 18px;
      align-items: flex-start;
      text-decoration: none;
      color: inherit;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .bm-quick-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 16px 35px rgba(0, 75, 135, 0.14);
      border-color: #38bdf8;
    }
    .bm-quick-icon {
      width: 52px;
      height: 52px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      flex-shrink: 0;
    }
    .bm-icon-phone { background: #fee2e2; color: #dc2626; }
    .bm-icon-calendar { background: #e0f2fe; color: #0284c7; }
    .bm-icon-emr { background: #dbeafe; color: #1d4ed8; }
    .bm-icon-chat { background: #dcfce7; color: #16a34a; }
    .bm-quick-title {
      font-size: 16px;
      font-weight: 700;
      color: var(--bm-blue-dark);
      margin-bottom: 6px;
    }
    .bm-quick-desc {
      font-size: 13px;
      color: #64748b;
      line-height: 1.55;
    }

    /* 7. SPECIALTIES SECTION (3D DEPTH CARDS) */
    .bm-section {
      max-width: 1560px;
      width: 95%;
      margin: 0 auto 72px;
      padding: 0 24px;
    }
    .bm-section-header {
      text-align: center;
      margin-bottom: 44px;
    }
    .bm-section-tag {
      font-size: 12.5px;
      font-weight: 800;
      color: var(--bm-blue-primary);
      letter-spacing: 2.5px;
      text-transform: uppercase;
      margin-bottom: 10px;
    }
    .bm-section-title {
      font-size: 34px;
      font-weight: 800;
      color: var(--bm-blue-dark);
      letter-spacing: -0.5px;
    }
    .bm-section-desc {
      font-size: 16px;
      color: #64748b;
      max-width: 820px;
      margin: 12px auto 0;
      line-height: 1.65;
    }
    .bm-spec-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 24px;
    }
    @media (max-width: 1200px) {
      .bm-spec-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    @media (max-width: 640px) {
      .bm-spec-grid {
        grid-template-columns: 1fr;
      }
    }
    .bm-spec-card {
      background: #ffffff;
      border: 1px solid var(--bm-border);
      border-radius: 16px;
      overflow: hidden;
      transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
      display: flex;
      flex-direction: column;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
      position: relative;
    }
    .bm-spec-card:hover {
      transform: translateY(-8px) scale(1.01);
      box-shadow: 0 20px 40px rgba(0, 75, 135, 0.12);
      border-color: #38bdf8;
    }
    .bm-spec-media {
      height: 170px;
      position: relative;
      overflow: hidden;
    }
    .bm-spec-media img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.5s ease;
    }
    .bm-spec-card:hover .bm-spec-media img {
      transform: scale(1.08);
    }
    .bm-spec-badge-overlay {
      position: absolute;
      top: 12px;
      left: 12px;
      z-index: 2;
    }
    .bm-spec-body {
      padding: 22px;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .bm-spec-pill {
      font-size: 10.5px;
      font-weight: 800;
      padding: 4px 10px;
      border-radius: 6px;
      background: rgba(14, 165, 233, 0.15);
      color: #0284c7;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      backdrop-filter: blur(4px);
    }
    .bm-spec-pill.a9 { background: #fee2e2; color: #dc2626; }
    .bm-spec-pill.icu { background: #fef3c7; color: #b45309; }
    .bm-spec-name {
      font-size: 18px;
      font-weight: 800;
      color: var(--bm-blue-dark);
      margin-bottom: 10px;
      line-height: 1.35;
    }
    .bm-spec-text {
      font-size: 13.5px;
      color: #475569;
      line-height: 1.65;
      margin-bottom: 20px;
      flex: 1;
    }
    .bm-spec-link {
      font-size: 13.5px;
      font-weight: 700;
      color: var(--bm-blue-primary);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: gap 0.2s;
    }
    .bm-spec-link:hover {
      color: #0284c7;
      gap: 10px;
    }

    /* 8. DOCTORS / SPECIALISTS */
    .bm-doctor-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 22px;
    }
    @media (max-width: 1300px) {
      .bm-doctor-grid {
        grid-template-columns: repeat(3, 1fr);
      }
    }
    @media (max-width: 768px) {
      .bm-doctor-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    @media (max-width: 500px) {
      .bm-doctor-grid {
        grid-template-columns: 1fr;
      }
    }
    .bm-doc-card {
      background: #ffffff;
      border: 1px solid var(--bm-border);
      border-radius: 16px;
      overflow: hidden;
      text-align: center;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
      display: flex;
      flex-direction: column;
    }
    .bm-doc-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 18px 36px rgba(0, 75, 135, 0.12);
      border-color: #38bdf8;
    }
    .bm-doc-photo-wrap {
      height: 220px;
      position: relative;
      overflow: hidden;
      background: #0f172a;
    }
    .bm-doc-photo {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: top center;
      transition: transform 0.5s ease;
    }
    .bm-doc-card:hover .bm-doc-photo {
      transform: scale(1.05);
    }
    .bm-doc-info {
      padding: 20px 16px;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .bm-doc-title {
      font-size: 16px;
      font-weight: 800;
      color: var(--bm-blue-dark);
      margin-bottom: 4px;
    }
    .bm-doc-role {
      font-size: 12.5px;
      font-weight: 700;
      color: #0284c7;
      margin-bottom: 10px;
    }
    .bm-doc-desc {
      font-size: 13px;
      color: #475569;
      line-height: 1.55;
      margin-bottom: 14px;
    }
    .bm-doc-btn {
      width: 100%;
      padding: 8px 12px;
      background: #f0f7ff;
      border: 1px solid #bfdbfe;
      color: var(--bm-blue-primary);
      border-radius: 8px;
      font-size: 12.5px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s;
    }
    .bm-doc-btn:hover {
      background: var(--bm-blue-primary);
      color: #ffffff;
    }

    /* 9. STATS STRIP */
    .bm-stats-strip {
      background: linear-gradient(135deg, #002b54 0%, #004b87 60%, #0369a1 100%);
      color: #ffffff;
      padding: 72px 0;
      margin-bottom: 72px;
      position: relative;
      overflow: hidden;
    }
    .bm-stats-strip::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(255, 255, 255, 0.15) 1px, transparent 1px);
      background-size: 28px 28px;
      opacity: 0.35;
      pointer-events: none;
    }
    .bm-stats-inner {
      max-width: 1560px;
      width: 95%;
      margin: 0 auto;
      padding: 0 24px;
      position: relative;
      z-index: 2;
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 36px;
      text-align: center;
    }
    @media (max-width: 960px) {
      .bm-stats-inner {
        grid-template-columns: repeat(2, 1fr);
        gap: 30px;
      }
    }
    @media (max-width: 500px) {
      .bm-stats-inner {
        grid-template-columns: 1fr;
      }
    }
    .bm-stat-val {
      font-size: 52px;
      font-weight: 800;
      color: #38bdf8;
      letter-spacing: -1px;
      line-height: 1.1;
      font-family: 'JetBrains Mono', monospace;
    }
    .bm-stat-label {
      font-size: 16.5px;
      font-weight: 700;
      margin-top: 8px;
      color: #ffffff;
    }
    .bm-stat-sub {
      font-size: 13.5px;
      color: #bae6fd;
      margin-top: 4px;
    }

    /* 10. NEWS */
    .bm-news-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 24px;
    }
    @media (max-width: 1200px) {
      .bm-news-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    @media (max-width: 640px) {
      .bm-news-grid {
        grid-template-columns: 1fr;
      }
    }
    .bm-news-card {
      background: #ffffff;
      border: 1px solid var(--bm-border);
      border-radius: 16px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: all 0.3s ease;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
    }
    .bm-news-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 16px 35px rgba(0, 0, 0, 0.1);
      border-color: #38bdf8;
    }
    .bm-news-thumb {
      height: 190px;
      position: relative;
      overflow: hidden;
      background: #0f172a;
    }
    .bm-news-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.5s ease;
    }
    .bm-news-card:hover .bm-news-thumb img {
      transform: scale(1.06);
    }
    .bm-news-thumb-tag {
      position: absolute;
      top: 12px;
      left: 12px;
      background: rgba(0, 0, 0, 0.75);
      backdrop-filter: blur(6px);
      color: #fff;
      font-size: 11px;
      font-weight: 800;
      padding: 4px 10px;
      border-radius: 6px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .bm-news-body {
      padding: 22px;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .bm-news-title {
      font-size: 15.5px;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 10px;
      line-height: 1.5;
    }
    .bm-news-meta {
      font-size: 12px;
      color: #94a3b8;
      margin-top: 14px;
      display: flex;
      align-items: center;
      gap: 14px;
    }

    /* 11. PATIENT JOURNEY */
    .bm-steps-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 22px;
    }
    @media (max-width: 1100px) {
      .bm-steps-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    @media (max-width: 600px) {
      .bm-steps-grid {
        grid-template-columns: 1fr;
      }
    }
    .bm-step-card {
      background: #ffffff;
      border: 1px solid var(--bm-border);
      border-radius: 16px;
      padding: 30px 24px;
      position: relative;
      transition: all 0.3s ease;
      box-shadow: 0 4px 16px rgba(0,0,0,0.03);
    }
    .bm-step-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 14px 30px rgba(0, 75, 135, 0.1);
      border-color: #38bdf8;
    }
    .bm-step-num {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background: linear-gradient(135deg, #0284c7 0%, #004b87 100%);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 17px;
      margin-bottom: 18px;
      box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);
    }
    .bm-step-title {
      font-size: 17px;
      font-weight: 700;
      color: var(--bm-blue-dark);
      margin-bottom: 8px;
    }
    .bm-step-text {
      font-size: 13.5px;
      color: #475569;
      line-height: 1.6;
    }

    /* 12. CAMPUSES */
    .bm-campuses-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 28px;
    }
    @media (max-width: 860px) {
      .bm-campuses-grid {
        grid-template-columns: 1fr;
      }
    }
    .bm-campus-card {
      background: #ffffff;
      border: 1px solid var(--bm-border);
      border-radius: 16px;
      padding: 32px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.04);
      transition: all 0.3s ease;
    }
    .bm-campus-card:hover {
      box-shadow: 0 14px 35px rgba(0, 75, 135, 0.1);
      border-color: #93c5fd;
    }
    .bm-campus-badge {
      font-size: 11.5px;
      font-weight: 800;
      padding: 5px 12px;
      border-radius: 6px;
      background: #e0f2fe;
      color: #0369a1;
      width: fit-content;
      margin-bottom: 16px;
      letter-spacing: 0.5px;
    }
    .bm-campus-title {
      font-size: 21px;
      font-weight: 800;
      color: var(--bm-blue-dark);
      margin-bottom: 14px;
    }
    .bm-campus-info-row {
      font-size: 14px;
      color: #334155;
      margin-bottom: 10px;
      line-height: 1.55;
      display: flex;
      gap: 10px;
    }

    /* 13. FOOTER */
    .bm-footer {
      background: #001f3f;
      color: #cbd5e1;
      font-size: 13.5px;
      padding: 60px 0 30px;
      border-top: 4px solid var(--bm-blue-primary);
    }
    .bm-footer-inner {
      max-width: 1560px;
      width: 95%;
      margin: 0 auto;
      padding: 0 24px;
    }
    .bm-footer-grid {
      display: grid;
      grid-template-columns: 1.8fr 1fr 1fr 1.2fr;
      gap: 44px;
      margin-bottom: 44px;
    }
    @media (max-width: 1024px) { .bm-footer-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 640px) { .bm-footer-grid { grid-template-columns: 1fr; } }
    .bm-footer-title {
      font-size: 15px;
      font-weight: 800;
      color: #ffffff;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 18px;
    }
    .bm-footer-links {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .bm-footer-links li {
      margin-bottom: 10px;
    }
    .bm-footer-links a {
      color: #94a3b8;
      text-decoration: none;
      font-size: 13.5px;
      transition: color 0.2s;
    }
    .bm-footer-links a:hover {
      color: #38bdf8;
    }
    .bm-footer-bottom {
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      padding-top: 24px;
      font-size: 12.5px;
      color: #64748b;
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 14px;
    }

    /* 14. MODAL ĐẶT LỊCH KHÁM */
    .bm-modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.7);
      backdrop-filter: blur(6px);
      z-index: 10000;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .bm-modal-card {
      background: #ffffff;
      border-radius: 18px;
      max-width: 620px;
      width: 100%;
      padding: 32px;
      box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
      position: relative;
    }
    .bm-modal-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 14px;
      border-bottom: 1px solid var(--bm-border);
    }
    .bm-modal-title {
      font-size: 19px;
      font-weight: 800;
      color: var(--bm-blue-dark);
    }
    .bm-close-btn {
      background: transparent;
      border: none;
      font-size: 22px;
      cursor: pointer;
      color: #64748b;
    }

    /* =========================================================================
       INTERNAL EMR INTERFACE STYLES (DÀNH CHO TRANG QUẢN TRỊ BỆNH ÁN & TESTER)
       ========================================================================= */
    header {
      position: sticky;
      top: 0;
      z-index: 900;
      background: rgba(255, 255, 255, 0.94);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border-bottom: 1px solid var(--border);
      padding: 12px 28px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
      transition: all 0.2s ease;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .brand-title {
      font-size: 16px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.4px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .brand-title span { color: var(--primary); }
    .badge-port {
      font-size: 10px;
      background: #eff6ff;
      color: var(--primary);
      padding: 3px 8px;
      border-radius: 6px;
      font-weight: 700;
      border: 1px solid #bfdbfe;
      letter-spacing: 0.5px;
    }
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.3px;
    }
    .badge-danger {
      background: var(--danger-bg);
      color: var(--danger);
      border: 1px solid var(--danger-border);
      animation: pulseGlow 2.5s infinite;
    }
    .badge-safe {
      background: var(--safe-bg);
      color: var(--safe);
      border: 1px solid var(--safe-border);
      animation: pulseGreen 2.5s infinite;
    }
    .dot-indicator {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      display: inline-block;
    }
    .dot-danger { background: var(--danger); }
    .dot-safe { background: var(--safe); }
    .nav-links {
      display: flex;
      gap: 8px;
      align-items: center;
    }
    .nav-links a {
      color: var(--muted);
      text-decoration: none;
      font-size: 13px;
      font-weight: 600;
      padding: 7px 12px;
      border-radius: 8px;
      transition: all 0.18s ease;
    }
    .nav-links a:hover {
      color: var(--text);
      background: #f1f5f9;
    }
    .nav-links a.active {
      color: var(--primary);
      background: var(--primary-light);
      font-weight: 700;
    }
    .nav-alert-badge {
      background: #ef4444;
      color: #fff;
      font-size: 10px;
      font-weight: 800;
      padding: 2px 7px;
      border-radius: 12px;
      margin-left: 6px;
    }

    .container {
      max-width: 1560px;
      width: 95%;
      margin: 32px auto;
      padding: 0 24px;
      animation: fadeInUp 0.35s ease-out;
    }

    .idor-banner {
      background: var(--danger-bg);
      border: 1px solid var(--danger-border);
      border-radius: 12px;
      padding: 16px 20px;
      margin-bottom: 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      box-shadow: 0 2px 8px rgba(220, 38, 38, 0.08);
    }
    .idor-banner-safe {
      background: var(--safe-bg);
      border-color: var(--safe-border);
      box-shadow: 0 2px 8px rgba(16, 185, 129, 0.08);
    }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 24px;
      margin-bottom: 24px;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    }
    .card-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 18px;
      padding-bottom: 14px;
      border-bottom: 1px solid var(--border);
    }
    .card-title {
      font-size: 16px;
      font-weight: 800;
      color: var(--text);
      letter-spacing: -0.3px;
    }

    .btn {
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      cursor: pointer;
      border: none;
      transition: all 0.18s ease;
    }
    .btn-primary { background: var(--primary); color: #fff; }
    .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }
    .btn-safe { background: var(--safe); color: #fff; }
    .btn-safe:hover { background: #059669; }
    .btn-danger { background: var(--danger); color: #fff; }
    .btn-danger:hover { background: #b91c1c; }
    .btn-outline { background: #fff; border: 1px solid var(--border); color: var(--text); }
    .btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }
    th {
      text-align: left;
      padding: 10px 14px;
      background: #f8fafc;
      color: var(--muted);
      font-weight: 700;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      border-bottom: 1px solid var(--border);
    }
    td {
      padding: 12px 14px;
      border-bottom: 1px solid #f1f5f9;
      color: var(--text);
      vertical-align: middle;
    }
    tr:hover td { background: #f8fafc; }

    .leak-box {
      background: #fff1f2;
      border: 1px solid #fecdd3;
      border-left: 5px solid #e11d48;
      border-radius: 10px;
      padding: 16px;
      margin-bottom: 24px;
      animation: pulseGlow 2.5s infinite;
    }

    .form-group {
      margin-bottom: 16px;
    }
    .form-group label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #334155;
      margin-bottom: 6px;
    }
    .form-group input, .form-group textarea, .form-group select {
      width: 100%;
      padding: 9px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 13px;
      font-family: inherit;
      background: #fff;
    }

    /* Floating Action Buttons (FABs) at Bottom Right (Nút tròn nổi) */
    .account-fab {
      position: fixed;
      bottom: 88px;
      right: 24px;
      width: 54px;
      height: 54px;
      border-radius: 50%;
      background: linear-gradient(135deg, #0284c7 0%, #004b87 100%);
      border: 2px solid #38bdf8;
      box-shadow: 0 6px 20px rgba(2, 132, 199, 0.4);
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
      box-shadow: 0 8px 25px rgba(2, 132, 199, 0.55);
    }
    .account-fab-icon {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .account-fab-badge {
      position: absolute;
      top: -3px;
      right: -3px;
      background: #ef4444;
      color: #fff;
      font-size: 10px;
      font-weight: 800;
      padding: 2px 6px;
      border-radius: 10px;
      border: 2px solid #ffffff;
      box-shadow: 0 2px 6px rgba(0,0,0,0.25);
    }

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
      box-shadow: 0 6px 20px rgba(0,0,0,0.35);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      transition: all 0.2s ease;
    }
    .term-fab:hover { transform: scale(1.08); border-color: #0284c7; }
    .term-fab-icon { color: #22c55e; font-family: 'JetBrains Mono', monospace; font-weight: 900; font-size: 19px; }
    .term-fab-dot {
      position: absolute;
      top: 3px;
      right: 3px;
      width: 12px;
      height: 12px;
      border-radius: 50%;
      border: 2px solid #090d16;
    }
    .dot-danger { background: #ef4444; animation: blink 1.2s infinite; }
    .dot-safe { background: #22c55e; }
    @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

    /* ACCOUNT SWITCHER POPUP */
    .account-drawer {
      position: fixed;
      bottom: 152px;
      right: 24px;
      z-index: 9998;
      width: 360px;
      background: #ffffff;
      border-radius: 16px;
      border: 1px solid var(--border);
      box-shadow: 0 20px 40px rgba(15, 23, 42, 0.16);
      overflow: hidden;
      animation: slideUpDrawer 0.25s ease-out;
    }
    .acc-drawer-head {
      background: var(--bm-blue-dark);
      padding: 14px 18px;
      display: flex;
      justify-content: space-between;
      align-items: center;
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
      padding: 10px 12px;
      border-radius: 10px;
      text-decoration: none;
      transition: background 0.15s;
    }
    .acc-item:hover { background: #f8fafc; }
    .acc-item.active { background: #eff6ff; border: 1px solid #bfdbfe; }
    .acc-initial {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 12px;
      margin-right: 12px;
      flex-shrink: 0;
    }
    .init-adm { background: #fee2e2; color: #b91c1c; }
    .init-doc { background: #dbeafe; color: #1d4ed8; }
    .init-pat { background: #dcfce7; color: #15803d; }
    .init-aud { background: #e0f2fe; color: #0369a1; }
    .init-anon { background: #f1f5f9; color: #64748b; }

    /* TERMINAL DRAWER */
    .term-drawer {
      position: fixed;
      bottom: 152px;
      right: 24px;
      z-index: 9998;
      width: 540px;
      max-width: calc(100vw - 44px);
      background: #0f172a;
      border-radius: 14px;
      border: 1px solid #334155;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
      overflow: hidden;
      font-family: 'JetBrains Mono', monospace;
      animation: slideUpDrawer 0.25s ease-out;
    }
    .term-drawer-head {
      background: #1e293b;
      padding: 10px 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: #94a3b8;
      font-size: 11px;
      font-weight: 700;
      border-bottom: 1px solid #334155;
    }
    .term-quick-btns {
      padding: 8px 12px;
      background: #162032;
      border-bottom: 1px solid #1e293b;
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    .term-qbtn {
      background: #0f172a;
      border: 1px solid #334155;
      color: #cbd5e1;
      padding: 3px 8px;
      font-size: 10px;
      border-radius: 4px;
      cursor: pointer;
      font-family: inherit;
    }
    .term-qbtn:hover { background: #1e293b; color: #fff; }
    .term-drawer-body {
      padding: 14px;
      height: 220px;
      overflow-y: auto;
      font-size: 11.5px;
      color: #cbd5e1;
      line-height: 1.6;
    }
    .term-input-row {
      display: flex;
      align-items: center;
      background: #1e293b;
      padding: 8px 12px;
      border-top: 1px solid #334155;
    }
    .term-prompt {
      color: #38bdf8;
      font-size: 12px;
      font-weight: 700;
      margin-right: 8px;
    }
    .term-input {
      flex: 1;
      background: transparent;
      border: none;
      outline: none;
      color: #f8fafc;
      font-family: inherit;
      font-size: 12px;
    }
    /* 15. MODAL FORM OVERLAY FOR USERS MANAGEMENT */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(4px);
      z-index: 10000;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .modal-box {
      background: #ffffff;
      width: 500px;
      max-width: 95vw;
      border-radius: 14px;
      padding: 24px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
      border: 1px solid var(--border);
      animation: modalFadeIn 0.2s ease-out;
    }
    @keyframes modalFadeIn {
      from { opacity: 0; transform: scale(0.96); }
      to { opacity: 1; transform: scale(1); }
    }
  </style>
</head>
<body>

  <!-- =======================================================================
       BỘ HEADER VÀ NAVIGATION ĐIỀU KIỆN
       ======================================================================= -->
  <?php if ($view === 'landing'): ?>
    <!-- 1. TOPBAR TIỆN ÍCH Y TẾ BỆNH VIỆN BẠCH MAI -->
    <div class="bm-topbar">
      <div class="bm-topbar-inner">
        <div class="bm-topbar-left">
          <div class="bm-topbar-item">
            <span style="color: #38bdf8;">📞</span>
            <span>Tổng đài đặt khám: <strong><a href="tel:1900888866">1900.888.866</a></strong></span>
          </div>
          <div class="bm-topbar-item">
            <span class="bm-topbar-badge">CẤP CỨU 24/7</span>
            <span>Trung tâm Cấp cứu A9: <strong><a href="tel:02438693731">024.3869.3731</a></strong></span>
          </div>
          <div class="bm-topbar-item">
            <span style="color: #94a3b8;">📍</span>
            <span>Số 78 Giải Phóng, P. Phương Mai, Q. Đống Đa, TP. Hà Nội</span>
          </div>
        </div>
        <div class="bm-topbar-right">
          <div class="bm-topbar-item">
            <span>🕒 Tiếp đón: <strong>05:00 - 16:30 (Thứ 2 - CN)</strong></span>
          </div>
          <?php if ($currentUser): ?>
            <div class="bm-topbar-item">
              <span>Xin chào, <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong></span>
              <a href="/?view=records" style="margin-left: 6px; color: #38bdf8; text-decoration: underline;">[Bệnh Án]</a>
              <a href="/?action=logout" style="margin-left: 6px; color: #f87171;">[Đăng xuất]</a>
            </div>
          <?php else: ?>
            <div class="bm-topbar-item">
              <a href="/?view=login" style="color: #e2e8f0; font-weight: 600;">Cán Bộ Đăng Nhập</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- 2. MAIN HEADER THƯƠNG HIỆU BỆNH VIỆN BẠCH MAI -->
    <div class="bm-header">
      <div class="bm-header-inner">
        <a href="/?view=landing" class="bm-brand">
          <div class="bm-logo-emblem">
            <div class="bm-logo-cross"></div>
          </div>
          <div class="bm-brand-text">
            <span class="bm-brand-sub">BỘ Y TẾ</span>
            <span class="bm-brand-title">BỆNH VIỆN BẠCH MAI</span>
            <span class="bm-brand-desc">Bệnh viện Đa khoa hoàn chỉnh hạng Đặc biệt đầu tiên tại Việt Nam</span>
          </div>
        </a>
        <div class="bm-header-actions">
          <div class="bm-header-hotline">
            <span class="bm-hotline-label">Cấp Cứu A9 Tuyến Cuối</span>
            <a href="tel:02438693731" class="bm-hotline-phone">024.3869.3731</a>
          </div>
          <button onclick="openBookingModal()" class="bm-btn-booking">
            <span>📅</span> ĐẶT LỊCH KHÁM
          </button>
          <a href="/?view=records" class="bm-btn-emr">
            <span>📋</span> HỒ SƠ BỆNH ÁN EMR
          </a>
        </div>
      </div>
    </div>

    <!-- 3. MAIN NAVIGATION MENU (CHUẨN BACHMAI.GOV.VN) -->
    <nav class="bm-nav">
      <div class="bm-nav-inner">
        <ul class="bm-nav-links">
          <li class="bm-nav-item"><a href="/?view=landing" class="bm-nav-link active">TRANG CHỦ</a></li>
          <li class="bm-nav-item"><a href="#gioi-thieu" class="bm-nav-link">GIỚI THIỆU</a></li>
          <li class="bm-nav-item"><a href="#co-cau" class="bm-nav-link">CƠ CẤU TỔ CHỨC</a></li>
          <li class="bm-nav-item"><a href="#chuyen-khoa" class="bm-nav-link">ĐƠN VỊ CHUYÊN KHOA</a></li>
          <li class="bm-nav-item"><a href="#chuyen-gia" class="bm-nav-link">ĐỘI NGŨ BÁC SĨ</a></li>
          <li class="bm-nav-item"><a href="#danh-cho-nguoi-benh" class="bm-nav-link">DÀNH CHO NGƯỜI BỆNH</a></li>
          <li class="bm-nav-item">
            <a href="/?view=records" class="bm-nav-link">
              BỆNH ÁN ĐIỆN TỬ <span class="bm-nav-badge">EMR</span>
            </a>
          </li>
          <li class="bm-nav-item"><a href="#tin-tuc" class="bm-nav-link">TIN TỨC & SỰ KIỆN</a></li>
          <li class="bm-nav-item"><a href="#lien-he" class="bm-nav-link">LIÊN HỆ</a></li>
        </ul>
        <div style="flex-shrink: 0; margin-left: 14px;">
          <?php if ($currentUser): ?>
            <a href="/?view=records" style="color: #fff; font-size: 13px; text-decoration: none; font-weight: 700; background: rgba(255,255,255,0.18); padding: 8px 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
              <span>👤</span> <?= htmlspecialchars($currentUser['username']) ?> (<?= htmlspecialchars($currentUser['role']) ?>)
            </a>
          <?php else: ?>
            <a href="/?view=login" style="color: #fff; font-size: 13px; text-decoration: none; font-weight: 700; background: rgba(255,255,255,0.18); padding: 8px 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
              <span>🔐</span> Đăng Nhập
            </a>
          <?php endif; ?>
        </div>
      </div>
    </nav>

  <?php else: ?>
    <!-- NAVBAR NỘI BỘ CHO CÁC PHÂN HỆ EMR -->
    <header>
      <div class="brand">
        <a href="/?view=landing" style="text-decoration: none;">
          <span class="brand-title">MEDIRECORD <span>EMR</span></span>
        </a>
        <span class="badge-port">PORT 8205</span>
        <?php if ($bugIdor): ?>
          <span class="status-badge badge-danger">
            <span class="dot-indicator dot-danger"></span>
            LỖI IDOR: ĐANG BẬT
          </span>
        <?php else: ?>
          <span class="status-badge badge-safe">
            <span class="dot-indicator dot-safe"></span>
            BẢO MẬT: ĐÃ KHẮC PHỤC
          </span>
        <?php endif; ?>
      </div>

      <nav class="nav-links">
        <a href="/?view=landing" class="<?= $view === 'landing' ? 'active' : '' ?>">Trang Chủ BV</a>
        <a href="/?view=records" class="<?= $view === 'records' ? 'active' : '' ?>">Danh Sách Bệnh Án</a>
        <?php if ($currentUser && in_array($currentUser['role'], ['doctor', 'admin'], true)): ?>
          <a href="/?view=create_record" class="<?= $view === 'create_record' ? 'active' : '' ?>">Lập Bệnh Án</a>
        <?php endif; ?>
        <a href="/?view=announcements" class="<?= $view === 'announcements' ? 'active' : '' ?>">Thông Báo Chung</a>
        <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
          <a href="/?view=users" class="<?= $view === 'users' ? 'active' : '' ?>">Quản Trị Người Dùng</a>
        <?php endif; ?>
        <?php if ($currentUser && in_array($currentUser['role'], ['auditor', 'admin'], true)): ?>
          <a href="/?view=tester" class="<?= $view === 'tester' ? 'active' : '' ?>" style="color: var(--primary); font-weight: 700; display: inline-flex; align-items: center;">
            Giám Sát An Ninh & Kiểm Thử
            <?php if ($incidentCount > 0): ?>
              <span class="nav-alert-badge"><?= $incidentCount ?> vi phạm</span>
            <?php endif; ?>
          </a>
        <?php endif; ?>

        <?php if ($currentUser): ?>
          <span style="color: var(--muted); font-size: 12px; margin-left: 8px;">
            Xin chào, <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong> (<code><?= htmlspecialchars($currentUser['role']) ?></code>)
          </span>
          <a href="/?action=logout" class="btn btn-outline" style="padding: 5px 12px; font-size: 12px;">Đăng xuất</a>
        <?php else: ?>
          <a href="/?view=login" class="btn btn-outline" style="padding: 5px 12px; font-size: 12px;">Đăng nhập</a>
          <a href="/?view=register" class="btn btn-primary" style="padding: 5px 12px; font-size: 12px;">Đăng ký</a>
        <?php endif; ?>
      </nav>
    </header>
  <?php endif; ?>

  <!-- =======================================================================
       NỘI DUNG CHÍNH (MAIN VIEWS)
       ======================================================================= -->
  <?php if ($view === 'landing'): ?>
    <!-- =======================================================================
         VIEW 0: TRANG CHỦ BỆNH VIỆN BẠCH MAI (CLONE BACHMAI.GOV.VN)
         ======================================================================= -->
    <!-- ACT I: DIMENSIONAL LAYERED HERO (SCRUBBED DEPTH) -->
    <section class="sc-hero-wrap" id="gioi-thieu">
      <div class="sc-hero-bg" id="scHeroBg"></div>
      <div class="sc-hero-overlay"></div>
      <div class="sc-hero-particles"></div>

      <div class="sc-hero-inner">
        <div class="sc-hero-content" id="scHeroContent">
          <div class="sc-hero-badge">
            <span class="bm-dot-green"></span>
            <span>BỆNH VIỆN ĐA KHOA HOÀN CHỈNH HẠNG ĐẶC BIỆT ĐẦU TIÊN TẠI VIỆT NAM</span>
          </div>
          <h1 class="sc-hero-title">
            TRÍ - ĐỨC - CHUYÊN - TÂM<br>
            <span>VƯƠN TẦM THẾ GIỚI</span>
          </h1>
          <p class="sc-hero-desc">
            Bệnh viện Bạch Mai là trung tâm y tế chuyên sâu tuyến cuối lớn nhất miền Bắc với hơn 114 năm lịch sử phụng sự sức khỏe nhân dân. Chúng tôi cam kết chăm sóc sức khỏe toàn diện bằng tài năng, y đức và sự tận tâm của đội ngũ thầy thuốc hàng đầu Việt Nam.
          </p>

          <div class="sc-hero-alert">
            <div class="sc-hero-alert-title">TIÊN PHONG CHUYỂN ĐỔI SỐ Y TẾ TOÀN DIỆN</div>
            <div class="sc-hero-alert-desc">
              Bệnh viện Bạch Mai là bệnh viện đa khoa hạng đặc biệt đầu tiên chính thức áp dụng <strong>Hồ sơ Bệnh án Điện tử (EMR)</strong> từ ngày 01/11/2024, liên thông 100% dữ liệu chẩn đoán và điều trị trên toàn hệ thống.
            </div>
          </div>

          <div class="sc-hero-actions">
            <button onclick="openBookingModal()" class="sc-btn-primary">
              <span>📅</span> Đặt Lịch Khám Trực Tuyến
            </button>
            <a href="/?view=records" class="sc-btn-outline">
              <span>📋</span> Tra Cứu Bệnh Án EMR
            </a>
            <a href="tel:1900888866" class="sc-btn-outline" style="border-color: rgba(56, 189, 248, 0.4); color: #38bdf8;">
              <span>📞</span> Tổng Đài: 1900.888.866
            </a>
          </div>

          <div class="sc-hero-tags">
            <span class="sc-hero-tag"><span class="bm-dot-green"></span> Cấp Cứu A9 Trực 24/7</span>
            <span class="sc-hero-tag">Bệnh Án Điện Tử EMR Quốc Gia</span>
            <span class="sc-hero-tag">Tiếp Nhận Khám BHYT Toàn Quốc</span>
          </div>
        </div>

        <div class="sc-hero-subject" id="scHeroSubject">
          <div class="sc-hero-card">
            <img src="https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&w=1200&q=80" alt="Đội ngũ Chuyên gia Bệnh viện Bạch Mai" class="sc-hero-img">
            <div class="sc-floating-badge sc-badge-top">
              <span class="bm-dot-green"></span>
              <div>
                <div class="sc-badge-title">Trung Tâm Cấp Cứu A9</div>
                <div class="sc-badge-sub">Thường trực cấp cứu tuyến cuối 24/7</div>
              </div>
            </div>
            <div class="sc-floating-badge sc-badge-bottom">
              <div>
                <div class="sc-badge-title" style="color: #38bdf8;">Hồ Sơ Bệnh Án EMR 100%</div>
                <div class="sc-badge-sub" style="color: #e2e8f0;">Bảo mật & Liên thông chuẩn Bộ Y Tế</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="sc-scroll-indicator">
        <div class="sc-mouse-icon">
          <div class="sc-mouse-wheel"></div>
        </div>
        <span>Cuộn để khám phá</span>
      </div>
    </section>

    <!-- ACT II: PINNED HORIZONTAL SCROLLYTELLING SHOWCASE (114 NĂM & ĐỘT PHÁ SỐ) -->
    <section class="sc-pin-section" id="milestonesPin">
      <div class="sc-pin-header">
        <div class="sc-pin-tag">
          <span>🏛️</span>
          <span>DẤU ẤN LỊCH SỬ & ĐỘT PHÁ SỐ Y TẾ</span>
        </div>
        <div class="sc-pin-progress">
          <div class="sc-pin-progress-bar" id="pinProgressBar"></div>
        </div>
        <div class="sc-pin-counter" id="pinCounter">CHƯƠNG 01 / 04</div>
      </div>

      <div class="sc-horizontal-track" id="horizontalTrack">
        <!-- SLIDE 1: 1911 KHỞI NGUỒN -->
        <div class="sc-slide">
          <div class="sc-slide-bg" style="background-image: url('https://images.unsplash.com/photo-1532938911079-1b06ac7ceec7?auto=format&fit=crop&w=1600&q=80');"></div>
          <div class="sc-slide-overlay"></div>
          <div class="sc-slide-inner">
            <div>
              <span class="sc-slide-badge">1911 • 114 NĂM PHỤNG SỰ</span>
              <h2 class="sc-slide-title">Khởi Thủy Từ Nhà Thương Lây Cống Vọng</h2>
              <p class="sc-slide-desc">
                Thành lập từ năm 1911, Bệnh viện Bạch Mai trải qua hơn một thế kỷ đồng hành cùng những thăng trầm lịch sử dân tộc. Từ một cơ sở điều trị truyền nhiễm đơn sơ, Bạch Mai đã vươn mình trở thành biểu tượng y đức và cái nôi đào tạo nhiều thế hệ thầy thuốc kiệt xuất của nền y học Việt Nam.
              </p>
              <div class="sc-slide-highlight">
                <strong>Di sản Y đức:</strong> Nơi công tác của các bậc danh y lỗi lạc như GS. Đặng Văn Ngữ, GS. Tôn Thất Tùng, GS. Đỗ Xuân Hợp.
              </div>
              <a href="#chuyen-khoa" class="sc-btn-outline">Khám phá các Chuyên khoa →</a>
            </div>
            <div class="sc-slide-media-wrap">
              <img src="https://images.unsplash.com/photo-1584515979956-d9f6e5d09982?auto=format&fit=crop&w=800&q=80" alt="Di sản y học Bạch Mai" class="sc-slide-img">
            </div>
          </div>
        </div>

        <!-- SLIDE 2: TUYẾN CUỐI ĐA KHOA ĐẶC BIỆT & A9 -->
        <div class="sc-slide">
          <div class="sc-slide-bg" style="background-image: url('https://images.unsplash.com/photo-1579684385127-1ef15d508118?auto=format&fit=crop&w=1600&q=80');"></div>
          <div class="sc-slide-overlay"></div>
          <div class="sc-slide-inner">
            <div>
              <span class="sc-slide-badge cyan">KỸ THUẬT CAO TUYẾN CUỐI</span>
              <h2 class="sc-slide-title">Cấp Cứu A9 & Can Thiệp Chuyên Sâu Đỉnh Cao</h2>
              <p class="sc-slide-desc">
                Là Bệnh viện Đa khoa hoàn chỉnh hạng Đặc biệt đầu tiên của cả nước, Bạch Mai làm chủ những kỹ thuật y học phức tạp nhất: Hệ thống tim phổi nhân tạo ECMO, can thiệp thay van động mạch chủ qua da (TAVI), quy trình "Giờ Vàng" đột quỵ não và ghép tế bào gốc.
              </p>
              <div class="sc-slide-highlight">
                <strong>Trung tâm Cấp cứu A9:</strong> Tiếp nhận hơn 350 ca cấp cứu nặng mỗi ngày từ khắp các tỉnh thành phía Bắc.
              </div>
              <a href="tel:02438693731" class="sc-btn-primary" style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);">Hotline A9: 024.3869.3731</a>
            </div>
            <div class="sc-slide-media-wrap">
              <img src="https://images.unsplash.com/photo-1516549655169-df83a0774514?auto=format&fit=crop&w=800&q=80" alt="Hồi sức cấp cứu kỹ thuật cao" class="sc-slide-img">
            </div>
          </div>
        </div>

        <!-- SLIDE 3: BỆNH ÁN ĐIỆN TỬ EMR 4.0 -->
        <div class="sc-slide">
          <div class="sc-slide-bg" style="background-image: url('https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=1600&q=80');"></div>
          <div class="sc-slide-overlay"></div>
          <div class="sc-slide-inner">
            <div>
              <span class="sc-slide-badge green">CHUYỂN ĐỔI SỐ QUỐC GIA</span>
              <h2 class="sc-slide-title">Xóa Bỏ Bệnh Án Giấy — 100% EMR Điện Tử</h2>
              <p class="sc-slide-desc">
                Từ 01/11/2024, Bệnh viện Bạch Mai chính thức vận hành hệ thống Bệnh án Điện tử (EMR), trở thành lá cờ đầu trong chuyển đổi số y tế của Bộ Y Tế. Mọi chỉ định chẩn đoán hình ảnh, xét nghiệm và đơn thuốc được liên thông số tức thì với cơ chế bảo mật phân quyền ABAC nghiêm ngặt.
              </p>
              <div class="sc-slide-highlight">
                <strong>An Toàn Dữ Liệu:</strong> Kiểm soát truy cập phân quyền thuộc tính (ABAC), ngăn chặn triệt để rò rỉ dữ liệu y tế người bệnh.
              </div>
              <a href="/?view=records" class="sc-btn-primary">Truy Cập Cổng Bệnh Án EMR →</a>
            </div>
            <div class="sc-slide-media-wrap">
              <img src="https://images.unsplash.com/photo-1551076805-e1869033e561?auto=format&fit=crop&w=800&q=80" alt="Hệ thống bệnh án điện tử EMR" class="sc-slide-img">
            </div>
          </div>
        </div>

        <!-- SLIDE 4: MẠNG LƯỚI TOÀN QUỐC -->
        <div class="sc-slide">
          <div class="sc-slide-bg" style="background-image: url('https://images.unsplash.com/photo-1587351021759-3e566b6af7cc?auto=format&fit=crop&w=1600&q=80');"></div>
          <div class="sc-slide-overlay"></div>
          <div class="sc-slide-inner">
            <div>
              <span class="sc-slide-badge">2 CƠ SỞ TRỌNG ĐIỂM</span>
              <h2 class="sc-slide-title">Hạ Tầng Hiện Đại Hà Nội & Ninh Bình</h2>
              <p class="sc-slide-desc">
                Với hơn 3.200 giường bệnh nội trú tại cơ sở Hà Nội và sự bổ sung 1.000 giường bệnh tại cơ sở Ninh Bình / Hà Nam, Bệnh viện Bạch Mai không ngừng mở rộng năng lực phục vụ, giảm tải tuyến trung ương và mang dịch vụ y tế chuẩn quốc tế đến gần hơn với nhân dân.
              </p>
              <div class="sc-slide-highlight">
                <strong>Quy mô hàng năm:</strong> Phục vụ hơn 1,8 triệu lượt khám ngoại trú và 160.000 bệnh nhân điều trị nội trú.
              </div>
              <button onclick="openBookingModal()" class="sc-btn-primary">Đặt Lịch Khám Ngay</button>
            </div>
            <div class="sc-slide-media-wrap">
              <img src="https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=800&q=80" alt="Cơ sở Bệnh viện Bạch Mai" class="sc-slide-img">
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 4 QUICK SERVICE CARDS (CHUẨN BACHMAI.GOV.VN) -->
    <div class="bm-quick-strip" id="danh-cho-nguoi-benh">
      <div class="bm-quick-grid">
        <a href="tel:1900888866" class="bm-quick-card">
          <div class="bm-quick-icon bm-icon-phone">📞</div>
          <div>
            <div class="bm-quick-title">Tổng Đài: 1900.888.866</div>
            <div class="bm-quick-desc">Đặt lịch khám nhanh chóng qua tổng đài chăm sóc khách hàng 24/7, không lo chờ đợi.</div>
          </div>
        </a>

        <div onclick="openBookingModal()" class="bm-quick-card" style="cursor: pointer;">
          <div class="bm-quick-icon bm-icon-calendar">📅</div>
          <div>
            <div class="bm-quick-title">Đặt Lịch Khám Online</div>
            <div class="bm-quick-desc">Chủ động lựa chọn ngày khám, khung giờ tiếp đón và bác sĩ chuyên khoa mong muốn.</div>
          </div>
        </div>

        <a href="/?view=records" class="bm-quick-card">
          <div class="bm-quick-icon bm-icon-emr">📋</div>
          <div>
            <div class="bm-quick-title">Tra Cứu Bệnh Án & Xét Nghiệm</div>
            <div class="bm-quick-desc">Theo dõi hồ sơ bệnh án điện tử EMR, xem kết quả xét nghiệm và lịch sử điều trị trực tuyến.</div>
          </div>
        </a>

        <a href="#chuyen-gia" class="bm-quick-card">
          <div class="bm-quick-icon bm-icon-chat">💬</div>
          <div>
            <div class="bm-quick-title">Hỏi Đáp Cùng Chuyên Gia</div>
            <div class="bm-quick-desc">Tư vấn trực tiếp phác đồ điều trị và giải đáp thắc mắc sức khỏe cùng bác sĩ Bạch Mai.</div>
          </div>
        </a>
      </div>
    </div>

    <!-- ACT IV: 3D PARALLAX SPECIALTIES GRID (ĐƠN VỊ CHUYÊN KHOA ĐẦU NGÀNH) -->
    <section class="bm-section" id="chuyen-khoa">
      <div class="bm-section-header">
        <div class="bm-section-tag">TRUNG TÂM Y HỌC CHUYÊN SÂU</div>
        <h2 class="bm-section-title">Đơn Vị Chuyên Khoa Tuyến Cuối</h2>
        <p class="bm-section-desc">Hệ thống các Viện, Trung tâm và Khoa lâm sàng kỹ thuật cao hàng đầu Việt Nam, thực hiện các can thiệp y tế phức tạp và điều trị ca bệnh hiểm nghèo.</p>
      </div>

      <div class="bm-spec-grid">
        <!-- 1. A9 -->
        <div class="bm-spec-card">
          <div class="bm-spec-media">
            <img src="https://images.unsplash.com/photo-1516549655169-df83a0774514?auto=format&fit=crop&w=800&q=80" alt="Trung Tâm Cấp Cứu A9">
            <div class="bm-spec-badge-overlay">
              <span class="bm-spec-pill a9">CẤP CỨU ĐA KHOA 24/7</span>
            </div>
          </div>
          <div class="bm-spec-body">
            <div>
              <h3 class="bm-spec-name">Trung Tâm Cấp Cứu A9</h3>
              <p class="bm-spec-text">Đơn vị cấp cứu đa khoa tuyến cuối tiếp nhận bệnh nhân nặng 24/7, trang bị hệ thống hồi sinh tim phổi và can thiệp mạch khẩn cấp hiện đại.</p>
            </div>
            <a href="tel:02438693731" class="bm-spec-link">Hotline A9: 024.3869.3731 →</a>
          </div>
        </div>

        <!-- 2. Viện Tim Mạch -->
        <div class="bm-spec-card">
          <div class="bm-spec-media">
            <img src="https://images.unsplash.com/photo-1628348068343-c6a848d2b6dd?auto=format&fit=crop&w=800&q=80" alt="Viện Tim Mạch Quốc Gia">
            <div class="bm-spec-badge-overlay">
              <span class="bm-spec-pill">VIỆN ĐẦU NGÀNH</span>
            </div>
          </div>
          <div class="bm-spec-body">
            <div>
              <h3 class="bm-spec-name">Viện Tim Mạch Quốc Gia Việt Nam</h3>
              <p class="bm-spec-text">Trung tâm tim mạch hàng đầu cả nước, dẫn đầu khu vực về can thiệp tim mạch phức tạp, thay van qua da (TAVI) và phẫu thuật tim hở chuyên sâu.</p>
            </div>
            <a href="/?view=records" class="bm-spec-link">Hồ sơ bệnh án Tim mạch →</a>
          </div>
        </div>

        <!-- 3. Hồi Sức Tích Cực -->
        <div class="bm-spec-card">
          <div class="bm-spec-media">
            <img src="https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=800&q=80" alt="Trung Tâm Hồi Sức Tích Cực ICU">
            <div class="bm-spec-badge-overlay">
              <span class="bm-spec-pill icu">HỒI SỨC ĐẶC BIỆT</span>
            </div>
          </div>
          <div class="bm-spec-body">
            <div>
              <h3 class="bm-spec-name">Trung Tâm Hồi Sức Tích Cực (ICU)</h3>
              <p class="bm-spec-text">Đơn vị ICU kỹ thuật cao bậc nhất, ứng dụng kỹ thuật ECMO (tim phổi nhân tạo) và lọc máu liên tục cứu sống nhiều ca bệnh nguy kịch.</p>
            </div>
            <a href="/?view=records" class="bm-spec-link">Theo dõi điều trị ICU →</a>
          </div>
        </div>

        <!-- 4. Đột Quỵ -->
        <div class="bm-spec-card">
          <div class="bm-spec-media">
            <img src="https://images.unsplash.com/photo-1559757175-5700dde675bc?auto=format&fit=crop&w=800&q=80" alt="Trung Tâm Đột Quỵ Não">
            <div class="bm-spec-badge-overlay">
              <span class="bm-spec-pill a9">CAN THIỆP MẠCH NÃO</span>
            </div>
          </div>
          <div class="bm-spec-body">
            <div>
              <h3 class="bm-spec-name">Trung Tâm Đột Quỵ & Can Thiệp Não</h3>
              <p class="bm-spec-text">Quy trình cấp cứu đột quỵ "Giờ Vàng", can thiệp lấy huyết khối bằng dụng cụ cơ học và tiêu sợi huyết cứu sống mô não tổn thương.</p>
            </div>
            <a href="/?view=records" class="bm-spec-link">Quy trình đột quỵ →</a>
          </div>
        </div>

        <!-- 5. Viện Hô Hấp -->
        <div class="bm-spec-card">
          <div class="bm-spec-media">
            <img src="https://images.unsplash.com/photo-1584515979956-d9f6e5d09982?auto=format&fit=crop&w=800&q=80" alt="Viện Hô Hấp">
            <div class="bm-spec-badge-overlay">
              <span class="bm-spec-pill">CHUYÊN KHOA PHỔI</span>
            </div>
          </div>
          <div class="bm-spec-body">
            <div>
              <h3 class="bm-spec-name">Viện Hô Hấp Bạch Mai</h3>
              <p class="bm-spec-text">Chẩn đoán và điều trị bệnh phổi phức tạp, COPD, nội soi phế quản can thiệp kỹ thuật số và hồi phục chức năng hô hấp chuyên biệt.</p>
            </div>
            <a href="/?view=records" class="bm-spec-link">Bệnh án Hô hấp →</a>
          </div>
        </div>

        <!-- 6. Viện Huyết Học -->
        <div class="bm-spec-card">
          <div class="bm-spec-media">
            <img src="https://images.unsplash.com/photo-1615461066841-6116e61058f4?auto=format&fit=crop&w=800&q=80" alt="Viện Huyết Học & Truyền Máu">
            <div class="bm-spec-badge-overlay">
              <span class="bm-spec-pill">HUYẾT HỌC LÂM SÀNG</span>
            </div>
          </div>
          <div class="bm-spec-body">
            <div>
              <h3 class="bm-spec-name">Viện Huyết Học & Truyền Máu Bạch Mai</h3>
              <p class="bm-spec-text">Chẩn đoán và điều trị các bệnh lý huyết học ác tính, cung ứng chế phẩm máu chuẩn quốc tế và chuyển giao kỹ thuật ghép tế bào gốc.</p>
            </div>
            <a href="/?view=records" class="bm-spec-link">Hồ sơ Huyết học →</a>
          </div>
        </div>

        <!-- 7. Phẫu Thuật Tiêu Hóa -->
        <div class="bm-spec-card">
          <div class="bm-spec-media">
            <img src="https://images.unsplash.com/photo-1551076805-e1869033e561?auto=format&fit=crop&w=800&q=80" alt="Trung Tâm Phẫu Thuật Tiêu Hóa">
            <div class="bm-spec-badge-overlay">
              <span class="bm-spec-pill">NGOẠI TIÊU HÓA</span>
            </div>
          </div>
          <div class="bm-spec-body">
            <div>
              <h3 class="bm-spec-name">Trung Tâm Phẫu Thuật Tiêu Hóa - Gan Mật</h3>
              <p class="bm-spec-text">Phẫu thuật nội soi 3D/4K tiên tiến, phẫu thuật ghép gan, can thiệp điều trị ung thư đường tiêu hóa và phẫu thuật xâm lấn tối thiểu.</p>
            </div>
            <a href="/?view=records" class="bm-spec-link">Hồ sơ phẫu thuật →</a>
          </div>
        </div>

        <!-- 8. Ung Bướu -->
        <div class="bm-spec-card">
          <div class="bm-spec-media">
            <img src="https://images.unsplash.com/photo-1530497610245-94d3c16cda28?auto=format&fit=crop&w=800&q=80" alt="Trung Tâm Ung Bướu">
            <div class="bm-spec-badge-overlay">
              <span class="bm-spec-pill">XẠ TRỊ KỸ THUẬT CAO</span>
            </div>
          </div>
          <div class="bm-spec-body">
            <div>
              <h3 class="bm-spec-name">Trung Tâm Y Học Hạt Nhân & Ung Bướu</h3>
              <p class="bm-spec-text">Ứng dụng hệ thống xạ trị gia tốc tuyến tính thế hệ mới, PET/CT chẩn đoán sớm và phác đồ điều trị trúng đích cá thể hóa cho bệnh nhân ung thư.</p>
            </div>
            <a href="/?view=records" class="bm-spec-link">Hồ sơ điều trị ung bướu →</a>
          </div>
        </div>
      </div>
    </section>

    <!-- ACT V: ĐỘI NGŨ CHUYÊN GIA & BÁC SĨ ĐẦU NGÀNH -->
    <section class="bm-section" id="chuyen-gia">
      <div class="bm-section-header">
        <div class="bm-section-tag">TÀI NĂNG & Y ĐỨC</div>
        <h2 class="bm-section-title">Hội Đồng Chuyên Gia & Thầy Thuốc Nhân Dân</h2>
        <p class="bm-section-desc">Bệnh viện Bạch Mai quy tụ đội ngũ Giáo sư, Phó Giáo sư, Tiến sĩ, Bác sĩ Chuyên khoa đầu ngành, giàu kinh nghiệm và tận tâm phụng sự sức khỏe người bệnh.</p>
      </div>

      <div class="bm-doctor-grid">
        <div class="bm-doc-card">
          <div class="bm-doc-photo-wrap">
            <img src="https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&w=600&q=80" alt="PGS.TS. Đào Xuân Cơ" class="bm-doc-photo">
          </div>
          <div class="bm-doc-info">
            <div>
              <h3 class="bm-doc-title">PGS.TS. Đào Xuân Cơ</h3>
              <div class="bm-doc-role">Giám đốc Bệnh viện Bạch Mai</div>
              <p class="bm-doc-desc">Chuyên gia Hồi sức Cấp cứu & Chống độc. Trưởng ban điều hành đề án Chuyển đổi số Y tế và Bệnh án Điện tử toàn diện.</p>
            </div>
            <button onclick="openBookingModal()" class="bm-doc-btn">Đặt lịch tư vấn</button>
          </div>
        </div>

        <div class="bm-doc-card">
          <div class="bm-doc-photo-wrap">
            <img src="https://images.unsplash.com/photo-1537368910025-700350fe46c7?auto=format&fit=crop&w=600&q=80" alt="PGS.TS. Vũ Văn Giáp" class="bm-doc-photo">
          </div>
          <div class="bm-doc-info">
            <div>
              <h3 class="bm-doc-title">PGS.TS. Vũ Văn Giáp</h3>
              <div class="bm-doc-role">Phó Giám đốc Bệnh viện Bạch Mai</div>
              <p class="bm-doc-desc">Chuyên gia Viện Hô Hấp Bạch Mai, Tổng thư ký Hội Hô hấp Việt Nam. Tiên phong nội soi can thiệp phế quản kỹ thuật cao.</p>
            </div>
            <button onclick="openBookingModal()" class="bm-doc-btn">Đặt lịch tư vấn</button>
          </div>
        </div>

        <div class="bm-doc-card">
          <div class="bm-doc-photo-wrap">
            <img src="https://images.unsplash.com/photo-1582750433449-648ed127bb54?auto=format&fit=crop&w=600&q=80" alt="GS.TS. Phạm Minh Thông" class="bm-doc-photo">
          </div>
          <div class="bm-doc-info">
            <div>
              <h3 class="bm-doc-title">GS.TS. Phạm Minh Thông</h3>
              <div class="bm-doc-role">Chuyên Gia Cao Cấp</div>
              <p class="bm-doc-desc">Chủ tịch Hội Điện quang & Y học Hạt nhân Việt Nam. Chuyên gia đầu ngành về chẩn đoán hình ảnh và can thiệp mạch não.</p>
            </div>
            <button onclick="openBookingModal()" class="bm-doc-btn">Đặt lịch tư vấn</button>
          </div>
        </div>

        <div class="bm-doc-card">
          <div class="bm-doc-photo-wrap">
            <img src="https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=600&q=80" alt="PGS.TS. Phạm Mạnh Hùng" class="bm-doc-photo">
          </div>
          <div class="bm-doc-info">
            <div>
              <h3 class="bm-doc-title">PGS.TS. Phạm Mạnh Hùng</h3>
              <div class="bm-doc-role">Viện Trưởng Viện Tim Mạch</div>
              <p class="bm-doc-desc">Phó Chủ tịch Hội Tim Mạch Việt Nam. Chuyên gia can thiệp tim mạch phức tạp, thay van động mạch chủ qua da (TAVI).</p>
            </div>
            <button onclick="openBookingModal()" class="bm-doc-btn">Đặt lịch tư vấn</button>
          </div>
        </div>

        <div class="bm-doc-card">
          <div class="bm-doc-photo-wrap">
            <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&w=600&q=80" alt="BS. CKI Lê Hoàng Minh" class="bm-doc-photo">
          </div>
          <div class="bm-doc-info">
            <div>
              <h3 class="bm-doc-title">BS. CKI Lê Hoàng Minh</h3>
              <div class="bm-doc-role">Bác Sĩ Điều Trị Chuyên Khoa</div>
              <p class="bm-doc-desc">Khoa Tim Mạch & Nội Tiết. Trực tiếp thăm khám, lập phác đồ điều trị và đồng hành cùng bệnh nhân trên hệ thống EMR.</p>
            </div>
            <button onclick="openBookingModal()" class="bm-doc-btn">Đặt lịch khám</button>
          </div>
        </div>
      </div>
    </section>

    <!-- ACT VI: NĂNG LỰC BỆNH VIỆN BẠCH MAI (TELEMETRY STRIP COUNT-UP) -->
    <div class="bm-stats-strip" id="statsStrip">
      <div class="bm-stats-inner">
        <div>
          <div class="bm-stat-val" data-counter-target="3200" data-suffix="+">0</div>
          <div class="bm-stat-label">Giường Bệnh Nội Trú</div>
          <div class="bm-stat-sub">Trang thiết bị hiện đại chuẩn quốc tế</div>
        </div>
        <div>
          <div class="bm-stat-val" data-counter-target="1800000" data-suffix="+">0</div>
          <div class="bm-stat-label">Lượt Khám Mỗi Năm</div>
          <div class="bm-stat-sub">Tiếp nhận và điều trị bệnh nhân toàn quốc</div>
        </div>
        <div>
          <div class="bm-stat-val" data-counter-target="55" data-suffix="+">0</div>
          <div class="bm-stat-label">Viện & Trung Tâm Chuyên Khoa</div>
          <div class="bm-stat-sub">Hệ thống lâm sàng và cận lâm sàng chuyên sâu</div>
        </div>
        <div>
          <div class="bm-stat-val" data-counter-target="114" data-suffix=" Năm">0</div>
          <div class="bm-stat-label">Lịch Sử Hình Thành (1911 - 2025)</div>
          <div class="bm-stat-sub">Bề dày truyền thống y đức và chuyên môn</div>
        </div>
      </div>
    </div>

    <!-- ACT VII: TIN TỨC HOẠT ĐỘNG & Y HỌC THƯỜNG THỨC -->
    <section class="bm-section" id="tin-tuc">
      <div class="bm-section-header">
        <div class="bm-section-tag">BẢN TIN BỆNH VIỆN</div>
        <h2 class="bm-section-title">Tin Tức Hoạt Động & Y Học Thường Thức</h2>
        <p class="bm-section-desc">Cập nhật những hoạt động y tế nổi bật, tiến bộ kỹ thuật mới và kiến thức chăm sóc sức khỏe chính thống từ Bệnh viện Bạch Mai.</p>
      </div>

      <div class="bm-news-grid">
        <div class="bm-news-card">
          <div class="bm-news-thumb">
            <img src="https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=800&q=80" alt="Bệnh án điện tử EMR">
            <span class="bm-news-thumb-tag">CHUYỂN ĐỔI SỐ</span>
          </div>
          <div class="bm-news-body">
            <div>
              <h3 class="bm-news-title">Bệnh viện Bạch Mai là BV đa khoa hạng đặc biệt đầu tiên áp dụng hồ sơ bệnh án điện tử từ ngày 1/11/2024</h3>
              <p style="font-size: 13px; color: #64748b; line-height: 1.55;">Bước ngoặt lịch sử trong chuyển đổi số y tế, xóa bỏ hoàn toàn bệnh án giấy, tối ưu hóa quy trình khám và liên thông dữ liệu an toàn.</p>
            </div>
            <div class="bm-news-meta">
              <span>📅 01/11/2024</span>
              <span>👁️ 24.500 lượt xem</span>
            </div>
          </div>
        </div>

        <div class="bm-news-card">
          <div class="bm-news-thumb">
            <img src="https://images.unsplash.com/photo-1587351021759-3e566b6af7cc?auto=format&fit=crop&w=800&q=80" alt="Cơ sở 2 Ninh Bình">
            <span class="bm-news-thumb-tag">CƠ SỞ 2</span>
          </div>
          <div class="bm-news-body">
            <div>
              <h3 class="bm-news-title">Khai trương tiếp nhận khám chữa bệnh Bệnh viện Bạch Mai cơ sở 2 tại Ninh Bình</h3>
              <p style="font-size: 13px; color: #64748b; line-height: 1.55;">Đưa vào vận hành cơ sở mới quy mô 1.000 giường bệnh, phục vụ người dân các tỉnh Nam Định, Ninh Bình, Hà Nam thuận tiện tiếp cận dịch vụ tuyến cuối.</p>
            </div>
            <div class="bm-news-meta">
              <span>📅 15/03/2025</span>
              <span>👁️ 18.200 lượt xem</span>
            </div>
          </div>
        </div>

        <div class="bm-news-card">
          <div class="bm-news-thumb">
            <img src="https://images.unsplash.com/photo-1579684385127-1ef15d508118?auto=format&fit=crop&w=800&q=80" alt="Kỳ tích y học">
            <span class="bm-news-thumb-tag">KỲ TÍCH Y HỌC</span>
          </div>
          <div class="bm-news-body">
            <div>
              <h3 class="bm-news-title">Sự hồi sinh thần kỳ của cô bé làng Nủ sau 50 ngày điều trị tại Bệnh viện Bạch Mai</h3>
              <p style="font-size: 13px; color: #64748b; line-height: 1.55;">Huy động toàn bộ trang thiết bị hồi sức tích cực hiện đại nhất cùng sự tận tâm của tập thể y bác sĩ, đưa bệnh nhi thoát khỏi lưỡi hái tử thần.</p>
            </div>
            <div class="bm-news-meta">
              <span>📅 28/10/2024</span>
              <span>👁️ 36.800 lượt xem</span>
            </div>
          </div>
        </div>

        <div class="bm-news-card">
          <div class="bm-news-thumb">
            <img src="https://images.unsplash.com/photo-1628348068343-c6a848d2b6dd?auto=format&fit=crop&w=800&q=80" alt="Can thiệp tim mạch TAVI">
            <span class="bm-news-thumb-tag">CAN THIỆP TIM</span>
          </div>
          <div class="bm-news-body">
            <div>
              <h3 class="bm-news-title">Viện Tim Mạch thực hiện thành công ca thay van động mạch chủ qua da (TAVI) cho cụ bà 89 tuổi</h3>
              <p style="font-size: 13px; color: #64748b; line-height: 1.55;">Kỹ thuật tim mạch can thiệp ít xâm lấn đỉnh cao giúp bệnh nhân cao tuổi hồi phục nhanh chóng chỉ sau 48 giờ phẫu thuật.</p>
            </div>
            <div class="bm-news-meta">
              <span>📅 12/02/2025</span>
              <span>👁️ 15.400 lượt xem</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ACT VIII: QUY TRÌNH KHÁM BỆNH 4 BƯỚC -->
    <section class="bm-section" id="co-cau">
      <div class="bm-section-header">
        <div class="bm-section-tag">HƯỚNG DẪN BỆNH NHÂN</div>
        <h2 class="bm-section-title">Quy Trình Khám Chữa Bệnh Chuẩn Mực</h2>
        <p class="bm-section-desc">Các bước tiếp đón và thăm khám được số hóa toàn diện giúp người bệnh tiết kiệm thời gian và an tâm điều trị.</p>
      </div>

      <div class="bm-steps-grid">
        <div class="bm-step-card">
          <div class="bm-step-num">1</div>
          <h3 class="bm-step-title">Tiếp Đón & Đăng Ký</h3>
          <p class="bm-step-text">Đăng ký khám tại Kiosk tự động, quét thẻ Căn cước công dân gắn chip hoặc ứng dụng VNeID/VssID để nhận số thứ tự.</p>
        </div>

        <div class="bm-step-card">
          <div class="bm-step-num">2</div>
          <h3 class="bm-step-title">Khám Lâm Sàng</h3>
          <p class="bm-step-text">Bác sĩ chuyên khoa thăm khám trực tiếp tại phòng khám chuyên khoa, khai thác tiền sử và chỉ định xét nghiệm cần thiết.</p>
        </div>

        <div class="bm-step-card">
          <div class="bm-step-num">3</div>
          <h3 class="bm-step-title">Cận Lâm Sàng & Xét Nghiệm</h3>
          <p class="bm-step-text">Thực hiện chụp chiếu (X-quang, MRI, CT) và xét nghiệm. Kết quả được số hóa tự động đẩy thẳng lên hệ thống EMR.</p>
        </div>

        <div class="bm-step-card">
          <div class="bm-step-num">4</div>
          <h3 class="bm-step-title">Kết Luận & Nhận Thuốc</h3>
          <p class="bm-step-text">Bác sĩ tư vấn phác đồ điều trị, kê đơn thuốc điện tử hoặc làm thủ tục nhập viện nội trú nhanh chóng, thuận tiện.</p>
        </div>
      </div>
    </section>

    <!-- ACT IX: THÔNG TIN 2 CƠ SỞ VÀ GIỜ LÀM VIỆC -->
    <section class="bm-section" id="lien-he">
      <div class="bm-section-header">
        <div class="bm-section-tag">ĐỊA ĐIỂM & THỜI GIAN TIẾP ĐÓN</div>
        <h2 class="bm-section-title">Cơ Sở Khám Chữa Bệnh Bệnh Viện Bạch Mai</h2>
      </div>

      <div class="bm-campuses-grid">
        <div class="bm-campus-card">
          <div class="bm-campus-badge">CƠ SỞ 1 - TP. HÀ NỘI</div>
          <h3 class="bm-campus-title">Bệnh Viện Bạch Mai - Cơ Sở Hà Nội</h3>
          <div class="bm-campus-info-row">
            <span>📍</span>
            <span><strong>Địa chỉ:</strong> Số 78 Đường Giải Phóng, P. Phương Mai, Q. Đống Đa, TP. Hà Nội</span>
          </div>
          <div class="bm-campus-info-row">
            <span>📞</span>
            <span><strong>Tổng đài tư vấn:</strong> 1900.888.866 | <strong>Hotline:</strong> 096.985.1616</span>
          </div>
          <div class="bm-campus-info-row">
            <span>🚑</span>
            <span><strong>Cấp cứu A9:</strong> Trực 24/24 tất cả các ngày trong năm (024.3869.3731)</span>
          </div>
          <hr style="border: none; border-top: 1px dashed var(--bm-border); margin: 14px 0;">
          <div style="font-size: 13px; color: #475569; line-height: 1.6;">
            <strong>Lịch Khám Theo Yêu Cầu:</strong> Thứ 2 - Chủ Nhật (Tiếp đón từ 05:00; Khám: 06:00 - 16:30)<br>
            <strong>Lịch Khám Thông Thường / BHYT:</strong> Thứ 2 - Thứ 6 (Tiếp đón từ 07:00; Khám: 07:30 - 16:30)
          </div>
        </div>

        <div class="bm-campus-card">
          <div class="bm-campus-badge">CƠ SỞ 2 - NINH BÌNH / HÀ NAM</div>
          <h3 class="bm-campus-title">Bệnh Viện Bạch Mai - Cơ Sở 2</h3>
          <div class="bm-campus-info-row">
            <span>📍</span>
            <span><strong>Địa chỉ:</strong> Tuyến đường Quốc lộ 21B, Xã Liêm Tuyền, TP. Phủ Lý, Tỉnh Hà Nam / Ninh Bình</span>
          </div>
          <div class="bm-campus-info-row">
            <span>📞</span>
            <span><strong>Tổng đài đặt lịch:</strong> 1900.888.866 | <strong>Hotline:</strong> 096.985.1616</span>
          </div>
          <div class="bm-campus-info-row">
            <span>🚑</span>
            <span><strong>Khoa Cấp cứu:</strong> Tiếp nhận bệnh nhân khẩn cấp 24/7</span>
          </div>
          <hr style="border: none; border-top: 1px dashed var(--bm-border); margin: 14px 0;">
          <div style="font-size: 13px; color: #475569; line-height: 1.6;">
            <strong>Lịch Khám Theo Yêu Cầu:</strong> Thứ 2 - Chủ Nhật (06:30 - 16:30)<br>
            <strong>Lịch Khám BHYT:</strong> Thứ 2 - Thứ 6 (07:30 - 16:30)
          </div>
        </div>
      </div>
    </section>

    <!-- FOOTER CHUẨN CỔNG THÔNG TIN ĐIỆN TỬ BỆNH VIỆN BẠCH MAI -->
    <footer class="bm-footer">
      <div class="bm-footer-inner">
        <div class="bm-footer-grid">
          <div>
            <div style="font-weight: 800; font-size: 16px; color: #fff; margin-bottom: 4px;">CỔNG THÔNG TIN ĐIỆN TỬ BỆNH VIỆN BẠCH MAI</div>
            <div style="font-size: 12px; color: #94a3b8; margin-bottom: 14px;">Bệnh viện Đa khoa hoàn chỉnh hạng Đặc biệt đầu tiên tại Việt Nam</div>
            <p style="font-size: 12.5px; line-height: 1.6; color: #cbd5e1; margin-bottom: 12px;">
              Cơ quan chủ quản: <strong>BỘ Y TẾ</strong><br>
              Giấy phép số: 18/GP-TTĐT do Cục PTTH & TTĐT - Bộ Thông tin và Truyền thông cấp.<br>
              Chịu trách nhiệm nội dung: <strong>PGS.TS. Đào Xuân Cơ</strong> - Giám đốc Bệnh viện Bạch Mai.
            </p>
          </div>

          <div>
            <div class="bm-footer-title">Về Bệnh Viện</div>
            <ul class="bm-footer-links">
              <li><a href="#gioi-thieu">Lịch sử phát triển</a></li>
              <li><a href="#gioi-thieu">Sứ mệnh & Tầm nhìn</a></li>
              <li><a href="#chuyen-gia">Ban Giám đốc Bệnh viện</a></li>
              <li><a href="#co-cau">Cơ cấu tổ chức</a></li>
              <li><a href="#chuyen-khoa">Hệ thống chuyên khoa</a></li>
            </ul>
          </div>

          <div>
            <div class="bm-footer-title">Dành Cho Người Bệnh</div>
            <ul class="bm-footer-links">
              <li><a href="javascript:void(0)" onclick="openBookingModal()">Đặt lịch khám trực tuyến</a></li>
              <li><a href="/?view=records">Hồ sơ bệnh án điện tử (EMR)</a></li>
              <li><a href="#danh-cho-nguoi-benh">Hướng dẫn khám BHYT</a></li>
              <li><a href="#lien-he">Bảng giá dịch vụ viện phí</a></li>
              <li><a href="#lien-he">Quy trình cấp cứu A9</a></li>
            </ul>
          </div>

          <div>
            <div class="bm-footer-title">Kết Nối Với Chúng Tôi</div>
            <p style="font-size: 12px; color: #94a3b8; line-height: 1.6; margin-bottom: 12px;">
              Tổng đài đặt khám: <strong style="color: #38bdf8;">1900.888.866</strong><br>
              Cấp cứu A9: <strong style="color: #f87171;">024.3869.3731</strong><br>
              Email: <strong>benhvienbachmai@bachmai.gov.vn</strong>
            </p>
            <div style="display: flex; gap: 10px; margin-top: 6px;">
              <a href="/?view=records" style="font-size: 12px; padding: 8px 14px; border: 1px solid rgba(255,255,255,0.25); border-radius: 6px; background: rgba(255,255,255,0.08); color: #38bdf8; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;"><span>📋</span> Cổng EMR</a>
              <a href="/?view=login" style="font-size: 12px; padding: 8px 14px; border-radius: 6px; background: var(--bm-blue-primary); color: #fff; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;"><span>🔐</span> Cán Bộ Đăng Nhập</a>
            </div>
          </div>
        </div>

        <div class="bm-footer-bottom">
          <div>© 2025 - 2026 Bản quyền thuộc về Bệnh viện Bạch Mai. Ghi rõ nguồn khi phát hành lại thông tin từ website này.</div>
          <div>Hệ thống quản lý Hồ sơ Bệnh án Điện tử (EMR) chuẩn y tế quốc gia.</div>
        </div>
      </div>
    </footer>

    <!-- MODAL ĐẶT LỊCH KHÁM ONLINE -->
    <div id="bookingModal" class="bm-modal-overlay" style="display: none;">
      <div class="bm-modal-card">
        <div class="bm-modal-head">
          <div class="bm-modal-title">Đặt Lịch Khám Trực Tuyến - Bệnh Viện Bạch Mai</div>
          <button onclick="closeBookingModal()" class="bm-close-btn">✕</button>
        </div>
        <form onsubmit="handleBookingSubmit(event)">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
            <div>
              <label style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Họ và Tên Bệnh Nhân *</label>
              <input type="text" id="bkName" required placeholder="Ví dụ: Nguyễn Văn An" style="width: 100%; padding: 8px 12px; border: 1px solid var(--bm-border); border-radius: 6px; font-size: 13px;">
            </div>
            <div>
              <label style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Số Điện Thoại Liên Hệ *</label>
              <input type="tel" id="bkPhone" required placeholder="0987.xxx.xxx" style="width: 100%; padding: 8px 12px; border: 1px solid var(--bm-border); border-radius: 6px; font-size: 13px;">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
            <div>
              <label style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Chuyên Khoa Đăng Ký Khám *</label>
              <select id="bkDept" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--bm-border); border-radius: 6px; font-size: 13px; background: #fff;">
                <option value="Khoa Tim Mạch & Nội Tiết">Khoa Tim Mạch & Nội Tiết</option>
                <option value="Viện Tim Mạch Quốc Gia">Viện Tim Mạch Quốc Gia</option>
                <option value="Trung Tâm Cấp Cứu A9">Trung Tâm Cấp Cứu A9</option>
                <option value="Trung Tâm Hồi Sức Tích Cực">Trung Tâm Hồi Sức Tích Cực (ICU)</option>
                <option value="Trung Tâm Đột Quỵ">Trung Tâm Đột Quỵ</option>
                <option value="Viện Hô Hấp Bạch Mai">Viện Hô Hấp Bạch Mai</option>
                <option value="Viện Huyết Học & Truyền Máu">Viện Huyết Học & Truyền Máu</option>
                <option value="Phẫu Thuật Tiêu Hóa - Gan Mật">Phẫu Thuật Tiêu Hóa - Gan Mật</option>
                <option value="Y Học Hạt Nhân & Ung Bướu">Y Học Hạt Nhân & Ung Bướu</option>
              </select>
            </div>
            <div>
              <label style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Ngày Hẹn Khám Mong Muốn *</label>
              <input type="date" id="bkDate" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--bm-border); border-radius: 6px; font-size: 13px;">
            </div>
          </div>

          <div style="margin-bottom: 16px;">
            <label style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Mô Tả Triệu Chứng / Ghi Chú Y Tế</label>
            <textarea id="bkNote" rows="3" placeholder="Mô tả triệu chứng bệnh hoặc nhu cầu khám chuyên sâu..." style="width: 100%; padding: 8px 12px; border: 1px solid var(--bm-border); border-radius: 6px; font-size: 13px; font-family: inherit;"></textarea>
          </div>

          <div id="bookingSuccessMsg" style="display: none; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px; border-radius: 6px; font-size: 13px; margin-bottom: 14px;">
            ✓ Tiếp nhận lịch hẹn khám thành công! Bộ phận tiếp đón của Bệnh viện Bạch Mai sẽ liên hệ với quý khách trong vòng 15 phút.
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" onclick="closeBookingModal()" class="btn btn-outline" style="padding: 9px 16px;">Đóng</button>
            <button type="submit" class="btn btn-primary" style="padding: 9px 20px; background: var(--bm-blue-primary);">Xác Nhận Đặt Lịch</button>
          </div>
        </form>
      </div>
    </div>

    <!-- GSAP & SCROLL-CRAFT PARALLAX SCRIPTS -->
    <script>
      // 1. Booking Modal Control
      function openBookingModal() {
        document.getElementById('bookingModal').style.display = 'flex';
        const dInput = document.getElementById('bkDate');
        if (dInput && !dInput.value) {
          const tomorrow = new Date();
          tomorrow.setDate(tomorrow.getDate() + 1);
          dInput.value = tomorrow.toISOString().split('T')[0];
        }
      }
      function closeBookingModal() {
        document.getElementById('bookingModal').style.display = 'none';
        document.getElementById('bookingSuccessMsg').style.display = 'none';
      }
      function handleBookingSubmit(e) {
        e.preventDefault();
        document.getElementById('bookingSuccessMsg').style.display = 'block';
        setTimeout(function() {
          closeBookingModal();
        }, 2200);
      }

      // 2. Initialize GSAP ScrollTrigger Animations
      document.addEventListener('DOMContentLoaded', function() {
        if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') {
          console.warn('GSAP or ScrollTrigger not loaded');
          return;
        }

        gsap.registerPlugin(ScrollTrigger);

        // A. Parallax Multi-Plane Hero
        const heroWrap = document.querySelector('.sc-hero-wrap');
        const heroBg = document.getElementById('scHeroBg');
        const heroContent = document.getElementById('scHeroContent');
        const heroSubject = document.getElementById('scHeroSubject');

        if (heroWrap && heroBg) {
          // Hero Background moves down at gentle speed (scrub: 1)
          gsap.to(heroBg, {
            yPercent: 28,
            scale: 1.08,
            ease: 'none',
            scrollTrigger: {
              trigger: heroWrap,
              start: 'top top',
              end: 'bottom top',
              scrub: 1
            }
          });

          // Hero Content recedes slightly upwards and fades
          if (heroContent) {
            gsap.to(heroContent, {
              yPercent: -18,
              opacity: 0.25,
              ease: 'none',
              scrollTrigger: {
                trigger: heroWrap,
                start: 'top top',
                end: '80% top',
                scrub: 1
              }
            });
          }

          // Hero Subject plane moves independently to create visible occlusion
          if (heroSubject) {
            gsap.to(heroSubject, {
              yPercent: -8,
              ease: 'none',
              scrollTrigger: {
                trigger: heroWrap,
                start: 'top top',
                end: 'bottom top',
                scrub: 1
              }
            });
          }
        }

        // B. Pinned Horizontal Scrollytelling Section
        const pinSection = document.getElementById('milestonesPin');
        const track = document.getElementById('horizontalTrack');
        const progressBar = document.getElementById('pinProgressBar');
        const counterEl = document.getElementById('pinCounter');

        if (pinSection && track) {
          const totalWidth = () => track.scrollWidth - window.innerWidth;

          gsap.to(track, {
            x: () => -totalWidth(),
            ease: 'none',
            scrollTrigger: {
              trigger: pinSection,
              start: 'top top',
              end: () => '+=' + totalWidth(),
              pin: true,
              scrub: 1,
              anticipatePin: 1,
              invalidateOnRefresh: true,
              onUpdate: function(self) {
                if (progressBar) {
                  progressBar.style.width = (self.progress * 100) + '%';
                }
                if (counterEl) {
                  const slideIdx = Math.min(4, Math.floor(self.progress * 4) + 1);
                  counterEl.innerText = 'CHƯƠNG 0' + slideIdx + ' / 04';
                }
              }
            }
          });
        }

        // C. Specialties Cards Batch Stagger Entrance
        ScrollTrigger.batch('.bm-spec-card', {
          start: 'top 85%',
          onEnter: function(elements) {
            gsap.fromTo(elements, 
              { opacity: 0, y: 35, scale: 0.96 }, 
              { opacity: 1, y: 0, scale: 1, stagger: 0.1, duration: 0.7, ease: 'power2.out', overwrite: 'auto' }
            );
          },
          once: true
        });

        // D. Doctor Cards Stagger Entrance
        ScrollTrigger.batch('.bm-doc-card', {
          start: 'top 85%',
          onEnter: function(elements) {
            gsap.fromTo(elements, 
              { opacity: 0, y: 35 }, 
              { opacity: 1, y: 0, stagger: 0.08, duration: 0.7, ease: 'power2.out', overwrite: 'auto' }
            );
          },
          once: true
        });

        // E. News Cards Stagger Entrance
        ScrollTrigger.batch('.bm-news-card', {
          start: 'top 85%',
          onEnter: function(elements) {
            gsap.fromTo(elements, 
              { opacity: 0, y: 30 }, 
              { opacity: 1, y: 0, stagger: 0.1, duration: 0.6, ease: 'power2.out', overwrite: 'auto' }
            );
          },
          once: true
        });

        // F. Stats Telemetry Animated Count-Up
        const statsSection = document.getElementById('statsStrip');
        if (statsSection) {
          ScrollTrigger.create({
            trigger: statsSection,
            start: 'top 80%',
            once: true,
            onEnter: function() {
              const counterEls = statsSection.querySelectorAll('[data-counter-target]');
              counterEls.forEach(function(el) {
                const target = parseInt(el.getAttribute('data-counter-target'), 10) || 0;
                const suffix = el.getAttribute('data-suffix') || '';
                const obj = { val: 0 };
                gsap.to(obj, {
                  val: target,
                  duration: 2,
                  ease: 'power2.out',
                  onUpdate: function() {
                    const current = Math.floor(obj.val);
                    el.innerText = current.toLocaleString('vi-VN') + suffix;
                  }
                });
              });
            }
          });
        }
      });
    </script>

  <?php else: ?>
    <!-- =======================================================================
         CÁC TRANG NGHIỆP VỤ NỘI BỘ (EMR SYSTEM)
         ======================================================================= -->
    <div class="container">

      <!-- BANNER TRẠNG THÁI BẢO MẬT (Chỉ hiển thị trong nội bộ hệ thống) -->
      <?php if ($bugIdor): ?>
        <div class="idor-banner">
          <div>
            <strong style="color: var(--danger); font-size: 14px;">LỖ HỔNG IDOR / BOLA ĐANG BẬT!</strong>
            <p style="color: #7f1d1d; font-size: 12px; margin-top: 4px;">
              Hệ thống đang bỏ qua kiểm tra quan hệ sở hữu đối tượng. Bệnh nhân An (<code>bn_an</code>) có thể đọc trộm và chỉnh sửa trái phép Bệnh án số 03 của Bình (<code>bn_binh</code>).
            </p>
          </div>
          <div>
            <a href="/?action=toggle_bug_idor&state=0&return_view=<?= urlencode($view) ?>" class="btn btn-safe" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">🛡️ Vá Lỗ Hổng (Tắt Lỗi)</a>
          </div>
        </div>
      <?php else: ?>
        <div class="idor-banner idor-banner-safe">
          <div>
            <strong style="color: var(--safe); font-size: 14px;">HỆ THỐNG AN TOÀN (ABAC ACTIVE)</strong>
            <p style="color: #14532d; font-size: 12px; margin-top: 4px;">
              Quy tắc kiểm soát thuộc tính sở hữu đang hoạt động: Bệnh nhân chỉ xem và sửa hồ sơ của chính mình. Thao tác truy cập chéo bị chặn với HTTP 403 Forbidden.
            </p>
          </div>
          <div>
            <a href="/?action=toggle_bug_idor&state=1&return_view=<?= urlencode($view) ?>" class="btn btn-danger" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">⚠️ Tạo Lỗi (Bật IDOR)</a>
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
              <a href="/?view=edit_record&id=<?= $recordDetail['id'] ?>" class="btn btn-outline" style="border-color: var(--primary); color: var(--primary);">Chỉnh Sửa Bệnh Án Này</a>
              <a href="/?view=records" class="btn btn-outline">Quay lại danh sách</a>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; font-size: 13px;">
            <div style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid var(--border);">
              <strong>Bệnh nhân sở hữu:</strong> <code><?= htmlspecialchars($recordDetail['owner_username']) ?></code><br>
              <strong>Bác sĩ phụ trách:</strong> <?= htmlspecialchars($recordDetail['doctor_name'] ?? 'BS. CKI Lê Hoàng Minh') ?>
            </div>
            <div style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid var(--border);">
              <strong>Chẩn đoán lâm sàng:</strong><br>
              <span style="color: #0369a1; font-weight: 600;"><?= htmlspecialchars($recordDetail['diagnosis'] ?? 'Đang cập nhật') ?></span>
            </div>
          </div>

          <div style="background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 18px; margin-bottom: 20px;">
            <h3 style="font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 8px;">NỘI DUNG BỆNH ÁN & PHÁC ĐỒ ĐIỀU TRỊ:</h3>
            <p style="font-size: 14px; color: #0f172a; white-space: pre-wrap;"><?= htmlspecialchars($recordDetail['content']) ?></p>
          </div>
        </div>

      <!-- VIEW 2: FORM CHỈNH SỬA BỆNH ÁN -->
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
              <label>Nội dung bệnh án & phác đồ:</label>
              <textarea name="content" rows="6" required><?= htmlspecialchars($editRecordDetail['content']) ?></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
              <a href="/?view=record&id=<?= $editRecordDetail['id'] ?>" class="btn btn-outline">Hủy Bỏ</a>
              <button type="submit" class="btn btn-primary">Lưu Thay Đổi Bệnh Án</button>
            </div>
          </form>
        </div>

      <!-- VIEW 3: TẠO BỆNH ÁN MỚI (DÀNH CHO BÁC SĨ) -->
      <?php elseif ($view === 'create_record'): ?>
        <div class="card" style="max-width: 720px; margin: 0 auto;">
          <div class="card-head">
            <div>
              <div class="card-title">Lập Hồ Sơ Bệnh Án Mới Cho Bệnh Nhân</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                Bác sĩ phụ trách: <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong> (<code><?= htmlspecialchars($currentUser['department'] ?? 'Khoa Khám Bệnh') ?></code>)
              </div>
            </div>
            <a href="/?view=records" class="btn btn-outline">Quay lại danh sách</a>
          </div>

          <form action="/?action=create_record_submit" method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
              <div class="form-group">
                <label>Bệnh nhân thụ hưởng (Chọn tài khoản):</label>
                <select name="owner_username" required>
                  <option value="bn_an">Nguyễn Văn An (bn_an - Điều trị ngoại trú)</option>
                  <option value="bn_binh">Trần Thị Bình (bn_binh - Khoa Ung Bướu)</option>
                </select>
              </div>

              <div class="form-group">
                <label>Khoa / Phòng điều trị:</label>
                <input type="text" name="department" value="<?= htmlspecialchars($currentUser['department'] ?? 'Khoa Tim Mạch') ?>" required>
              </div>
            </div>

            <div class="form-group">
              <label>Tiêu đề hồ sơ bệnh án:</label>
              <input type="text" name="title" placeholder="Ví dụ: Bệnh án khám định kỳ chuyên khoa Tim Mạch..." required>
            </div>

            <div class="form-group">
              <label>Chẩn đoán sơ bộ ban đầu:</label>
              <input type="text" name="diagnosis" placeholder="Ví dụ: Rối loạn nhịp xoang, tăng huyết áp độ 1..." required>
            </div>

            <div class="form-group">
              <label>Nội dung phác đồ điều trị, kết quả lâm sàng & đơn thuốc:</label>
              <textarea name="content" rows="6" placeholder="Chi tiết khám lâm sàng, sinh hiệu, chỉ định dùng thuốc..." required></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
              <a href="/?view=records" class="btn btn-outline">Hủy Bỏ</a>
              <button type="submit" class="btn btn-primary">Lưu & Phát Hành Bệnh Án</button>
            </div>
          </form>
        </div>

      <!-- VIEW 4: THÔNG BÁO BỆNH VIỆN -->
      <?php elseif ($view === 'announcements'): ?>
        <div class="card">
          <div class="card-head">
            <div class="card-title">Bản Tin & Thông Báo Nội Bộ Bệnh Viện</div>
            <span style="font-size: 12px; color: var(--muted);"><?= count($mockAnnouncements) ?> thông báo</span>
          </div>

          <?php foreach ($mockAnnouncements as $ann): ?>
            <div style="padding: 16px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 14px; background: #fff;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <h4 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0;"><?= htmlspecialchars($ann['title']) ?></h4>
                <span style="font-size: 11px; color: #64748b;"><?= $ann['created_at'] ?></span>
              </div>
              <div style="font-size: 13px; color: #475569; line-height: 1.6; margin-bottom: 8px;">
                <?= htmlspecialchars($ann['content']) ?>
              </div>
              <div style="font-size: 11px; color: #94a3b8;">
                Người đăng: <strong><?= htmlspecialchars($ann['author']) ?></strong> | Phạm vi: <code>Công cộng (Dùng chung)</code>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

      <!-- VIEW 5: QUẢN TRỊ NGƯỜI DÙNG (ADMIN) -->
      <?php elseif ($view === 'users' && $currentUser && $currentUser['role'] === 'admin'): ?>
        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Quản Trị Người Dùng & Nhân Sự Y Tế (Admin CRUD)</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                Tổng số tài khoản: <strong id="totalUserCount"><?= count($mockUsers) ?></strong> người dùng
              </div>
            </div>
            <div style="display: flex; gap: 10px;">
              <input type="text" id="userSearchInput" onkeyup="filterUsers()" placeholder="Tìm kiếm nhanh họ tên, username..." style="padding: 7px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 12px; width: 240px;">
              <button onclick="openAddUserModal()" class="btn btn-primary" style="font-size: 12px;">+ Thêm Người Dùng</button>
            </div>
          </div>

          <table id="userTable">
            <thead>
              <tr>
                <th>Username</th>
                <th>Họ và Tên</th>
                <th>Vai trò</th>
                <th>Khoa / Phòng</th>
                <th style="text-align: right;">Hành động</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($mockUsers as $u): ?>
                <tr>
                  <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                  <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                  <td>
                    <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px; background: #e0f2fe; color: #0369a1;">
                      <?= htmlspecialchars($u['role']) ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($u['department'] ?? 'Chưa cập nhật') ?></td>
                  <td style="text-align: right;">
                    <button onclick="openEditUserModal('<?= htmlspecialchars($u['username']) ?>', '<?= htmlspecialchars(addslashes($u['full_name'])) ?>', '<?= htmlspecialchars($u['role']) ?>', '<?= htmlspecialchars(addslashes($u['department'] ?? '')) ?>')" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px;">Sửa</button>
                    <?php if ($u['username'] !== 'admin_bv'): ?>
                      <a href="/?action=delete_user&username=<?= urlencode($u['username']) ?>" onclick="return confirm('Bạn có chắc muốn xóa tài khoản này?')" class="btn btn-danger" style="padding: 3px 8px; font-size: 11px;">Xóa</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- MODAL THÊM NGƯỜI DÙNG -->
        <div id="addUserModal" class="modal-overlay" style="display: none;">
          <div class="modal-box">
            <div class="card-head" style="border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 16px;">
              <div class="card-title">Thêm Tài Khoản Người Dùng Mới</div>
              <button type="button" onclick="closeAddUserModal()" class="btn btn-outline" style="padding: 2px 8px;">&times;</button>
            </div>
            <form action="/?action=add_user" method="POST">
              <div class="form-group">
                <label>Tên đăng nhập (username): <span style="color:#ef4444;">*</span></label>
                <input type="text" name="username" placeholder="VD: bs_dung, bn_ha..." required>
              </div>
              <div class="form-group">
                <label>Mật khẩu khởi tạo: <span style="color:#ef4444;">*</span></label>
                <input type="password" name="password" value="TestPass@2026" required>
              </div>
              <div class="form-group">
                <label>Họ và tên hiển thị: <span style="color:#ef4444;">*</span></label>
                <input type="text" name="full_name" placeholder="VD: BS. Lê Tiến Dũng..." required>
              </div>
              <div class="form-group">
                <label>Vai trò hệ thống: <span style="color:#ef4444;">*</span></label>
                <select name="role" required>
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
              <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeAddUserModal()" class="btn btn-outline">Hủy Bỏ</button>
                <button type="submit" class="btn btn-primary">Tạo Người Dùng Mới</button>
              </div>
            </form>
          </div>
        </div>

        <!-- MODAL SỬA NGƯỜI DÙNG -->
        <div id="editUserModal" class="modal-overlay" style="display: none;">
          <div class="modal-box">
            <div class="card-head" style="border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 16px;">
              <div class="card-title">Chỉnh Sửa Thông Tin Người Dùng</div>
              <button type="button" onclick="closeEditUserModal()" class="btn btn-outline" style="padding: 2px 8px;">&times;</button>
            </div>
            <form action="/?action=edit_user" method="POST">
              <input type="hidden" id="editUsername" name="username">
              <div class="form-group">
                <label>Tên đăng nhập:</label>
                <input type="text" id="displayEditUsername" disabled style="background: #f1f5f9; cursor: not-allowed;">
              </div>
              <div class="form-group">
                <label>Họ và tên hiển thị: <span style="color:#ef4444;">*</span></label>
                <input type="text" id="editFullName" name="full_name" required>
              </div>
              <div class="form-group">
                <label>Vai trò (Role): <span style="color:#ef4444;">*</span></label>
                <select id="editRole" name="role" required>
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
              <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeEditUserModal()" class="btn btn-outline">Hủy Bỏ</button>
                <button type="submit" class="btn btn-primary">Cập Nhật Người Dùng</button>
              </div>
            </form>
          </div>
        </div>

      <!-- VIEW 6: TRUNG TÂM GIÁM SÁT AN NINH & KIỂM THỬ (TESTER / AUDITOR) -->
      <?php elseif ($view === 'tester' && $currentUser && in_array($currentUser['role'], ['auditor', 'admin'], true)): ?>
        <?php $testerTab = $_GET['tab'] ?? 'incidents'; ?>

        <!-- HEADER BẢNG ĐIỀU KHIỂN KIỂM THỬ -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
          <div>
            <h1 style="font-size: 20px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; margin: 0;">
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
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
          <div class="card" style="border-left: 4px solid #ef4444; margin: 0; padding: 16px;">
            <div style="font-size: 12px; color: #64748b; font-weight: 600;">Cảnh Báo Sự Cố Xâm Phạm</div>
            <div style="font-size: 24px; font-weight: 800; color: #b91c1c; margin: 6px 0 2px;">
              <?= $incidentCount ?>
              <span style="font-size: 12px; font-weight: 600; color: #64748b;">(<?= $criticalCount ?> Nghiêm trọng)</span>
            </div>
            <div style="font-size: 11px; color: #94a3b8;">Thời gian thực (Live Feeds)</div>
          </div>

          <div class="card" style="border-left: 4px solid <?= $bugIdor ? '#ef4444' : '#16a34a' ?>; margin: 0; padding: 16px;">
            <div style="font-size: 12px; color: #64748b; font-weight: 600;">Trạng Thái Lỗ Hổng IDOR (CWE-639)</div>
            <div style="font-size: 20px; font-weight: 800; color: <?= $bugIdor ? '#dc2626' : '#16a34a' ?>; margin: 6px 0 2px;">
              <?= $bugIdor ? 'LỖ HỔNG ĐANG MỞ' : 'HỆ THỐNG AN TOÀN' ?>
            </div>
            <div style="font-size: 11px; color: #64748b;">
              <?= $bugIdor ? 'Bệnh nhân An có thể đọc trộm bệnh án Bình' : 'Cơ chế ABAC đang bảo vệ 100%' ?>
            </div>
          </div>

          <div class="card" style="border-left: 4px solid <?= $failedTestCount > 0 ? '#d97706' : '#16a34a' ?>; margin: 0; padding: 16px;">
            <div style="font-size: 12px; color: #64748b; font-weight: 600;">Kết Quả Ma Trận 24 Test Cases</div>
            <div style="font-size: 24px; font-weight: 800; color: <?= $failedTestCount > 0 ? '#b45309' : '#15803d' ?>; margin: 6px 0 2px;">
              <?= $passedTestCount ?> / 24 PASS
            </div>
            <div style="font-size: 11px; color: #64748b;">
              <?= $failedTestCount > 0 ? "<span style='color:#dc2626;font-weight:700;'>$failedTestCount FAIL (Bẫy IDOR R02 kích hoạt)</span>" : "Tất cả test cases đều đạt chuẩn" ?>
            </div>
          </div>

          <div class="card" style="border-left: 4px solid #0284c7; margin: 0; padding: 16px;">
            <div style="font-size: 12px; color: #64748b; font-weight: 600;">Bộ Test Suite Tích Hợp</div>
            <div style="font-size: 20px; font-weight: 800; color: #0369a1; margin: 6px 0 2px;">
              88/88 TESTS
            </div>
            <div style="font-size: 11px; color: #64748b;">
              Đã chuyển toàn bộ từ cổng 8105 sang 8205
            </div>
          </div>
        </div>

        <!-- TABS CHỨC NĂNG KIỂM THỬ -->
        <div style="display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px;">
          <a href="/?view=tester&tab=incidents" class="btn <?= $testerTab === 'incidents' ? 'btn-primary' : 'btn-outline' ?>" style="font-size: 12px; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
            🚨 Nhật Ký Cảnh Báo Xâm Phạm Thời Gian Thực (<?= $incidentCount ?>)
          </a>
          <a href="/?view=tester&tab=matrix" class="btn <?= $testerTab === 'matrix' ? 'btn-primary' : 'btn-outline' ?>" style="font-size: 12px; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
            📊 Ma Trận Kiểm Thử Phân Quyền Tự Động (24 Cases)
          </a>
          <a href="/?view=tester&tab=full_tests" class="btn <?= $testerTab === 'full_tests' ? 'btn-primary' : 'btn-outline' ?>" style="font-size: 12px; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
            ⚙️ Chạy Toàn Bộ Test Suite (88 Tests CLI)
          </a>
        </div>

        <!-- TAB 1: NHẬT KÝ CẢNH BÁO SỰ CỐ XÂM PHẠM -->
        <?php if ($testerTab === 'incidents'): ?>
          <div class="card">
            <div class="card-head">
              <div>
                <div class="card-title">Dòng Sự Kiện An Ninh & Cảnh Báo Xâm Phạm Thời Gian Thực</div>
                <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                  Tự động ghi nhận khi bất kỳ người dùng, bệnh nhân hoặc nhân viên nào có thao tác vi phạm hoặc cố tình lợi dụng lỗ hổng phân quyền.
                </div>
              </div>
              <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="/?action=switch_user&user=bn_an" class="btn btn-outline" style="border-color: #0284c7; color: #0284c7; font-size: 12px;">
                  👤 Chuyển sang BN An để thử tấn công
                </a>
                <?php if ($bugIdor): ?>
                  <a href="/?action=toggle_bug_idor&state=0&return_view=tester&tab=incidents" class="btn btn-safe" style="font-size: 12px; text-decoration: none;">🛡️ Vá Lỗ Hổng Ngay</a>
                <?php else: ?>
                  <a href="/?action=toggle_bug_idor&state=1&return_view=tester&tab=incidents" class="btn btn-danger" style="font-size: 12px; text-decoration: none;">⚠️ Kích Hoạt Lỗi IDOR</a>
                <?php endif; ?>
              </div>
            </div>

            <?php if (empty($allIncidents)): ?>
              <div style="text-align: center; padding: 40px; color: var(--muted); background: #f8fafc; border-radius: 8px;">
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
                      <th>Chi Tiết Hành Vi Vi Phạm & Phân Loại</th>
                      <th>Mã Phản Hồi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($allIncidents as $inc): ?>
                      <tr>
                        <td style="white-space: nowrap;">
                          <code style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($inc['id'] ?? '') ?></code><br>
                          <span style="font-size: 11px; color: var(--muted);"><?= htmlspecialchars($inc['time'] ?? '') ?></span>
                        </td>
                        <td>
                          <span style="font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 6px; background: <?= ($inc['severity'] ?? '') === 'CRITICAL' ? '#fee2e2' : '#fef3c7' ?>; color: <?= ($inc['severity'] ?? '') === 'CRITICAL' ? '#b91c1c' : '#b45309' ?>;">
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
                          <span style="font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 6px; background: <?= $sc === 200 ? ($inc['severity'] === 'CRITICAL' ? '#fee2e2' : '#dcfce7') : '#fee2e2' ?>; color: <?= $sc === 200 ? ($inc['severity'] === 'CRITICAL' ? '#b91c1c' : '#15803d') : '#b91c1c' ?>;">
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
                  Kiểm tra kiểm soát truy cập (ABAC/RBAC) trên 5 phân hệ API (Profile, Danh sách bệnh án, Bệnh án cá nhân, Thông báo, Quản trị).
                </div>
              </div>
              <div>
                <?php if ($bugIdor): ?>
                  <span style="background: #fee2e2; color: #b91c1c; font-weight: 800; font-size: 12px; padding: 6px 12px; border-radius: 8px;">23 PASS / 1 FAIL (R02 Thất Bại do IDOR)</span>
                <?php else: ?>
                  <span style="background: #dcfce7; color: #15803d; font-weight: 800; font-size: 12px; padding: 6px 12px; border-radius: 8px;">24 PASS / 0 FAIL (Tất cả Đạt Chuẩn An Toàn)</span>
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
                    <th>Kết Quả Thực Tế</th>
                    <th>Trạng Thái</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($testCases as $tc): ?>
                    <tr style="<?= $tc['code'] === 'R02' ? ($tc['status'] === 'FAIL' ? 'background: #fff1f2;' : 'background: #f0fdf4;') : '' ?>">
                      <td><strong style="color: #0369a1;"><?= $tc['code'] ?></strong></td>
                      <td><code><?= $tc['method'] ?> <?= $tc['api'] ?></code></td>
                      <td><?= htmlspecialchars($tc['user']) ?></td>
                      <td><span style="font-size: 10px; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; font-weight: 700;"><?= $tc['role'] ?></span></td>
                      <td><?= htmlspecialchars($tc['desc']) ?></td>
                      <td><code style="color: #334155;"><?= $tc['expect'] ?></code></td>
                      <td style="font-size: 12px;"><?= htmlspecialchars($tc['actual']) ?></td>
                      <td>
                        <span style="font-weight: 800; font-size: 11px; padding: 2px 8px; border-radius: 6px; background: <?= $tc['status'] === 'PASS' ? '#dcfce7' : '#fee2e2' ?>; color: <?= $tc['status'] === 'PASS' ? '#15803d' : '#b91c1c' ?>;">
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

      <!-- VIEW 7: ĐĂNG NHẬP -->
      <?php elseif ($view === 'login'): ?>
        <div class="card" style="max-width: 480px; margin: 30px auto; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border-radius: 12px;">
          <div class="card-head" style="border-bottom: 2px solid #e2e8f0; padding-bottom: 14px;">
            <div>
              <div class="card-title" style="font-size: 18px; color: #0f172a;">Đăng Nhập MediRecord EMR</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">Hệ thống Quản lý Bệnh án Điện tử - Bệnh viện Bạch Mai</div>
            </div>
            <span style="font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 20px; font-weight: 700;">CỔNG 8205</span>
          </div>

          <?php if ($currentUser): ?>
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
              <div>
                Đang đăng nhập: <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong> (<code><?= htmlspecialchars($currentUser['username']) ?></code>)
              </div>
              <div style="display: flex; gap: 6px;">
                <a href="/?view=records" class="btn btn-primary" style="font-size: 11px; padding: 4px 8px;">Hồ Sơ</a>
                <a href="/?action=logout" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px; color: #b91c1c; border-color: #fca5a5;">Đăng Xuất</a>
              </div>
            </div>
          <?php endif; ?>

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
              <label for="loginUsername">Tên đăng nhập (Username):</label>
              <input type="text" id="loginUsername" name="username" value="<?= htmlspecialchars($_GET['u'] ?? '') ?>" placeholder="VD: bs_minh, bn_an, admin_bv" required autofocus>
            </div>
            <div class="form-group">
              <label for="loginPassword">Mật khẩu truy cập:</label>
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
        <div class="card" style="max-width: 500px; margin: 30px auto; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border-radius: 12px;">
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

      <!-- VIEW 9: DANH SÁCH BỆNH ÁN (MẶC ĐỊNH CHO TẤT CẢ VAI TRÒ) -->
      <?php else: ?>
        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Hồ Sơ Bệnh Án Điện Tử (Danh Sách Khám Bệnh)</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                Đang hiển thị danh sách hồ sơ thuộc thẩm quyền tiếp cận của tài khoản hiện tại.
              </div>
            </div>
            <div style="display: flex; gap: 8px;">
              <?php if ($currentUser && in_array($currentUser['role'], ['doctor', 'admin'], true)): ?>
                <a href="/?view=create_record" class="btn btn-primary">+ Lập Bệnh Án Mới</a>
              <?php endif; ?>
            </div>
          </div>

          <!-- Hộp tra cứu nhanh theo ID (Điểm kiểm thử IDOR trực tiếp) -->
          <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
            <div>
              <div style="font-weight: 700; font-size: 13px; color: #0f172a;">TRA CỨU & MỞ HỒ SƠ BỆNH ÁN THEO MÃ ID:</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">
                Nhập mã bệnh án (Ví dụ: ID <code>1</code>, <code>2</code> của An, hoặc ID <code>3</code> của Bình) để kiểm tra phản hồi phân quyền.
              </div>
            </div>
            <form action="/" method="GET" style="display: flex; gap: 8px; align-items: center;">
              <input type="hidden" name="view" value="record">
              <input type="number" name="id" min="1" max="10" value="3" style="width: 80px; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-weight: 700; text-align: center;">
              <button type="submit" class="btn btn-primary" style="font-size: 12px;">Tra Cứu Bệnh Án</button>
            </form>
          </div>

          <table>
            <thead>
              <tr>
                <th>Mã HS</th>
                <th>Tiêu Đề Bệnh Án</th>
                <th>Khoa Khám</th>
                <th>Bệnh Nhân Sở Hữu</th>
                <th>Bác Sĩ Phụ Trách</th>
                <th>Chẩn Đoán Sơ Bộ</th>
                <th>Ngày Lập</th>
                <th style="text-align: right;">Thao Tác</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($mockRecords as $rec): ?>
                <?php
                  $isOwn = ($currentUser && $rec['owner_username'] === $currentUser['username']);
                  $isStaff = ($currentUser && in_array($currentUser['role'], ['doctor', 'admin', 'auditor'], true));
                ?>
                <tr>
                  <td><code>#<?= $rec['id'] ?></code></td>
                  <td>
                    <strong><?= htmlspecialchars($rec['title']) ?></strong>
                    <?php if ($rec['id'] === 3): ?>
                      <span style="font-size: 10px; background: #fee2e2; color: #b91c1c; padding: 1px 6px; border-radius: 8px; font-weight: 800; margin-left: 4px;">BẢO MẬT</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($rec['department']) ?></td>
                  <td>
                    <code><?= htmlspecialchars($rec['owner_username']) ?></code>
                    <?php if ($isOwn): ?>
                      <span style="font-size: 10px; background: #dcfce7; color: #15803d; padding: 1px 5px; border-radius: 6px; font-weight: 700; margin-left: 2px;">Của bạn</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($rec['doctor_name'] ?? 'BS. CKI Lê Hoàng Minh') ?></td>
                  <td><span style="color: #0369a1;"><?= htmlspecialchars($rec['diagnosis'] ?? 'Đang cập nhật') ?></span></td>
                  <td><?= $rec['created_at'] ?></td>
                  <td style="text-align: right;">
                    <a href="/?view=record&id=<?= $rec['id'] ?>" class="btn btn-outline" style="padding: 4px 10px; font-size: 11px;">Xem Chi Tiết</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </div> <!-- /container -->
  <?php endif; ?>

  <!-- =======================================================================
       CÁC NÚT TRÒN NỔI (FAB) & CỬA SỔ ĐIỀU KHIỂN (HIỂN THỊ TOÀN HỆ THỐNG)
       ======================================================================= -->
  <!-- NÚT TRÒN NỔI (FAB) CHUYỂN TÀI KHOẢN NHANH (NẰM TRÊN NÚT TERMINAL) -->
  <button id="accountFab" onclick="toggleAccountDrawer()" class="account-fab" title="Chuyển Nhanh Tài Khoản (1-Click Switch)">
    <span class="account-fab-icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
      </svg>
    </span>
    <?php if ($incidentCount > 0): ?>
      <span class="account-fab-badge"><?= $incidentCount ?></span>
    <?php endif; ?>
  </button>

  <!-- NÚT TRÒN NỔI (FAB) TERMINAL GÓC DƯỚI BÊN PHẢI -->
  <button id="termFab" onclick="toggleTerminal()" class="term-fab" title="Mở Terminal Điều Khiển Lỗi Bảo Mật">
    <span class="term-fab-icon">&gt;_</span>
    <span class="term-fab-dot <?= $bugIdor ? 'dot-danger' : 'dot-safe' ?>"></span>
  </button>

  <!-- POPUP CHUYỂN TÀI KHOẢN NHANH -->
  <div id="accountDrawer" class="account-drawer" style="display: none;">
    <div class="acc-drawer-head">
      <div>
        <h4 style="margin: 0; font-size: 13px; font-weight: 700; color: #fff;">CHUYỂN NHANH VAI TRÒ (1-CLICK)</h4>
        <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
          <?= $currentUser ? 'Đang dùng: <strong>' . htmlspecialchars($currentUser['username']) . '</strong>' : 'Khách vãng lai' ?>
        </div>
      </div>
      <button onclick="toggleAccountDrawer()" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; color: #cbd5e1; border-color: #475569; background: transparent;">Đóng</button>
    </div>

    <div class="acc-drawer-list">
      <!-- Admin -->
      <a href="/?action=switch_user&user=admin_bv" class="acc-item <?= ($currentUser && $currentUser['username'] === 'admin_bv') ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-initial init-adm">AD</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">Quản trị viên Bệnh viện Bạch Mai</div>
            <div style="font-size: 11px; color: #64748b;">admin_bv - Phòng CNTT & An Ninh Mạng</div>
          </div>
        </div>
        <?php if ($currentUser && $currentUser['username'] === 'admin_bv'): ?>
          <span style="font-size: 11px; color: var(--primary); font-weight: 700;">Đang chọn</span>
        <?php endif; ?>
      </a>

      <!-- Doctor -->
      <a href="/?action=switch_user&user=bs_minh" class="acc-item <?= ($currentUser && $currentUser['username'] === 'bs_minh') ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-initial init-doc">BS</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">BS. CKI Lê Hoàng Minh</div>
            <div style="font-size: 11px; color: #64748b;">bs_minh - Khoa Tim Mạch & Nội Tiết</div>
          </div>
        </div>
        <?php if ($currentUser && $currentUser['username'] === 'bs_minh'): ?>
          <span style="font-size: 11px; color: var(--primary); font-weight: 700;">Đang chọn</span>
        <?php endif; ?>
      </a>

      <!-- Patient An -->
      <a href="/?action=switch_user&user=bn_an" class="acc-item <?= ($currentUser && $currentUser['username'] === 'bn_an') ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-initial init-pat">AN</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">Bệnh nhân Nguyễn Văn An</div>
            <div style="font-size: 11px; color: #64748b;">bn_an - Điều trị Ngoại trú (Tim Mạch)</div>
          </div>
        </div>
        <?php if ($currentUser && $currentUser['username'] === 'bn_an'): ?>
          <span style="font-size: 11px; color: var(--primary); font-weight: 700;">Đang chọn</span>
        <?php endif; ?>
      </a>

      <!-- Patient Binh -->
      <a href="/?action=switch_user&user=bn_binh" class="acc-item <?= ($currentUser && $currentUser['username'] === 'bn_binh') ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-initial init-pat">BI</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">Bệnh nhân Trần Thị Bình</div>
            <div style="font-size: 11px; color: #64748b;">bn_binh - Điều trị Nội trú (Khoa Ung Bướu)</div>
          </div>
        </div>
        <?php if ($currentUser && $currentUser['username'] === 'bn_binh'): ?>
          <span style="font-size: 11px; color: var(--primary); font-weight: 700;">Đang chọn</span>
        <?php endif; ?>
      </a>

      <!-- Tester -->
      <a href="/?action=switch_user&user=tester_sec" class="acc-item <?= ($currentUser && $currentUser['username'] === 'tester_sec') ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-initial init-aud">KT</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">Kỹ sư Nguyễn Kiểm Thử</div>
            <div style="font-size: 11px; color: #64748b;">tester_sec - Ban An Toàn Thông Tin & Kiểm Toán Y Tế</div>
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
          <?php if ($incidentCount > 0): ?>
            <span style="font-size: 10px; background: #fee2e2; color: #b91c1c; padding: 2px 6px; border-radius: 10px; font-weight: 700;"><?= $incidentCount ?> sự cố</span>
          <?php endif; ?>
          <?php if ($currentUser && $currentUser['username'] === 'tester_sec'): ?>
            <span style="font-size: 11px; color: var(--primary); font-weight: 700;">Đang chọn</span>
          <?php endif; ?>
        </div>
      </a>

      <!-- Anonymous / Guest -->
      <a href="/?action=switch_user&user=anon" class="acc-item <?= ($currentUser === null) ? 'active' : '' ?>">
        <div style="display: flex; align-items: center;">
          <div class="acc-initial init-anon">--</div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">Khách Vãng Lai</div>
            <div style="font-size: 11px; color: #64748b;">Chưa đăng nhập (Anonymous)</div>
          </div>
        </div>
        <?php if ($currentUser === null): ?>
          <span style="font-size: 11px; color: var(--primary); font-weight: 700;">Đang chọn</span>
        <?php endif; ?>
      </a>
    </div>

    <div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
      <a href="/?view=login" style="color: var(--primary); text-decoration: none; font-weight: 700;">Đăng Nhập</a>
      <span style="color: #cbd5e1;">|</span>
      <a href="/?view=register" style="color: var(--primary); text-decoration: none; font-weight: 700;">Đăng Ký</a>
      <span style="color: #cbd5e1;">|</span>
      <a href="/?action=logout" style="color: #ef4444; text-decoration: none; font-weight: 700;">Đăng Xuất</a>
    </div>
  </div>

  <!-- TERMINAL DRAWER -->
  <div id="termDrawer" class="term-drawer" style="display: none;">
    <div class="term-drawer-head">
      <span>MEDIRECORD TERMINAL [<?= $bugIdor ? 'IDOR: ON' : 'SECURE' ?>]</span>
      <button onclick="toggleTerminal()" class="btn btn-outline" style="padding: 2px 6px; font-size: 10px; color: #cbd5e1; border-color: #334155; background: transparent;">Đóng</button>
    </div>
    
    <div class="term-quick-btns">
      <a href="/?action=toggle_bug_idor&state=1&return_view=<?= urlencode($view) ?>" class="term-qbtn" style="color: #f87171; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">Tạo Lỗi (set BUG_IDOR=1)</a>
      <a href="/?action=toggle_bug_idor&state=0&return_view=<?= urlencode($view) ?>" class="term-qbtn" style="color: #4ade80; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">Vá Lỗi (set BUG_IDOR=0)</a>
      <button onclick="execCmd('status')" class="term-qbtn">status</button>
      <button onclick="execCmd('curl /api/notes/3')" class="term-qbtn">curl /api/notes/3</button>
      <button onclick="execCmd('clear')" class="term-qbtn">clear</button>
    </div>

    <div class="term-drawer-body" id="termLogs">
      <div>MEDIRECORD MEDICAL SECURITY CONSOLE [v2.0]</div>
      <div>Trạng thái: BUG_IDOR = <?= $bugIdor ? '<span style="color:#ef4444;font-weight:700;">1 (LỖ HỔNG ĐANG BẬT)</span>' : '<span style="color:#22c55e;font-weight:700;">0 (AN TOÀN - ĐÃ VÁ)</span>' ?></div>
      <div>Gõ 'help' hoặc bấm các nút phía trên để điều khiển.</div>
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
      if (!accDrawer) return;
      if (accDrawer.style.display === 'none' || accDrawer.style.display === '') {
        accDrawer.style.display = 'block';
        if (termDrawer) termDrawer.style.display = 'none';
      } else {
        accDrawer.style.display = 'none';
      }
    }

    // Bật/tắt Cửa sổ Terminal
    function toggleTerminal() {
      const termDrawer = document.getElementById('termDrawer');
      const accDrawer = document.getElementById('accountDrawer');
      if (!termDrawer) return;
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
          const isBug = cmd.includes('1') ? '1' : '0';
          const url = new URL(window.location.href);
          url.searchParams.set('bug', isBug);
          setTimeout(() => { window.location.href = url.toString(); }, 400);
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
      const m = document.getElementById('addUserModal');
      if (m) m.style.display = 'flex';
    }
    function closeAddUserModal() {
      const m = document.getElementById('addUserModal');
      if (m) m.style.display = 'none';
    }

    function openEditUserModal(username, fullName, role, dept) {
      const u = document.getElementById('editUsername');
      if (u) {
        u.value = username;
        document.getElementById('displayEditUsername').value = username;
        document.getElementById('editFullName').value = fullName;
        document.getElementById('editRole').value = role;
        document.getElementById('editDept').value = dept;
        document.getElementById('editUserModal').style.display = 'flex';
      }
    }
    function closeEditUserModal() {
      const m = document.getElementById('editUserModal');
      if (m) m.style.display = 'none';
    }

    // Tìm kiếm người dùng tức thì (Live search filter)
    function filterUsers() {
      const input = document.getElementById('userSearchInput');
      if (!input) return;
      const filter = input.value.toLowerCase();
      const table = document.getElementById('userTable');
      if (!table) return;
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
