<?php

declare(strict_types=1);

final class FakeThemeRepository implements ThemeRepositoryInterface
{
    /** @var array<string,Theme> */
    public array $themes = [];

    public ?string $activeSlug = null;

    /**
     * @return array<int, Theme>
     */
    public function all(): array
    {
        return array_values($this->themes);
    }

    public function find(string $slug): ?Theme
    {
        return $this->themes[$slug] ?? null;
    }

    public function activate(string $slug): bool
    {
        if (! isset($this->themes[$slug])) {
            return false;
        }
        $this->activeSlug = $slug;

        return true;
    }

    public function deactivate(string $slug): bool
    {
        if ($this->activeSlug === $slug) {
            $this->activeSlug = null;
        }

        return true;
    }

    public function getActive(): ?Theme
    {
        return $this->activeSlug ? ($this->themes[$this->activeSlug] ?? null) : null;
    }
}
