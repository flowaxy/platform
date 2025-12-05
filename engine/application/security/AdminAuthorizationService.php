<?php

declare(strict_types=1);

final class AdminAuthorizationService
{
    public function __construct(private readonly AdminRoleRepositoryInterface $roles)
    {
    }

    public function userHasPermission(int $userId, string $permission): bool
    {
        if (function_exists('logDebug')) {
            logDebug('AdminAuthorizationService::userHasPermission: Checking permission', [
                'user_id' => $userId,
                'permission' => $permission,
            ]);
        }

        $result = $this->roles->userHasPermission($userId, $permission);

        if ($result && function_exists('logDebug')) {
            logDebug('AdminAuthorizationService::userHasPermission: Permission granted', [
                'user_id' => $userId,
                'permission' => $permission,
            ]);
        } elseif (!$result && function_exists('logWarning')) {
            logWarning('AdminAuthorizationService::userHasPermission: Permission denied', [
                'user_id' => $userId,
                'permission' => $permission,
            ]);
        }

        return $result;
    }

    public function userHasRole(int $userId, string $roleSlug): bool
    {
        if (function_exists('logDebug')) {
            logDebug('AdminAuthorizationService::userHasRole: Checking role', [
                'user_id' => $userId,
                'role_slug' => $roleSlug,
            ]);
        }

        $result = $this->roles->userHasRole($userId, $roleSlug);

        if ($result && function_exists('logInfo')) {
            logInfo('AdminAuthorizationService::userHasRole: User has role', [
                'user_id' => $userId,
                'role_slug' => $roleSlug,
            ]);
        }

        return $result;
    }

    /**
     * @return string[]
     */
    public function getUserPermissions(int $userId): array
    {
        if (function_exists('logDebug')) {
            logDebug('AdminAuthorizationService::getUserPermissions: Retrieving user permissions', [
                'user_id' => $userId,
            ]);
        }

        $permissions = $this->roles->getPermissionsForUser($userId);

        if (function_exists('logInfo')) {
            logInfo('AdminAuthorizationService::getUserPermissions: Permissions retrieved', [
                'user_id' => $userId,
                'count' => count($permissions),
            ]);
        }

        return $permissions;
    }

    /**
     * @return AdminRole[]
     */
    public function getUserRoles(int $userId): array
    {
        if (function_exists('logDebug')) {
            logDebug('AdminAuthorizationService::getUserRoles: Retrieving user roles', [
                'user_id' => $userId,
            ]);
        }

        $roles = $this->roles->getRolesForUser($userId);

        if (function_exists('logInfo')) {
            logInfo('AdminAuthorizationService::getUserRoles: Roles retrieved', [
                'user_id' => $userId,
                'count' => count($roles),
            ]);
        }

        return $roles;
    }

    public function assignRole(int $userId, int $roleId): bool
    {
        if (function_exists('logDebug')) {
            logDebug('AdminAuthorizationService::assignRole: Assigning role', [
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }

        $result = $this->roles->assignRole($userId, $roleId);

        if ($result && function_exists('logInfo')) {
            logInfo('AdminAuthorizationService::assignRole: Role assigned successfully', [
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        } elseif (!$result && function_exists('logError')) {
            logError('AdminAuthorizationService::assignRole: Failed to assign role', [
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }

        return $result;
    }

    public function removeRole(int $userId, int $roleId): bool
    {
        if (function_exists('logDebug')) {
            logDebug('AdminAuthorizationService::removeRole: Removing role', [
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }

        $result = $this->roles->removeRole($userId, $roleId);

        if ($result && function_exists('logInfo')) {
            logInfo('AdminAuthorizationService::removeRole: Role removed successfully', [
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        } elseif (!$result && function_exists('logError')) {
            logError('AdminAuthorizationService::removeRole: Failed to remove role', [
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }

        return $result;
    }
}
