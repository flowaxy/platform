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
        if ($themeSlug === '' || empty($settings)) {
            return false;
        }

        $theme = $this->themes->find($themeSlug);
        if ($theme === null) {
            return false;
        }

        $valid = array_filter(
            $settings,
            static fn ($key) => $key !== '',
            ARRAY_FILTER_USE_KEY
        );

        if (empty($valid)) {
            return false;
        }

        $result = $this->settings->setMany($themeSlug, $valid);
        if ($result) {
            $this->settings->clearCache($themeSlug);
        }

        return $result;
    }
}
