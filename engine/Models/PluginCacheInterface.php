<?php

declare(strict_types=1);

interface PluginCacheInterface
{
    public function afterInstall(string $slug): void;

    public function afterActivate(string $slug): void;

    public function afterDeactivate(string $slug): void;

    public function afterUninstall(string $slug): void;
}
