<?php

declare(strict_types=1);

final class AuthenticationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?int $userId = null,
        public readonly string $message = ''
    ) {
    }
}
