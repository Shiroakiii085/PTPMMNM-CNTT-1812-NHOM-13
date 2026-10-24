<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ProjectRepository.php';
require_once __DIR__ . '/../src/CaseRepository.php';
require_once __DIR__ . '/../src/RunRepository.php';
require_once __DIR__ . '/../src/RunStats.php';
require_once __DIR__ . '/../src/Policy.php';

echo "=== KIỂM THỬ TÍCH HỢP CSDL (INTEGRATION TESTS) ===\n";

try {
    $connection = db();
} catch (Throwable $e) {
    fwrite(STDERR, "NOT_RUN: PostgreSQL unavailable or unconfigured (" . $e->getMessage() . ")\n");
    exit(2);
}

if (!pg_query($connection, 'BEGIN')) {
    exit(2);
}

try {
    $rand = random_int(100000, 999999);
    $projName = "Test Project $rand";
    
    // 1. Kiểm thử ProjectRepository CRUD & Phân quyền thành viên
    $projectRepo = new ProjectRepository($connection);
    $projectId = $projectRepo->create($projName, 'http://127.0.0.1:8205', "owner_$rand", "Mô tả dự án $rand");
    same($projectId > 0, true, 'project-created');
    same($projectRepo->getMemberRole($projectId, "owner_$rand"), 'owner', 'project-owner-role');

    // Thêm editor và viewer
    $projectRepo->addMember($projectId, "editor_$rand", 'editor');
    $projectRepo->addMember($projectId, "viewer_$rand", 'viewer');
    same($projectRepo->getMemberRole($projectId, "editor_$rand"), 'editor', 'project-editor-role');
    same($projectRepo->getMemberRole($projectId, "viewer_$rand"), 'viewer', 'project-viewer-role');

    // 2. Kiểm thử Phép thử 4: viewer & người ngoài dự án không được sửa ca thử
    $caseRepo = new CaseRepository($connection);
    same($caseRepo->canModify($projectId, "owner_$rand"), true, 'owner-can-modify-cases');
    same($caseRepo->canModify($projectId, "editor_$rand"), true, 'editor-can-modify-cases');
    same($caseRepo->canModify($projectId, "viewer_$rand"), false, 'viewer-cannot-modify-cases');
    same($caseRepo->canModify($projectId, 'stranger_user'), false, 'stranger-cannot-modify-cases');

    // Thử lưu ca với quyền viewer -> Phải bị từ chối bằng Exception
    $viewerBlocked = false;
    try {
        $caseRepo->saveCase($projectId, "viewer_$rand", [
            'code' => 'T01',
            'account_id' => 1,
            'resource_id' => 1,
            'action_id' => 1,
            'method' => 'GET',
            'path' => '/api/test',
            'expected' => 'ALLOW'
        ]);
    } catch (Throwable $e) {
        $viewerBlocked = str_contains($e->getMessage(), 'FORBIDDEN');
    }
    same($viewerBlocked, true, 'viewer-modification-rejected-forbidden');

    // 3. Khai báo Role, Resource (owner_scoped), Action, Account & Record bằng quyền editor
    $roleId = $projectRepo->addRole($projectId, 'test_member_role');
    same($roleId > 0, true, 'role-added');

    $resId = $projectRepo->addResource($projectId, 'notes', true);
    same($resId > 0, true, 'resource-added-with-owner-scoped');

    $actId = $projectRepo->addAction($projectId, 'read');
    same($actId > 0, true, 'action-added');

    // Khai báo quyền: test_member_role được read notes với scope own
    $projectRepo->setPermission($roleId, $resId, $actId, 'own');
    $perms = $projectRepo->getPermissions($projectId);
    same(count($perms) >= 1, true, 'permission-set-in-matrix');

    // Thêm bản ghi gắn với chủ sở hữu alice
    $recordId = $projectRepo->saveRecord($projectId, $resId, '101', 'alice', 'Ghi chú số 101 của Alice');
    same($recordId > 0, true, 'record-saved-with-owner');

    // Thêm tài khoản alice
    $accId = $projectRepo->saveAccount($projectId, 'alice', $roleId, 'alice', 'TOKEN_ALICE');
    same($accId > 0, true, 'account-saved-with-role-and-owner');

    // 4. Lưu ca thử với đầy đủ thông tin kiểm tra phản hồi
    $caseId = $caseRepo->saveCase($projectId, "editor_$rand", [
        'code' => 'TEST01',
        'account_id' => $accId,
        'resource_id' => $resId,
        'action_id' => $actId,
        'method' => 'GET',
        'path' => '/api/notes/101',
        'record_id' => $recordId,
        'target_owner' => 'alice',
        'expected' => 'ALLOW',
        'expected_status' => 200,
        'must_contain' => 'Ghi chú số 101',
        'must_not_contain' => 'Secret',
    ]);
    same($caseId > 0, true, 'case-saved-with-response-checks');

    // Kiểm thử xuất JSON ca thử
    $jsonExport = $caseRepo->exportJson($projectId);
    same(str_contains($jsonExport, 'TEST01'), true, 'case-exported-json-contains-code');

    // 5. Kiểm thử Policy Oracle tính quyền kỳ vọng từ CSDL
    $loadedCase = $caseRepo->findById($caseId);
    $oracleRes = Policy::computeExpectedForCase($connection, $loadedCase);
    same($oracleRes['expected'], 'ALLOW', 'policy-oracle-evaluates-own-match-allow');
    same($oracleRes['owner_match'], true, 'policy-oracle-owner-match-true');

    // 6. Kiểm thử RunRepository lưu snapshot kết quả & đối chiếu so sánh
    $runRepo = new RunRepository($connection);
    $run1Id = $runRepo->createRun($projectId, 'int-run-before', 'hash_aaa', 'cli', 'test_runner');
    same($run1Id > 0, true, 'run1-created');

    $savedSnap1 = $runRepo->saveResultSnapshot(
        $run1Id,
        $caseId,
        'TEST01',
        'alice',
        'test_member_role',
        'alice',
        'notes',
        'read',
        'GET',
        '/api/notes/101',
        'alice',
        'ALLOW',
        'ALLOW',
        'ALLOW',
        200,
        'FAIL',
        null,
        'Phát hiện lỗi thử nghiệm',
        true,
        50,
        15
    );
    same($savedSnap1, true, 'snapshot-result-saved-run1');

    $run2Id = $runRepo->createRun($projectId, 'int-run-after', 'hash_aaa', 'cli', 'test_runner');
    $savedSnap2 = $runRepo->saveResultSnapshot(
        $run2Id,
        $caseId,
        'TEST01',
        'alice',
        'test_member_role',
        'alice',
        'notes',
        'read',
        'GET',
        '/api/notes/101',
        'alice',
        'ALLOW',
        'ALLOW',
        'ALLOW',
        200,
        'PASS',
        null,
        'Hợp lệ sau sửa',
        false,
        40,
        12
    );
    same($savedSnap2, true, 'snapshot-result-saved-run2');

    // Đối chiếu so sánh 2 lần chạy
    $diffs = $runRepo->compareRuns($run1Id, $run2Id, $projectId);
    same(count($diffs), 1, 'compare-runs-has-1-diff');
    same($diffs[0]['before_outcome'], 'FAIL', 'compare-before-fail');
    same($diffs[0]['after_outcome'], 'PASS', 'compare-after-pass');

    // 7. Kiểm thử RunStats với LEFT JOIN & COUNT FILTER
    $stats = runStats($connection, $run2Id);
    same($stats['total'], 1, 'run-stats-total-1');
    same($stats['pass_count'], 1, 'run-stats-pass-1');
    same($stats['fail_count'], 0, 'run-stats-fail-0');
    same($stats['error_count'], 0, 'run-stats-error-0');

} catch (Throwable $e) {
    $failed++;
    echo "FAIL integration: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
} finally {
    pg_query($connection, 'ROLLBACK');
}

finish();
