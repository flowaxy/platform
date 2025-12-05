<?php

declare(strict_types=1);

final class PluginLifecycleService implements PluginLifecycleInterface
{
    public function __construct(
        private readonly PluginFilesystemInterface $filesystem,
        private readonly PluginCacheInterface $cache,
        private readonly InstallPluginService $installer,
        private readonly ActivatePluginService $activator,
        private readonly DeactivatePluginService $deactivator,
        private readonly UninstallPluginService $uninstaller
    ) {
    }

    public function install(string $slug): bool
    {
        if ($slug === '' || ! $this->filesystem->exists($slug)) {
            return false;
        }

        $config = $this->filesystem->readConfig($slug);
        if ($config === null) {
            return false;
        }

        $result = $this->installer->execute($slug, $config, function () use ($slug) {
            $this->filesystem->runMigrations($slug);
        });

        if ($result) {
            $this->cache->afterInstall($slug);
            hook_dispatch('plugin_installed', $slug);
        }

        return $result;
    }

    public function activate(string $slug): bool
    {
        if ($slug === '') {
            return false;
        }

        $result = $this->activator->execute($slug);
        if ($result) {
            $this->cache->afterActivate($slug);
            hook_dispatch('plugin_activated', $slug);
        }

        return $result;
    }

    public function deactivate(string $slug): bool
    {
        if ($slug === '') {
            return false;
        }

        $result = $this->deactivator->execute($slug);
        if ($result) {
            $this->cache->afterDeactivate($slug);
            hook_dispatch('plugin_deactivated', $slug);
        }

        return $result;
    }

    public function uninstall(string $slug): bool
    {
        if ($slug === '') {
            return false;
        }

        $result = $this->uninstaller->execute($slug, function () use ($slug) {
            $this->filesystem->delete($slug);
        });

        if ($result) {
            $this->cache->afterUninstall($slug);
            hook_dispatch('plugin_uninstalled', $slug);
        }

        return $result;
    }
}
