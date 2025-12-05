<?php

declare(strict_types=1);

final class Plugin
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $version,
        public readonly bool $active,
        public readonly string $description = '',
        public readonly string $author = '',
        public readonly array $meta = []
    ) {
    }
}
