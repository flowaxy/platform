<?php

declare(strict_types=1);

interface AdminRoleRepositoryInterface
{
    /**
     * @return AdminRole[]
     */
    public function getRolesForUser(int $userId): array;

    /**
     * @return string[]
     */
    public function getPermissionsForUser(int $userId): array;

    public function userHasRole(int $userId, string $roleSlug): bool;

    public function userHasPermission(int $userId, string $permission): bool;

    public function assignRole(int $userId, int $roleId): bool;

    public function removeRole(int $userId, int $roleId): bool;
}
