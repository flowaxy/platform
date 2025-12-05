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
            return new AuthenticationResult(false, message: 'Заповніть всі поля');
        }

        $user = $this->users->findByUsername($username);
        if ($user === null) {
            return new AuthenticationResult(false, message: 'Невірний логін або пароль');
        }

        if (! password_verify($password, $user->passwordHash)) {
            return new AuthenticationResult(false, message: 'Невірний логін або пароль');
        }

        // Завжди очищаємо стару сесію перед новою авторизацією
        $this->users->clearSession($user->id);

        $sessionToken = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $this->users->updateSession($user->id, $sessionToken, $now);

        return new AuthenticationResult(true, userId: $user->id);
    }
}
