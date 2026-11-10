<?php

declare(strict_types=1);

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
    foreach ($cases as &$c) {
        $c['latency_ms'] = ($c['code'] === 'R02') ? 24 : (10 + (crc32($c['code']) % 18));
        $c['leak_detected'] = ($c['code'] === 'R02' && $bugIdor);
    }
    unset($c);
    return $cases;
}

function getPending24AccessTestCases(): array {
    $cases = run24AccessTests(false);
    foreach ($cases as &$c) {
        $c['status'] = 'PENDING';
        $c['actual'] = 'Chưa thực thi — Nhấn nút Chạy để kiểm tra';
        $c['latency_ms'] = 0;
        $c['leak_detected'] = false;
    }
    unset($c);
    return $cases;
}

function getCurrentManualMatrixCases(): array {
    if (!empty($_SESSION['manual_matrix_cases']) && is_array($_SESSION['manual_matrix_cases'])) {
        return array_values($_SESSION['manual_matrix_cases']);
    }
    return getPending24AccessTestCases();
}

function getTestRunsHistory(): array {
    $file = SAMPLE_PUBLIC_DIR . '/../../.local/test_runs_history.json';
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) {
            $_SESSION['test_runs_history'] = $decoded;
            return $decoded;
        }
    }
    if (!empty($_SESSION['test_runs_history']) && is_array($_SESSION['test_runs_history'])) {
        return $_SESSION['test_runs_history'];
    }
    return [];
}

function saveTestRunsHistory(array $runs): void {
    $file = SAMPLE_PUBLIC_DIR . '/../../.local/test_runs_history.json';
    @mkdir(dirname($file), 0777, true);
    @file_put_contents($file, json_encode(array_values($runs), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $_SESSION['test_runs_history'] = array_values($runs);
}

function buildTestRunRecord(string $name, string $note, array $cases, bool $bugIdor, ?array $user, ?string $customTime = null, ?string $customId = null): array {
    $total = count($cases);
    $passed = 0;
    $failed = 0;
    $pending = 0;
    $durationMs = 0;
    $leakCount = 0;

    foreach ($cases as $c) {
        $st = $c['status'] ?? 'PENDING';
        if ($st === 'PASS') $passed++;
        elseif ($st === 'FAIL') $failed++;
        else $pending++;

        $durationMs += (int)($c['latency_ms'] ?? 15);
        if (!empty($c['leak_detected']) || ($c['code'] === 'R02' && $st === 'FAIL')) {
            $leakCount++;
        }
    }

    $executed = $passed + $failed;
    $passRate = $total > 0 ? round(($passed / $total) * 100, 1) : 0.0;

    return [
        'id' => $customId ?: ('RUN-' . date('Ymd-His') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 4))),
        'name' => $name,
        'note' => $note,
        'tester' => $user['username'] ?? 'tester_sec',
        'tester_name' => $user['full_name'] ?? 'Kỹ sư Kiểm thử An ninh',
        'tester_role' => $user['role'] ?? 'auditor',
        'created_at' => $customTime ?: date('d/m/Y H:i:s'),
        'bug_idor' => $bugIdor,
        'total' => $total,
        'executed' => $executed,
        'passed' => $passed,
        'failed' => $failed,
        'pending' => $pending,
        'pass_rate' => $passRate,
        'leak_count' => $leakCount,
        'duration_ms' => $durationMs,
        'cases' => array_values($cases),
    ];
}

