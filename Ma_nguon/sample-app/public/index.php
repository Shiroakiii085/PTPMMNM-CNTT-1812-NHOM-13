<?php

declare(strict_types=1);

// Khởi tạo session cho trình duyệt Web
if (session_status() === PHP_SESSION_NONE) {
    // Đặt thư mục session nếu có
    $sessionDir = __DIR__ . '/../../.local/sessions';
    if (is_dir($sessionDir) || @mkdir($sessionDir, 0777, true)) {
        session_save_path($sessionDir);
    }
    @session_start();
}

// 1. Chỉ lắng nghe trên loopback (127.0.0.1, ::1, localhost) để đảm bảo an toàn
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
} catch (Throwable) {
    // Fallback: in-memory store
}

// 3. Danh sách tài khoản thật theo Bối cảnh Y Tế (MediRecord EMR)
// Bác sĩ gửi bệnh án, Bệnh nhân nhận bệnh án, Quản trị viên, Người kiểm thử
$mockUsers = [
    'admin_bv' => [
        'username' => 'admin_bv',
        'role' => 'admin',
        'full_name' => 'Quản trị viên Bệnh viện Bạch Mai',
        'department' => 'Phòng Công Nghệ Thông Tin & An Ninh Mạng',
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
        'role' => 'member', // patient
        'full_name' => 'Bệnh nhân Nguyễn Văn An',
        'department' => 'Bệnh nhân điều trị ngoại trú',
        'token' => 'test_token_bn_an'
    ],
    'bn_binh' => [
        'username' => 'bn_binh',
        'role' => 'member', // patient
        'full_name' => 'Bệnh nhân Trần Thị Bình',
        'department' => 'Bệnh nhân điều trị chuyên khoa',
        'token' => 'test_token_bn_binh'
    ],
];

// Alias tokens để các ca kiểm thử tự động của AccessTester hoạt động trơn tru
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

// 4. Danh sách Bệnh án mẫu (Medical Records do Bác sĩ Minh gửi cho bệnh nhân)
$mockRecords = [
    1 => [
        'id' => 1,
        'owner_username' => 'bn_an',
        'doctor_username' => 'bs_minh',
        'doctor_name' => 'BS. CKI Lê Hoàng Minh',
        'title' => 'Bệnh án số 01: Khám chuyên khoa Tim Mạch - BN Nguyễn Văn An',
        'department' => 'Khoa Tim Mạch Can Thiệp',
        'diagnosis' => 'Rối loạn nhịp xoang độ 2, theo dõi thiếu máu cơ tim cục bộ',
        'content' => 'Bi mat cua Alice 1: Chẩn đoán rối loạn nhịp xoang độ 2. Phác đồ điều trị nội khoa bảo mật do BS Minh chỉ định. Đề nghị hạn chế vận động mạnh và tái khám sau 4 tuần.',
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
        'content' => 'Chỉ số HbA1c 5.8%, lipid máu trong giới hạn kiểm soát. Tiếp tục duy trì chế độ dinh dưỡng ít đường và dùng thuốc điều hòa nhịp tim theo đơn.',
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
        'content' => 'Bi mat cua Bob 3: Kết quả sinh thiết giải phẫu bệnh lý giai đoạn 1. Phác đồ hóa xạ trị bảo mật thông tin bệnh nhân nghiêm ngặt. Chỉ định theo dõi sát tác dụng phụ chu kỳ 1.',
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
        'content' => 'Báo cáo chi tiết các đợt phát thuốc bảo hiểm y tế đặc biệt. Thể trạng bệnh nhân ổn định, đáp ứng tốt với phác đồ hỗ trợ miễn dịch.',
        'created_at' => '2026-10-24 10:45:00'
    ],
];

