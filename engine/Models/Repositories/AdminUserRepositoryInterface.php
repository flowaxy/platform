<?php

declare(strict_types=1);

interface AdminUserRepositoryInterface
{
    public function findByUsername(string $username): ?AdminUser;

    public function findById(int $id): ?AdminUser;

    public function updateSession(int $userId, string $token, string $lastActivity): bool;

    public function clearSession(int $userId): bool;

    public function markInactive(int $userId): bool;

    public function updateLastActivity(int $userId, string $timestamp): bool;
}
