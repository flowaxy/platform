<?php

/**
 * Приклад події: Реєстрація користувача
 * 
 * @package Engine\System\Events\Examples
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/Event.php';

final class UserRegisteredEvent extends Event
{
    private int $userId;
    private string $email;
    private string $username;

    public function __construct(int $userId, string $email, string $username)
    {
        parent::__construct([
            'user_id' => $userId,
            'email' => $email,
            'username' => $username,
        ]);

        $this->userId = $userId;
        $this->email = $email;
        $this->username = $username;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUsername(): string
    {
        return $this->username;
    }
}