// Khởi tạo danh sách bệnh án động trong Session nếu chưa có
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
        'body' => 'Bệnh viện duy trì trực cấp cứu 24/7. Các khoa lâm sàng và cận lâm sàng làm việc từ 07:30 đến 17:00 hàng ngày. Dữ liệu dùng chung cho toàn thể cán bộ y tế và bệnh nhân.',
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
if (preg_match('/Bearer\s+([A-Za-z0-9_\-\.\~]+)/i', $authHeader, $matches)) {
    $token = $matches[1];
    $matchedUsername = $tokenMap[$token] ?? null;

    if ($db !== null) {
        try {
            $r = queryParams(
                $db,
                'SELECT u.username, u.role, u.full_name, u.department
                 FROM sample_app.tokens t
                 JOIN sample_app.users u ON u.username = t.username
                 WHERE t.token = $1',
                [$token]
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

// Nếu không có Bearer token, lấy từ Session Web
if ($currentUser === null && isset($_SESSION['auth_user'])) {
    $sessionUser = $_SESSION['auth_user'];
    if (isset($mockUsers[$sessionUser])) {
        $currentUser = $mockUsers[$sessionUser];
    } elseif (isset($_SESSION['registered_users'][$sessionUser])) {
        $currentUser = $_SESSION['registered_users'][$sessionUser];
    }
}

// 7. Xác định Phương thức & URI
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// 8. Quản lý cờ lỗi IDOR: Ưu tiên Header > ENV > File cờ .local/bug_idor.flag > Query Param > Session
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
// XỬ LÝ CÁC HÀNH ĐỘNG WEB (QUICK SWITCH, LOGIN, REGISTER, TERMINAL COMMANDS)
// =============================================================================
$action = $_GET['action'] ?? '';

// Chuyển đổi tài khoản nhanh 1-Click
if ($action === 'switch_user') {
    $targetUser = $_GET['user'] ?? '';
    if ($targetUser === 'anon') {
        unset($_SESSION['auth_user']);
    } elseif (isset($mockUsers[$targetUser]) || isset($_SESSION['registered_users'][$targetUser])) {
        $_SESSION['auth_user'] = $targetUser;
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?view=records');
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
        $output[] = "  curl <path>         : Giả lập yêu cầu HTTP kiểm tra mã phản hồi (VD: curl /api/notes/3)";
        $output[] = "  clear               : Xóa màn hình terminal";
    } elseif (preg_match('/^set\s+BUG_IDOR\s*=\s*([01])$/i', $cmd, $m)) {
        $val = $m[1];
        @mkdir(dirname($flagFile), 0777, true);
        @file_put_contents($flagFile, $val);
        $_SESSION['bug_idor_flag'] = ($val === '1');
        $newStatus = ($val === '1');
        if ($val === '1') {
            $output[] = "[OK] KÍCH HOẠT LỖ HỔNG IDOR THÀNH CÔNG!";
            $output[] = "-> Cờ BUG_IDOR = 1 đã được ghi vào .local/bug_idor.flag";
            $output[] = "-> Cảnh báo: Bệnh nhân An hiện có thể đọc trộm Bệnh án số 03 của Bệnh nhân Bình!";
        } else {
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
        if (isset($mockUsers[$u]) || isset($_SESSION['registered_users'][$u])) {
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
                    $output[] = "< [RÒ RỈ NỘI DUNG] Bi mat cua Bob 3: Kết quả sinh thiết giải phẫu bệnh lý giai đoạn 1...";
                } else {
                    $output[] = "< HTTP/1.1 403 Forbidden";
                    $output[] = "< [TỪ CHỐI AN TOÀN] error: FORBIDDEN - Bệnh nhân An không có quyền xem bệnh án của Bệnh nhân Bình";
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

// Xử lý Form Bác sĩ gửi bệnh án mới
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

        // Lưu vào PostgreSQL nếu có
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
    $regFullName = trim((string)($_POST['full_name'] ?? ''));
    $regRole = in_array($_POST['role'] ?? '', ['doctor', 'member'], true) ? $_POST['role'] : 'member';
    $regDept = trim((string)($_POST['department'] ?? 'Khoa Khám Bệnh'));

    if ($regUsername !== '' && $regPassword !== '' && $regFullName !== '') {
        if (!isset($_SESSION['registered_users'])) {
            $_SESSION['registered_users'] = [];
        }
        $newUser = [
            'username' => $regUsername,
            'role' => $regRole,
            'full_name' => $regFullName,
            'department' => $regDept,
            'token' => 'token_' . bin2hex(random_bytes(8))
        ];
        $_SESSION['registered_users'][$regUsername] = $newUser;
        $_SESSION['auth_user'] = $regUsername;

        if ($db !== null) {
            try {
                $hash = password_hash($regPassword, PASSWORD_DEFAULT);
                queryParams(
                    $db,
                    'INSERT INTO sample_app.users (username, password_hash, role, full_name, department) VALUES ($1, $2, $3, $4, $5) ON CONFLICT DO NOTHING',
                    [$regUsername, $hash, $regRole, $regFullName, $regDept]
                );
            } catch (Throwable) {}
        }

        header('Location: /?view=records&reg_success=1');
        exit;
    }
}

// Xử lý Form Đăng nhập
if ($action === 'login_submit' && $method === 'POST') {
    $loginUser = trim((string)($_POST['username'] ?? ''));
    if (isset($mockUsers[$loginUser]) || isset($_SESSION['registered_users'][$loginUser])) {
        $_SESSION['auth_user'] = $loginUser;
        header('Location: /?view=records');
        exit;
    } else {
        header('Location: /?view=login&err=invalid');
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
    if (isset($token)) {
        if ($token === 'test_token_alice_123') {
            $respUsername = 'alice';
        } elseif ($token === 'test_token_admin_789') {
            $respUsername = 'admin1';
        } elseif ($token === 'test_token_auditor_321') {
            $respUsername = 'auditor1';
        } elseif ($token === 'test_token_bob_456') {
            $respUsername = 'bob';
        }
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

// GET /api/notes/{id} hoặc /api/records/{id} (TRỌNG TÂM LỖ HỔNG IDOR)
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

    // =========================================================================
    // LỖ HỔNG IDOR CỐ Ý (KHI BUG_IDOR = 1):
    // Chỉ kiểm tra đăng nhập mà không so sánh người sở hữu!
    // Bệnh nhân An (bn_an) đọc được bệnh án số 03 tuyệt mật của Bệnh nhân Bình (bn_binh)
    // =========================================================================
    if ($bugIdor && $noteId === 3) {
        echo json_encode([
            'note' => $note,
            'record' => $note,
            'debug_bug_idor' => true,
            'warning' => 'IDOR_VULNERABILITY_ACTIVE',
            'leak_detected' => true
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================================
    // BẢN AN TOÀN ĐÃ SỬA LỖI (KHI BUG_IDOR = 0):
    // Kiểm tra đúng quyền sở hữu: Bệnh nhân sở hữu, Bác sĩ điều trị, hoặc Admin/Auditor
    // =========================================================================
    $username = $currentUser['username'];
    $role = $currentUser['role'];
    $isOwner = ($note['owner_username'] === $username);
    $canRead = $isOwner || in_array($role, ['admin', 'auditor', 'doctor'], true);

    if (!$canRead) {
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

    $note = null;
    if ($db !== null) {
        try {
            $r = queryParams($db, 'SELECT id, owner_username, title, content FROM sample_app.notes WHERE id = $1', [$noteId]);
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

    // Chỉ chủ sở hữu mới có quyền sửa
    if ($note['owner_username'] !== $currentUser['username']) {
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
    $errMsg = $_GET['msg'] ?? 'Truy cập bị từ chối: Bệnh nhân Nguyễn Văn An không có quyền xem bệnh án thuộc sở hữu của Bệnh nhân Trần Thị Bình.';
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
        .btn-row { display: flex; gap: 12px; }
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
          <strong>Thông điệp hệ thống:</strong><br>
          <?= htmlspecialchars($errMsg) ?>
        </div>
        <div class="sec-detail">
          <strong>Nguyên tắc bảo vệ:</strong> Hệ thống áp dụng kiểm soát truy cập dựa trên thuộc tính sở hữu (ABAC - Attribute-Based Access Control). Bệnh nhân chỉ được xem hồ sơ bệnh án thuộc quyền sở hữu của chính mình. Chỉ Bác sĩ điều trị hoặc Quản trị viên mới có quyền tra cứu chéo.
        </div>
        <div class="btn-row">
          <a href="/?view=records" class="btn btn-pri">Quay lại Bệnh án của tôi</a>
          <a href="/?action=switch_user&user=bs_minh" class="btn btn-sec">Đổi sang Bác sĩ Minh để xem</a>
          <a href="/?view=terminal" class="btn btn-sec">Mở Terminal để bật lại lỗi</a>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// Xử lý xem chi tiết bệnh án trên Web (View Record)
$recordDetail = null;
$viewForbidden = false;
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

    // KIỂM TRA LỖI IDOR:
    $isOwner = ($recordDetail['owner_username'] === $currentUser['username']);
    $isStaff = in_array($currentUser['role'], ['doctor', 'admin', 'auditor'], true);

    if (!$isOwner && !$isStaff) {
        if ($bugIdor && $recId === 3) {
            // ĐANG BẬT LỖI: Cho phép xem nhưng bật cảnh báo đỏ rực!
            $leakActive = true;
        } else {
            // ĐÃ SỬA LỖI: Bị chặn với HTTP 403
            header('Location: /?view=error&code=403&msg=' . urlencode("Bệnh nhân {$currentUser['full_name']} ({$currentUser['username']}) không có quyền xem bệnh án số 03 của {$recordDetail['owner_username']}!"));
            exit;
        }
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
    
    /* Top Fast Switch Bar */
    .switch-bar { background: #0f172a; color: #e2e8f0; padding: 8px 16px; font-size: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
    .switch-bar span { font-weight: 600; color: #94a3b8; }
    .switch-btns { display: flex; gap: 6px; flex-wrap: wrap; }
    .sw-btn { background: #1e293b; color: #cbd5e1; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; transition: all 0.15s ease; border: 1px solid #334155; }
    .sw-btn:hover { background: #334155; color: #fff; }
    .sw-btn.active { background: #0284c7; color: #fff; border-color: #0284c7; }

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
    
    /* Main Layout */
    .container { max-width: 1040px; margin: 24px auto; padding: 0 16px; }

    /* Security Notice Bar */
    .idor-banner { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
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

    /* Terminal Console */
    .term-window { background: #090d16; border: 1px solid #1e293b; border-radius: 8px; overflow: hidden; font-family: "Courier New", Courier, monospace; color: #10b981; margin-top: 16px; }
    .term-head { background: #111827; padding: 8px 14px; font-size: 12px; color: #94a3b8; display: flex; justify-content: space-between; border-bottom: 1px solid #1f2937; }
    .term-body { padding: 16px; min-height: 220px; max-height: 380px; overflow-y: auto; font-size: 13px; line-height: 1.6; }
    .term-input-row { display: flex; align-items: center; background: #111827; padding: 8px 14px; border-top: 1px solid #1f2937; }
    .term-prompt { color: #38bdf8; font-weight: 700; margin-right: 8px; font-size: 13px; }
    .term-input { flex: 1; background: transparent; border: none; color: #f8fafc; font-family: monospace; font-size: 13px; outline: none; }
    
    /* Forms */
    .form-group { margin-bottom: 16px; }
    label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
    input[type="text"], input[type="password"], textarea, select { width: 100%; padding: 9px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; font-family: inherit; }
    textarea { min-height: 100px; resize: vertical; }

    /* Leak Alert Box */
    .leak-box { background: #fff1f2; border: 2px solid #f43f5e; border-radius: 8px; padding: 18px; margin-bottom: 20px; animation: pulse 2s infinite; }
    @keyframes pulse { 0%, 100% { border-color: #f43f5e; } 50% { border-color: #be123c; } }
  </style>
</head>
<body>

  <!-- FAST ACCOUNT SWITCHER BAR -->
  <div class="switch-bar">
    <div>
      <span>CHUYỂN ĐỔI TÀI KHOẢN NHANH (1-CLICK FAST SWITCH):</span>
    </div>
    <div class="switch-btns">
      <a href="/?action=switch_user&user=bs_minh" class="sw-btn <?= ($currentUser && $currentUser['username'] === 'bs_minh') ? 'active' : '' ?>">Bác sĩ Minh (Doctor)</a>
      <a href="/?action=switch_user&user=bn_an" class="sw-btn <?= ($currentUser && $currentUser['username'] === 'bn_an') ? 'active' : '' ?>">Bệnh nhân An (Patient)</a>
      <a href="/?action=switch_user&user=bn_binh" class="sw-btn <?= ($currentUser && $currentUser['username'] === 'bn_binh') ? 'active' : '' ?>">Bệnh nhân Bình (Patient)</a>
      <a href="/?action=switch_user&user=admin_bv" class="sw-btn <?= ($currentUser && $currentUser['username'] === 'admin_bv') ? 'active' : '' ?>">Quản trị viên (Admin)</a>
      <a href="/?action=switch_user&user=tester_sec" class="sw-btn <?= ($currentUser && $currentUser['username'] === 'tester_sec') ? 'active' : '' ?>">Người kiểm thử (Tester)</a>
      <a href="/?action=switch_user&user=anon" class="sw-btn <?= ($currentUser === null) ? 'active' : '' ?>">Khách vãng lai (Anon)</a>
    </div>
  </div>

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
      <a href="/?view=terminal" class="<?= $view === 'terminal' ? 'active' : '' ?>">Màn Hình Terminal</a>
      <a href="http://localhost:8105" target="_blank" style="color: #2563eb;">Web AccessTester (Cổng 8105)</a>

      <?php if ($currentUser): ?>
        <span style="color: var(--muted); font-size: 12px; margin-left: 8px;">
          Xin chào, <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong> (<?= htmlspecialchars($currentUser['role']) ?>)
        </span>
        <a href="/?action=logout" class="btn btn-outline" style="padding: 4px 10px; font-size: 12px;">Đăng xuất</a>
      <?php else: ?>
        <a href="/?view=login" class="btn btn-outline" style="padding: 4px 10px; font-size: 12px;">Đăng nhập</a>
        <a href="/?view=register" class="btn btn-primary" style="padding: 4px 10px; font-size: 12px;">Đăng ký</a>
      <?php endif; ?>
    </nav>
  </header>

  <div class="container">

    <!-- BANNER TRỰC QUAN HÓA TRẠNG THÁI BẢO MẬT -->
    <?php if ($bugIdor): ?>
      <div class="idor-banner">
        <div>
          <strong style="color: var(--danger); font-size: 14px;">CHẾ ĐỘ THỬ NGHIỆM: LỖ HỔNG IDOR ĐANG KÍCH HOẠT!</strong>
          <p style="color: #7f1d1d; font-size: 12px; margin-top: 4px;">
            Bệnh nhân An (<code>bn_an</code>) hiện có thể đọc trộm bệnh án số 03 riêng tư của Bệnh nhân Bình (<code>bn_binh</code>) do hệ thống bỏ qua kiểm tra quyền sở hữu.
          </p>
        </div>
        <div>
          <button onclick="execCmd('set BUG_IDOR=0')" class="btn btn-safe">Vá Lỗ Hổng (Tắt Lỗi)</button>
        </div>
      </div>
    <?php else: ?>
      <div class="idor-banner idor-banner-safe">
        <div>
          <strong style="color: var(--safe); font-size: 14px;">HỆ THỐNG AN TOÀN: LỖ HỔNG IDOR ĐÃ ĐƯỢC VÁ!</strong>
          <p style="color: #14532d; font-size: 12px; margin-top: 4px;">
            Quy tắc ABAC đang hoạt động chuẩn xác: Bệnh nhân chỉ được xem bệnh án của chính mình. Truy cập chéo sẽ bị chặn với mã HTTP 403 Forbidden.
          </p>
        </div>
        <div>
          <button onclick="execCmd('set BUG_IDOR=1')" class="btn btn-danger">Tạo Lỗi (Bật IDOR)</button>
        </div>
      </div>
    <?php endif; ?>

    <!-- VIEW 1: CHI TIẾT BỆNH ÁN (TRỌNG TÂM LỖI IDOR) -->
    <?php if ($view === 'record' && $recordDetail): ?>
      <?php if ($leakActive): ?>
        <div class="leak-box">
          <div style="font-weight: 800; color: #be123c; font-size: 15px; margin-bottom: 6px;">
            [CẢNH BÁO BẢO MẬT] PHÁT HIỆN LỖ HỔNG IDOR / BOLA (CWE-639) ĐANG BỊ KHAI THÁC!
          </div>
          <p style="color: #9f1239; font-size: 13px;">
            Tài khoản hiện tại: <strong><?= htmlspecialchars($currentUser['username']) ?></strong> (<?= htmlspecialchars($currentUser['full_name']) ?>)<br>
            Chủ sở hữu thực sự của bệnh án: <strong><?= htmlspecialchars($recordDetail['owner_username']) ?></strong> (Bệnh nhân Trần Thị Bình)<br>
            <em>Máy chủ đã bỏ qua kiểm tra quan hệ sở hữu đối tượng và làm rò rỉ dữ liệu y tế nhạy cảm bên dưới:</em>
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
          <div>
            <a href="/?view=records" class="btn btn-outline">Quay lại danh sách</a>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; font-size: 13px;">
          <div style="background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--border);">
            <strong>Bệnh nhân sở hữu:</strong> <?= htmlspecialchars($recordDetail['owner_username']) ?><br>
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

        <div style="font-size: 12px; color: var(--muted); border-top: 1px solid var(--border); padding-top: 12px;">
          Kiểm tra API tương đương: <code>GET http://127.0.0.1:8205/api/notes/<?= $recordDetail['id'] ?></code>
        </div>
      </div>

    <!-- VIEW 2: DANH SÁCH BỆNH ÁN -->
    <?php elseif ($view === 'records'): ?>
      <div class="card">
        <div class="card-head">
          <div>
            <div class="card-title">Hồ Sơ Bệnh Án Điện Tử (MediRecord Records)</div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
              <?php if ($currentUser && $currentUser['role'] === 'member'): ?>
                Góc nhìn Bệnh nhân: Bạn chỉ xem được hồ sơ thuộc quyền sở hữu của mình.
              <?php elseif ($currentUser && in_array($currentUser['role'], ['doctor', 'admin', 'auditor'], true)): ?>
                Góc nhìn Cán bộ Y tế / Kiểm toán: Bạn có quyền xem toàn bộ hồ sơ trong khoa.
              <?php else: ?>
                Khách vãng lai: Chưa đăng nhập. Hãy chọn tài khoản trên thanh trên cùng để thử nghiệm.
              <?php endif; ?>
            </div>
          </div>
          <div>
            <?php if ($currentUser && in_array($currentUser['role'], ['doctor', 'admin'], true)): ?>
              <a href="/?view=create_record" class="btn btn-primary">+ Bác Sĩ Gửi Bệnh Án Mới</a>
            <?php endif; ?>
          </div>
        </div>

        <!-- HỘP THỬ NGHIỆM IDOR NHANH CHO BỆNH NHÂN AN -->
        <?php if ($currentUser && $currentUser['username'] === 'bn_an'): ?>
          <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <div>
              <strong style="color: #1e40af;">KỊCH BẢN THỬ NGHIỆM TRỰC QUAN CHO BỆNH NHÂN AN:</strong>
              <div style="font-size: 12px; color: #1d4ed8; margin-top: 2px;">
                Thử cố tình bấm nút để mở <strong>Bệnh án số 03 của Bệnh nhân Bình</strong> xem hệ thống xử lý như thế nào:
              </div>
            </div>
            <div>
              <a href="/?view=record&id=3" class="btn <?= $bugIdor ? 'btn-danger' : 'btn-primary' ?>">
                Cố tình mở Bệnh án số 03 (của Bình)
              </a>
            </div>
          </div>
        <?php endif; ?>

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
                  Chưa có bệnh án nào hiển thị hoặc bạn chưa đăng nhập. Vui lòng chọn 1 tài khoản ở thanh chuyển đổi nhanh phía trên!
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
                  <td>
                    <code><?= htmlspecialchars($rec['owner_username']) ?></code>
                  </td>
                  <td><?= htmlspecialchars($rec['department']) ?></td>
                  <td><?= htmlspecialchars($rec['doctor_name'] ?? 'BS. CKI Lê Hoàng Minh') ?></td>
                  <td style="color: var(--muted); font-size: 12px;"><?= $rec['created_at'] ?></td>
                  <td>
                    <a href="/?view=record&id=<?= $rec['id'] ?>" class="btn btn-outline" style="padding: 4px 8px; font-size: 12px;">Xem chi tiết</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    <!-- VIEW 3: BÁC SĨ GỬI BỆNH ÁN MỚI -->
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

    <!-- VIEW 4: MÀN HÌNH TERMINAL ĐIỀU KHIỂN LỖI -->
    <?php elseif ($view === 'terminal'): ?>
      <div class="card">
        <div class="card-head">
          <div>
            <div class="card-title">Màn Hình Terminal Điều Khiển Lỗi & Kiểm Thử Dòng Lệnh</div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
              Tạo lỗi, vá lỗi, tra cứu trạng thái bảo mật và kiểm tra mã HTTP phản hồi thời gian thực.
            </div>
          </div>
          <div style="display: flex; gap: 8px;">
            <button onclick="execCmd('set BUG_IDOR=1')" class="btn btn-danger">Lệnh: Tạo Lỗi (set BUG_IDOR=1)</button>
            <button onclick="execCmd('set BUG_IDOR=0')" class="btn btn-safe">Lệnh: Vá Lỗi (set BUG_IDOR=0)</button>
            <button onclick="execCmd('status')" class="btn btn-outline">Lệnh: status</button>
            <button onclick="execCmd('curl /api/notes/3')" class="btn btn-outline">Lệnh: curl /api/notes/3</button>
          </div>
        </div>

        <div class="term-window">
          <div class="term-head">
            <span>MEDIRECORD TERMINAL CONSOLE v2.0</span>
            <span>HOST: 127.0.0.1:8205</span>
          </div>
          <div class="term-body" id="termLogs">
            <div>MEDIRECORD MEDICAL SECURITY CONSOLE [Version 2.0.26]</div>
            <div>(c) 2026 Nhóm 13 - Phát triển phần mềm mã nguồn mở.</div>
            <div>Trạng thái hiện tại: BUG_IDOR = <?= $bugIdor ? '<span style="color:#ef4444;font-weight:700;">1 (LỖ HỔNG ĐANG BẬT)</span>' : '<span style="color:#22c55e;font-weight:700;">0 (AN TOÀN - ĐÃ VÁ LỖI)</span>' ?></div>
            <div>Gõ 'help' để xem danh sách lệnh hoặc bấm các nút phía trên.</div>
            <br>
          </div>
          <div class="term-input-row">
            <span class="term-prompt">medirecord@sec:~$</span>
            <input type="text" id="termInput" class="term-input" placeholder="Nhập lệnh (VD: set BUG_IDOR=1, status, help)..." autocomplete="off">
          </div>
        </div>
      </div>

    <!-- VIEW 5: THÔNG BÁO DÙNG CHUNG -->
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

    <!-- VIEW 6: QUẢN LÝ NGƯỜI DÙNG (ADMIN ONLY) -->
    <?php elseif ($view === 'users'): ?>
      <div class="card">
        <div class="card-head">
          <div class="card-title">Danh Sách Người Dùng Hệ Thống (Chỉ Dành Cho Quản Trị Viên)</div>
        </div>
        <table>
          <thead>
            <tr>
              <th>Tên Đăng Nhập</th>
              <th>Họ Và Tên</th>
              <th>Vai Trò (Role)</th>
              <th>Đơn Vị / Khoa Phòng</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($mockUsers as $u): ?>
              <tr>
                <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                <td><span class="status-badge <?= $u['role'] === 'admin' ? 'badge-danger' : ($u['role'] === 'doctor' ? 'badge-safe' : 'badge-safe') ?>"><?= htmlspecialchars($u['role']) ?></span></td>
                <td><?= htmlspecialchars($u['department'] ?? 'Khoa Khám Bệnh') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <!-- VIEW 7: ĐĂNG NHẬP -->
    <?php elseif ($view === 'login'): ?>
      <div class="card" style="max-width: 440px; margin: 40px auto;">
        <div class="card-head">
          <div class="card-title">Đăng Nhập MediRecord</div>
        </div>
        <form action="/?action=login_submit" method="POST">
          <div class="form-group">
            <label>Tên đăng nhập:</label>
            <input type="text" name="username" placeholder="VD: bs_minh, bn_an, bn_binh, admin_bv" required>
          </div>
          <div class="form-group">
            <label>Mật khẩu:</label>
            <input type="password" name="password" value="TestPass@2026" required>
          </div>
          <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px;">Đăng Nhập</button>
        </form>
        <div style="margin-top: 16px; font-size: 12px; text-align: center; color: var(--muted);">
          Mẹo: Bạn có thể bấm thẳng vào các nút trên thanh màu đen ở đầu trang để đăng nhập tức thì!
        </div>
      </div>

    <!-- VIEW 8: ĐĂNG KÝ -->
    <?php elseif ($view === 'register'): ?>
      <div class="card" style="max-width: 480px; margin: 40px auto;">
        <div class="card-head">
          <div class="card-title">Đăng Ký Tài Khoản Y Tế Mới</div>
        </div>
        <form action="/?action=register_submit" method="POST">
          <div class="form-group">
            <label>Tên đăng nhập:</label>
            <input type="text" name="username" placeholder="VD: bn_tuan" required>
          </div>
          <div class="form-group">
            <label>Mật khẩu:</label>
            <input type="password" name="password" required>
          </div>
          <div class="form-group">
            <label>Họ và tên:</label>
            <input type="text" name="full_name" placeholder="VD: Nguyễn Tuấn Anh" required>
          </div>
          <div class="form-group">
            <label>Vai trò:</label>
            <select name="role">
              <option value="member">Bệnh nhân (Patient)</option>
              <option value="doctor">Bác sĩ khám chữa bệnh (Doctor)</option>
            </select>
          </div>
          <div class="form-group">
            <label>Đơn vị / Khoa phòng:</label>
            <input type="text" name="department" value="Bệnh nhân ngoại trú">
          </div>
          <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px;">Hoàn Tất Đăng Ký</button>
        </form>
      </div>
    <?php endif; ?>

  </div>

  <script>
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
      appendLog('medirecord@sec:~$ ' + cmd, '#38bdf8');
      
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
        // Tự động tải lại trang nếu đổi cờ IDOR
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
  </script>
</body>
</html>
