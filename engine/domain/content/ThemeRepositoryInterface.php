<?php

declare(strict_types=1);

interface ThemeRepositoryInterface
{
    /**
     * @return array<int, ThemeEntity>
     */
    public function all(): array;

    public function find(string $slug): ?ThemeEntity;

    public function activate(string $slug): bool;

    public function deactivate(string $slug): bool;

    public function getActive(): ?ThemeEntity;
}
