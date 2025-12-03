<?php

declare(strict_types=1);

final class LogoutAdminUserService
{
    public function __construct(private readonly AdminUserRepositoryInterface $users)
    {
    }

    public function execute(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $result = $this->users->clearSession($userId);
        if ($result && function_exists('hook_dispatch')) {
            hook_dispatch('admin.logout', ['user_id' => $userId]);
        }

        return $result;
    }
}
