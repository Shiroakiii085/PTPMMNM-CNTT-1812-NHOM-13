<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/Redactor.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Policy.php';
require_once __DIR__ . '/ProjectRepository.php';
require_once __DIR__ . '/CaseRepository.php';
require_once __DIR__ . '/RunRepository.php';
require_once __DIR__ . '/RunStats.php';

final class Runner
{
    private ProjectRepository $projectRepo;
    private CaseRepository $caseRepo;
    private RunRepository $runRepo;

    public function __construct(private PgSql\Connection $connection)
    {
        $this->projectRepo = new ProjectRepository($connection);
        $this->caseRepo = new CaseRepository($connection);
        $this->runRepo = new RunRepository($connection);
    }

    /**
     * Tính hash đại diện cho cấu hình ca thử của dự án (config_hash - SHA-256).
     */
    public static function computeConfigHash(array $cases): string
    {
        $normalized = array_map(function ($c) {
            return [
                'code' => $c['code'],
                'method' => $c['method'],
                'path' => $c['path'],
                'expected' => $c['expected'],
                'expected_status' => $c['expected_status'] ?? null,
                'must_contain' => $c['must_contain'] ?? '',
                'must_not_contain' => $c['must_not_contain'] ?? '',
                'account' => $c['account_label'] ?? '',
                'target_owner' => $c['target_owner'] ?? '',
                'request_body' => $c['request_body'] ?? '',
            ];
        }, $cases);

        usort($normalized, fn($a, $b) => strcmp($a['code'], $b['code']));
        return hash('sha256', (string)json_encode($normalized));
    }

