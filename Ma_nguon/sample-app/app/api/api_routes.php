<?php

declare(strict_types=1);

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
                $r = queryParams($db, 'SELECT id, owner_username, doctor_username, title, content, department, diagnosis, created_at FROM sample_app.notes WHERE owner_username = $1 OR owner_username = $2 ORDER BY id', [$username, $username === 'bn_an' ? 'alice' : ($username === 'bn_binh' ? 'bob' : $username)]);
            } else {
                $r = queryParams($db, 'SELECT id, owner_username, doctor_username, title, content, department, diagnosis, created_at FROM sample_app.notes ORDER BY id', []);
            }
            $notes = pg_fetch_all($r) ?: [];
            echo json_encode(['notes' => $notes, 'records' => $notes], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Throwable) {}
    }

    if ($role === 'member') {
        $filtered = array_values(array_filter($mockRecords, fn($n) => checkRecordOwnerMatch($n['owner_username'], $username)));
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
    $isOwner = checkRecordOwnerMatch($note['owner_username'], $username);
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

    if (!checkRecordOwnerMatch($note['owner_username'], $currentUser['username'])) {
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

