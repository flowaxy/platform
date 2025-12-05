<?php

declare(strict_types=1);

final class AuthenticateAdminUserService
{
    public function __construct(private readonly AdminUserRepositoryInterface $users)
    {
    }

    public function execute(string $username, string $password): AuthenticationResult
    {
        if ($username === '' || $password === '') {
            if (function_exists('logWarning')) {
                logWarning('AuthenticateAdminUserService::execute: Empty username or password');
            }
            return new AuthenticationResult(false, message: 'Заповніть всі поля');
        }

        if (function_exists('logDebug')) {
            logDebug('AuthenticateAdminUserService::execute: Attempting login', ['username' => $username]);
        }

        $user = $this->users->findByUsername($username);
        if ($user === null) {
            if (function_exists('logWarning')) {
                logWarning('AuthenticateAdminUserService::execute: User not found', ['username' => $username]);
            }
            return new AuthenticationResult(false, message: 'Невірний логін або пароль');
        }

        if (function_exists('logDebug')) {
            logDebug('AuthenticateAdminUserService::execute: User found', [
                'user_id' => $user->id,
                'password_hash_length' => strlen($user->passwordHash ?? ''),
                'password_length' => strlen($password),
            ]);
        }

        $passwordHash = $user->passwordHash ?? '';
        if (empty($passwordHash)) {
            if (function_exists('logError')) {
                logError('AuthenticateAdminUserService::execute: Password hash is empty', [
                    'user_id' => $user->id,
                    'username' => $username,
                ]);
            }
            return new AuthenticationResult(false, message: 'Невірний логін або пароль');
        }

        $verifyResult = password_verify($password, $passwordHash);

        if (!$verifyResult) {
            if (function_exists('logWarning')) {
                logWarning('AuthenticateAdminUserService::execute: Password verification failed', [
                    'user_id' => $user->id,
                    'username' => $username,
                    'hash_prefix' => substr($passwordHash, 0, 7),
                ]);
            }
            return new AuthenticationResult(false, message: 'Невірний логін або пароль');
        }

        if (function_exists('logDebug')) {
            logDebug('AuthenticateAdminUserService::execute: Password verified successfully', [
                'user_id' => $user->id,
                'username' => $username,
            ]);
        }

        // Завжди очищаємо стару сесію перед новою авторизацією
        $this->users->clearSession($user->id);

        $sessionToken = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $this->users->updateSession($user->id, $sessionToken, $now);

        if (function_exists('logInfo')) {
            logInfo('AuthenticateAdminUserService::execute: User authenticated successfully', [
                'user_id' => $user->id,
                'username' => $username,
            ]);
        }

        return new AuthenticationResult(true, userId: $user->id);
    }
}
