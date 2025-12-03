<?php

declare(strict_types=1);

final class ThemeRepository implements ThemeRepositoryInterface
{
    private ?PDO $connection;
    private string $themesDir;

    public function __construct()
    {
        $database = Database::getInstance();
        $this->connection = $database->getConnection();

        $engineDir = dirname(__DIR__, 2); // engine
        $rootDir = dirname($engineDir);
        $dir = $rootDir . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR;
        $this->themesDir = realpath($dir) ? realpath($dir) . DIRECTORY_SEPARATOR : $dir;
    }

    /**
     * @return array<int, ThemeEntity>
     */
    public function all(): array
    {
        $directories = glob($this->themesDir . '*', GLOB_ONLYDIR) ?: [];
        $themes = [];

        foreach ($directories as $dir) {
            $slug = basename($dir);
            $config = $this->loadThemeConfig($slug);

            $themes[] = new ThemeEntity(
                slug: $config['slug'] ?? $slug,
                name: $config['name'] ?? ucfirst($slug),
                version: $config['version'] ?? '1.0.0',
                description: $config['description'] ?? '',
                active: $this->isActive($slug),
                supportsCustomization: (bool)($config['supports_customization'] ?? false),
                meta: $config
            );
        }

        return $themes;
    }

    public function find(string $slug): ?ThemeEntity
    {
        foreach ($this->all() as $theme) {
            if ($theme->slug === $slug) {
                return $theme;
            }
        }

        return null;
    }

    public function getActive(): ?ThemeEntity
    {
        $slug = $this->fetchActiveSlug();

        return $slug ? $this->find($slug) : null;
    }

    public function activate(string $slug): bool
    {
        if (! $this->themeExists($slug) || $this->connection === null) {
            return false;
        }

        try {
            $stmt = $this->connection->prepare("
                INSERT INTO site_settings (setting_key, setting_value)
                VALUES ('active_theme', ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$slug]);
        } catch (PDOException $e) {
            error_log('ThemeRepository activate error: ' . $e->getMessage());

            return false;
        }

        $this->flushThemeCache($slug);

        return true;
    }

    public function deactivate(string $slug): bool
    {
        if ($this->connection === null) {
            return false;
        }

        try {
            $stmt = $this->connection->prepare("
                UPDATE site_settings
                SET setting_value = NULL
                WHERE setting_key = 'active_theme' AND setting_value = ?
            ");
            $stmt->execute([$slug]);
        } catch (PDOException $e) {
            error_log('ThemeRepository deactivate error: ' . $e->getMessage());

            return false;
        }

        $this->flushThemeCache($slug);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function loadThemeConfig(string $slug): array
    {
        $file = $this->themesDir . $slug . '/theme.json';
        if (! file_exists($file) || ! is_readable($file)) {
            return ['slug' => $slug];
        }

        $contents = @file_get_contents($file);
        if ($contents === false) {
            return ['slug' => $slug];
        }

        $config = json_decode($contents, true);
        if (! is_array($config)) {
            return ['slug' => $slug];
        }

        if (empty($config['slug'])) {
            $config['slug'] = $slug;
        }

        return $config;
    }

    private function themeExists(string $slug): bool
    {
        return is_dir($this->themesDir . $slug);
    }

    private function isActive(string $slug): bool
    {
        $active = $this->fetchActiveSlug();

        return $active !== null && $active === $slug;
    }

    private function fetchActiveSlug(): ?string
    {
        if ($this->connection === null) {
            return null;
        }

        try {
            $stmt = $this->connection->query("SELECT setting_value FROM site_settings WHERE setting_key = 'active_theme' LIMIT 1");
            $value = $stmt ? $stmt->fetchColumn() : false;

            return $value ? (string)$value : null;
        } catch (PDOException $e) {
            error_log('ThemeRepository fetchActiveSlug error: ' . $e->getMessage());

            return null;
        }
    }

    private function flushThemeCache(?string $slug = null): void
    {
        if (function_exists('cache_forget')) {
            cache_forget('active_theme_slug');
            cache_forget('active_theme');
            cache_forget('all_themes_filesystem');
            if ($slug) {
                cache_forget('theme_settings_' . $slug);
                cache_forget('theme_config_' . $slug);
                cache_forget('theme_' . $slug);
            }
        }
    }
}
