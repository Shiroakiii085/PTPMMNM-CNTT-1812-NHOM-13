<?php

declare(strict_types=1);

require_once __DIR__ . '/Redactor.php';
require_once __DIR__ . '/Input.php';

final class Report
{
    /**
     * Xuất báo cáo dạng Markdown (Phép thử 5 & Rubric).
     */
    public static function generateMarkdown(array $run, array $results, ?array $comparison = null): string
    {
        $lines = [];
        $lines[] = "# Báo cáo kiểm thử tự động quyền truy cập Web";
        $lines[] = "";
        $lines[] = "- **Dự án:** " . ($run['project_name'] ?? 'N/A');
        $lines[] = "- **Mã lần chạy (Run ID):** #" . $run['id'];
        $lines[] = "- **Nhãn lần chạy:** `" . ($run['label'] ?? 'unlabeled') . "`";
        $lines[] = "- **Nguồn thực hiện:** `" . strtoupper((string)($run['source'] ?? 'cli')) . "`";
        $lines[] = "- **Người thực hiện:** `" . ($run['started_by'] ?? 'cli_user') . "`";
        $lines[] = "- **Máy chủ mục tiêu:** `" . ($run['target_base_url'] ?? $run['base_url'] ?? 'N/A') . "`";
        $lines[] = "- **Thời điểm bắt đầu:** " . ($run['started_at'] ?? 'N/A');
        $lines[] = "- **Thời điểm hoàn thành:** " . ($run['finished_at'] ?? 'N/A');
        $lines[] = "- **Config Hash (SHA-256):** `" . ($run['config_hash'] ?? 'N/A') . "`";
        $lines[] = "";

        // Thống kê tổng hợp
        $total = count($results);
        $passCount = count(array_filter($results, fn($r) => ($r['outcome'] ?? '') === 'PASS'));
        $failCount = count(array_filter($results, fn($r) => ($r['outcome'] ?? '') === 'FAIL'));
        $errorCount = count(array_filter($results, fn($r) => ($r['outcome'] ?? '') === 'ERROR'));

        $lines[] = "## 1. Tóm tắt kết quả kiểm thử";
        $lines[] = "";
        $lines[] = "| Chỉ số | Số lượng | Tỷ lệ |";
        $lines[] = "| :--- | :--- | :--- |";
        $lines[] = sprintf("| **Tổng số ca thử** | %d | 100%% |", $total);
        $lines[] = sprintf("| **PASS (Đạt quyền hạn)** | %d | %.1f%% |", $passCount, $total > 0 ? ($passCount / $total * 100) : 0);
        $lines[] = sprintf("| **FAIL (Lỗi phân quyền/Rò rỉ)** | %d | %.1f%% |", $failCount, $total > 0 ? ($failCount / $total * 100) : 0);
        $lines[] = sprintf("| **ERROR (Lỗi kết nối/Máy chủ)** | %d | %.1f%% |", $errorCount, $total > 0 ? ($errorCount / $total * 100) : 0);
        $lines[] = "";

        // So sánh trước và sau nếu có
        if ($comparison !== null && !empty($comparison)) {
            $lines[] = "## 2. Đối chiếu so sánh trước và sau sửa lỗi (Tình huống 2)";
            $lines[] = "";

            $fixedCases = [];
            $regressions = [];
            foreach ($comparison as $c) {
                if (($c['before_outcome'] ?? '') === 'FAIL' && ($c['after_outcome'] ?? '') === 'PASS') {
                    $fixedCases[] = $c['code'];
                } elseif (($c['before_outcome'] ?? '') === 'PASS' && ($c['after_outcome'] ?? '') === 'FAIL') {
                    $regressions[] = $c['code'];
                }
            }

            if (!empty($fixedCases)) {
                $lines[] = "> **[FIXED] Đã khắc phục lỗi thành công (FAIL -> PASS):** " . implode(', ', array_map(fn($c) => "`$c`", $fixedCases));
                $lines[] = "";
            }

            if (!empty($regressions)) {
                $lines[] = "> **[REGRESSION] Cảnh báo phát sinh lỗi (PASS -> FAIL):** " . implode(', ', array_map(fn($c) => "`$c`", $regressions));
                $lines[] = "";
            }

            $lines[] = "| Mã ca | Tài khoản | Yêu cầu | Kỳ vọng | Trước sửa | Sau sửa | Đánh giá |";
            $lines[] = "| :--- | :--- | :--- | :--- | :--- | :--- | :--- |";

            foreach ($comparison as $row) {
                $bOut = $row['before_outcome'] ?? 'N/A';
                $aOut = $row['after_outcome'] ?? 'N/A';
                $diff = "Không đổi";
                if ($bOut === 'FAIL' && $aOut === 'PASS') {
                    $diff = "**[FIXED] Sửa lỗi thành công**";
                } elseif ($bOut === 'PASS' && $aOut === 'FAIL') {
                    $diff = "**[REGRESSION] Lỗi mới**";
                }

                $lines[] = sprintf(
                    "| `%s` | %s | `%s %s` | **%s** | %s (HTTP %s) | %s (HTTP %s) | %s |",
                    $row['code'],
                    $row['account_label'] ?? '',
                    $row['method'] ?? 'GET',
                    $row['path'] ?? '',
                    $row['expected'] ?? '',
                    $bOut,
                    $row['before_status'] ?? '-',
                    $aOut,
                    $row['after_status'] ?? '-',
                    $diff
                );
            }
            $lines[] = "";
        }

        // Bảng chi tiết từng ca thử
        $lines[] = "## " . ($comparison !== null ? "3" : "2") . ". Chi tiết kết quả 24 ca kiểm thử";
        $lines[] = "";
        $lines[] = "| Mã ca | Tài khoản | Endpoint | Chủ sở hữu | Kỳ vọng | Thực tế | Kết quả | Chi tiết đánh giá |";
        $lines[] = "| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |";

        foreach ($results as $res) {
            $outcomeBadge = match ($res['outcome'] ?? '') {
                'PASS' => '`PASS`',
                'FAIL' => '**`FAIL`**',
                'ERROR' => '**`ERROR`** (' . ($res['error_code'] ?? 'ERR') . ')',
                default => $res['outcome'] ?? 'N/A',
            };

            $lines[] = sprintf(
                "| `%s` | %s | `%s %s` | %s | %s | HTTP %s | %s | %s |",
                $res['code'],
                $res['account_label'] ?? ($res['account'] ?? 'N/A'),
                $res['method'],
                $res['path'],
                $res['target_owner'] ?? '-',
                $res['expected'],
                $res['http_status'] ?? '-',
                $outcomeBadge,
                $res['detail'] ?? ($res['outcome'] === 'PASS' ? 'Hợp lệ' : '-')
            );
        }

        $lines[] = "";
        $lines[] = "---";
        $lines[] = "*Báo cáo được sinh tự động bởi AccessTester (Đề tài 4 - PTPMMNM).*";
        $lines[] = "*Quy định an toàn: Toàn bộ mật khẩu, token xác thực đã được tự động khử (Redacted) trước khi xuất.*";

        $output = implode("\n", $lines);
        return Redactor::redactText($output);
    }

