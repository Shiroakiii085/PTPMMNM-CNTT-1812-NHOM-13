<?php

declare(strict_types=1);

// =============================================================================
// GIAO DIỆN WEB TRỰC QUAN (VISUAL WEB APPLICATION FOR MEDIRECORD)
// =============================================================================
$view = $_GET['view'] ?? 'landing';
if ($view === 'analytics' || $view === 'stats') {
    $view = 'records';
}
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
      
    /* =========================================================================
       19. ROBOT-HEADS AVATAR & CHATGPT TYPEWRITER EFFECT STYLING
       ========================================================================= */
    .robot-head-container {
      position: relative;
      cursor: pointer;
      user-select: none;
      -webkit-user-select: none;
      overflow: visible;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .robot-head-container canvas {
      display: block !important;
      max-width: none !important;
      touch-action: none;
    }
    .ai-mascot-card {
      text-align: center;
      padding: 18px 14px;
      margin-bottom: 14px;
      background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%);
      border: 1px solid #3730a3;
      border-radius: 14px;
      box-shadow: 0 4px 18px rgba(0,0,0,0.3);
      user-select: none;
    }
    .ai-mascot-card canvas {
      cursor: grab;
      filter: drop-shadow(0 8px 16px rgba(0,0,0,0.4));
    }
    .ai-mascot-card canvas:active {
      cursor: grabbing;
    }
    @keyframes aiFadeInUp {
      0% {
        opacity: 0;
        filter: blur(2px);
        transform: translateY(2px);
      }
      100% {
        opacity: 1;
        filter: blur(0);
        transform: translateY(0);
      }
    }
    .ai-fade-token {
      display: inline;
      animation: aiFadeInUp 0.24s cubic-bezier(0.16, 1, 0.3, 1) both;
      will-change: opacity, transform, filter;
    }
    .ai-typing-cursor {
      display: inline-block;
      color: #6366f1;
      font-weight: 700;
      margin-left: 2px;
      animation: cursorBlink 0.65s infinite;
      line-height: 1;
      font-size: 14px;
      vertical-align: baseline;
    }
    @keyframes cursorBlink {
      0%, 100% { opacity: 1; transform: scaleY(1); }
      50% { opacity: 0; transform: scaleY(0.7); }
    }
    .ai-msg-content {
      line-height: 1.6;
      white-space: pre-wrap;
      word-break: break-word;
    }
    .ai-chat-msg.bot {
      display: flex;
      flex-direction: column;
      gap: 4px;
      animation: messageSlideIn 0.2s ease-out;
    }
    @keyframes messageSlideIn {
      from { opacity: 0; transform: translateY(4px); }
      to { opacity: 1; transform: translateY(0); }
    }

</style>
      <!-- 3D ROBOT HEADS CANVAS ENGINE (FAYAZARA/ROBOT-HEADS STANDALONE BUNDLE) -->
  <script src="/assets/robot-heads.bundle.js"></script>
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
    $rawId = $_GET['id'] ?? '';
    $recId = (is_numeric($rawId) && (int)$rawId > 0) ? (int)$rawId : 0;
    $recordDetail = ($recId > 0) ? ($mockRecords[$recId] ?? null) : null;

    if ($recordDetail === null) {
        $searchedId = trim((string)$rawId);
        header('Location: /?view=records&not_found=1' . ($searchedId !== '' ? '&searched_id=' . urlencode($searchedId) : ''));
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
    $rawId = $_GET['id'] ?? '';
    $recId = (is_numeric($rawId) && (int)$rawId > 0) ? (int)$rawId : 0;
    $editRecordDetail = ($recId > 0) ? ($mockRecords[$recId] ?? null) : null;

    if ($editRecordDetail === null) {
        $searchedId = trim((string)$rawId);
        header('Location: /?view=records&not_found=1' . ($searchedId !== '' ? '&searched_id=' . urlencode($searchedId) : ''));
        exit;
    }
    if ($currentUser === null) {
        header('Location: /?view=login&msg=require_login');
        exit;
    }

    $isStaff = in_array($currentUser['role'], ['doctor', 'admin'], true);

    // Phân quyền: Bệnh nhân chỉ có quyền xem, không được vào trang chỉnh sửa bệnh án
    if (!$isStaff) {
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
                'PATIENT_EDIT_DENIED',
                'WARNING',
                $currentUser,
                "Bệnh án số 0{$recId} (BN {$editRecordDetail['owner_username']})",
                "Bệnh nhân " . ($currentUser['full_name'] ?? $currentUser['username']) . " cố mở màn hình sửa bệnh án nhưng hệ thống đã chặn lại do Bệnh nhân chỉ có quyền XEM.",
                ['status_code' => 403, 'cwe' => 'CWE-285 (Improper Authorization)']
            );
            header('Location: /?view=record&id=' . $recId . '&err=patient_read_only');
            exit;
        }
    }

    if (false) {
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
$testCases = getCurrentManualMatrixCases();
$failedTestCount = count(array_filter($testCases, fn($c) => ($c['status'] ?? '') === 'FAIL'));
$passedTestCount = count(array_filter($testCases, fn($c) => ($c['status'] ?? '') === 'PASS'));
$pendingTestCount = count(array_filter($testCases, fn($c) => ($c['status'] ?? 'PENDING') === 'PENDING'));
$executedTestCount = $passedTestCount + $failedTestCount;
$testRunsHistory = getTestRunsHistory();
$totalSavedRuns = count($testRunsHistory);

// Kiểm tra quyền truy cập View Tester (Chỉ auditor hoặc admin)
// Kiểm tra quyền truy cập View Profile (Cần đăng nhập)
if ($view === 'profile') {
    if (!$currentUser) {
        header('Location: /?view=login&msg=require_login');
        exit;
    }
}

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
