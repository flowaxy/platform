<?php

/**
 * Менеджер ролей та прав доступу
 *
 * @package Engine\Classes\Managers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class RoleManager
{
    private static ?self $instance = null;
    private ?PDO $db = null;
    /**
     * @var array<int, array<int, array<string, mixed>>>
     */
    private array $userRolesCache = [];
    /**
     * @var array<int, array<int, string>>
     */
    private array $userPermissionsCache = [];
    /**
     * @var array<int, array<int, string>>
     */
    private array $rolePermissionsCache = [];

    private function __construct()
    {
        $this->db = DatabaseHelper::getConnection();
    }

    /**
     * Отримання екземпляра (Singleton)
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Перевірка наявності дозволу у користувача
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        // Перевіряємо кеш
        if (isset($this->userPermissionsCache[$userId])) {
            return in_array($permission, $this->userPermissionsCache[$userId]);
        }

        // Завантажуємо всі дозволи користувача
        $permissions = $this->getUserPermissions($userId);
        $this->userPermissionsCache[$userId] = $permissions;

        return in_array($permission, $permissions);
    }

    /**
     * Перевірка наявності ролі у користувача
     */
    public function hasRole(int $userId, string $roleSlug): bool
    {
        $roles = $this->getUserRoles($userId);
        foreach ($roles as $role) {
            if ($role['slug'] === $roleSlug) {
                return true;
            }
        }

        return false;
    }

    /**
     * Отримання всіх дозволів користувача
     *
     * @return array<int, string>
     */
    public function getUserPermissions(int $userId): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            // Отримуємо role_ids з users
            $stmt = $this->db->prepare('SELECT role_ids FROM users WHERE id = ?');
            if ($stmt === false) {
                return [];
            }
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (! $user || empty($user['role_ids'])) {
                return [];
            }

            $roleIds = json_decode($user['role_ids'], true);
            if (! is_array($roleIds) || empty($roleIds)) {
                return [];
            }

            // Отримуємо дозволи для всіх ролей користувача
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $stmt = $this->db->prepare("
                SELECT permissions
                FROM roles
                WHERE id IN ($placeholders)
            ");
            if ($stmt === false) {
                return [];
            }
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
            $result = [];
            if (!empty($allPermissionIds)) {
                $allPermissionIds = array_unique($allPermissionIds);
                $permPlaceholders = implode(',', array_fill(0, count($allPermissionIds), '?'));
                $permStmt = $this->db->prepare("SELECT slug FROM permissions WHERE id IN ($permPlaceholders)");
                $permStmt->execute($allPermissionIds);
                $result = $permStmt->fetchAll(PDO::FETCH_COLUMN);
            }

            return $result ?: [];
        } catch (Exception $e) {
            logger()->logError('RoleManager getUserPermissions error: ' . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }

    /**
     * Отримання всіх ролей користувача
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUserRoles(int $userId): array
    {
        // Перевіряємо кеш
        if (isset($this->userRolesCache[$userId])) {
            return $this->userRolesCache[$userId];
        }

        if ($this->db === null) {
            return [];
        }

        try {
            // Отримуємо role_ids з users
            $stmt = $this->db->prepare('SELECT role_ids FROM users WHERE id = ?');
            if ($stmt === false) {
                return [];
            }
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (! $user || empty($user['role_ids'])) {
                $this->userRolesCache[$userId] = [];

                return [];
            }

            $roleIds = json_decode($user['role_ids'], true);
            if (! is_array($roleIds) || empty($roleIds)) {
                $this->userRolesCache[$userId] = [];

                return [];
            }

            // Отримуємо ролі по ID
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $stmt = $this->db->prepare("
                SELECT id, name, slug, description, is_system
                FROM roles
                WHERE id IN ($placeholders)
                ORDER BY name
            ");
            if ($stmt === false) {
                return [];
            }
            $stmt->execute($roleIds);
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->userRolesCache[$userId] = $roles ?: [];

            return $this->userRolesCache[$userId];
        } catch (Exception $e) {
            logger()->logError('RoleManager getUserRoles error: ' . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }

    /**
     * Призначення ролі користувачу
     */
    public function assignRole(int $userId, int $roleId): bool
    {
        if ($this->db === null) {
            return false;
        }

        try {
            // Отримуємо поточні role_ids
            $stmt = $this->db->prepare('SELECT role_ids FROM users WHERE id = ?');
            if ($stmt === false) {
                return false;
            }
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $roleIds = [];
            if ($user && ! empty($user['role_ids'])) {
                $roleIds = json_decode($user['role_ids'], true) ?: [];
            }

            // Добавляем новую роль, если её ещё нет
            if (! in_array($roleId, $roleIds)) {
                $roleIds[] = $roleId;
                $roleIdsJson = json_encode($roleIds);

                $stmt = $this->db->prepare('UPDATE users SET role_ids = ? WHERE id = ?');
                if ($stmt === false) {
                    return false;
                }
                $stmt->execute([$roleIdsJson, $userId]);
            }

            // Очищаємо кеш
            unset($this->userRolesCache[$userId]);
            unset($this->userPermissionsCache[$userId]);

            return true;
        } catch (Exception $e) {
            logger()->logError('RoleManager assignRole error: ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Видалення ролі у користувача
     */
    public function removeRole(int $userId, int $roleId): bool
    {
        if ($this->db === null) {
            return false;
        }

        try {
            // Отримуємо поточні role_ids
            $stmt = $this->db->prepare('SELECT role_ids FROM users WHERE id = ?');
            if ($stmt === false) {
                return false;
            }
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (! $user || empty($user['role_ids'])) {
                return true; // Ролей немає, нічого робити не потрібно
            }

            $roleIds = json_decode($user['role_ids'], true) ?: [];

            // Видаляємо роль з масиву
            $roleIds = array_filter($roleIds, function ($id) use ($roleId) {
                return (int)$id !== $roleId;
            });
            $roleIds = array_values($roleIds); // Переіндексуємо масив

            $roleIdsJson = json_encode($roleIds);
            $stmt = $this->db->prepare('UPDATE users SET role_ids = ? WHERE id = ?');
            if ($stmt === false) {
                return false;
            }
            $stmt->execute([$roleIdsJson, $userId]);

            // Очищаємо кеш
            unset($this->userRolesCache[$userId]);
            unset($this->userPermissionsCache[$userId]);

            return true;
        } catch (Exception $e) {
            logger()->logError('RoleManager removeRole error: ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Отримання всіх ролей
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllRoles(): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            $stmt = $this->db->query('
                SELECT id, name, slug, description, is_system, created_at, updated_at
                FROM roles
                ORDER BY name
            ');
            if ($stmt === false) {
                return [];
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            logger()->logError('RoleManager getAllRoles error: ' . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }

    /**
     * Отримання всіх дозволів
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllPermissions(?string $category = null): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            $sql = '
                SELECT id, name, slug, description, category, created_at, updated_at
                FROM permissions
            ';

            if ($category !== null) {
                $sql .= ' WHERE category = ?';
                $stmt = $this->db->prepare($sql);
                if ($stmt === false) {
                    return [];
                }
                $stmt->execute([$category]);
            } else {
                $stmt = $this->db->query($sql);
                if ($stmt === false) {
                    return [];
                }
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            logger()->logError('RoleManager getAllPermissions error: ' . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }

    /**
     * Створення нової ролі
     */
    public function createRole(string $name, string $slug, ?string $description = null, bool $isSystem = false): ?int
    {
        if ($this->db === null) {
            return null;
        }

        try {
            $stmt = $this->db->prepare('
                INSERT INTO roles (name, slug, description, is_system)
                VALUES (?, ?, ?, ?)
            ');
            if ($stmt === false) {
                return null;
            }
            $stmt->execute([$name, $slug, $description, $isSystem ? 1 : 0]);

            return (int)$this->db->lastInsertId();
        } catch (Exception $e) {
            logger()->logError('RoleManager createRole error: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    /**
     * Створення нового дозволу
     */
    public function createPermission(string $name, string $slug, ?string $description = null, ?string $category = null): ?int
    {
        if ($this->db === null) {
            return null;
        }

        try {
            $stmt = $this->db->prepare('
                INSERT INTO permissions (name, slug, description, category)
                VALUES (?, ?, ?, ?)
            ');
            if ($stmt === false) {
                return null;
            }
            $stmt->execute([$name, $slug, $description, $category]);

            return (int)$this->db->lastInsertId();
        } catch (Exception $e) {
            logger()->logError('RoleManager createPermission error: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    /**
     * Призначення дозволу ролі
     */
    public function assignPermissionToRole(int $roleId, int $permissionId): bool
    {
        if ($this->db === null) {
            return false;
        }

        try {
            // Отримуємо поточні права ролі
            $stmt = $this->db->prepare('SELECT permissions FROM roles WHERE id = ?');
            $stmt->execute([$roleId]);
            $permissionsJson = $stmt->fetchColumn();
            $permissionIds = [];
            if (!empty($permissionsJson)) {
                $permissionIds = json_decode($permissionsJson, true) ?: [];
            }
            
            // Додаємо новий дозвіл, якщо його ще немає
            if (!in_array($permissionId, $permissionIds)) {
                $permissionIds[] = $permissionId;
                $permissionsJson = json_encode($permissionIds);
                $stmt = $this->db->prepare('UPDATE roles SET permissions = ? WHERE id = ?');
                $stmt->execute([$permissionsJson, $roleId]);
            }

            // Очищаємо кеш дозволів ролі
            unset($this->rolePermissionsCache[$roleId]);

            return true;
        } catch (Exception $e) {
            logger()->logError('RoleManager assignPermissionToRole error: ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Видалення дозволу у ролі
     */
    public function removePermissionFromRole(int $roleId, int $permissionId): bool
    {
        if ($this->db === null) {
            return false;
        }

        try {
            // Отримуємо поточні права ролі
            $stmt = $this->db->prepare('SELECT permissions FROM roles WHERE id = ?');
            $stmt->execute([$roleId]);
            $permissionsJson = $stmt->fetchColumn();
            $permissionIds = [];
            if (!empty($permissionsJson)) {
                $permissionIds = json_decode($permissionsJson, true) ?: [];
            }
            
            // Видаляємо дозвіл з масиву
            $permissionIds = array_values(array_filter($permissionIds, function($id) use ($permissionId) {
                return $id != $permissionId;
            }));
            
            $permissionsJson = json_encode($permissionIds);
            $stmt = $this->db->prepare('UPDATE roles SET permissions = ? WHERE id = ?');
            $stmt->execute([$permissionsJson, $roleId]);

            // Очищаємо кеш дозволів ролі
            unset($this->rolePermissionsCache[$roleId]);

            return true;
        } catch (Exception $e) {
            logger()->logError('RoleManager removePermissionFromRole error: ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Отримання дозволів ролі
     *
     * @return array<int, string>
     */
    public function getRolePermissions(int $roleId): array
    {
        // Перевіряємо кеш
        if (isset($this->rolePermissionsCache[$roleId])) {
            return $this->rolePermissionsCache[$roleId];
        }

        if ($this->db === null) {
            return [];
        }

        try {
            // Отримуємо права з JSON стовпця
            $stmt = $this->db->prepare('SELECT permissions FROM roles WHERE id = ?');
            $stmt->execute([$roleId]);
            $permissionsJson = $stmt->fetchColumn();
            $permissionIds = [];
            if (!empty($permissionsJson)) {
                $permissionIds = json_decode($permissionsJson, true) ?: [];
            }
            
            if (empty($permissionIds)) {
                return [];
            }
            
            $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
            $stmt = $this->db->prepare("
                SELECT p.id, p.name, p.slug, p.description, p.category
                FROM permissions p
                WHERE p.id IN ($placeholders)
                ORDER BY p.category, p.name
            ");
            if ($stmt === false) {
                return [];
            }
            $stmt->execute([$roleId]);
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->rolePermissionsCache[$roleId] = $permissions ?: [];

            return $this->rolePermissionsCache[$roleId];
        } catch (Exception $e) {
            logger()->logError('RoleManager getRolePermissions error: ' . $e->getMessage(), ['exception' => $e]);

            return [];
        }
    }

    /**
     * Видалення ролі
     */
    public function deleteRole(int $roleId): bool
    {
        if ($this->db === null) {
            return false;
        }

        try {
            // Перевіряємо, чи не системна роль
            $stmt = $this->db->prepare('SELECT is_system, slug FROM roles WHERE id = ?');
            if ($stmt === false) {
                return false;
            }
            $stmt->execute([$roleId]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($role && ! empty($role['is_system'])) {
                return false; // Не можна видаляти системні ролі
            }

            // Додаткова перевірка: роль developer не можна видалити навіть якщо is_system = 0
            if ($role && isset($role['slug']) && $role['slug'] === 'developer') {
                return false; // Роль розробника не можна видалити
            }

            $stmt = $this->db->prepare('DELETE FROM roles WHERE id = ?');
            if ($stmt === false) {
                return false;
            }
            $stmt->execute([$roleId]);

            // Очищаємо кеш
            $this->userRolesCache = [];
            $this->userPermissionsCache = [];
            $this->rolePermissionsCache = [];

            return true;
        } catch (Exception $e) {
            logger()->logError('RoleManager deleteRole error: ' . $e->getMessage(), ['exception' => $e]);

            return false;
        }
    }

    /**
     * Очищення кешу користувача
     */
    public function clearUserCache(?int $userId = null): void
    {
        if ($userId !== null) {
            unset($this->userRolesCache[$userId]);
            unset($this->userPermissionsCache[$userId]);
        } else {
            $this->userRolesCache = [];
            $this->userPermissionsCache = [];
        }
    }

    /**
     * Очищення кешу ролей
     */
    public function clearRoleCache(?int $roleId = null): void
    {
        if ($roleId !== null) {
            unset($this->rolePermissionsCache[$roleId]);
        } else {
            $this->rolePermissionsCache = [];
        }
    }
}
