<?php

declare(strict_types=1);

final class InstallPluginService
{
    public function __construct(private readonly PluginRepositoryInterface $plugins)
    {
    }

    /**
     * @param string $slug
     * @param array<string, mixed> $config
     * @param callable|null $afterInstall
     * @return bool
     */
    public function execute(string $slug, array $config, ?callable $afterInstall = null): bool
    {
        if (function_exists('logDebug')) {
            logDebug('InstallPluginService::execute: Installing plugin', [
                'slug' => $slug,
                'name' => $config['name'] ?? ucfirst($slug),
                'version' => $config['version'] ?? '1.0.0',
            ]);
        }

        if ($slug === '') {
            if (function_exists('logWarning')) {
                logWarning('InstallPluginService::execute: Invalid slug');
            }
            return false;
        }

        $plugin = new Plugin(
            slug: $slug,
            name: $config['name'] ?? ucfirst($slug),
            version: $config['version'] ?? '1.0.0',
            active: false,
            description: $config['description'] ?? '',
            author: $config['author'] ?? '',
            meta: $config
        );

        $result = $this->plugins->install($plugin);

        if ($result && $afterInstall !== null) {
            try {
                $afterInstall($plugin);
                if (function_exists('logDebug')) {
                    logDebug('InstallPluginService::execute: After install callback executed', ['slug' => $slug]);
                }
            } catch (\Exception $e) {
                if (function_exists('logError')) {
                    logError('InstallPluginService::execute: After install callback error', [
                        'slug' => $slug,
                        'error' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                }
            }
        }

        if ($result && function_exists('logInfo')) {
            logInfo('InstallPluginService::execute: Plugin installed successfully', [
                'slug' => $slug,
                'name' => $plugin->name,
            ]);
        } elseif (!$result && function_exists('logError')) {
            logError('InstallPluginService::execute: Failed to install plugin', ['slug' => $slug]);
        }

        return $result;
    }
}
