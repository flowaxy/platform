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
            if (function_exists('logWarning')) {
                logWarning('LogoutAdminUserService::execute: Invalid user ID', ['user_id' => $userId]);
            }
            return false;
        }

        if (function_exists('logDebug')) {
            logDebug('LogoutAdminUserService::execute: Logging out user', ['user_id' => $userId]);
        }

        $result = $this->users->clearSession($userId);
        if ($result && function_exists('hook_dispatch')) {
            hook_dispatch('admin.logout', ['user_id' => $userId]);
        }

        if ($result && function_exists('logInfo')) {
            logInfo('LogoutAdminUserService::execute: User logged out successfully', ['user_id' => $userId]);
        } elseif (!$result && function_exists('logError')) {
            logError('LogoutAdminUserService::execute: Failed to logout user', ['user_id' => $userId]);
        }

        return $result;
    }
}
