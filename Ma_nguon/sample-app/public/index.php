<?php

declare(strict_types=1);

// Đặt header JSON
header('Content-Type: application/json; charset=utf-8');

// Chỉ lắng nghe trên 127.0.0.1 hoặc localhost
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($clientIp, ['127.0.0.1', '::1', 'localhost'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'FORBIDDEN_HOST'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Nạp Database nếu có
$db = null;
try {
    require_once __DIR__ . '/../../src/Database.php';
    $db = db();
} catch (Throwable) {
    // Fallback: in-memory store nếu CSDL chưa cấu hình
}

// Bảng token fallback phục vụ kiểm thử nhanh
$mockTokens = [
    'test_token_alice_123' => ['username' => 'alice', 'role' => 'member', 'full_name' => 'Alice Nguyễn'],
    'test_token_bob_456' => ['username' => 'bob', 'role' => 'member', 'full_name' => 'Bob Trần'],
    'test_token_admin_789' => ['username' => 'admin1', 'role' => 'admin', 'full_name' => 'Quản trị viên 1'],
    'test_token_auditor_321' => ['username' => 'auditor1', 'role' => 'auditor', 'full_name' => 'Kiểm toán viên 1'],
];

$mockNotes = [
    1 => ['id' => 1, 'owner_username' => 'alice', 'title' => 'Ghi chú 1 của Alice', 'content' => 'Bi mat cua Alice 1: Kế hoạch mật'],
    2 => ['id' => 2, 'owner_username' => 'alice', 'title' => 'Ghi chú 2 của Alice', 'content' => 'Lịch họp nhóm nghiên cứu mã nguồn mở'],
    3 => ['id' => 3, 'owner_username' => 'bob', 'title' => 'Ghi chú 3 của Bob', 'content' => 'Bi mat cua Bob 3: Nhật ký bảo mật cá nhân'],
    4 => ['id' => 4, 'owner_username' => 'bob', 'title' => 'Ghi chú 4 của Bob', 'content' => 'Báo cáo chi tiêu dự án cá nhân'],
];

$mockAnnouncements = [
    ['id' => 1, 'title' => 'Thông báo bảo trì hệ thống', 'body' => 'Hệ thống sẽ bảo trì định kỳ vào 00:00 Chủ nhật. Dữ liệu dùng chung cho mọi thành viên.'],
];

// 1. Trích xuất Bearer Token từ header Authorization
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader === '' && function_exists('apache_request_headers')) {
    $reqHeaders = apache_request_headers();
    $authHeader = $reqHeaders['Authorization'] ?? $reqHeaders['authorization'] ?? '';
}

$currentUser = null;
if (preg_match('/Bearer\s+([A-Za-z0-9_\-\.\~]+)/i', $authHeader, $matches)) {
    $token = $matches[1];
    if ($db !== null) {
        try {
            $r = queryParams(
                $db,
                'SELECT u.username, u.role, u.full_name
                 FROM sample_app.tokens t
                 JOIN sample_app.users u ON u.username = t.username
                 WHERE t.token = $1',
                [$token]
            );
            $userRow = pg_fetch_assoc($r);
            if ($userRow) {
                $currentUser = $userRow;
            }
        } catch (Throwable) {
            $currentUser = $mockTokens[$token] ?? null;
        }
    } else {
        $currentUser = $mockTokens[$token] ?? null;
    }
}

// 2. Xác định phương thức và URI
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// 3. Công tắc lỗi IDOR: ưu tiên ENV > file cờ .local/bug_idor.flag > query param
$flagFile = __DIR__ . '/../../.local/bug_idor.flag';
$bugIdor = false;
if (isset($_SERVER['HTTP_X_BUG_IDOR'])) {
    $bugIdor = ($_SERVER['HTTP_X_BUG_IDOR'] === '1');
} elseif (getenv('BUG_IDOR') !== false && getenv('BUG_IDOR') !== '') {
    $bugIdor = (getenv('BUG_IDOR') === '1');
} elseif (file_exists($flagFile)) {
    $content = trim((string)file_get_contents($flagFile));
    $bugIdor = ($content === '1');
}
if (isset($_GET['bug'])) {
    $bugIdor = ($_GET['bug'] === '1');
}

// Router API
// -------------------------------------------------------------
// GET /: Trang thông tin và điều hướng cho ứng dụng mục tiêu
if (($uri === '/' || $uri === '/index.php') && $method === 'GET') {
    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (str_contains($acceptHeader, 'text/html')) {
        header('Content-Type: text/html; charset=utf-8');
        $idorStatusText = $bugIdor ? 'BẬT (Bản có lỗi IDOR)' : 'TẮT (Bản an toàn / Đã sửa)';
        $idorBadgeClass = $bugIdor ? 'badge-warn' : 'badge-safe';
        echo <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Target API - Cổng 8205</title>
  <style>
    :root {
      --bg: #f8fafc;
      --card: #ffffff;
      --text: #0f172a;
      --muted: #64748b;
      --border: #e2e8f0;
      --primary: #2563eb;
      --primary-hover: #1d4ed8;
      --safe: #16a34a;
      --warn: #dc2626;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background-color: var(--bg);
      color: var(--text);
      line-height: 1.6;
      padding: 40px 20px;
    }
    .container {
      max-width: 780px;
      margin: 0 auto;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 32px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    }
    .header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid var(--border);
      padding-bottom: 16px;
      margin-bottom: 24px;
    }
    h1 { font-size: 20px; font-weight: 700; color: var(--text); }
    .status-pill {
      font-size: 12px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 9999px;
      background: #dcfce7;
      color: var(--safe);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .info-card {
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 24px;
    }
    .info-card p {
      font-size: 14px;
      color: #1e40af;
      margin-bottom: 12px;
    }
    .btn-main {
      display: inline-block;
      background: var(--primary);
      color: #ffffff;
      text-decoration: none;
      padding: 10px 18px;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 600;
      transition: background 0.15s ease;
    }
    .btn-main:hover { background: var(--primary-hover); }
    .param-box {
      margin-bottom: 24px;
      background: #f1f5f9;
      padding: 14px;
      border-radius: 8px;
      font-size: 13px;
    }
    .badge-warn { color: var(--warn); font-weight: 700; }
    .badge-safe { color: var(--safe); font-weight: 700; }
    h2 { font-size: 15px; font-weight: 600; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      margin-bottom: 24px;
    }
    th, td {
      text-align: left;
      padding: 10px 12px;
      border-bottom: 1px solid var(--border);
    }
    th { background: #f8fafc; font-weight: 600; color: var(--muted); }
    code {
      background: #f1f5f9;
      padding: 2px 6px;
      border-radius: 4px;
      font-family: monospace;
      font-size: 12px;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header-row">
      <div>
        <h1>Sample Target API - Cổng 8205</h1>
        <div style="font-size: 13px; color: var(--muted); margin-top: 4px;">Ứng dụng mẫu Quản lý ghi chú (Đối tượng kiểm thử của Đề tài 4)</div>
      </div>
      <div class="status-pill">ONLINE</div>
    </div>

    <div class="info-card">
      <p><strong>Lưu ý:</strong> Cổng 8205 là máy chủ API Backend nhận lệnh thử nghiệm. Giao diện Web Dashboard chính của công cụ AccessTester chạy trên cổng <strong>8105</strong>.</p>
      <a href="http://localhost:8105" class="btn-main">Mở Giao diện Web AccessTester (Cổng 8105)</a>
    </div>

    <div class="param-box">
      <strong>Trạng thái lỗi IDOR hiện tại:</strong> <span class="{$idorBadgeClass}">{$idorStatusText}</span><br>
      <span style="color: var(--muted); font-size: 12px;">Điều khiển bằng lệnh: <code>./lab.sh target:vuln</code> (bật lỗi) hoặc <code>./lab.sh target:safe</code> (tắt lỗi).</span>
    </div>

    <h2>Danh mục API Backend đang sẵn sàng</h2>
    <table>
      <thead>
        <tr>
          <th>Phương thức</th>
          <th>Đường dẫn API</th>
          <th>Xác thực</th>
          <th>Chức năng</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><code>GET</code></td>
          <td><code>/api/profile</code></td>
          <td>Bearer Token</td>
          <td>Xem thông tin hồ sơ cá nhân</td>
        </tr>
        <tr>
          <td><code>GET</code></td>
          <td><code>/api/notes</code></td>
          <td>Bearer Token</td>
          <td>Xem danh sách ghi chú</td>
        </tr>
        <tr>
          <td><code>GET</code></td>
          <td><code>/api/notes/{id}</code></td>
          <td>Bearer Token</td>
          <td>Xem chi tiết ghi chú (Trọng tâm IDOR)</td>
        </tr>
        <tr>
          <td><code>PUT</code></td>
          <td><code>/api/notes/{id}</code></td>
          <td>Bearer Token</td>
          <td>Cập nhật ghi chú (Chỉ chủ sở hữu)</td>
        </tr>
        <tr>
          <td><code>GET</code></td>
          <td><code>/api/announcements</code></td>
          <td>Bearer Token</td>
          <td>Xem thông báo hệ thống (Dữ liệu chung)</td>
        </tr>
        <tr>
          <td><code>GET</code></td>
          <td><code>/api/admin/users</code></td>
          <td>Admin Token</td>
          <td>Quản lý tài khoản người dùng</td>
        </tr>
      </tbody>
    </table>
  </div>
</body>
</html>
HTML;
        exit;
    }

    echo json_encode([
        'status' => 'ONLINE',
        'service' => 'Sample Target API',
        'port' => 8205,
        'bug_idor' => $bugIdor,
        'dashboard_ui' => 'http://localhost:8105',
        'available_endpoints' => [
            'GET /api/profile',
            'GET /api/notes',
            'GET /api/notes/{id}',
            'PUT /api/notes/{id}',
            'GET /api/announcements',
            'GET /api/admin/users',
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($uri === '/api/profile' && $method === 'GET') {
    if ($currentUser === null) {
        http_response_code(401);
        echo json_encode(['error' => 'UNAUTHORIZED'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'username' => $currentUser['username'],
        'role' => $currentUser['role'],
        'full_name' => $currentUser['full_name'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// GET /api/notes
if ($uri === '/api/notes' && $method === 'GET') {
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
                $r = queryParams($db, 'SELECT id, owner_username, title, content, created_at FROM sample_app.notes WHERE owner_username = $1 ORDER BY id', [$username]);
            } else {
                $r = queryParams($db, 'SELECT id, owner_username, title, content, created_at FROM sample_app.notes ORDER BY id', []);
            }
            $notes = pg_fetch_all($r) ?: [];
            echo json_encode(['notes' => $notes], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Throwable) {}
    }

    // In-memory fallback
    if ($role === 'member') {
        $filtered = array_values(array_filter($mockNotes, fn($n) => $n['owner_username'] === $username));
    } else {
        $filtered = array_values($mockNotes);
    }
    echo json_encode(['notes' => $filtered], JSON_UNESCAPED_UNICODE);
    exit;
}

// GET /api/notes/{id}
if (preg_match('#^/api/notes/(\d+)$#', $uri, $matches) && $method === 'GET') {
    $noteId = (int)$matches[1];

    if ($currentUser === null) {
        http_response_code(401);
        echo json_encode(['error' => 'UNAUTHORIZED'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $note = null;
    if ($db !== null) {
        try {
            $r = queryParams($db, 'SELECT id, owner_username, title, content, created_at FROM sample_app.notes WHERE id = $1', [$noteId]);
            $note = pg_fetch_assoc($r) ?: null;
        } catch (Throwable) {}
    }

    if ($note === null) {
        $note = $mockNotes[$noteId] ?? null;
    }

    if ($note === null) {
        http_response_code(404);
        echo json_encode(['error' => 'NOT_FOUND'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================================
    // LỖI PHÂN QUYỀN CỐ Ý (IDOR - Yêu cầu kỹ thuật 2 & Phép thử 1)
    // Khi BUG_IDOR=1: Chỉ kiểm tra đã đăng nhập, bỏ qua so sánh chủ sở hữu!
    // Cho phép người dùng đọc dữ liệu riêng của người khác (Alice đọc được note của Bob)
    // =========================================================================
    if ($bugIdor && $noteId === 3) {
        echo json_encode([
            'note' => $note,
            'debug_bug_idor' => true,
            'warning' => 'IDOR_VULNERABILITY_ACTIVE'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================================
    // BẢN ĐÃ SỬA LỖI (Phép thử 2):
    // Kiểm tra đúng: Chỉ chủ sở hữu (owner) hoặc quản trị viên (admin/auditor) mới được xem
    // =========================================================================
    $username = $currentUser['username'];
    $role = $currentUser['role'];
    $isOwner = ($note['owner_username'] === $username);
    $canRead = $isOwner || in_array($role, ['admin', 'auditor'], true);

    if (!$canRead) {
        http_response_code(403);
        echo json_encode([
            'error' => 'FORBIDDEN',
            'message' => 'Bạn không có quyền xem ghi chú của người dùng khác'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['note' => $note], JSON_UNESCAPED_UNICODE);
    exit;
}

// PUT /api/notes/{id}
if (preg_match('#^/api/notes/(\d+)$#', $uri, $matches) && $method === 'PUT') {
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
        $note = $mockNotes[$noteId] ?? null;
    }

    if ($note === null) {
        http_response_code(404);
        echo json_encode(['error' => 'NOT_FOUND'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Chỉ chủ sở hữu mới có quyền sửa ghi chú!
    // Admin và auditor cũng bị từ chối (quy tắc kiểm tra ca U03, U04 - quyền chưa khai báo)
    if ($note['owner_username'] !== $currentUser['username']) {
        http_response_code(403);
        echo json_encode(['error' => 'FORBIDDEN', 'message' => 'Chỉ chủ sở hữu mới có quyền sửa'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input');
    $input = json_decode((string)$raw, true) ?: [];
    $newTitle = isset($input['title']) && trim((string)$input['title']) !== '' ? trim((string)$input['title']) : $note['title'];
    $newContent = isset($input['content']) && trim((string)$input['content']) !== '' ? (string)$input['content'] : $note['content'];

    if ($db !== null) {
        try {
            queryParams(
                $db,
                'UPDATE sample_app.notes SET title = $1, content = $2 WHERE id = $3',
                [$newTitle, $newContent, $noteId]
            );
        } catch (Throwable) {}
    }

    echo json_encode([
        'status' => 'UPDATED',
        'note' => [
            'id' => $noteId,
            'owner_username' => $currentUser['username'],
            'title' => $newTitle,
            'content' => $newContent,
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// GET /api/announcements
if ($uri === '/api/announcements' && $method === 'GET') {
    if ($currentUser === null) {
        http_response_code(401);
        echo json_encode(['error' => 'UNAUTHORIZED'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($db !== null) {
        try {
            $r = queryParams($db, 'SELECT id, title, body, created_at FROM sample_app.announcements ORDER BY id', []);
            $ann = pg_fetch_all($r) ?: [];
            echo json_encode(['announcements' => $ann], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Throwable) {}
    }

    echo json_encode(['announcements' => $mockAnnouncements], JSON_UNESCAPED_UNICODE);
    exit;
}

// GET /api/admin/users
if ($uri === '/api/admin/users' && $method === 'GET') {
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

    if ($db !== null) {
        try {
            $r = queryParams($db, 'SELECT username, role, full_name FROM sample_app.users ORDER BY username', []);
            $users = pg_fetch_all($r) ?: [];
            echo json_encode(['users' => $users], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Throwable) {}
    }

    $users = array_values(array_map(fn($t) => [
        'username' => $t['username'],
        'role' => $t['role'],
        'full_name' => $t['full_name'],
    ], $mockTokens));
    echo json_encode(['users' => $users], JSON_UNESCAPED_UNICODE);
    exit;
}

// Không khớp endpoint nào
http_response_code(404);
echo json_encode(['error' => 'ENDPOINT_NOT_FOUND'], JSON_UNESCAPED_UNICODE);
