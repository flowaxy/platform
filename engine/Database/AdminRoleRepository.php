<?php

declare(strict_types=1);

final class AdminRoleRepository implements AdminRoleRepositoryInterface
{
    private ?PDO $connection = null;

    public function __construct()
    {
        try {
            // Використовуємо повний namespace для Database
            if (class_exists(\Flowaxy\Core\Infrastructure\Persistence\Database::class)) {
                $this->connection = \Flowaxy\Core\Infrastructure\Persistence\Database::getInstance()->getConnection();
            } elseif (class_exists('Database')) {
                $this->connection = Database::getInstance()->getConnection();
            } else {
                // Fallback через DatabaseHelper
                $this->connection = DatabaseHelper::getConnection();
            }
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('AdminRoleRepository ctor error: ' . $e->getMessage(), ['exception' => $e]);
            } elseif (function_exists('logger')) {
                logger()->logError('AdminRoleRepository ctor error: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    /**
     * @return array<int, AdminRole>
     */
    public function getRolesForUser(int $userId): array
    {
        if ($this->connection === null) {
            return [];
        }

        $roleIds = $this->getRoleIdsForUser($userId);
        if ($roleIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->connection->prepare("
            SELECT id, name, slug, description, is_system
            FROM roles
            WHERE id IN ($placeholders)
            ORDER BY name
        ");
        $stmt->execute($roleIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $roles = [];
        foreach ($rows as $row) {
            $roles[] = new AdminRole(
                id: (int)$row['id'],
                name: $row['name'],
                slug: $row['slug'],
                description: $row['description'] ?? null,
                isSystem: (bool)$row['is_system'],
                permissions: $this->getRolePermissionSlugs((int)$row['id'])
            );
        }

        return $roles;
    }

    /**
     * @return array<int, string>
     */
    public function getPermissionsForUser(int $userId): array
    {
        if ($this->connection === null) {
            if (function_exists('logDebug')) {
                logDebug("AdminRoleRepository::getPermissionsForUser: Connection is null", ['user_id' => $userId]);
            }
            return [];
        }

        $roleIds = $this->getRoleIdsForUser($userId);
        if (function_exists('logDebug')) {
            logDebug("AdminRoleRepository::getPermissionsForUser: User roles retrieved", [
                'user_id' => $userId,
                'role_ids' => $roleIds,
            ]);
        }

        if ($roleIds === []) {
            if (function_exists('logDebug')) {
                logDebug("AdminRoleRepository::getPermissionsForUser: No roles found for user", ['user_id' => $userId]);
            }
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->connection->prepare("
            SELECT permissions
            FROM roles
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($roleIds);
        $permissionsRows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (function_exists('logDebug')) {
            logDebug("AdminRoleRepository::getPermissionsForUser: Permissions JSON from roles", [
                'user_id' => $userId,
                'permissions_json' => $permissionsRows,
            ]);
        }

        // Збираємо всі ID прав з JSON
        $allPermissionIds = [];
        foreach ($permissionsRows as $permissionsJson) {
            if (!empty($permissionsJson)) {
                $permissionIds = json_decode($permissionsJson, true) ?: [];
                if (function_exists('logDebug')) {
                    logDebug("AdminRoleRepository::getPermissionsForUser: Decoded permission IDs", [
                        'user_id' => $userId,
                        'permission_ids' => $permissionIds,
                    ]);
                }
                $allPermissionIds = array_merge($allPermissionIds, $permissionIds);
            }
        }

        if (function_exists('logDebug')) {
            logDebug("AdminRoleRepository::getPermissionsForUser: All permission IDs collected", [
                'user_id' => $userId,
                'all_permission_ids' => $allPermissionIds,
            ]);
        }

        // Отримуємо slug прав
        $permissions = [];
        if (!empty($allPermissionIds)) {
            $allPermissionIds = array_unique($allPermissionIds);
            $permPlaceholders = implode(',', array_fill(0, count($allPermissionIds), '?'));
            $permStmt = $this->connection->prepare("SELECT slug FROM permissions WHERE id IN ($permPlaceholders)");
            $permStmt->execute($allPermissionIds);
            $permissions = $permStmt->fetchAll(\PDO::FETCH_COLUMN);
        }

        if (function_exists('logDebug')) {
            logDebug("AdminRoleRepository::getPermissionsForUser: Final permissions retrieved", [
                'user_id' => $userId,
                'permissions' => $permissions,
            ]);
        }

        if (!empty($permissions) && function_exists('logInfo')) {
            logInfo("AdminRoleRepository::getPermissionsForUser: Permissions retrieved successfully", [
                'user_id' => $userId,
                'permission_count' => count($permissions),
            ]);
        }

        return array_values(array_filter(array_map('strval', $permissions ?: [])));
    }

    public function userHasRole(int $userId, string $roleSlug): bool
    {
        foreach ($this->getRolesForUser($userId) as $role) {
            if ($role->slug === $roleSlug) {
                return true;
            }
        }

        return false;
    }

    public function userHasPermission(int $userId, string $permission): bool
    {
        $permissions = $this->getPermissionsForUser($userId);
        $hasPermission = in_array($permission, $permissions, true);

        if (function_exists('logDebug')) {
            logDebug("AdminRoleRepository::userHasPermission: Permission check", [
                'user_id' => $userId,
                'permission' => $permission,
                'has_permission' => $hasPermission,
                'all_permissions' => $permissions,
            ]);
        }

        return $hasPermission;
    }

    public function assignRole(int $userId, int $roleId): bool
    {
        if ($this->connection === null) {
            return false;
        }

        $roleIds = $this->getRoleIdsForUser($userId);
        if (in_array($roleId, $roleIds, true)) {
            return true;
        }

        $roleIds[] = $roleId;
        $stmt = $this->connection->prepare('UPDATE users SET role_ids = ? WHERE id = ?');

        return $stmt->execute([json_encode($roleIds), $userId]);
    }

    public function removeRole(int $userId, int $roleId): bool
    {
        if ($this->connection === null) {
            return false;
        }

        $roleIds = array_values(array_filter(
            $this->getRoleIdsForUser($userId),
            static fn (int $id) => $id !== $roleId
        ));

        $stmt = $this->connection->prepare('UPDATE users SET role_ids = ? WHERE id = ?');

        return $stmt->execute([json_encode($roleIds), $userId]);
    }

    /**
     * @return int[]
     */
    /**
     * @return array<int, int>
     */
    private function getRoleIdsForUser(int $userId): array
    {
        if ($this->connection === null) {
            if (function_exists('logDebug')) {
                logDebug("AdminRoleRepository::getRoleIdsForUser: Connection is null", ['user_id' => $userId]);
            }
            return [];
        }

        try {
            if (function_exists('logDebug')) {
                logDebug("AdminRoleRepository::getRoleIdsForUser: Retrieving role IDs", ['user_id' => $userId]);
            }

            $stmt = $this->connection->prepare('SELECT role_ids FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (! $user || empty($user['role_ids'])) {
                if (function_exists('logDebug')) {
                    logDebug("AdminRoleRepository::getRoleIdsForUser: User not found or role_ids is empty", [
                        'user_id' => $userId,
                        'role_ids_value' => $user['role_ids'] ?? 'NULL',
                    ]);
                }
                return [];
            }

            if (function_exists('logDebug')) {
                logDebug("AdminRoleRepository::getRoleIdsForUser: Raw role_ids JSON retrieved", [
                    'user_id' => $userId,
                    'role_ids_json' => $user['role_ids'],
                ]);
            }

            $roleIds = json_decode($user['role_ids'], true);

            if (function_exists('logDebug')) {
                logDebug("AdminRoleRepository::getRoleIdsForUser: Decoded role_ids", [
                    'user_id' => $userId,
                    'role_ids' => $roleIds,
                ]);
            }

            $result = array_values(array_filter(array_map('intval', is_array($roleIds) ? $roleIds : [])));

            if (!empty($result) && function_exists('logInfo')) {
                logInfo("AdminRoleRepository::getRoleIdsForUser: Role IDs retrieved successfully", [
                    'user_id' => $userId,
                    'role_count' => count($result),
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            if (function_exists('logDbError')) {
                logDbError("AdminRoleRepository::getRoleIdsForUser error: " . $e->getMessage(), [
                    'user_id' => $userId,
                    'exception' => $e,
                ]);
            }
            return [];
        }
    }

    /**
     * @return string[]
     */
    /**
     * @return array<int, string>
     */
    private function getRolePermissionSlugs(int $roleId): array
    {
        if ($this->connection === null) {
            return [];
        }

        $stmt = $this->connection->prepare('SELECT permissions FROM roles WHERE id = ?');
        $stmt->execute([$roleId]);
        $permissionsJson = $stmt->fetchColumn();

        $permissionIds = [];
        if (!empty($permissionsJson)) {
            $permissionIds = json_decode($permissionsJson, true) ?: [];
        }

        $slugs = [];
        if (!empty($permissionIds)) {
            $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
            $permStmt = $this->connection->prepare("SELECT slug FROM permissions WHERE id IN ($placeholders)");
            $permStmt->execute($permissionIds);
            $slugs = $permStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        return array_values(array_filter(array_map('strval', $slugs ?: [])));
    }
}
