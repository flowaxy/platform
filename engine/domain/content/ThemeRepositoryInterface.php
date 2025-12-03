<?php

declare(strict_types=1);

interface ThemeRepositoryInterface
{
    /**
     * @return array<int, Theme>
     */
    public function all(): array;

    public function find(string $slug): ?Theme;

    public function activate(string $slug): bool;

    public function deactivate(string $slug): bool;

    public function getActive(): ?Theme;
}
