<?php

declare(strict_types=1);

interface PluginLifecycleInterface
{
    public function install(string $slug): bool;

    public function activate(string $slug): bool;

    public function deactivate(string $slug): bool;

    public function uninstall(string $slug): bool;
}
