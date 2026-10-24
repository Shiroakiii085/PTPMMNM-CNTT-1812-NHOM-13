<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class ProjectRepository
{
    public function __construct(private PgSql\Connection $connection) {}

    public function listProjects(): array
    {
        $result = queryParams(
            $this->connection,
            'SELECT p.id, p.name, p.base_url, p.description, p.created_at,
                    COUNT(DISTINCT tc.id)::int AS case_count,
                    COUNT(DISTINCT r.id)::int AS run_count,
                    COUNT(DISTINCT pm.username)::int AS member_count
             FROM projects p
             LEFT JOIN test_cases tc ON tc.project_id = p.id
             LEFT JOIN runs r ON r.project_id = p.id
             LEFT JOIN project_members pm ON pm.project_id = p.id
             GROUP BY p.id, p.name, p.base_url, p.description, p.created_at
             ORDER BY p.id',
            []
        );

        return pg_fetch_all($result) ?: [];
    }

    public function findById(int $id): ?array
    {
        $result = queryParams(
            $this->connection,
            'SELECT id, name, base_url, description, created_at FROM projects WHERE id = $1',
            [$id]
        );

        $row = pg_fetch_assoc($result);
        return $row ?: null;
    }

    public function create(string $name, string $baseUrl, string $ownerUsername, string $description = ''): int
    {
        $name = trim($name);
        $baseUrl = rtrim(trim($baseUrl), '/');
        $ownerUsername = trim($ownerUsername);

        if ($name === '' || $baseUrl === '' || $ownerUsername === '') {
            throw new InvalidArgumentException('PROJECT_DATA_INVALID');
        }

        $res = queryParams(
            $this->connection,
            'INSERT INTO projects (name, base_url, description) VALUES ($1, $2, $3) RETURNING id',
            [$name, $baseUrl, $description]
        );
        $row = pg_fetch_assoc($res);
        $projectId = (int)$row['id'];

        queryParams(
            $this->connection,
            'INSERT INTO project_members (project_id, username, member_role) VALUES ($1, $2, $3)
             ON CONFLICT (project_id, username) DO UPDATE SET member_role = EXCLUDED.member_role',
            [$projectId, $ownerUsername, 'owner']
        );

        return $projectId;
    }

    public function update(int $projectId, string $name, string $baseUrl, string $description = ''): void
    {
        queryParams(
            $this->connection,
            'UPDATE projects SET name = $1, base_url = $2, description = $3 WHERE id = $4',
            [trim($name), rtrim(trim($baseUrl), '/'), trim($description), $projectId]
        );
    }

    public function delete(int $projectId): void
    {
        queryParams($this->connection, 'DELETE FROM projects WHERE id = $1', [$projectId]);
    }

    // ==========================================================
    // Thành viên dự án
    // ==========================================================
    public function getMemberRole(int $projectId, string $username): ?string
    {
        $result = queryParams(
            $this->connection,
            'SELECT member_role FROM project_members WHERE project_id = $1 AND username = $2',
            [$projectId, trim($username)]
        );

        $row = pg_fetch_assoc($result);
        return $row ? (string)$row['member_role'] : null;
    }

    public function getMembers(int $projectId): array
    {
        $result = queryParams(
            $this->connection,
            'SELECT pm.username, pm.member_role, COALESCE(u.display_name, pm.username) AS display_name
             FROM project_members pm
             LEFT JOIN app_users u ON u.username = pm.username
             WHERE pm.project_id = $1
             ORDER BY pm.username',
            [$projectId]
        );

        return pg_fetch_all($result) ?: [];
    }

    public function addMember(int $projectId, string $username, string $role): void
    {
        $role = strtolower(trim($role));
        if (!in_array($role, ['owner', 'editor', 'viewer'], true)) {
            throw new InvalidArgumentException('INVALID_ROLE');
        }

        queryParams(
            $this->connection,
            'INSERT INTO project_members (project_id, username, member_role)
             VALUES ($1, $2, $3)
             ON CONFLICT (project_id, username) DO UPDATE SET member_role = EXCLUDED.member_role',
            [$projectId, trim($username), $role]
        );
    }

    public function removeMember(int $projectId, string $username): void
    {
        queryParams(
            $this->connection,
            'DELETE FROM project_members WHERE project_id = $1 AND username = $2',
            [$projectId, trim($username)]
        );
    }

    // ==========================================================
    // Vai trò (Roles) của ứng dụng đích
    // ==========================================================
    public function getRoles(int $projectId): array
    {
        $r = queryParams($this->connection, 'SELECT id, name FROM roles WHERE project_id = $1 ORDER BY id', [$projectId]);
        return pg_fetch_all($r) ?: [];
    }

    public function addRole(int $projectId, string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('ROLE_NAME_EMPTY');
        }
        $r = queryParams(
            $this->connection,
            'INSERT INTO roles (project_id, name) VALUES ($1, $2) ON CONFLICT (project_id, name) DO UPDATE SET name = EXCLUDED.name RETURNING id',
            [$projectId, $name]
        );
        $row = pg_fetch_assoc($r);
        return (int)$row['id'];
    }

    // ==========================================================
    // Tài nguyên (Resources)
    // ==========================================================
    public function getResources(int $projectId): array
    {
        $r = queryParams($this->connection, 'SELECT id, name, owner_scoped FROM resources WHERE project_id = $1 ORDER BY id', [$projectId]);
        return pg_fetch_all($r) ?: [];
    }

    public function addResource(int $projectId, string $name, bool $ownerScoped): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('RESOURCE_NAME_EMPTY');
        }
        $r = queryParams(
            $this->connection,
            'INSERT INTO resources (project_id, name, owner_scoped) VALUES ($1, $2, $3)
             ON CONFLICT (project_id, name) DO UPDATE SET owner_scoped = EXCLUDED.owner_scoped RETURNING id',
            [$projectId, $name, $ownerScoped ? 'true' : 'false']
        );
        $row = pg_fetch_assoc($r);
        return (int)$row['id'];
    }

    // ==========================================================
    // Hành động (Actions)
    // ==========================================================
    public function getActions(int $projectId): array
    {
        $r = queryParams($this->connection, 'SELECT id, name FROM actions WHERE project_id = $1 ORDER BY id', [$projectId]);
        return pg_fetch_all($r) ?: [];
    }

    public function addAction(int $projectId, string $name): int
    {
        $name = strtolower(trim($name));
        if (!in_array($name, ['read', 'create', 'update', 'delete'], true)) {
            throw new InvalidArgumentException('ACTION_INVALID');
        }
        $r = queryParams(
            $this->connection,
            'INSERT INTO actions (project_id, name) VALUES ($1, $2) ON CONFLICT (project_id, name) DO UPDATE SET name = EXCLUDED.name RETURNING id',
            [$projectId, $name]
        );
        $row = pg_fetch_assoc($r);
        return (int)$row['id'];
    }

    // ==========================================================
    // Ma trận quyền (Permissions)
    // ==========================================================
    public function getPermissions(int $projectId): array
    {
        $r = queryParams(
            $this->connection,
            'SELECT p.role_id, p.resource_id, p.action_id, p.scope,
                    ro.name AS role_name, res.name AS resource_name, act.name AS action_name
             FROM permissions p
             JOIN roles ro ON ro.id = p.role_id
             JOIN resources res ON res.id = p.resource_id
             JOIN actions act ON act.id = p.action_id
             WHERE ro.project_id = $1
             ORDER BY ro.name, res.name, act.name',
            [$projectId]
        );
        return pg_fetch_all($r) ?: [];
    }

    public function setPermission(int $roleId, int $resourceId, int $actionId, string $scope): void
    {
        $scope = strtolower(trim($scope));
        if (!in_array($scope, ['any', 'own'], true)) {
            throw new InvalidArgumentException('INVALID_SCOPE');
        }

        queryParams(
            $this->connection,
            'INSERT INTO permissions (role_id, resource_id, action_id, scope) VALUES ($1, $2, $3, $4)
             ON CONFLICT (role_id, resource_id, action_id) DO UPDATE SET scope = EXCLUDED.scope',
            [$roleId, $resourceId, $actionId, $scope]
        );
    }

    public function deletePermission(int $roleId, int $resourceId, int $actionId): void
    {
        queryParams(
            $this->connection,
            'DELETE FROM permissions WHERE role_id = $1 AND resource_id = $2 AND action_id = $3',
            [$roleId, $resourceId, $actionId]
        );
    }

    // ==========================================================
    // Bản ghi và chủ sở hữu (Records)
    // ==========================================================
    public function getRecords(int $projectId): array
    {
        $r = queryParams(
            $this->connection,
            'SELECT rec.id, rec.resource_id, rec.record_key, rec.owner_key, rec.description,
                    res.name AS resource_name
             FROM records rec
             JOIN resources res ON res.id = rec.resource_id
             WHERE rec.project_id = $1
             ORDER BY res.name, rec.record_key',
            [$projectId]
        );
        return pg_fetch_all($r) ?: [];
    }

    public function saveRecord(int $projectId, int $resourceId, string $recordKey, ?string $ownerKey, ?string $description): int
    {
        $recordKey = trim($recordKey);
        $ownerKey = ($ownerKey !== null && trim($ownerKey) !== '') ? trim($ownerKey) : null;
        $description = ($description !== null && trim($description) !== '') ? trim($description) : null;

        if ($recordKey === '') {
            throw new InvalidArgumentException('RECORD_KEY_EMPTY');
        }

        $r = queryParams(
            $this->connection,
            'INSERT INTO records (project_id, resource_id, record_key, owner_key, description)
             VALUES ($1, $2, $3, $4, $5)
             ON CONFLICT (project_id, resource_id, record_key) DO UPDATE
             SET owner_key = EXCLUDED.owner_key, description = EXCLUDED.description
             RETURNING id',
            [$projectId, $resourceId, $recordKey, $ownerKey, $description]
        );
        $row = pg_fetch_assoc($r);
        return (int)$row['id'];
    }

    // ==========================================================
    // Tài khoản kiểm thử (Test Accounts)
    // ==========================================================
    public function getAccounts(int $projectId): array
    {
        $r = queryParams(
            $this->connection,
            'SELECT acc.id, acc.label, acc.role_id, acc.owner_key, acc.credential_env,
                    ro.name AS role_name
             FROM test_accounts acc
             LEFT JOIN roles ro ON ro.id = acc.role_id
             WHERE acc.project_id = $1
             ORDER BY acc.id',
            [$projectId]
        );
        return pg_fetch_all($r) ?: [];
    }

    public function saveAccount(int $projectId, string $label, ?int $roleId, ?string $ownerKey, ?string $credentialEnv): int
    {
        $label = trim($label);
        $ownerKey = ($ownerKey !== null && trim($ownerKey) !== '') ? trim($ownerKey) : null;
        $credentialEnv = ($credentialEnv !== null && trim($credentialEnv) !== '') ? trim($credentialEnv) : null;

        if ($label === '') {
            throw new InvalidArgumentException('ACCOUNT_LABEL_EMPTY');
        }

        $r = queryParams(
            $this->connection,
            'INSERT INTO test_accounts (project_id, label, role_id, owner_key, credential_env)
             VALUES ($1, $2, $3, $4, $5)
             ON CONFLICT (project_id, label) DO UPDATE
             SET role_id = EXCLUDED.role_id, owner_key = EXCLUDED.owner_key, credential_env = EXCLUDED.credential_env
             RETURNING id',
            [$projectId, $label, $roleId > 0 ? $roleId : null, $ownerKey, $credentialEnv]
        );
        $row = pg_fetch_assoc($r);
        return (int)$row['id'];
    }
}