    /**
     * Tự động nạp token từ env.local.sh hoặc mảng token thử nghiệm mặc định.
     * Đảm bảo Web UI và CLI đều có token đầy đủ khi kiểm thử.
     */
    public static function loadEnvSecrets(): void
    {
        $possibleFiles = [
            __DIR__ . '/../env.local.sh',
            __DIR__ . '/../../env.local.sh',
            dirname(__DIR__, 2) . '/env.local.sh',
        ];

        foreach ($possibleFiles as $file) {
            if (file_exists($file) && is_readable($file)) {
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines !== false) {
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (str_starts_with($line, '#')) {
                            continue;
                        }
                        if (preg_match('/^(?:export\s+)?([A-Za-z0-9_]+)=[\'"]?(.*?)[\'"]?$/', $line, $m)) {
                            $k = $m[1];
                            $v = $m[2];
                            putenv("$k=$v");
                            $_ENV[$k] = $v;
                            Redactor::registerSecret($v);
                        }
                    }
                }
                break;
            }
        }

        // Fallback token chuẩn từ mock nếu chưa có trong môi trường
        $defaultTokens = [
            'TOKEN_ALICE' => 'test_token_alice_123',
            'TOKEN_BOB' => 'test_token_bob_456',
            'TOKEN_ADMIN' => 'test_token_admin_789',
            'TOKEN_AUDITOR' => 'test_token_auditor_321',
        ];

        foreach ($defaultTokens as $envKey => $tokenVal) {
            $cur = getenv($envKey);
            if ($cur === false || $cur === '') {
                putenv("$envKey=$tokenVal");
                $_ENV[$envKey] = $tokenVal;
            }
            Redactor::registerSecret(getenv($envKey) ?: $tokenVal);
        }
    }

    /**
     * Chạy toàn bộ các ca thử của dự án.
     *
     * @param int $projectId
     * @param string $label Nhãn của lần chạy (ví dụ: 'before-fix', 'after-fix')
     * @param string $source 'web' hoặc 'cli'
     * @param string|null $startedBy Người thực thi
     * @param (callable(array): void)|null $progressCallback Hàm gọi lại mỗi khi hoàn thành 1 ca
     * @return array Thống kê và kết quả lần chạy
     */
    public function run(
        int $projectId,
        string $label,
        string $source = 'cli',
        ?string $startedBy = null,
        ?callable $progressCallback = null
    ): array {
        self::loadEnvSecrets();

        $project = $this->projectRepo->findById($projectId);
        if (!$project) {
            throw new RuntimeException("PROJECT_NOT_FOUND: Project ID $projectId does not exist.");
        }

        $cases = $this->caseRepo->listForProject($projectId);
        if (empty($cases)) {
            throw new RuntimeException("NO_TEST_CASES: Project ID $projectId has no test cases configured.");
        }

        // Đăng ký trước các biến môi trường nhạy cảm vào Redactor
        foreach ($cases as $c) {
            if (!empty($c['credential_env'])) {
                $val = getenv($c['credential_env']) ?: ($_ENV[$c['credential_env']] ?? null);
                if (!empty($val)) {
                    Redactor::registerSecret($val);
                }
            }
        }

        $configHash = self::computeConfigHash($cases);
        $baseUrl = rtrim($project['base_url'], '/');

        $runId = $this->runRepo->createRun(
            $projectId,
            $label,
            $configHash,
            $source,
            $startedBy,
            $baseUrl,
            $cases
        );

        Logger::info("Bắt đầu đợt kiểm thử #$runId [$label] cho dự án {$project['name']} ($source)");

        $results = [];

        foreach ($cases as $case) {
            $url = $baseUrl . $case['path'];

            // Lấy token từ biến môi trường theo credential_env
            $token = null;
            if (!empty($case['credential_env'])) {
                $envVal = getenv($case['credential_env']);
                if ($envVal !== false && $envVal !== '') {
                    $token = $envVal;
                } elseif (isset($_ENV[$case['credential_env']]) && $_ENV[$case['credential_env']] !== '') {
                    $token = $_ENV[$case['credential_env']];
                }
            }

            // Dữ liệu payload nếu là PUT / POST
            $payload = null;
            if (!empty($case['request_body'])) {
                $decoded = json_decode($case['request_body'], true);
                $payload = ($decoded !== null) ? $decoded : $case['request_body'];
            } elseif (in_array(strtoupper($case['method']), ['PUT', 'POST', 'PATCH'], true)) {
                $payload = [
                    'title' => 'Cập nhật bởi ' . ($case['account_label'] ?? 'test'),
                ];
            }

            // Gửi HTTP thật (HttpClient tự động kiểm tra TargetAllowlist và đo lường thời gian)
            $httpRes = HttpClient::request($case['method'], $url, $token, $payload);

            // Tính toán quyết định từ ma trận quyền (Policy Oracle - Kỹ thuật 1)
            $oracle = Policy::computeExpectedForCase($this->connection, $case);
            $policyExpected = $oracle['expected'];
            $targetOwner = $oracle['target_owner'] ?? $case['target_owner'];

            // Phân loại kết quả (Phép thử 1, 2, 3)
            $classifyRes = HttpClient::classify(
                $case['expected'],
                $httpRes['status'],
                $httpRes['body'],
                $case['must_contain'] ?? null,
                $case['must_not_contain'] ?? null,
                !empty($case['expected_status']) ? (int)$case['expected_status'] : null,
                $httpRes['error_code']
            );

            $outcome = $classifyRes['outcome'];
            $detail = $classifyRes['detail'];
            $leakDetected = $classifyRes['leak_detected'];

            // Lưu kết quả dưới dạng snapshot hoàn chỉnh
            $this->runRepo->saveResultSnapshot(
                $runId,
                (int)$case['id'],
                $case['code'],
                $case['account_label'],
                $case['role_name'] ?? null,
                $case['owner_key'] ?? null,
                $case['resource_name'],
                $case['action_name'],
                $case['method'],
                $case['path'],
                $targetOwner,
                $case['expected'],
                $policyExpected,
                $case['expected'],
                $httpRes['status'],
                $outcome,
                $httpRes['error_code'],
                $detail,
                $leakDetected,
                $httpRes['bytes'],
                $httpRes['duration_ms']
            );

            $item = [
                'test_case_id' => (int)$case['id'],
                'code' => $case['code'],
                'method' => $case['method'],
                'path' => $case['path'],
                'account' => $case['account_label'],
                'target_owner' => $targetOwner,
                'expected' => $case['expected'],
                'policy_expected' => $policyExpected,
                'http_status' => $httpRes['status'],
                'outcome' => $outcome,
                'detail' => $detail,
                'error_code' => $httpRes['error_code'],
                'leak_detected' => $leakDetected,
                'duration_ms' => $httpRes['duration_ms'],
            ];

            $results[] = $item;

            if ($progressCallback !== null) {
                $progressCallback($item);
            }
        }

        $this->runRepo->finishRun($runId);
        $stats = runStats($this->connection, $runId);

        Logger::info("Hoàn tất đợt kiểm thử #$runId: PASS={$stats['pass_count']}, FAIL={$stats['fail_count']}, ERROR={$stats['error_count']}");

        return [
            'run_id' => $runId,
            'project' => $project,
            'stats' => $stats,
            'results' => $results,
        ];
    }
}
