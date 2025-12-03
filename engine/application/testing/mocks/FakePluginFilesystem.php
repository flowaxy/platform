<?php

declare(strict_types=1);

final class FakePluginFilesystem implements PluginFilesystemInterface
{
    /**
     * @param array<string,array<string,mixed>> $configs
     */
    public function __construct(
        private array $configs = []
    ) {
    }

    public bool $migrated = false;
    public bool $deleted = false;

    public function exists(string $slug): bool
    {
        return isset($this->configs[$slug]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readConfig(string $slug): ?array
    {
        return $this->configs[$slug] ?? null;
    }

    public function runMigrations(string $slug): void
    {
        $this->migrated = true;
    }

    public function delete(string $slug): bool
    {
        $this->deleted = true;
        unset($this->configs[$slug]);

        return true;
    }
}
