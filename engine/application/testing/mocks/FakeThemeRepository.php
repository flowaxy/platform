<?php

declare(strict_types=1);

final class FakeThemeRepository implements ThemeRepositoryInterface
{
    /** @var array<string,ThemeEntity> */
    public array $themes = [];

    public ?string $activeSlug = null;

    /**
     * @return array<int, ThemeEntity>
     */
    public function all(): array
    {
        return array_values($this->themes);
    }

    public function find(string $slug): ?ThemeEntity
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

    public function getActive(): ?ThemeEntity
    {
        return $this->activeSlug ? ($this->themes[$this->activeSlug] ?? null) : null;
    }
}
