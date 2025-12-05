<?php

declare(strict_types=1);

final class DeactivatePluginService
{
    public function __construct(private readonly PluginRepositoryInterface $plugins)
    {
    }

    public function execute(string $slug): bool
    {
        if (function_exists('logDebug')) {
            logDebug('DeactivatePluginService::execute: Deactivating plugin', ['slug' => $slug]);
        }

        if ($slug === '') {
            if (function_exists('logWarning')) {
                logWarning('DeactivatePluginService::execute: Invalid slug');
            }
            return false;
        }

        $result = $this->plugins->deactivate($slug);

        if ($result && function_exists('logInfo')) {
            logInfo('DeactivatePluginService::execute: Plugin deactivated successfully', ['slug' => $slug]);
        } elseif (!$result && function_exists('logError')) {
            logError('DeactivatePluginService::execute: Failed to deactivate plugin', ['slug' => $slug]);
        }

        return $result;
    }
}
