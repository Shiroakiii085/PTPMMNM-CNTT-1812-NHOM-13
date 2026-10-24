<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Policy
{
    /**
     * Quy tắc phân quyền cốt lõi (Yêu cầu kỹ thuật 1).
     * Mặc định là DENY nếu không có quyền khai báo hoặc tài khoản ẩn danh.
     *
     * @param string|null $role Tên vai trò (admin, member, auditor, hoặc null cho anon)
     * @param string $resource Tên tài nguyên (profile, notes, announcements, users)
     * @param string $action Hành động (read, create, update, delete)
     * @param string|null $scope Phạm vi quyền ('any', 'own', hoặc null nếu chưa khai báo)
     * @param bool $ownerMatch Có phải chủ sở hữu tài nguyên không
     * @return string 'ALLOW' hoặc 'DENY'
     */
    public static function decide(
        ?string $role,
        string $resource,
        string $action,
        ?string $scope = null,
        bool $ownerMatch = false
    ): string {
        // 1. Tài khoản ẩn danh (không có role) luôn bị từ chối với tài nguyên yêu cầu xác thực
        if ($role === null || trim($role) === '') {
            return 'DENY';
        }

        // 2. Không có phạm vi khai báo tương đương không có dòng permission -> DENY
        if ($scope === null || trim($scope) === '') {
            return 'DENY';
        }

        $scope = strtolower(trim($scope));
        if ($scope === 'any') {
            return 'ALLOW';
        }

        if ($scope === 'own') {
            return $ownerMatch ? 'ALLOW' : 'DENY';
        }

        // Mặc định luôn DENY với bất kỳ cấu hình không nhận diện được
        return 'DENY';
    }

    /**
     * Tra cứu quyền từ danh sách permissions đã nạp.
     * Thiếu dòng khai báo = DENY (Yêu cầu 1).
     *
     * @param string|null $role
     * @param string $resource
     * @param string $action
     * @param bool $ownerMatch
     * @param array<int, array{role_name: string, resource_name: string, action_name: string, scope: string}> $permissions
     * @return string 'ALLOW' hoặc 'DENY'
     */
    public static function evaluateFromPermissions(
        ?string $role,
        string $resource,
        string $action,
        bool $ownerMatch,
        array $permissions
    ): string {
        if ($role === null) {
            return 'DENY';
        }

        foreach ($permissions as $p) {
            if (
                strcasecmp($p['role_name'] ?? '', $role) === 0 &&
                strcasecmp($p['resource_name'] ?? '', $resource) === 0 &&
                strcasecmp($p['action_name'] ?? '', $action) === 0
            ) {
                return self::decide($role, $resource, $action, $p['scope'] ?? 'deny', $ownerMatch);
            }
        }

        return 'DENY';
    }

    /**
     * Tính toán quyền kỳ vọng tự động từ CSDL cho một ca kiểm thử.
     */
    public static function computeExpectedForCase(PgSql\Connection $db, array $case): array
    {
        $roleName = $case['role_name'] ?? null;
        $accountOwner = $case['owner_key'] ?? null;
        $targetOwner = $case['target_owner'] ?? null;
        $resourceName = $case['resource_name'] ?? '';
        $actionName = $case['action_name'] ?? '';
        $projectId = (int)($case['project_id'] ?? 1);

        // Lấy chủ sở hữu từ bảng records nếu chưa có target_owner trực tiếp
        if (empty($targetOwner) && !empty($case['record_id'])) {
            $r = queryParams($db, 'SELECT owner_key FROM records WHERE id = $1', [(int)$case['record_id']]);
            $row = pg_fetch_assoc($r);
            if ($row) {
                $targetOwner = $row['owner_key'];
            }
        }

        $ownerMatch = false;
        if (!empty($accountOwner) && !empty($targetOwner)) {
            $ownerMatch = (strcasecmp((string)$accountOwner, (string)$targetOwner) === 0);
        }

        // Truy vấn permission trực tiếp từ CSDL
        $scope = null;
        if (!empty($roleName)) {
            $permRes = queryParams(
                $db,
                'SELECT p.scope
                 FROM permissions p
                 JOIN roles ro ON ro.id = p.role_id
                 JOIN resources res ON res.id = p.resource_id
                 JOIN actions act ON act.id = p.action_id
                 WHERE ro.project_id = $1 AND ro.name = $2 AND res.name = $3 AND act.name = $4',
                [$projectId, $roleName, $resourceName, $actionName]
            );
            $pRow = pg_fetch_assoc($permRes);
            if ($pRow) {
                $scope = (string)$pRow['scope'];
            }
        }

        $expected = self::decide($roleName, $resourceName, $actionName, $scope, $ownerMatch);

        return [
            'expected' => $expected,
            'scope' => $scope,
            'owner_match' => $ownerMatch,
            'target_owner' => $targetOwner,
        ];
    }
}
