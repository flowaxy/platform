<?php

declare(strict_types=1);

interface PluginRepositoryInterface
{
    /**
     * @return array<int, Plugin>
     */
    public function all(): array;

    public function find(string $slug): ?Plugin;

    public function install(Plugin $plugin): bool;

    public function uninstall(string $slug): bool;

    public function activate(string $slug): bool;

    public function deactivate(string $slug): bool;

    /**
     * @return array<string, mixed>
     */
    public function getSettings(string $slug): array;

    public function setSetting(string $slug, string $key, mixed $value): bool;
}