    /**
     * Xuất báo cáo dạng HTML độc lập.
     */
    public static function generateHtml(array $run, array $results, ?array $comparison = null): string
    {
        $total = count($results);
        $passCount = count(array_filter($results, fn($r) => ($r['outcome'] ?? '') === 'PASS'));
        $failCount = count(array_filter($results, fn($r) => ($r['outcome'] ?? '') === 'FAIL'));
        $errorCount = count(array_filter($results, fn($r) => ($r['outcome'] ?? '') === 'ERROR'));

        ob_start();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Báo cáo kiểm thử #<?= (int)$run['id'] ?> - <?= e((string)($run['label'] ?? '')) ?></title>
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        .report-wrapper { max-width: 1100px; margin: 2rem auto; padding: 0 1rem; }
        .summary-badge-group { display: flex; gap: 1rem; margin-top: 1rem; }
        .summary-badge { padding: 0.6rem 1.2rem; border-radius: 0.5rem; font-weight: 700; font-size: 1rem; }
    </style>
</head>
<body style="background: #f8fafc;">
    <div class="report-wrapper">
        <div class="card mb-4">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <span class="badge badge-subtle">BÁO CÁO TỰ ĐỘNG BẢO MẬT & PHÂN QUYỀN</span>
                    <h1 style="font-size: 1.8rem; margin: 0.5rem 0;">Kiểm thử tự động quyền truy cập Web</h1>
                    <p class="text-muted">Dự án: <strong><?= e((string)($run['project_name'] ?? 'Sample App')) ?></strong> • Lần chạy: <code>#<?= (int)$run['id'] ?> (<?= e((string)($run['label'] ?? '')) ?>)</code></p>
                </div>
                <div class="summary-badge-group">
                    <span class="badge badge-pass" style="font-size: 0.95rem; padding: 0.5rem 1rem;"><?= $passCount ?> PASS</span>
                    <?php if ($failCount > 0): ?>
                        <span class="badge badge-fail" style="font-size: 0.95rem; padding: 0.5rem 1rem;"><?= $failCount ?> FAIL</span>
                    <?php endif; ?>
                    <?php if ($errorCount > 0): ?>
                        <span class="badge badge-error" style="font-size: 0.95rem; padding: 0.5rem 1rem;"><?= $errorCount ?> ERROR</span>
                    <?php endif; ?>
                </div>
            </div>

            <hr style="margin: 1.25rem 0; border: 0; border-top: 1px solid #e2e8f0;">

            <div class="grid grid-cols-3 gap-4" style="font-size: 0.9rem;">
                <div><strong>Nguồn chạy:</strong> <code><?= e(strtoupper((string)($run['source'] ?? 'cli'))) ?></code> (bởi <?= e((string)($run['started_by'] ?? 'cli')) ?>)</div>
                <div><strong>Thời điểm:</strong> <?= e((string)($run['started_at'] ?? '')) ?></div>
                <div><strong>Đích kiểm thử:</strong> <code><?= e((string)($run['target_base_url'] ?? $run['base_url'] ?? '127.0.0.1')) ?></code></div>
                <div style="grid-column: span 3;"><strong>Config Hash (SHA-256):</strong> <code><?= e((string)($run['config_hash'] ?? '')) ?></code></div>
            </div>
        </div>

        <?php if ($comparison !== null && !empty($comparison)): ?>
        <div class="card mb-4">
            <h2 class="card-title mb-4">Đối chiếu so sánh trước và sau sửa lỗi (Tình huống 2)</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã ca</th>
                            <th>Tài khoản</th>
                            <th>Yêu cầu</th>
                            <th>Kỳ vọng</th>
                            <th>Trước sửa</th>
                            <th>Sau sửa</th>
                            <th>Đánh giá</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparison as $row): 
                            $bOut = $row['before_outcome'] ?? 'N/A';
                            $aOut = $row['after_outcome'] ?? 'N/A';
                            $isFixed = ($bOut === 'FAIL' && $aOut === 'PASS');
                            $isReg = ($bOut === 'PASS' && $aOut === 'FAIL');
                        ?>
                        <tr class="<?= $isFixed ? 'row-fixed' : ($isReg ? 'row-regression' : '') ?>">
                            <td><strong><code><?= e($row['code']) ?></code></strong></td>
                            <td><?= e($row['account_label'] ?? '') ?></td>
                            <td><code><?= e($row['method'] ?? 'GET') ?> <?= e($row['path'] ?? '') ?></code></td>
                            <td><span class="badge badge-<?= strtolower($row['expected'] ?? '') ?>"><?= e($row['expected'] ?? '') ?></span></td>
                            <td><span class="badge badge-<?= strtolower($bOut) ?>"><?= e($bOut) ?></span> (HTTP <?= e((string)($row['before_status'] ?? '-')) ?>)</td>
                            <td><span class="badge badge-<?= strtolower($aOut) ?>"><?= e($aOut) ?></span> (HTTP <?= e((string)($row['after_status'] ?? '-')) ?>)</td>
                            <td>
                                <?php if ($isFixed): ?>
                                    <span class="badge badge-pass" style="font-weight: 800;">[FIXED] ĐÃ KHẮC PHỤC</span>
                                <?php elseif ($isReg): ?>
                                    <span class="badge badge-fail" style="font-weight: 800;">[REGRESSION] PHÁT SINH LỖI</span>
                                <?php else: ?>
                                    <span class="text-muted">Không đổi</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title mb-4">Chi tiết kết quả 24 ca kiểm thử</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã ca</th>
                            <th>Tài khoản</th>
                            <th>Endpoint</th>
                            <th>Chủ sở hữu</th>
                            <th>Kỳ vọng</th>
                            <th>Thực tế</th>
                            <th>Kết quả</th>
                            <th>Thời gian</th>
                            <th>Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $res): 
                            $isFail = ($res['outcome'] === 'FAIL');
                            $isErr = ($res['outcome'] === 'ERROR');
                        ?>
                        <tr style="<?= $isFail ? 'background-color: #fef2f2;' : ($isErr ? 'background-color: #fff7ed;' : '') ?>">
                            <td><strong><code><?= e($res['code']) ?></code></strong></td>
                            <td><?= e($res['account_label'] ?? ($res['account'] ?? '')) ?></td>
                            <td><code><?= e($res['method']) ?> <?= e($res['path']) ?></code></td>
                            <td><?= e($res['target_owner'] ?? '-') ?></td>
                            <td><span class="badge badge-<?= strtolower($res['expected']) ?>"><?= e($res['expected']) ?></span></td>
                            <td>HTTP <?= e((string)($res['http_status'] ?? '-')) ?></td>
                            <td>
                                <span class="badge badge-<?= strtolower($res['outcome']) ?>">
                                    <?= e($res['outcome']) ?>
                                    <?= !empty($res['error_code']) ? '(' . e($res['error_code']) . ')' : '' ?>
                                </span>
                            </td>
                            <td><?= (int)($res['duration_ms'] ?? 0) ?> ms</td>
                            <td><small><?= e((string)($res['detail'] ?? '-')) ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem; color: #94a3b8; font-size: 0.85rem;">
            Báo cáo được sinh tự động. Không chứa thông tin xác thực nhạy cảm.
        </div>
    </div>
</body>
</html>
<?php
        $html = (string)ob_get_clean();
        return Redactor::redactText($html);
    }
}
