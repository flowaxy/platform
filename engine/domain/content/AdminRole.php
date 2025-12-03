<?php

declare(strict_types=1);

final class AdminRole
{
    /**
     * @param array<int, string> $permissions
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly bool $isSystem,
        public readonly array $permissions = []
    ) {
    }
}
