<?php

declare(strict_types=1);

final class FakeAdminUserRepository implements AdminUserRepositoryInterface
{
    /** @var array<int,AdminUser> */
    private array $usersById = [];

    /** @var array<string,int> */
    private array $usernames = [];

    /** @var array<int,array{token:string,last_activity:string}> */
    public array $sessions = [];

    /** @var array<int,bool> */
    public array $cleared = [];

    public function addUser(AdminUser $user): void
    {
        $this->usersById[$user->id] = $user;
        $this->usernames[$user->username] = $user->id;
    }

    public function findByUsername(string $username): ?AdminUser
    {
        $id = $this->usernames[$username] ?? null;

        return $id ? ($this->usersById[$id] ?? null) : null;
    }

    public function findById(int $id): ?AdminUser
    {
        return $this->usersById[$id] ?? null;
    }

    public function updateSession(int $userId, string $token, string $lastActivity): bool
    {
        $this->sessions[$userId] = [
            'token' => $token,
            'last_activity' => $lastActivity,
        ];

        return true;
    }

    public function clearSession(int $userId): bool
    {
        $this->cleared[$userId] = true;
        unset($this->sessions[$userId]);

        return true;
    }

    public function markInactive(int $userId): bool
    {
        return true;
    }

    public function updateLastActivity(int $userId, string $timestamp): bool
    {
        if (isset($this->sessions[$userId])) {
            $this->sessions[$userId]['last_activity'] = $timestamp;
        }

        return true;
    }
}
