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
        if (function_exists('logDebug')) {
            logDebug('PluginLifecycleService::install: Installing plugin', ['slug' => $slug]);
        }

        if ($slug === '' || ! $this->filesystem->exists($slug)) {
            if (function_exists('logWarning')) {
                logWarning('PluginLifecycleService::install: Plugin not found or invalid slug', ['slug' => $slug]);
            }
            return false;
        }

        $config = $this->filesystem->readConfig($slug);
        if ($config === null) {
            if (function_exists('logError')) {
                logError('PluginLifecycleService::install: Failed to read plugin config', ['slug' => $slug]);
            }
            return false;
        }

        $result = $this->installer->execute($slug, $config, function () use ($slug) {
            $this->filesystem->runMigrations($slug);
        });

        if ($result) {
            $this->cache->afterInstall($slug);
            hook_dispatch('plugin_installed', $slug);
            if (function_exists('logInfo')) {
                logInfo('PluginLifecycleService::install: Plugin installed successfully', ['slug' => $slug]);
            }
        } else {
            if (function_exists('logError')) {
                logError('PluginLifecycleService::install: Failed to install plugin', ['slug' => $slug]);
            }
        }

        return $result;
    }

    public function activate(string $slug): bool
    {
        if (function_exists('logDebug')) {
            logDebug('PluginLifecycleService::activate: Activating plugin', ['slug' => $slug]);
        }

        if ($slug === '') {
            if (function_exists('logWarning')) {
                logWarning('PluginLifecycleService::activate: Invalid slug');
            }
            return false;
        }

        $result = $this->activator->execute($slug);
        if ($result) {
            $this->cache->afterActivate($slug);
            hook_dispatch('plugin_activated', $slug);
            if (function_exists('logInfo')) {
                logInfo('PluginLifecycleService::activate: Plugin activated successfully', ['slug' => $slug]);
            }
        } else {
            if (function_exists('logError')) {
                logError('PluginLifecycleService::activate: Failed to activate plugin', ['slug' => $slug]);
            }
        }

        return $result;
    }

    public function deactivate(string $slug): bool
    {
        if (function_exists('logDebug')) {
            logDebug('PluginLifecycleService::deactivate: Deactivating plugin', ['slug' => $slug]);
        }

        if ($slug === '') {
            if (function_exists('logWarning')) {
                logWarning('PluginLifecycleService::deactivate: Invalid slug');
            }
            return false;
        }

        $result = $this->deactivator->execute($slug);
        if ($result) {
            $this->cache->afterDeactivate($slug);
            hook_dispatch('plugin_deactivated', $slug);
            if (function_exists('logInfo')) {
                logInfo('PluginLifecycleService::deactivate: Plugin deactivated successfully', ['slug' => $slug]);
            }
        } else {
            if (function_exists('logError')) {
                logError('PluginLifecycleService::deactivate: Failed to deactivate plugin', ['slug' => $slug]);
            }
        }

        return $result;
    }

    public function uninstall(string $slug): bool
    {
        if (function_exists('logDebug')) {
            logDebug('PluginLifecycleService::uninstall: Uninstalling plugin', ['slug' => $slug]);
        }

        if ($slug === '') {
            if (function_exists('logWarning')) {
                logWarning('PluginLifecycleService::uninstall: Invalid slug');
            }
            return false;
        }

        $result = $this->uninstaller->execute($slug, function () use ($slug) {
            $this->filesystem->delete($slug);
        });

        if ($result) {
            $this->cache->afterUninstall($slug);
            hook_dispatch('plugin_uninstalled', $slug);
            if (function_exists('logInfo')) {
                logInfo('PluginLifecycleService::uninstall: Plugin uninstalled successfully', ['slug' => $slug]);
            }
        } else {
            if (function_exists('logError')) {
                logError('PluginLifecycleService::uninstall: Failed to uninstall plugin', ['slug' => $slug]);
            }
        }

        return $result;
    }
}
