<?php

declare(strict_types=1);

interface ThemeSettingsRepositoryInterface
{
    /**
     * @return array<string, string>
     */
    public function get(string $themeSlug): array;

    public function getValue(string $themeSlug, string $key, mixed $default = null): mixed;

    public function setValue(string $themeSlug, string $key, mixed $value): bool;

    /**
     * @param array<string, mixed> $settings
     * @return bool
     */
    public function setMany(string $themeSlug, array $settings): bool;

    public function clearCache(string $themeSlug): void;
}
