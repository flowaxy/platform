<?php

declare(strict_types=1);

final class UninstallPluginService
{
    public function __construct(private readonly PluginRepositoryInterface $plugins)
    {
    }

    public function execute(string $slug, ?callable $beforeRemove = null): bool
    {
        if (function_exists('logDebug')) {
            logDebug('UninstallPluginService::execute: Uninstalling plugin', ['slug' => $slug]);
        }

        if ($slug === '') {
            if (function_exists('logWarning')) {
                logWarning('UninstallPluginService::execute: Invalid slug');
            }
            return false;
        }

        if ($beforeRemove !== null) {
            try {
                $beforeRemove($slug);
                if (function_exists('logDebug')) {
                    logDebug('UninstallPluginService::execute: Before remove callback executed', ['slug' => $slug]);
                }
            } catch (\Exception $e) {
                if (function_exists('logError')) {
                    logError('UninstallPluginService::execute: Before remove callback error', [
                        'slug' => $slug,
                        'error' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                }
            }
        }

        $result = $this->plugins->uninstall($slug);

        if ($result && function_exists('logInfo')) {
            logInfo('UninstallPluginService::execute: Plugin uninstalled successfully', ['slug' => $slug]);
        } elseif (!$result && function_exists('logError')) {
            logError('UninstallPluginService::execute: Failed to uninstall plugin', ['slug' => $slug]);
        }

        return $result;
    }
}
