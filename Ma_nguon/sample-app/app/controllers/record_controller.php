<?php

declare(strict_types=1);

// Tải về bản sao hồ sơ bệnh án cá nhân (JSON)
if ($action === 'export_my_records_json') {
    if (!$currentUser) {
        header('Location: /?view=login&err=unauthorized');
        exit;
    }
    $isMember = ($currentUser['role'] === 'member');
    $recordsToExport = [];
    if ($isMember) {
        $recordsToExport = array_values(array_filter($mockRecords, fn($r) => checkRecordOwnerMatch($r['owner_username'], $currentUser['username'])));
    } else {
        $recordsToExport = array_values($mockRecords);
    }

    $exportData = [
        'system' => 'MediRecord EMR - Bệnh viện Bạch Mai',
        'exported_at' => date('Y-m-d H:i:s'),
        'user' => [
            'username' => $currentUser['username'],
            'full_name' => $currentUser['full_name'],
            'role' => $currentUser['role'],
            'department' => $currentUser['department'] ?? 'Khoa Khám Bệnh'
        ],
        'total_records' => count($recordsToExport),
        'records' => $recordsToExport
    ];

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="medirecord_export_' . $currentUser['username'] . '_' . date('Ymd_His') . '.json"');
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}


// -----------------------------------------------------------------------------
// CHỨC NĂNG CHỈNH SỬA BỆNH ÁN (NƠI TEST LỖI IDOR TRỰC TIẾP TRÊN FORM)
// -----------------------------------------------------------------------------
if ($action === 'edit_record_submit' && $method === 'POST') {
    $recId = (int)($_POST['id'] ?? 0);
    $newTitle = trim((string)($_POST['title'] ?? ''));
    $newDiagnosis = trim((string)($_POST['diagnosis'] ?? ''));
    $newContent = trim((string)($_POST['content'] ?? ''));

    if ($recId <= 0 || !isset($mockRecords[$recId])) {
        header('Location: /?view=records&not_found=1&searched_id=' . urlencode((string)$recId));
        exit;
    }
    if ($newTitle === '' || $newContent === '') {
        header('Location: /?view=edit_record&id=' . $recId . '&err=missing_fields');
        exit;
    }

    $targetRecord = $mockRecords[$recId];
    $isStaff = ($currentUser && in_array($currentUser['role'], ['doctor', 'admin'], true));

    // Phân quyền: Bệnh nhân chỉ có quyền xem, không được sửa bệnh án
    if (!$isStaff) {
        if ($bugIdor && $recId === 3) {
            // Cho phép sửa trái phép khi bật lỗi IDOR
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
            recordSecurityIncident(
                'PATIENT_TAMPERING_DENIED',
                'HIGH',
                $currentUser,
                "Bệnh án số {$recId} (" . ($targetRecord['owner_username'] ?? 'Khác') . ")",
                "Phát hiện hành vi sửa đổi trái phép: Bệnh nhân " . ($currentUser['full_name'] ?? $currentUser['username']) . " cố tình gửi lệnh ghi đè bệnh án số {$recId} nhưng đã bị hệ thống ABAC chặn đứng với quyền CHỈ XEM.",
                ['status_code' => 403, 'cwe' => 'CWE-285 (Improper Authorization)']
            );
            header('Location: /?view=record&id=' . $recId . '&err=patient_read_only');
            exit;
        }
    }

    if (false) {
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
// Xóa hồ sơ bệnh án (Dành cho Bác sĩ và Quản trị viên)
if ($action === 'delete_record') {
    if (!$currentUser || !in_array($currentUser['role'], ['doctor', 'admin'], true)) {
        header('Location: /?view=error&code=403&msg=' . urlencode('Chỉ Bác sĩ điều trị hoặc Quản trị viên mới có thẩm quyền xóa hồ sơ bệnh án!'));
        exit;
    }

    $rawId = $_GET['id'] ?? '';
    $recId = (is_numeric($rawId) && (int)$rawId > 0) ? (int)$rawId : 0;

    if ($recId <= 0 || !isset($mockRecords[$recId])) {
        header('Location: /?view=records&err=record_not_found');
        exit;
    }

    $targetRecord = $mockRecords[$recId];

    // Bác sĩ chỉ xóa bệnh án do mình phụ trách hoặc khoa mình phụ trách (nếu không phải admin)
    if ($currentUser['role'] === 'doctor' && $currentUser['role'] !== 'admin') {
        $isMyDoctor = (($targetRecord['doctor_username'] ?? '') === $currentUser['username']);
        $isMyDept = (($targetRecord['department'] ?? '') === ($currentUser['department'] ?? ''));
        if (!$isMyDoctor && !$isMyDept) {
            header('Location: /?view=error&code=403&msg=' . urlencode('Bác sĩ chỉ được phép xóa bệnh án thuộc chuyên khoa hoặc do chính mình phụ trách!'));
            exit;
        }
    }

    // Xóa khỏi danh sách bộ nhớ & session
    unset($mockRecords[$recId]);
    unset($_SESSION['custom_records'][$recId]);
    if (!isset($_SESSION['deleted_records'])) {
        $_SESSION['deleted_records'] = [];
    }
    if (!in_array($recId, $_SESSION['deleted_records'], true)) {
        $_SESSION['deleted_records'][] = $recId;
    }

    // Xóa khỏi CSDL nếu có kết nối
    if ($db !== null) {
        try {
            queryParams($db, 'DELETE FROM sample_app.notes WHERE id = $1', [$recId]);
        } catch (Throwable) {}
    }

    header('Location: /?view=records&msg=record_deleted');
    exit;
}

if ($action === 'create_record_submit' && $method === 'POST') {
    if (!$currentUser || !in_array($currentUser['role'], ['doctor', 'admin'], true)) {
        header('Location: /?view=error&code=403&msg=' . urlencode('Chỉ Bác sĩ hoặc Quản trị viên mới có quyền lập và gửi bệnh án!'));
        exit;
    }

    $targetPatient = trim((string)($_POST['owner_username'] ?? $_POST['patient_username'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $department = trim((string)($_POST['department'] ?? 'Khoa Khám Bệnh'));
    $diagnosis = trim((string)($_POST['diagnosis'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));

    if ($title === '' || $targetPatient === '' || $content === '') {
        header('Location: /?view=create_record&err=missing_fields');
        exit;
    }

    if (true) {
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

