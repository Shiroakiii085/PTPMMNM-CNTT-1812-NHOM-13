<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';

final class Auth
{
    public static function getCurrentUsername(): string
    {
        if (isset($_SESSION['auth_user']) && is_string($_SESSION['auth_user']) && $_SESSION['auth_user'] !== '') {
            return $_SESSION['auth_user'];
        }
        // Mặc định tài khoản demo ban đầu là phamle
        $_SESSION['auth_user'] = 'phamle';
        return 'phamle';
    }

    public static function login(string $username, string $password): bool
    {
        $db = db();
        $r = queryParams($db, 'SELECT username, password_hash, display_name FROM app_users WHERE username = $1', [trim($username)]);
        $user = pg_fetch_assoc($r);
        if (!$user) {
            return false;
        }

        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['auth_user'] = $user['username'];
            $_SESSION['auth_display_name'] = $user['display_name'];
            return true;
        }

        return false;
    }

    public static function switchUser(string $username): void
    {
        $db = db();
        $r = queryParams($db, 'SELECT username, display_name FROM app_users WHERE username = $1', [trim($username)]);
        $user = pg_fetch_assoc($r);
        if ($user) {
            $_SESSION['auth_user'] = $user['username'];
            $_SESSION['auth_display_name'] = $user['display_name'];
        }
    }

    public static function listUsers(): array
    {
        $db = db();
        $r = queryParams($db, 'SELECT username, display_name FROM app_users ORDER BY id', []);
        return pg_fetch_all($r) ?: [];
    }

    public static function getMemberRole(int $projectId, ?string $username = null): ?string
    {
        $username = $username ?? self::getCurrentUsername();
        $db = db();
        $r = queryParams(
            $db,
            'SELECT member_role FROM project_members WHERE project_id = $1 AND username = $2',
            [$projectId, $username]
        );
        $row = pg_fetch_assoc($r);
        return $row ? (string)$row['member_role'] : null;
    }

    public static function canEdit(int $projectId, ?string $username = null): bool
    {
        $role = self::getMemberRole($projectId, $username);
        return in_array($role, ['owner', 'editor'], true);
    }

    public static function isOwner(int $projectId, ?string $username = null): bool
    {
        $role = self::getMemberRole($projectId, $username);
        return $role === 'owner';
    }
}
