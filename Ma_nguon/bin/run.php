<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(2);
}

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Runner.php';
require_once __DIR__ . '/../src/Report.php';
require_once __DIR__ . '/../src/RunRepository.php';
require_once __DIR__ . '/../src/ProjectRepository.php';
require_once __DIR__ . '/../src/CaseRepository.php';
require_once __DIR__ . '/../src/Redactor.php';

function printHelp(): void
{
    echo "=====================================================================\n";
    echo "  ACCESSTESTER - CÔNG CỤ KIỂM THỬ TỰ ĐỘNG QUYỀN TRUY CẬP WEB (CLI)   \n";
    echo "=====================================================================\n";
    echo "Cách dùng:\n";
    echo "  php bin/run.php --project=ID [--label=LABEL] [--user=NAME]  Chạy 24 ca kiểm thử\n";
    echo "  php bin/run.php --compare=ID1,ID2 [--project=ID]          So sánh 2 lần chạy\n";
    echo "  php bin/run.php --report=ID [--format=md|html]            Xuất báo cáo kết quả\n";
    echo "  php bin/run.php --export-cases=ID                         Xuất danh sách ca ra JSON\n";
    echo "  php bin/run.php --help                                    Hiển thị hướng dẫn này\n\n";
    echo "Ví dụ:\n";
    echo "  source env.local.sh\n";
    echo "  php bin/run.php --project=1 --label=before-fix\n";
    echo "  php bin/run.php --compare=1,2 --project=1\n";
    echo "  php bin/run.php --report=1 --format=md > report-1.md\n";
}

try {
    $db = db();
} catch (Throwable $e) {
    fwrite(STDERR, "LOI_CSDL: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Chạy 'bash lab.sh db-config' hoặc 'bash lab.sh setup' để cấu hình CSDL.\n");
    exit(2);
}

$longopts = [
    'project::',
    'label::',
    'user::',
    'compare::',
    'report::',
    'format::',
    'export-cases::',
    'help',
];

$options = getopt('', $longopts);

if (isset($options['help']) || empty($options)) {
    printHelp();
    exit(0);
}

// 1. Chế độ xuất ca thử: --export-cases=ID
if (isset($options['export-cases'])) {
    $projectId = (int)$options['export-cases'];
    $caseRepo = new CaseRepository($db);
    echo $caseRepo->exportJson($projectId) . "\n";
    exit(0);
}

// 2. Chế độ xuất báo cáo: --report=ID
if (isset($options['report'])) {
    $runId = (int)$options['report'];
    $format = strtolower((string)($options['format'] ?? 'md'));

    $runRepo = new RunRepository($db);
    $run = $runRepo->findRun($runId);
    if (!$run) {
        fwrite(STDERR, "Lỗi: Không tìm thấy lần chạy #$runId\n");
        exit(2);
    }

    $results = $runRepo->getResults($runId);

    if ($format === 'html') {
        echo Report::generateHtml($run, $results);
    } else {
        echo Report::generateMarkdown($run, $results);
    }
    exit(0);
}

