<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class CaseRepository
{
    public function __construct(private PgSql\Connection $connection) {}

    /**
     * Kiểm tra quyền sửa ca thử (Phép thử 4).
     * Chỉ 'owner' và 'editor' trong project_members mới được sửa.
     * 'viewer' hoặc người ngoài dự án bị từ chối tuyệt đối.
     */
    public function canModify(int $projectId, string $username): bool
    {
        $username = trim($username);
        if ($username === '') {
            return false;
        }

        $result = queryParams(
            $this->connection,
            'SELECT member_role FROM project_members WHERE project_id = $1 AND username = $2',
            [$projectId, $username]
        );

        $row = pg_fetch_assoc($result);
        if (!$row) {
            return false;
        }

        $role = $row['member_role'] ?? '';
        return in_array($role, ['owner', 'editor'], true);
    }

    public function listForProject(int $projectId): array
    {
        $result = queryParams(
            $this->connection,
            'SELECT tc.id, tc.project_id, tc.code, tc.method, tc.path, tc.target_owner,
                    tc.record_id, tc.request_body, tc.expected, tc.expected_status,
                    tc.must_contain, tc.must_not_contain, tc.updated_by, tc.updated_at,
                    acc.id AS account_id, acc.label AS account_label, acc.owner_key, acc.credential_env,
                    ro.name AS role_name,
                    res.id AS resource_id, res.name AS resource_name, res.owner_scoped,
                    act.id AS action_id, act.name AS action_name
             FROM test_cases tc
             JOIN test_accounts acc ON acc.id = tc.account_id
             LEFT JOIN roles ro ON ro.id = acc.role_id
             JOIN resources res ON res.id = tc.resource_id
             JOIN actions act ON act.id = tc.action_id
             WHERE tc.project_id = $1
             ORDER BY tc.code',
            [$projectId]
        );

        return pg_fetch_all($result) ?: [];
    }

    public function findById(int $caseId): ?array
    {
        $result = queryParams(
            $this->connection,
            'SELECT tc.*, acc.label AS account_label, acc.owner_key, acc.credential_env,
                    res.name AS resource_name, res.owner_scoped,
                    act.name AS action_name, ro.name AS role_name
             FROM test_cases tc
             JOIN test_accounts acc ON acc.id = tc.account_id
             LEFT JOIN roles ro ON ro.id = acc.role_id
             JOIN resources res ON res.id = tc.resource_id
             JOIN actions act ON act.id = tc.action_id
             WHERE tc.id = $1',
            [$caseId]
        );

        $row = pg_fetch_assoc($result);
        return $row ?: null;
    }

    public function saveCase(int $projectId, string $username, array $data): int
    {
        if (!$this->canModify($projectId, $username)) {
            throw new RuntimeException('FORBIDDEN: User does not have permission to modify test cases in this project.');
        }

        $code = trim($data['code'] ?? '');
        $accountId = (int)($data['account_id'] ?? 0);
        $resourceId = (int)($data['resource_id'] ?? 0);
        $actionId = (int)($data['action_id'] ?? 0);
        $method = strtoupper(trim($data['method'] ?? 'GET'));
        $path = trim($data['path'] ?? '');
        $recordId = !empty($data['record_id']) ? (int)$data['record_id'] : null;
        $targetOwner = !empty($data['target_owner']) ? trim($data['target_owner']) : null;
        $requestBody = !empty($data['request_body']) ? trim($data['request_body']) : null;
        $expected = strtoupper(trim($data['expected'] ?? 'DENY'));
        $expectedStatus = !empty($data['expected_status']) ? (int)$data['expected_status'] : null;
        $mustContain = !empty($data['must_contain']) ? trim($data['must_contain']) : null;
        $mustNotContain = !empty($data['must_not_contain']) ? trim($data['must_not_contain']) : null;

        if ($code === '' || $accountId <= 0 || $resourceId <= 0 || $actionId <= 0 || $path === '') {
            throw new InvalidArgumentException('CASE_DATA_INVALID');
        }

        if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
            throw new InvalidArgumentException('INVALID_HTTP_METHOD');
        }

        if (!in_array($expected, ['ALLOW', 'DENY'], true)) {
            throw new InvalidArgumentException('INVALID_EXPECTED_VALUE');
        }

        $result = queryParams(
            $this->connection,
            'INSERT INTO test_cases (project_id, code, account_id, resource_id, action_id, method, path,
                                     record_id, target_owner, request_body, expected, expected_status,
                                     must_contain, must_not_contain, updated_by, updated_at)
             VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, now())
             ON CONFLICT (project_id, code) DO UPDATE SET
                account_id = EXCLUDED.account_id,
                resource_id = EXCLUDED.resource_id,
                action_id = EXCLUDED.action_id,
                method = EXCLUDED.method,
                path = EXCLUDED.path,
                record_id = EXCLUDED.record_id,
                target_owner = EXCLUDED.target_owner,
                request_body = EXCLUDED.request_body,
                expected = EXCLUDED.expected,
                expected_status = EXCLUDED.expected_status,
                must_contain = EXCLUDED.must_contain,
                must_not_contain = EXCLUDED.must_not_contain,
                updated_by = EXCLUDED.updated_by,
                updated_at = now()
             RETURNING id',
            [
                $projectId, $code, $accountId, $resourceId, $actionId, $method, $path,
                $recordId, $targetOwner, $requestBody, $expected, $expectedStatus,
                $mustContain, $mustNotContain, $username
            ]
        );

        $row = pg_fetch_assoc($result);
        return (int)$row['id'];
    }

    public function deleteCase(int $projectId, string $username, int $caseId): bool
    {
        if (!$this->canModify($projectId, $username)) {
            throw new RuntimeException('FORBIDDEN: User does not have permission to delete test cases in this project.');
        }

        $res = queryParams(
            $this->connection,
            'DELETE FROM test_cases WHERE id = $1 AND project_id = $2',
            [$caseId, $projectId]
        );

        return pg_affected_rows($res) > 0;
    }

    /**
     * Xuất danh sách ca thử nghiệm của dự án ra cấu trúc JSON (để CLI/Web dùng chung cấu hình).
     */
    public function exportJson(int $projectId): string
    {
        $cases = $this->listForProject($projectId);
        $export = array_map(function ($c) {
            return [
                'code' => $c['code'],
                'account' => $c['account_label'],
                'resource' => $c['resource_name'],
                'action' => $c['action_name'],
                'method' => $c['method'],
                'path' => $c['path'],
                'target_owner' => $c['target_owner'],
                'expected' => $c['expected'],
                'expected_status' => $c['expected_status'] ? (int)$c['expected_status'] : null,
                'must_contain' => $c['must_contain'],
                'must_not_contain' => $c['must_not_contain'],
                'request_body' => $c['request_body'],
            ];
        }, $cases);

        return (string)json_encode([
            'project_id' => $projectId,
            'exported_at' => date('c'),
            'total_cases' => count($export),
            'cases' => $export,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
