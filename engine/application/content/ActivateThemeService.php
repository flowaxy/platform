<?php

declare(strict_types=1);

final class ActivateThemeService
{
    public function __construct(private readonly ThemeRepositoryInterface $themes)
    {
    }

    public function execute(string $slug): bool
    {
        if (function_exists('logDebug')) {
            logDebug('ActivateThemeService::execute: Activating theme', ['slug' => $slug]);
        }

        if ($slug === '') {
            if (function_exists('logWarning')) {
                logWarning('ActivateThemeService::execute: Invalid slug');
            }
            return false;
        }

        $result = $this->themes->activate($slug);

        if ($result && function_exists('logInfo')) {
            logInfo('ActivateThemeService::execute: Theme activated successfully', ['slug' => $slug]);
        } elseif (!$result && function_exists('logError')) {
            logError('ActivateThemeService::execute: Failed to activate theme', ['slug' => $slug]);
        }

        return $result;
    }
}
