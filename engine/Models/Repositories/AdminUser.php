<?php

declare(strict_types=1);

final class AdminUser
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $passwordHash,
        public ?string $sessionToken,
        public ?string $lastActivity,
        public bool $isActive
    ) {
    }
}
