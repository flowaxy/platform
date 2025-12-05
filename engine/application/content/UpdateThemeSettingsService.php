<?php

declare(strict_types=1);

final class UpdateThemeSettingsService
{
    public function __construct(
        private readonly ThemeSettingsRepositoryInterface $settings,
        private readonly ThemeRepositoryInterface $themes
    ) {
    }

    /**
     * @param string $themeSlug
     * @param array<string, mixed> $settings
     * @return bool
     */
    public function execute(string $themeSlug, array $settings): bool
    {
        if (function_exists('logDebug')) {
            logDebug('UpdateThemeSettingsService::execute: Updating theme settings', [
                'theme_slug' => $themeSlug,
                'settings_count' => count($settings),
            ]);
        }

        if ($themeSlug === '' || empty($settings)) {
            if (function_exists('logWarning')) {
                logWarning('UpdateThemeSettingsService::execute: Invalid theme slug or empty settings', [
                    'theme_slug' => $themeSlug,
                ]);
            }
            return false;
        }

        $theme = $this->themes->find($themeSlug);
        if ($theme === null) {
            if (function_exists('logError')) {
                logError('UpdateThemeSettingsService::execute: Theme not found', ['theme_slug' => $themeSlug]);
            }
            return false;
        }

        $valid = array_filter(
            $settings,
            static fn ($key) => $key !== '',
            ARRAY_FILTER_USE_KEY
        );

        if (empty($valid)) {
            if (function_exists('logWarning')) {
                logWarning('UpdateThemeSettingsService::execute: No valid settings after filtering', [
                    'theme_slug' => $themeSlug,
                ]);
            }
            return false;
        }

        $result = $this->settings->setMany($themeSlug, $valid);
        if ($result) {
            $this->settings->clearCache($themeSlug);
            if (function_exists('logInfo')) {
                logInfo('UpdateThemeSettingsService::execute: Theme settings updated successfully', [
                    'theme_slug' => $themeSlug,
                    'settings_count' => count($valid),
                ]);
            }
        } else {
            if (function_exists('logError')) {
                logError('UpdateThemeSettingsService::execute: Failed to update theme settings', [
                    'theme_slug' => $themeSlug,
                ]);
            }
        }

        return $result;
    }
}
