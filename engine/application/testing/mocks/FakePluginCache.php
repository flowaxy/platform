<?php

declare(strict_types=1);

final class FakePluginCache implements PluginCacheInterface
{
    /** @var array<string,array<int,string>> */
    public array $events = [
        'plugin_installed' => [],
        'plugin_activated' => [],
        'plugin_deactivated' => [],
        'plugin_uninstalled' => [],
    ];

    public function afterInstall(string $slug): void
    {
        $this->events['plugin_installed'][] = $slug;
    }

    public function afterActivate(string $slug): void
    {
        $this->events['plugin_activated'][] = $slug;
    }

    public function afterDeactivate(string $slug): void
    {
        $this->events['plugin_deactivated'][] = $slug;
    }

    public function afterUninstall(string $slug): void
    {
        $this->events['plugin_uninstalled'][] = $slug;
    }
}
