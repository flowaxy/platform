<?php

declare(strict_types=1);

final class AdminAuthorizationService
{
    public function __construct(private readonly AdminRoleRepositoryInterface $roles)
    {
    }

    public function userHasPermission(int $userId, string $permission): bool
    {
        return $this->roles->userHasPermission($userId, $permission);
    }

    public function userHasRole(int $userId, string $roleSlug): bool
    {
        return $this->roles->userHasRole($userId, $roleSlug);
    }

    /**
     * @return string[]
     */
    public function getUserPermissions(int $userId): array
    {
        return $this->roles->getPermissionsForUser($userId);
    }

    /**
     * @return AdminRole[]
     */
    public function getUserRoles(int $userId): array
    {
        return $this->roles->getRolesForUser($userId);
    }

    public function assignRole(int $userId, int $roleId): bool
    {
        return $this->roles->assignRole($userId, $roleId);
    }

    public function removeRole(int $userId, int $roleId): bool
    {
        return $this->roles->removeRole($userId, $roleId);
    }
}
