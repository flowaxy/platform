<?php

declare(strict_types=1);

final class AdminRoleRepository implements AdminRoleRepositoryInterface
{
    private ?PDO $connection = null;

    public function __construct()
    {
        try {
            $this->connection = Database::getInstance()->getConnection();
        } catch (Throwable $e) {
            logger()->logError('AdminRoleRepository ctor error: ' . $e->getMessage(), ['exception' => $e]);
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
            return [];
        }

        $roleIds = $this->getRoleIdsForUser($userId);
        if ($roleIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->connection->prepare("
            SELECT permissions
            FROM roles
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($roleIds);
        $permissionsRows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Збираємо всі ID прав з JSON
        $allPermissionIds = [];
        foreach ($permissionsRows as $permissionsJson) {
            if (!empty($permissionsJson)) {
                $permissionIds = json_decode($permissionsJson, true) ?: [];
                $allPermissionIds = array_merge($allPermissionIds, $permissionIds);
            }
        }
        
        // Отримуємо slug прав
        $permissions = [];
        if (!empty($allPermissionIds)) {
            $allPermissionIds = array_unique($allPermissionIds);
            $permPlaceholders = implode(',', array_fill(0, count($allPermissionIds), '?'));
            $permStmt = $this->connection->prepare("SELECT slug FROM permissions WHERE id IN ($permPlaceholders)");
            $permStmt->execute($allPermissionIds);
            $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);
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
        return in_array($permission, $this->getPermissionsForUser($userId), true);
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
            return [];
        }

        $stmt = $this->connection->prepare('SELECT role_ids FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $user || empty($user['role_ids'])) {
            return [];
        }

        $roleIds = json_decode($user['role_ids'], true);

        return array_values(array_filter(array_map('intval', is_array($roleIds) ? $roleIds : [])));
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
