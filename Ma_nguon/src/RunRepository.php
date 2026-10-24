<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class RunRepository
{
    public function __construct(private PgSql\Connection $connection) {}

    public function listForProject(int $projectId): array
    {
        $result = queryParams(
            $this->connection,
            "SELECT r.id, r.label, r.config_hash, r.source, r.started_by, r.target_base_url,
                    r.started_at, r.finished_at,
                    COUNT(res.id)::int AS total,
                    COUNT(res.id) FILTER (WHERE res.outcome = 'PASS')::int AS pass_count,
                    COUNT(res.id) FILTER (WHERE res.outcome = 'FAIL')::int AS fail_count,
                    COUNT(res.id) FILTER (WHERE res.outcome = 'ERROR')::int AS error_count
             FROM runs r
             LEFT JOIN run_results res ON res.run_id = r.id
             WHERE r.project_id = $1
             GROUP BY r.id, r.label, r.config_hash, r.source, r.started_by, r.target_base_url, r.started_at, r.finished_at
             ORDER BY r.id DESC",
            [$projectId]
        );

        return pg_fetch_all($result) ?: [];
    }

    public function findRun(int $runId): ?array
    {
        $result = queryParams(
            $this->connection,
            'SELECT r.id, r.project_id, r.label, r.config_hash, r.source, r.started_by,
                    r.target_base_url, r.config_snapshot, r.started_at, r.finished_at,
                    p.name AS project_name, p.base_url,
                    COUNT(res.id)::int AS total,
                    COUNT(res.id) FILTER (WHERE res.outcome = \'PASS\')::int AS pass_count,
                    COUNT(res.id) FILTER (WHERE res.outcome = \'FAIL\')::int AS fail_count,
                    COUNT(res.id) FILTER (WHERE res.outcome = \'ERROR\')::int AS error_count
             FROM runs r
             JOIN projects p ON p.id = r.project_id
             LEFT JOIN run_results res ON res.run_id = r.id
             WHERE r.id = $1
             GROUP BY r.id, r.project_id, r.label, r.config_hash, r.source, r.started_by,
                      r.target_base_url, r.config_snapshot, r.started_at, r.finished_at,
                      p.name, p.base_url',
            [$runId]
        );

        $row = pg_fetch_assoc($result);
        return $row ?: null;
    }

    public function getResults(int $runId): array
    {
        $result = queryParams(
            $this->connection,
            'SELECT res.id, res.run_id, res.test_case_id, res.case_code AS code,
                    res.account_label, res.role_name, res.owner_key,
                    res.resource_name, res.action_name, res.method, res.path,
                    res.target_owner, res.declared_expected, res.policy_expected, res.expected,
                    res.http_status, res.outcome, res.error_code, res.detail,
                    res.leak_detected, res.response_bytes, res.duration_ms
             FROM run_results res
             WHERE res.run_id = $1
             ORDER BY res.case_code',
            [$runId]
        );

        return pg_fetch_all($result) ?: [];
    }

    /**
     * So sánh hai lần chạy dựa trên dữ liệu snapshot đã lưu (Phép thử 2 & Tình huống 2).
     */
    public function compareRuns(int $runBeforeId, int $runAfterId, int $projectId): array
    {
        $result = queryParams(
            $this->connection,
            'SELECT COALESCE(a.case_code, b.case_code) AS code,
                    COALESCE(a.account_label, b.account_label) AS account_label,
                    COALESCE(a.resource_name, b.resource_name) AS resource_name,
                    COALESCE(a.action_name, b.action_name) AS action_name,
                    COALESCE(a.method, b.method) AS method,
                    COALESCE(a.path, b.path) AS path,
                    COALESCE(a.expected, b.expected) AS expected,
                    b.outcome AS before_outcome,
                    b.http_status AS before_status,
                    b.duration_ms AS before_duration_ms,
                    b.detail AS before_detail,
                    a.outcome AS after_outcome,
                    a.http_status AS after_status,
                    a.duration_ms AS after_duration_ms,
                    a.detail AS after_detail
             FROM (SELECT * FROM run_results WHERE run_id = $1) b
             FULL OUTER JOIN (SELECT * FROM run_results WHERE run_id = $2) a
               ON a.case_code = b.case_code
             ORDER BY code',
            [$runBeforeId, $runAfterId]
        );

        return pg_fetch_all($result) ?: [];
    }

    public function createRun(
        int $projectId,
        string $label,
        string $configHash,
        string $source,
        ?string $startedBy = null,
        ?string $targetBaseUrl = null,
        ?array $configSnapshot = null
    ): int {
        $source = in_array(strtolower($source), ['web', 'cli'], true) ? strtolower($source) : 'cli';
        $snapshotJson = $configSnapshot !== null ? json_encode($configSnapshot, JSON_UNESCAPED_UNICODE) : null;

        $result = queryParams(
            $this->connection,
            'INSERT INTO runs (project_id, label, config_hash, source, started_by, target_base_url, config_snapshot, started_at)
             VALUES ($1, $2, $3, $4, $5, $6, $7, now())
             RETURNING id',
            [
                $projectId,
                trim($label),
                trim($configHash),
                $source,
                $startedBy !== null ? trim($startedBy) : null,
                $targetBaseUrl !== null ? trim($targetBaseUrl) : null,
                $snapshotJson
            ]
        );

        $row = pg_fetch_assoc($result);
        return (int)$row['id'];
    }

    public function saveResultSnapshot(
        int $runId,
        ?int $testCaseId,
        string $caseCode,
        string $accountLabel,
        ?string $roleName,
        ?string $ownerKey,
        string $resourceName,
        string $actionName,
        string $method,
        string $path,
        ?string $targetOwner,
        string $declaredExpected,
        string $policyExpected,
        string $expected,
        ?int $httpStatus,
        string $outcome,
        ?string $errorCode,
        string $detail,
        bool $leakDetected,
        int $responseBytes,
        int $durationMs
    ): bool {
        $result = queryParams(
            $this->connection,
            'INSERT INTO run_results (
                run_id, test_case_id, case_code, account_label, role_name, owner_key,
                resource_name, action_name, method, path, target_owner,
                declared_expected, policy_expected, expected, http_status, outcome,
                error_code, detail, leak_detected, response_bytes, duration_ms
             ) VALUES (
                $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, $19, $20, $21
             )
             ON CONFLICT (run_id, test_case_id) DO NOTHING
             RETURNING id',
            [
                $runId, $testCaseId, $caseCode, $accountLabel, $roleName, $ownerKey,
                $resourceName, $actionName, $method, $path, $targetOwner,
                $declaredExpected, $policyExpected, $expected, $httpStatus, $outcome,
                $errorCode, $detail, $leakDetected ? 'true' : 'false', $responseBytes, $durationMs
            ]
        );

        return pg_num_rows($result) === 1;
    }

    // Tương thích ngược với hàm cũ
    public function saveResult(
        int $runId,
        int $testCaseId,
        ?int $httpStatus,
        string $outcome,
        ?string $errorCode,
        int $durationMs
    ): bool {
        // Lấy thông tin case để chèn snapshot
        $tcRes = queryParams(
            $this->connection,
            'SELECT tc.code, acc.label AS account_label, ro.name AS role_name, acc.owner_key,
                    res.name AS resource_name, act.name AS action_name, tc.method, tc.path,
                    tc.target_owner, tc.expected
             FROM test_cases tc
             JOIN test_accounts acc ON acc.id = tc.account_id
             LEFT JOIN roles ro ON ro.id = acc.role_id
             JOIN resources res ON res.id = tc.resource_id
             JOIN actions act ON act.id = tc.action_id
             WHERE tc.id = $1',
            [$testCaseId]
        );
        $tc = pg_fetch_assoc($tcRes);
        if (!$tc) {
            return false;
        }

        return $this->saveResultSnapshot(
            $runId,
            $testCaseId,
            (string)$tc['code'],
            (string)$tc['account_label'],
            $tc['role_name'] ?? null,
            $tc['owner_key'] ?? null,
            (string)$tc['resource_name'],
            (string)$tc['action_name'],
            (string)$tc['method'],
            (string)$tc['path'],
            $tc['target_owner'] ?? null,
            (string)$tc['expected'],
            (string)$tc['expected'],
            (string)$tc['expected'],
            $httpStatus,
            $outcome,
            $errorCode,
            $outcome,
            false,
            0,
            $durationMs
        );
    }

    public function finishRun(int $runId): void
    {
        queryParams(
            $this->connection,
            'UPDATE runs SET finished_at = now() WHERE id = $1',
            [$runId]
        );
    }
}
