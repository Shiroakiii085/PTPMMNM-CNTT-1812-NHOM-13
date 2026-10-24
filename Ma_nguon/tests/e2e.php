<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Runner.php';
require_once __DIR__ . '/../src/Report.php';
require_once __DIR__ . '/../src/RunRepository.php';
require_once __DIR__ . '/../src/CaseRepository.php';
require_once __DIR__ . '/../src/ProjectRepository.php';
require_once __DIR__ . '/../src/Logger.php';

echo "=== KIỂM THỬ ĐẦU-CUỐI END-TO-END (5 PHÉP THỬ BẮT BUỘC) ===\n";

// Nạp token vào môi trường để Runner sử dụng
putenv('TOKEN_ALICE=test_token_alice_123');
putenv('TOKEN_BOB=test_token_bob_456');
putenv('TOKEN_ADMIN=test_token_admin_789');
putenv('TOKEN_AUDITOR=test_token_auditor_321');

try {
    $db = db();
} catch (Throwable $e) {
    fwrite(STDERR, "NOT_RUN: CSDL không khả dụng (" . $e->getMessage() . ")\n");
    exit(2);
}

$sampleAppPath = __DIR__ . '/../sample-app/public';
$testPort = 8295; // Cổng thử nghiệm riêng cho E2E để tránh xung đột

/**
 * Khởi động máy chủ PHP test độc lập
 */
function startSampleServer(string $docRoot, int $port, int $bugIdor): mixed
{
    $cmd = [
        PHP_BINARY,
        '-S', "127.0.0.1:$port",
        '-t', $docRoot
    ];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    $env = array_merge($_ENV, getenv(), ['BUG_IDOR' => (string)$bugIdor]);
    $proc = proc_open($cmd, $descriptors, $pipes, $docRoot, $env);

    if (!is_resource($proc)) {
        return null;
    }

    // Chờ tối đa 3 giây để máy chủ lắng nghe
    $ready = false;
    for ($i = 0; $i < 30; $i++) {
        usleep(100000); // 100ms
        $sock = @fsockopen('127.0.0.1', $port, $errCode, $errMsg, 0.2);
        if ($sock) {
            fclose($sock);
            $ready = true;
            break;
        }
    }

    if (!$ready) {
        proc_terminate($proc);
        return null;
    }

    return ['proc' => $proc, 'pipes' => $pipes];
}

function stopSampleServer(mixed $server): void
{
    if (is_array($server) && isset($server['proc']) && is_resource($server['proc'])) {
        foreach ($server['pipes'] as $p) {
            if (is_resource($p)) {
                fclose($p);
            }
        }
        proc_terminate($server['proc']);
        proc_close($server['proc']);
    }
}

// Bắt đầu giao dịch để cách ly dữ liệu
if (!pg_query($db, 'BEGIN')) {
    exit(2);
}

