<?php

declare(strict_types=1);

interface PluginFilesystemInterface
{
    public function exists(string $slug): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function readConfig(string $slug): ?array;

    public function runMigrations(string $slug): void;

    public function delete(string $slug): bool;
}
