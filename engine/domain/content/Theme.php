<?php

declare(strict_types=1);

final class Theme
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $version,
        public readonly string $description,
        public readonly bool $active,
        public readonly bool $supportsCustomization = false,
        public readonly array $meta = []
    ) {
    }
}
