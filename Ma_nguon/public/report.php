<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/RunRepository.php';
require_once __DIR__ . '/../src/Report.php';
require_once __DIR__ . '/../src/Input.php';

$runId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$compareRunId = isset($_GET['compare']) ? (int)$_GET['compare'] : 0;
$format = strtolower($_GET['format'] ?? 'html');
$download = isset($_GET['download']) && $_GET['download'] === '1';

if ($runId <= 0) {
    http_response_code(400);
    echo "Thiếu Run ID hợp lệ.";
    exit;
}

try {
    $db = db();
} catch (Throwable $e) {
    http_response_code(500);
    echo "Lỗi kết nối CSDL: " . e($e->getMessage());
    exit;
}

$runRepo = new RunRepository($db);
$run = $runRepo->findRun($runId);

if (!$run) {
    http_response_code(404);
    echo "Không tìm thấy lần chạy #$runId";
    exit;
}

$results = $runRepo->getResults($runId);
$comparison = null;

if ($compareRunId > 0) {
    $comparison = $runRepo->compareRuns($compareRunId, $runId, (int)$run['project_id']);
}

// Tải file về
if ($download || $format === 'md') {
    if ($format === 'md') {
        $content = Report::generateMarkdown($run, $results, $comparison);
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="report-run-' . $runId . '.md"');
        echo $content;
        exit;
    }

    if ($format === 'html' && $download) {
        $content = Report::generateHtml($run, $results, $comparison);
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="report-run-' . $runId . '.html"');
        echo $content;
        exit;
    }
}

// Hiển thị trực tiếp HTML report
echo Report::generateHtml($run, $results, $comparison);
