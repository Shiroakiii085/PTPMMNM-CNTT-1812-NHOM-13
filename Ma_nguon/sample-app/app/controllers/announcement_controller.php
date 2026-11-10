<?php

declare(strict_types=1);

// =============================================================================
// QUẢN TRỊ BẢN TIN & THÔNG BÁO BỆNH VIỆN (CHỈ DÀNH CHO ADMIN)
// =============================================================================

// Thêm thông báo mới (Chỉ Admin)
if ($action === 'add_announcement' && $method === 'POST') {
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        header('Location: /?view=error&code=403&msg=' . urlencode('Chỉ Quản trị viên mới có quyền đăng thông báo mới!'));
        exit;
    }
    $jsonInput = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $title = trim((string)($_POST['title'] ?? $jsonInput['title'] ?? ''));
    $body = trim((string)($_POST['body'] ?? $jsonInput['body'] ?? ''));
    $audience = trim((string)($_POST['audience'] ?? $jsonInput['audience'] ?? 'patient'));
    if ($audience !== 'internal') $audience = 'patient';

    if ($title === '' || $body === '') {
        header('Location: /?view=announcements&err=missing_fields');
        exit;
    }

    $maxDbId = 0;
    if ($db !== null) {
        try {
            $mr = pg_query($db, 'SELECT COALESCE(MAX(id), 0) AS m FROM sample_app.announcements');
            if ($mr) {
                $mRow = pg_fetch_assoc($mr);
                $maxDbId = (int)($mRow['m'] ?? 0);
            }
        } catch (Throwable) {}
    }
    $newId = max($maxDbId, count($mockAnnouncements)) + 1;
    while (isset($mockAnnouncements[$newId])) {
        $newId++;
    }
    if ($db !== null) {
        try {
            queryParams(
                $db,
                'INSERT INTO sample_app.announcements (id, title, body, audience) VALUES ($1, $2, $3, $4)',
                [$newId, $title, $body, $audience]
            );
            pg_query($db, 'SELECT setval(\'sample_app.announcements_id_seq\', (SELECT COALESCE(MAX(id), 1) FROM sample_app.announcements))');
        } catch (Throwable) {}
    }
    $newAnn = [
        'id' => $newId,
        'title' => $title,
        'body' => $body,
        'content' => $body,
        'audience' => $audience,
        'author' => ($audience === 'internal') ? 'Ban Giám Đốc & Phòng An Toàn Thông Tin' : ($currentUser['full_name'] ?? 'Ban Giám Đốc Bệnh Viện'),
        'created_at' => date('Y-m-d H:i:s')
    ];
    $_SESSION['custom_announcements'][$newId] = $newAnn;
    header('Location: /?view=announcements&msg=announcement_added&tab=' . $audience);
    exit;
}

// Sửa thông báo (Chỉ Admin)
if ($action === 'edit_announcement' && $method === 'POST') {
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        header('Location: /?view=error&code=403&msg=' . urlencode('Chỉ Quản trị viên mới có quyền sửa thông báo!'));
        exit;
    }
    $jsonInput = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $id = (int)($_POST['id'] ?? $jsonInput['id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? $jsonInput['title'] ?? ''));
    $body = trim((string)($_POST['body'] ?? $jsonInput['body'] ?? ''));
    $audience = trim((string)($_POST['audience'] ?? $jsonInput['audience'] ?? 'patient'));
    if ($audience !== 'internal') $audience = 'patient';

    if ($id <= 0) {
        header('Location: /?view=announcements&err=invalid_id');
        exit;
    }
    if ($title === '' || $body === '') {
        header('Location: /?view=announcements&err=missing_fields');
        exit;
    }

    $updatedAnn = [
        'id' => $id,
        'title' => $title,
        'body' => $body,
        'content' => $body,
        'audience' => $audience,
        'author' => ($audience === 'internal') ? 'Ban Giám Đốc & Phòng An Toàn Thông Tin' : ($currentUser['full_name'] ?? 'Ban Giám Đốc Bệnh Viện'),
        'created_at' => date('Y-m-d H:i:s')
    ];
    $_SESSION['custom_announcements'][$id] = $updatedAnn;
    if ($db !== null) {
        try {
            queryParams(
                $db,
                'UPDATE sample_app.announcements SET title = $1, body = $2, audience = $3 WHERE id = $4',
                [$title, $body, $audience, $id]
            );
        } catch (Throwable) {}
    }
    header('Location: /?view=announcements&msg=announcement_updated&tab=' . $audience);
    exit;
}

// Xóa thông báo (Chỉ Admin)
if ($action === 'delete_announcement') {
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        header('Location: /?view=error&code=403&msg=' . urlencode('Chỉ Quản trị viên mới có quyền xóa thông báo!'));
        exit;
    }
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        header('Location: /?view=announcements&err=invalid_id');
        exit;
    }
    unset($_SESSION['custom_announcements'][$id]);
    if (!isset($_SESSION['deleted_announcements'])) {
        $_SESSION['deleted_announcements'] = [];
    }
    $_SESSION['deleted_announcements'][] = $id;
    if ($db !== null) {
        try {
            queryParams($db, 'DELETE FROM sample_app.announcements WHERE id = $1', [$id]);
        } catch (Throwable) {}
    }
    header('Location: /?view=announcements&msg=announcement_deleted');
    exit;
}