// 3. Chế độ so sánh: --compare=ID1,ID2
if (isset($options['compare'])) {
    $parts = explode(',', (string)$options['compare']);
    if (count($parts) !== 2) {
        fwrite(STDERR, "Lỗi: Tham số --compare phải có định dạng ID1,ID2 (ví dụ: --compare=1,2)\n");
        exit(2);
    }

    $runBeforeId = (int)trim($parts[0]);
    $runAfterId = (int)trim($parts[1]);

    $runRepo = new RunRepository($db);
    $run1 = $runRepo->findRun($runBeforeId);
    $run2 = $runRepo->findRun($runAfterId);

    if (!$run1 || !$run2) {
        fwrite(STDERR, "Lỗi: Một trong hai lần chạy không tồn tại (#$runBeforeId hoặc #$runAfterId).\n");
        exit(2);
    }

    $projectId = isset($options['project']) ? (int)$options['project'] : (int)$run1['project_id'];
    $diffs = $runRepo->compareRuns($runBeforeId, $runAfterId, $projectId);

    echo "=========================================================================\n";
    echo "  SO SÁNH ĐỐI CHIẾU KẾT QUẢ KIỂM THỬ: Lần chạy #$runBeforeId vs #$runAfterId\n";
    echo "=========================================================================\n";
    echo "Dự án: " . ($run1['project_name'] ?? 'ID ' . $projectId) . "\n";
    echo "Lần 1 (#$runBeforeId): " . $run1['label'] . " (" . $run1['started_at'] . ")\n";
    echo "Lần 2 (#$runAfterId): " . $run2['label'] . " (" . $run2['started_at'] . ")\n\n";

    printf("%-5s | %-8s | %-24s | %-6s | %-12s | %-12s | %s\n", "Mã", "Tài khoản", "Endpoint", "Kỳ vọng", "Trước sửa", "Sau sửa", "Đánh giá");
    echo str_repeat('-', 86) . "\n";

    $fixedCount = 0;
    $regCount = 0;

    foreach ($diffs as $row) {
        $b = $row['before_outcome'] ?? 'N/A';
        $a = $row['after_outcome'] ?? 'N/A';
        $status = "Giữ nguyên";
        if ($b === 'FAIL' && $a === 'PASS') {
            $status = "✔ KHẮC PHỤC";
            $fixedCount++;
        } elseif ($b === 'PASS' && $a === 'FAIL') {
            $status = "⚠ PHÁT SINH";
            $regCount++;
        }

        printf(
            "%-5s | %-8s | %-24s | %-6s | %-12s | %-12s | %s\n",
            $row['code'],
            $row['account_label'] ?? '',
            substr(($row['method'] ?? 'GET') . ' ' . ($row['path'] ?? ''), 0, 24),
            $row['expected'] ?? '',
            $b . ' (HTTP ' . ($row['before_status'] ?? '-') . ')',
            $a . ' (HTTP ' . ($row['after_status'] ?? '-') . ')',
            $status
        );
    }

    echo str_repeat('-', 86) . "\n";
    echo "Tổng kết so sánh: $fixedCount ca khắc phục lỗi (FAIL -> PASS), $regCount ca phát sinh lỗi (PASS -> FAIL).\n";
    exit(0);
}

// 4. Chế độ chạy kiểm thử: --project=ID
if (isset($options['project'])) {
    $projectId = (int)$options['project'];
    $label = (string)($options['label'] ?? ('cli-' . date('Ymd-His')));
    $startedBy = (string)($options['user'] ?? 'cli_user');

    echo "=====================================================================\n";
    echo "  KHỞI CHẠY KIỂM THỬ DỰ ÁN #$projectId (Nhãn: $label)\n";
    echo "=====================================================================\n";

    $runner = new Runner($db);

    $results = $runner->run($projectId, $label, 'cli', $startedBy, function (array $case) {
        $badge = match ($case['outcome']) {
            'PASS' => '[ PASS ]',
            'FAIL' => '[ FAIL ]',
            'ERROR' => '[ ERROR ] (' . ($case['error_code'] ?? 'ERR') . ')',
            default => '[' . $case['outcome'] . ']',
        };
        printf("%s %-5s %-8s %-6s %-25s -> HTTP %-3s (%d ms)\n",
            $badge,
            $case['code'],
            $case['account'],
            $case['method'],
            $case['path'],
            $case['http_status'] !== null ? (string)$case['http_status'] : 'N/A',
            $case['duration_ms']
        );
    });

    $stats = $results['stats'];
    echo "=====================================================================\n";
    echo "  KẾT QUẢ: TỔNG " . $stats['total'] . " CA | "
        . "PASS: " . $stats['pass_count'] . " | "
        . "FAIL: " . $stats['fail_count'] . " | "
        . "ERROR: " . $stats['error_count'] . "\n";
    echo "  Config Hash: " . $stats['config_hash'] . "\n";
    echo "=====================================================================\n";

    if ($stats['error_count'] > 0) {
        exit(2);
    }
    if ($stats['fail_count'] > 0) {
        exit(1);
    }
    exit(0);
}

printHelp();
exit(0);
