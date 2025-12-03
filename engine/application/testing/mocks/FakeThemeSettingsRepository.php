<?php

declare(strict_types=1);

final class FakeThemeSettingsRepository implements ThemeSettingsRepositoryInterface
{
    /** @var array<string,array<string,mixed>> */
    public array $store = [];

    public bool $cacheCleared = false;

    /**
     * @return array<string, mixed>
     */
    public function get(string $themeSlug): array
    {
        return $this->store[$themeSlug] ?? [];
    }

    public function getValue(string $themeSlug, string $key, mixed $default = null): mixed
    {
        return $this->store[$themeSlug][$key] ?? $default;
    }

    public function setValue(string $themeSlug, string $key, mixed $value): bool
    {
        $this->store[$themeSlug][$key] = $value;

        return true;
    }

    /**
     * @param array<string, mixed> $settings
     * @return bool
     */
    public function setMany(string $themeSlug, array $settings): bool
    {
        foreach ($settings as $key => $value) {
            $this->setValue($themeSlug, (string)$key, $value);
        }

        return true;
    }

    public function clearCache(string $themeSlug): void
    {
        $this->cacheCleared = true;
    }
}
