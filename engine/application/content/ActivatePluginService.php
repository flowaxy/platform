<?php

declare(strict_types=1);

final class ActivatePluginService
{
    public function __construct(private readonly PluginRepositoryInterface $plugins)
    {
    }

    public function execute(string $slug): bool
    {
        if (function_exists('logDebug')) {
            logDebug('ActivatePluginService::execute: Activating plugin', ['slug' => $slug]);
        }

        if ($slug === '') {
            if (function_exists('logWarning')) {
                logWarning('ActivatePluginService::execute: Invalid slug');
            }
            return false;
        }

        $result = $this->plugins->activate($slug);

        if ($result && function_exists('logInfo')) {
            logInfo('ActivatePluginService::execute: Plugin activated successfully', ['slug' => $slug]);
        } elseif (!$result && function_exists('logError')) {
            logError('ActivatePluginService::execute: Failed to activate plugin', ['slug' => $slug]);
        }

        return $result;
    }
}