$server = null;
try {
    // 1. Tạo project test trỏ tới cổng $testPort
    $rand = random_int(10000, 99999);
    $projRes = queryParams(
        $db,
        'INSERT INTO projects (name, base_url) VALUES ($1, $2) RETURNING id',
        ["E2E Project $rand", "http://127.0.0.1:$testPort"]
    );
    $projectId = (int)pg_fetch_assoc($projRes)['id'];

    // Sao chép tài khoản, vai trò, quyền, bản ghi và 24 ca thử từ project mẫu 1
    queryParams($db, '
        INSERT INTO test_cases (project_id, code, account_id, resource_id, action_id, method, path,
                                record_id, target_owner, request_body, expected, expected_status,
                                must_contain, must_not_contain)
        SELECT $1, code, account_id, resource_id, action_id, method, path,
               record_id, target_owner, request_body, expected, expected_status,
               must_contain, must_not_contain
        FROM test_cases WHERE project_id = 1
        ON CONFLICT DO NOTHING
    ', [$projectId]);

    $runner = new Runner($db);

    // =========================================================================
    // PHÉP THỬ 1: Phát hiện API mẫu cố ý cho người dùng đọc dữ liệu riêng của người khác
    // (Khởi chạy với BUG_IDOR=1 -> Ca R02 phát hiện lỗ hổng và nhận kết quả FAIL)
    // =========================================================================
    $server = startSampleServer($sampleAppPath, $testPort, 1);
    if ($server === null) {
        fwrite(STDERR, "NOT_RUN: Không khởi động được máy chủ thử nghiệm ở cổng $testPort\n");
        exit(2);
    }

    $runBuggy = $runner->run($projectId, 'e2e-before-fix', 'cli', 'test_e2e_runner');
    $resultsBuggy = $runBuggy['results'];

    $r02Buggy = null;
    foreach ($resultsBuggy as $r) {
        if ($r['code'] === 'R02') {
            $r02Buggy = $r;
            break;
        }
    }

    same($r02Buggy !== null, true, 'e2e-r02-case-present');
    same($r02Buggy['outcome'] ?? '', 'FAIL', 'e2e-test-1-idor-detected-as-fail');
    same($r02Buggy['leak_detected'] ?? false, true, 'e2e-test-1-leak-detected-true');

    stopSampleServer($server);
    $server = null;

    // =========================================================================
    // PHÉP THỬ 2: Sau khi sửa API, cùng ca truy cập trái quyền phải cho kết quả đúng (PASS)
    // (Khởi chạy với BUG_IDOR=0 -> 24/24 ca PASS; so sánh diff chỉ ra R02 FAIL -> PASS)
    // =========================================================================
    $server = startSampleServer($sampleAppPath, $testPort, 0);
    if ($server === null) {
        fwrite(STDERR, "NOT_RUN: Không khởi động được máy chủ sau khi sửa lỗi ở cổng $testPort\n");
        exit(2);
    }

    $runFixed = $runner->run($projectId, 'e2e-after-fix', 'cli', 'test_e2e_runner');
    $resultsFixed = $runFixed['results'];

    $r02Fixed = null;
    $allPassCount = 0;
    foreach ($resultsFixed as $r) {
        if ($r['code'] === 'R02') {
            $r02Fixed = $r;
        }
        if ($r['outcome'] === 'PASS') {
            $allPassCount++;
        }
    }

    same($r02Fixed['outcome'] ?? '', 'PASS', 'e2e-test-2-r02-passed-after-fix');
    same($allPassCount, count($resultsFixed), 'e2e-test-2-all-24-cases-passed');

    // So sánh trước và sau (Tình huống 2)
    $runRepo = new RunRepository($db);
    $diffs = $runRepo->compareRuns($runBuggy['run_id'], $runFixed['run_id'], $projectId);
    $r02Diff = null;
    foreach ($diffs as $d) {
        if ($d['code'] === 'R02') {
            $r02Diff = $d;
            break;
        }
    }
    same($r02Diff['before_outcome'], 'FAIL', 'e2e-test-2-diff-before-fail');
    same($r02Diff['after_outcome'], 'PASS', 'e2e-test-2-diff-after-pass');

    // =========================================================================
    // PHÉP THỬ 3: Không kết nối được hoặc timeout phải ghi là ERROR, không coi là PASS
    // =========================================================================
    // Cổng đóng
    $closedPortRes = HttpClient::request('GET', 'http://127.0.0.1:8298/api/notes', null, null, 1);
    same($closedPortRes['error_code'], 'CONNECT_FAILED', 'e2e-test-3-closed-port-error-code');
    $closedClassify = HttpClient::classify('DENY', $closedPortRes['status'], $closedPortRes['body'], null, null, null, $closedPortRes['error_code']);
    same($closedClassify['outcome'], 'ERROR', 'e2e-test-3-closed-port-never-pass');

    // Host ngoài whitelist
    $disallowedHostRes = HttpClient::request('GET', 'http://malicious-external-site.com:8205/api', null, null, 1);
    same($disallowedHostRes['error_code'], 'HOST_NOT_ALLOWED', 'e2e-test-3-disallowed-host-error-code');
    $disallowedClassify = HttpClient::classify('ALLOW', $disallowedHostRes['status'], $disallowedHostRes['body'], null, null, null, $disallowedHostRes['error_code']);
    same($disallowedClassify['outcome'], 'ERROR', 'e2e-test-3-disallowed-host-never-pass');

    // =========================================================================
    // PHÉP THỬ 4: Tài khoản chỉ được xem báo cáo không sửa được bộ ca của nhóm khác
    // =========================================================================
    $caseRepo = new CaseRepository($db);
    $projectRepo = new ProjectRepository($db);

    // dev2 (nhóm B) là viewer trong dự án 1
    same($caseRepo->canModify(1, 'dev2'), false, 'e2e-test-4-dev2-cannot-modify-proj1');
    same($caseRepo->canModify(1, 'auditor_user'), false, 'e2e-test-4-auditor-cannot-modify-proj1');

    $dev2Blocked = false;
    try {
        $caseRepo->saveCase(1, 'dev2', [
            'code' => 'ATTACK01',
            'account_id' => 1,
            'resource_id' => 1,
            'action_id' => 1,
            'method' => 'GET',
            'path' => '/api/profile',
            'expected' => 'ALLOW'
        ]);
    } catch (Throwable $e) {
        $dev2Blocked = str_contains($e->getMessage(), 'FORBIDDEN');
    }
    same($dev2Blocked, true, 'e2e-test-4-dev2-attack-blocked-with-forbidden');

    // =========================================================================
    // PHÉP THỬ 5: Báo cáo và nhật ký không chứa mật khẩu hoặc Bearer token nguyên văn
    // =========================================================================
    $reportMd = Report::generateMarkdown($runFixed['project'], $resultsFixed, $diffs);
    same(str_contains($reportMd, 'test_token_alice_123'), false, 'e2e-test-5-no-alice-token-in-report');
    same(str_contains($reportMd, 'test_token_admin_789'), false, 'e2e-test-5-no-admin-token-in-report');
    same(str_contains($reportMd, 'matkhau_lab_123'), false, 'e2e-test-5-no-db-password-in-report');

    $reportHtml = Report::generateHtml($runFixed['project'], $resultsFixed, $diffs);
    same(str_contains($reportHtml, 'test_token_bob_456'), false, 'e2e-test-5-no-bob-token-in-html-report');

    // Kiểm tra file log
    $testLogFile = __DIR__ . '/../.local/test_audit.log';
    Logger::setLogFile($testLogFile);
    Logger::info('Test audit log with Authorization: Bearer test_token_auditor_321 and password=super_secret');
    $logContent = (string)@file_get_contents($testLogFile);
    same(str_contains($logContent, 'test_token_auditor_321'), false, 'e2e-test-5-no-auditor-token-in-log');
    same(str_contains($logContent, 'super_secret'), false, 'e2e-test-5-no-password-in-log');
    @unlink($testLogFile);

} catch (Throwable $e) {
    $failed++;
    echo "FAIL e2e: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
} finally {
    if ($server !== null) {
        stopSampleServer($server);
    }
    pg_query($db, 'ROLLBACK');
}

finish();
