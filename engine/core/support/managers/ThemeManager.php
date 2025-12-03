<?php

/**
 * Модуль управління темами
 * Управління темами та їх налаштуваннями
 *
 * @package Engine\Modules
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class ThemeManager extends BaseModule
{
    private ?array $activeTheme = null;
    private array $themeSettings = [];

    /**
     * Ініціалізація модуля
     */
    protected function init(): void
    {
        $this->loadActiveTheme();
    }

    /**
     * Реєстрація хуків модуля
     */
    public function registerHooks(): void
    {
        // Модуль ThemeManager не реєструє хуки
    }

    /**
     * Отримання інформації про модуль
     *
     * @return array<string, string>
     */
    public function getInfo(): array
    {
        return [
            'name' => 'ThemeManager',
            'title' => 'Менеджер тем',
            'description' => 'Управління темами та їх налаштуваннями',
            'version' => '1.0.0 Alpha prerelease',
            'author' => 'Flowaxy CMS',
        ];
    }

    /**
     * Отримання API методів модуля
     *
     * @return array<string, string>
     */
    public function getApiMethods(): array
    {
        return [
            'getActiveTheme' => 'Отримання активної теми',
            'getAllThemes' => 'Отримання всіх тем',
            'getTheme' => 'Отримання теми за slug',
            'activateTheme' => 'Активація теми',
            'getSetting' => 'Отримання налаштування теми',
            'setSetting' => 'Збереження налаштування теми',
            'supportsCustomization' => 'Перевірка підтримки кастомізації',
            'hasScssSupport' => 'Перевірка підтримки SCSS',
            'compileScss' => 'Компіляція SCSS',
        ];
    }

    /**
     * Завантаження активної теми з кешуванням
     * Отримує активну тему з site_settings, потім завантажує дані з файлової системи
     *
     * @return void
     */
    private function loadActiveTheme(): void
    {
        $db = $this->getDB();
        if ($db === null) {
            return;
        }

        // Використовуємо кешування для активної теми
        $cacheKey = 'active_theme_slug';
        $activeSlug = cache_remember($cacheKey, function () use ($db) {
            if ($db === null) {
                return null;
            }

            try {
                $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'active_theme' LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $slug = $result ? ($result['setting_value'] ?? null) : null;

                // Валідація slug
                if ($slug && ! Validator::validateSlug($slug)) {
                    logger()->logWarning("ThemeManager: Invalid active theme slug from database: {$slug}");

                    return null;
                }

                return $slug;
            } catch (PDOException $e) {
                logger()->logError('ThemeManager loadActiveTheme error: ' . $e->getMessage(), ['exception' => $e]);
                return null;
            }
        }, 60); // Кешуємо на 1 хвилину (менше час для швидкого оновлення)

        if ($activeSlug) {
            // Завантажуємо тему з файлової системи
            $theme = $this->getTheme($activeSlug);
            if ($theme !== null && is_array($theme)) {
                $this->activeTheme = $theme;
                $this->loadThemeSettings($theme['slug']);
                
                // DEBUG: логуємо завантажену тему
                logger()->logDebug('Активну тему завантажено', [
                    'theme' => $activeSlug,
                    'name' => $theme['name'] ?? $activeSlug,
                ]);
            }
        }
    }

    /**
     * Завантаження налаштувань теми з кешуванням
     *
     * @param string $themeSlug Slug теми
     * @return void
     */
    private function loadThemeSettings(string $themeSlug): void
    {
        if (! Validator::validateSlug($themeSlug)) {
            logger()->logWarning("ThemeManager: Invalid theme slug: {$themeSlug}");
            return;
        }

        // Отримуємо збережені налаштування з бази даних
        $cacheKey = 'theme_settings_' . $themeSlug;
        $savedSettings = cache_remember($cacheKey, function () use ($themeSlug) {
            try {
                return $this->getSettingsRepository()->get($themeSlug);
            } catch (Throwable $e) {
                logger()->logError('ThemeManager loadThemeSettings error: ' . $e->getMessage(), ['exception' => $e]);
                return [];
            }
        }, 3600);

        // Отримуємо значення за замовчуванням з конфігурації теми
        $themeConfig = $this->getThemeConfig($themeSlug);
        $defaultSettings = $themeConfig['default_settings'] ?? [];

        // Об'єднуємо: спочатку значення за замовчуванням, потім збережені значення
        $this->themeSettings = array_merge($defaultSettings, $savedSettings);
    }

    /**
     * Отримання активної теми
     *
     * @return array<string, mixed>|null
     */
    public function getActiveTheme(): ?array
    {
        // Якщо активна тема не завантажена, намагаємося завантажити
        if (empty($this->activeTheme)) {
            $this->loadActiveTheme();
        }

        return $this->activeTheme;
    }

    /**
     * Примусова перезавантаження активної теми
     *
     * @return void
     */
    public function reloadActiveTheme(): void
    {
        // Очищаємо кеш
        cache_forget('active_theme_slug');
        cache_forget('active_theme');
        // Перезагружаем
        $this->loadActiveTheme();
    }

    /**
     * Отримання всіх тем (з файлової системи)
     * Автоматично виявляє теми за наявністю theme.json
     *
     * @return array
     */
    public function getAllThemes(): array
    {
        $themesDir = $this->getThemesBaseDir();

        if (! is_dir($themesDir)) {
            logger()->logError("ThemeManager: Themes directory not found: {$themesDir}", ['themesDir' => $themesDir]);
            return [];
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        return cache_remember('all_themes_filesystem', function () use ($themesDir): array {
            $themes = [];
            $directories = glob($themesDir . '*', GLOB_ONLYDIR);

            foreach ($directories as $dir) {
                $themeSlug = basename($dir);
                $configFile = $dir . '/theme.json';

                if (file_exists($configFile) && is_readable($configFile)) {
                    $configContent = @file_get_contents($configFile);
                    if ($configContent === false) {
                        logger()->logWarning("ThemeManager: Cannot read theme.json for theme: {$themeSlug}", ['themeSlug' => $themeSlug]);
                        continue;
                    }

                    $config = json_decode($configContent, true);
                    if ($config && is_array($config)) {
                        if (empty($config['slug'])) {
                            $config['slug'] = $themeSlug;
                        }

                        $isActive = $this->isThemeActive($config['slug'] ?? $themeSlug);

                        $theme = [
                            'slug' => $config['slug'],
                            'name' => $config['name'] ?? $themeSlug,
                            'description' => $config['description'] ?? '',
                            'version' => $config['version'] ?? '1.0.0',
                            'author' => $config['author'] ?? '',
                            'is_active' => $isActive ? 1 : 0,
                            'is_default' => $config['is_default'] ?? false,
                            'screenshot' => $this->getThemeScreenshot($themeSlug),
                            'supports_customization' => $config['supports_customization'] ?? false,
                        ];

                        $themes[$themeSlug] = $theme;
                    } else {
                        logger()->logWarning("ThemeManager: Invalid JSON in theme.json for theme: {$themeSlug}", ['themeSlug' => $themeSlug]);
                    }
                }
            }

            usort($themes, function ($a, $b) {
                if ($a['is_active'] != $b['is_active']) {
                    return $b['is_active'] - $a['is_active'];
                }

                return strcmp($a['name'], $b['name']);
            });

            return array_values($themes);
        }, 300);
    }

    /**
     * Перевірка активності теми з site_settings
     */
    private function isThemeActive(string $themeSlug): bool
    {
        $db = $this->getDB();
        if ($db === null || empty($themeSlug)) {
            return false;
        }

        try {
            $cacheKey = 'active_theme_check_' . md5($themeSlug);

            return cache_remember($cacheKey, function () use ($themeSlug, $db): bool {
                if ($db === null) {
                    return false;
                }

                try {
                    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'active_theme' LIMIT 1");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $activeTheme = $result ? ($result['setting_value'] ?? '') : '';

                    return ! empty($activeTheme) && $activeTheme === $themeSlug;
                } catch (PDOException $e) {
                    logger()->logError('ThemeManager isThemeActive error: ' . $e->getMessage(), ['exception' => $e]);
                    return false;
                }
            }, 60);
        } catch (Exception $e) {
            logger()->logError('ThemeManager isThemeActive error: ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Отримання шляху до скріншота теми
     */
    private function getThemeScreenshot(string $themeSlug): ?string
    {
        $themesDir = $this->getThemesBaseDir();
        $screenshotPath = $themesDir . $themeSlug . '/screenshot.png';

        if (file_exists($screenshotPath)) {
            // Використовуємо UrlHelper для отримання актуального URL з правильним протоколом
            if (class_exists('UrlHelper')) {
                return UrlHelper::site('/themes/' . $themeSlug . '/screenshot.png');
            }
            // Fallback на константу, якщо UrlHelper недоступний
            $siteUrl = defined('SITE_URL') ? SITE_URL : '';

            return $siteUrl . '/themes/' . $themeSlug . '/screenshot.png';
        }

        return null;
    }

    /**
     * Отримання теми за slug
     */
    public function getTheme(string $slug): ?array
    {
        $allThemes = $this->getAllThemes();
        foreach ($allThemes as $theme) {
            if ($theme['slug'] === $slug) {
                return $theme;
            }
        }

        return null;
    }

    /**
     * Активація теми
     */
    public function activateTheme(string $slug): bool
    {
        if (! Validator::validateSlug($slug)) {
            logger()->logError("ThemeManager: Invalid theme slug for activation: {$slug}", ['slug' => $slug]);
            return false;
        }

        try {
            if (function_exists('container')) {
                /** @var ActivateThemeService $service */
                $service = container()->make(ActivateThemeService::class);
            } else {
                $service = new ActivateThemeService(new ThemeRepository());
            }
        } catch (Throwable $e) {
            logger()->logError('ThemeManager activateTheme service error: ' . $e->getMessage(), ['exception' => $e]);
            $service = new ActivateThemeService(new ThemeRepository());
        }

        $result = $service->execute($slug);

        if ($result) {
            $this->clearThemeCache($slug);
            $this->activeTheme = null;
            $this->loadActiveTheme();
        }

        return $result;
    }

    /**
     * Деактивація теми
     *
     * @param string $slug Slug теми
     * @return bool
     */
    public function deactivateTheme(string $slug): bool
    {
        $db = $this->getDB();
        if ($db === null || empty($slug)) {
            return false;
        }

        if (! Validator::validateSlug($slug)) {
            logger()->logError("ThemeManager: Invalid theme slug for deactivation: {$slug}", ['slug' => $slug]);
            return false;
        }

        try {
            // Видаляємо активну тему з site_settings (встановлюємо в NULL)
            $stmt = $db->prepare("
                UPDATE site_settings 
                SET setting_value = NULL 
                WHERE setting_key = 'active_theme' AND setting_value = ?
            ");
            $result = $stmt->execute([$slug]);

            if (! $result) {
                logger()->logError('ThemeManager: Failed to deactivate theme in database', ['slug' => $slug]);
                return false;
            }

            // Очищаємо кеш
            $this->clearThemeCache($slug);
            cache_forget('active_theme_slug');
            cache_forget('active_theme');

            // Очищаємо активну тему з пам'яті
            $this->activeTheme = null;

            return true;
        } catch (Exception $e) {
            logger()->logError('ThemeManager deactivateTheme error: ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Ініціалізація налаштувань за замовчуванням для теми
     */
    private function initializeDefaultSettings(string $themeSlug): bool
    {
        $db = $this->getDB();
        if ($db === null || empty($themeSlug)) {
            return false;
        }

        if (! Validator::validateSlug($themeSlug)) {
            logger()->logError("ThemeManager: Invalid theme slug for default settings: {$themeSlug}", ['themeSlug' => $themeSlug]);
            return false;
        }

        try {
            $themeConfig = $this->getThemeConfig($themeSlug);
            $defaultSettings = $themeConfig['default_settings'] ?? [];

            if (empty($defaultSettings) || ! is_array($defaultSettings)) {
                return true;
            }

            $stmt = $db->prepare('SELECT COUNT(*) as count FROM theme_settings WHERE theme_slug = ?');
            $stmt->execute([$themeSlug]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (isset($result['count']) && (int)$result['count'] === 0) {
                Database::getInstance()->transaction(function (PDO $db) use ($themeSlug, $defaultSettings): void {
                    foreach ($defaultSettings as $key => $value) {
                        if (empty($key) || ! Validator::validateString($key, 1, 255)) {
                            logger()->logWarning("ThemeManager: Invalid default setting key: {$key}", ['key' => $key]);
                            continue;
                        }

                        $valueStr = is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                        $stmt = $db->prepare('
                            INSERT INTO theme_settings (theme_slug, setting_key, setting_value) 
                            VALUES (?, ?, ?)
                        ');
                        $stmt->execute([$themeSlug, $key, $valueStr]);
                    }
                });

                $this->loadThemeSettings($themeSlug);
                cache_forget('theme_settings_' . $themeSlug);
            }

            return true;
        } catch (Exception $e) {
            logger()->logError('ThemeManager initializeDefaultSettings error: ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Отримання налаштування теми
     */
    public function getSetting(string $key, $default = null)
    {
        if (empty($key)) {
            return $default;
        }

        return $this->themeSettings[$key] ?? $default;
    }

    /**
     * Отримання всіх налаштувань теми
     */
    public function getSettings(): array
    {
        return $this->themeSettings;
    }

    /**
     * Отримання налаштувань теми за slug
     *
     * @param string $themeSlug Slug теми
     * @return array Масив налаштувань теми
     */
    /**
     * @param string $themeSlug
     * @return array<string, mixed>
     */
    public function getThemeSettings(string $themeSlug): array
    {
        if (! Validator::validateSlug($themeSlug)) {
            logger()->logError("ThemeManager: Invalid theme slug: {$themeSlug}", ['themeSlug' => $themeSlug]);
            return [];
        }

        // Отримуємо налаштування з бази даних
        $cacheKey = 'theme_settings_' . $themeSlug;
        $savedSettings = cache_remember($cacheKey, function () use ($themeSlug) {
            try {
                return $this->getSettingsRepository()->get($themeSlug);
            } catch (Throwable $e) {
                logger()->logError('ThemeManager getThemeSettings error: ' . $e->getMessage(), ['exception' => $e]);
                return [];
            }
        }, 3600);

        // Отримуємо значення за замовчуванням з конфігурації теми
        $themeConfig = $this->getThemeConfig($themeSlug);
        $defaultSettings = $themeConfig['default_settings'] ?? [];

        // Об'єднуємо: спочатку значення за замовчуванням, потім збережені значення
        return array_merge($defaultSettings, $savedSettings);
    }

    /**
     * Збереження налаштування теми
     */
    public function setSetting(string $key, $value): bool
    {
        if ($this->activeTheme === null || empty($key)) {
            return false;
        }

        if (! Validator::validateString($key, 1, 255)) {
            logger()->logError("ThemeManager: Invalid setting key: {$key}", ['key' => $key]);
            return false;
        }

        $themeSlug = $this->activeTheme['slug'];

        try {
            $service = $this->getUpdateSettingsService();
        } catch (Throwable $e) {
            logger()->logError('ThemeManager setSetting service error: ' . $e->getMessage(), ['exception' => $e]);
            $service = new UpdateThemeSettingsService(
                new ThemeSettingsRepository(),
                new ThemeRepository()
            );
        }

        $result = $service->execute($themeSlug, [$key => $value]);

        if ($result) {
            $this->themeSettings[$key] = $value;
        }

        return $result;
    }

    /**
     * Збереження кількох налаштувань теми
     */
    public function setSettings(array $settings): bool
    {
        if ($this->activeTheme === null) {
            return false;
        }

        if (empty($settings)) {
            return false;
        }

        $themeSlug = $this->activeTheme['slug'];
        $filtered = [];
        foreach ($settings as $key => $value) {
            if (is_string($key) && Validator::validateString($key, 1, 255)) {
                $filtered[$key] = $value;
            }
        }

        if (empty($filtered)) {
            return false;
        }

        try {
            $service = $this->getUpdateSettingsService();
        } catch (Throwable $e) {
            logger()->logError('ThemeManager setSettings service error: ' . $e->getMessage(), ['exception' => $e]);
            $service = new UpdateThemeSettingsService(
                new ThemeSettingsRepository(),
                new ThemeRepository()
            );
        }

        $result = $service->execute($themeSlug, $filtered);
        if ($result) {
            $this->themeSettings = $this->getSettingsRepository()->get($themeSlug);
        }

        return $result;
    }

    /**
     * Отримання шляху до теми
     */
    public function getThemePath(?string $themeSlug = null): string
    {
        $theme = $themeSlug ? $this->getTheme($themeSlug) : $this->activeTheme;

        $themesBaseDir = $this->getThemesBaseDir();

        if ($theme === null || ! isset($theme['slug'])) {
            return $themesBaseDir . 'default/';
        }

        $slug = $theme['slug'];
        if (! Validator::validateSlug($slug)) {
            logger()->logError("ThemeManager: Invalid theme slug for path: {$slug}", ['slug' => $slug]);
            return $themesBaseDir . 'default/';
        }

        $path = $themesBaseDir . $slug . '/';

        return file_exists($path) ? $path : $themesBaseDir . 'default/';
    }

    /**
     * Отримання URL теми
     */
    public function getThemeUrl(?string $themeSlug = null): string
    {
        $theme = $themeSlug ? $this->getTheme($themeSlug) : $this->activeTheme;

        // Використовуємо UrlHelper для отримання актуального протоколу з налаштувань
        if (class_exists('UrlHelper')) {
            $protocol = UrlHelper::getProtocol();
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . $host;
        } elseif (function_exists('detectProtocol')) {
            $protocol = detectProtocol();
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . $host;
        } else {
            // Fallback на автоматическое определение
            $protocol = 'http://';
            if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
                (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)) {
                $protocol = 'https://';
            }
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . $host;
        }

        if ($theme === null || ! isset($theme['slug'])) {
            return $baseUrl . '/themes/default/';
        }

        $slug = $theme['slug'];
        if (! Validator::validateSlug($slug)) {
            logger()->logError("ThemeManager: Invalid theme slug for URL: {$slug}", ['slug' => $slug]);

            return $baseUrl . '/themes/default/';
        }

        return $baseUrl . '/themes/' . $slug . '/';
    }

    /**
     * Перевірка існування теми
     */
    public function themeExists(string $slug): bool
    {
        if (empty($slug)) {
            return false;
        }
        $theme = $this->getTheme($slug);

        return $theme !== null;
    }

    /**
     * Валидация структуры темы
     */
    public function validateThemeStructure(string $slug): array
    {
        $errors = [];
        $warnings = [];

        if (! Validator::validateSlug($slug)) {
            $errors[] = "Невірний slug теми: {$slug}";

            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        $themesBaseDir = $this->getThemesBaseDir();
        $themePath = $themesBaseDir . $slug . '/';

        if (! is_dir($themePath)) {
            $errors[] = "Директорія теми не знайдена: {$themePath}";

            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        $requiredFiles = [
            'index.php' => 'Головний шаблон теми',
            'theme.json' => 'Конфігурація теми',
        ];

        foreach ($requiredFiles as $file => $description) {
            if (! file_exists($themePath . $file)) {
                $errors[] = "Відсутній обов'язковий файл: {$file} ({$description})";
            }
        }

        $jsonFile = $themePath . 'theme.json';
        if (file_exists($jsonFile)) {
            try {
                $jsonContent = @file_get_contents($jsonFile);
                if ($jsonContent === false) {
                    $errors[] = 'Неможливо прочитати theme.json';
                } else {
                    $config = json_decode($jsonContent, true);
                    if (! is_array($config)) {
                        $errors[] = 'theme.json повинен містити валідний JSON';
                    } else {
                        $requiredConfigKeys = ['name', 'version', 'slug'];
                        foreach ($requiredConfigKeys as $key) {
                            if (! isset($config[$key])) {
                                $errors[] = "Відсутнє обов'язкове поле в theme.json: {$key}";
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'Помилка завантаження theme.json: ' . $e->getMessage();
            }
        }

        $recommendedFiles = [
            'style.css' => 'Стилі теми',
            'script.js' => 'JavaScript теми',
            'screenshot.png' => 'Скріншот теми',
            'customizer.php' => 'Конфігурація кастомізатора',
        ];

        foreach ($recommendedFiles as $file => $description) {
            if (! file_exists($themePath . $file)) {
                $warnings[] = "Рекомендується додати файл: {$file} ({$description})";
            }
        }

        $customizerFile = $themePath . 'customizer.php';
        if (file_exists($customizerFile)) {
            try {
                $customizerConfig = require $customizerFile;
                if (! is_array($customizerConfig)) {
                    $warnings[] = 'customizer.php повинен повертати масив';
                }
            } catch (Exception $e) {
                $warnings[] = 'Помилка завантаження customizer.php: ' . $e->getMessage();
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Встановлення теми
     */
    public function installTheme(string $slug, string $name, string $description = '', string $version = '1.0.0', string $author = ''): bool
    {
        $db = $this->getDB();
        if ($db === null || empty($slug) || empty($name)) {
            return false;
        }

        if (! Validator::validateSlug($slug)) {
            logger()->logError("ThemeManager: Invalid theme slug for installation: {$slug}", ['slug' => $slug]);
            return false;
        }

        if (! Validator::validateString($name, 1, 255)) {
            logger()->logError("ThemeManager: Invalid theme name: {$name}", ['name' => $name]);
            return false;
        }

        $validation = $this->validateThemeStructure($slug);
        if (! $validation['valid']) {
            logger()->logError("ThemeManager: Theme structure validation failed for {$slug}: " . implode(', ', $validation['errors']), ['slug' => $slug, 'errors' => $validation['errors']]);
            return false;
        }

        if (! empty($validation['warnings'])) {
            logger()->logWarning("ThemeManager: Theme structure warnings for {$slug}: " . implode(', ', $validation['warnings']), ['slug' => $slug, 'warnings' => $validation['warnings']]);
        }

        $themeConfig = $this->getThemeConfig($slug);
        if (! empty($themeConfig)) {
            $name = $themeConfig['name'] ?? $name;
            $description = $themeConfig['description'] ?? $description;
            $version = $themeConfig['version'] ?? $version;
            $author = $themeConfig['author'] ?? $author;
        }

        try {
            $activeTheme = getSetting('active_theme');
            if (empty($activeTheme)) {
                $this->activateTheme($slug);
            }

            cache_forget('all_themes_filesystem');
            cache_forget('theme_' . $slug);

            return true;
        } catch (Exception $e) {
            logger()->logError('ThemeManager installTheme error: ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Завантаження конфігурації теми з theme.json
     */
    public function getThemeConfig(?string $themeSlug = null): array
    {
        $theme = $themeSlug ? $this->getTheme($themeSlug) : $this->activeTheme;

        if ($theme === null) {
            return [
                'name' => 'Default',
                'version' => '1.0.0',
                'description' => '',
                'default_settings' => [],
                'available_settings' => [],
            ];
        }

        $slug = $theme['slug'] ?? 'default';

        if (! Validator::validateSlug($slug)) {
            logger()->logError("ThemeManager: Invalid theme slug for config: {$slug}", ['slug' => $slug]);
            return [
                'name' => $theme['name'] ?? 'Default',
                'version' => $theme['version'] ?? '1.0.0',
                'description' => $theme['description'] ?? '',
                'default_settings' => [],
                'available_settings' => [],
            ];
        }

        $cacheKey = 'theme_config_' . $slug;
        $themesBaseDir = $this->getThemesBaseDir();

        return cache_remember($cacheKey, function () use ($slug, $theme, $themesBaseDir) {
            $jsonFile = $themesBaseDir . $slug . '/theme.json';

            if (file_exists($jsonFile) && is_readable($jsonFile)) {
                try {
                    $jsonContent = @file_get_contents($jsonFile);
                    if ($jsonContent !== false) {
                        $config = json_decode($jsonContent, true);
                        if (is_array($config)) {
                            return $config;
                        }
                    }
                } catch (Exception $e) {
                    logger()->logError("ThemeManager: Error loading theme.json for {$slug}: " . $e->getMessage(), ['exception' => $e, 'slug' => $slug]);
                }
            }

            return [
                'name' => $theme['name'] ?? 'Default',
                'version' => $theme['version'] ?? '1.0.0',
                'description' => $theme['description'] ?? '',
                'default_settings' => [],
                'available_settings' => [],
            ];
        }, 3600);
    }

    /**
     * Перевірка підтримки кастомізації темою
     */
    public function supportsCustomization(?string $themeSlug = null): bool
    {
        $theme = $themeSlug ? $this->getTheme($themeSlug) : $this->activeTheme;
        if (! $theme) {
            return false;
        }

        $themeConfig = $this->getThemeConfig($theme['slug']);
        if (isset($themeConfig['supports_customization'])) {
            return (bool)$themeConfig['supports_customization'];
        }

        $themePath = $this->getThemePath($theme['slug']);

        return file_exists($themePath . 'customizer.php');
    }

    /**
     * Перевірка підтримки навігації темою
     */
    public function supportsNavigation(?string $themeSlug = null): bool
    {
        $theme = $themeSlug ? $this->getTheme($themeSlug) : $this->activeTheme;
        if (! $theme) {
            return false;
        }

        $themeConfig = $this->getThemeConfig($theme['slug']);

        return (bool)($themeConfig['supports_navigation'] ?? false);
    }

    /**
     * Перевірка підтримки SCSS темою
     */
    public function hasScssSupport(?string $themeSlug = null): bool
    {
        $theme = $themeSlug ? $this->getTheme($themeSlug) : $this->activeTheme;
        if (! $theme) {
            return false;
        }

        $themePath = $this->getThemePath($theme['slug']);
        $scssFile = $themePath . 'assets/scss/main.scss';

        return file_exists($scssFile) && is_readable($scssFile);
    }

    /**
     * Компиляция SCSS в CSS для темы
     */
    public function compileScss(?string $themeSlug = null, bool $force = false): bool
    {
        $theme = $themeSlug ? $this->getTheme($themeSlug) : $this->activeTheme;
        if (! $theme) {
            return false;
        }

        $themePath = $this->getThemePath($theme['slug']);

        $compiler = new ScssCompiler($themePath, 'assets/scss/main.scss', 'assets/css/style.css');

        if (! $compiler->hasScssFiles()) {
            return false;
        }

        return $compiler->compile($force);
    }

    /**
     * Отримання URL файлу стилів теми
     */
    public function getStylesheetUrl(?string $themeSlug = null, string $cssFile = 'style.css'): string
    {
        $theme = $themeSlug ? $this->getTheme($themeSlug) : $this->activeTheme;
        if (! $theme) {
            return $this->getThemeUrl() . $cssFile;
        }

        $themePath = $this->getThemePath($theme['slug']);

        if ($this->hasScssSupport($theme['slug'])) {
            try {
                $this->compileScss($theme['slug']);
            } catch (Exception $e) {
                logger()->logWarning('ThemeManager: SCSS compilation failed: ' . $e->getMessage(), ['exception' => $e]);
            }

            $compiledCssFile = $themePath . 'assets/css/style.css';

            if (file_exists($compiledCssFile) && is_readable($compiledCssFile)) {
                return $this->getThemeUrl($theme['slug']) . 'assets/css/style.css';
            }
        }

        $regularCssFile = $themePath . $cssFile;

        if (file_exists($regularCssFile) && is_readable($regularCssFile)) {
            return $this->getThemeUrl($theme['slug']) . $cssFile;
        }

        return $this->getThemeUrl($theme['slug']) . $cssFile;
    }

    /**
     * Очищення кешу теми
     */
    public function clearThemeCache(?string $themeSlug = null): void
    {
        // Всегда очищаем кеш активной темы
        cache_forget('active_theme');
        cache_forget('active_theme_slug');
        cache_forget('all_themes_filesystem');

        if ($themeSlug) {
            cache_forget('theme_settings_' . $themeSlug);
            cache_forget('theme_config_' . $themeSlug);
            cache_forget('theme_' . $themeSlug);
            cache_forget('active_theme_check_' . md5($themeSlug));
        } else {
            // Очищаємо кеш для всіх тем
            $themesDir = $this->getThemesBaseDir();
            if (is_dir($themesDir)) {
                $directories = glob($themesDir . '*', GLOB_ONLYDIR);
                if ($directories !== false) {
                    foreach ($directories as $dir) {
                        $slug = basename($dir);
                        cache_forget('active_theme_check_' . md5($slug));
                        cache_forget('theme_config_' . $slug);
                        cache_forget('theme_settings_' . $slug);
                    }
                }
            }
        }
    }

    private function getSettingsRepository(): ThemeSettingsRepositoryInterface
    {
        if (function_exists('container')) {
            return container()->make(ThemeSettingsRepositoryInterface::class);
        }

        return new ThemeSettingsRepository();
    }

    private function getEngineDir(): string
    {
        static $engineDir = null;
        if ($engineDir === null) {
            $engineDir = dirname(__DIR__, 3);
        }

        return $engineDir;
    }

    private function getRootDir(): string
    {
        static $rootDir = null;
        if ($rootDir === null) {
            $rootDir = dirname($this->getEngineDir());
        }

        return $rootDir;
    }

    private function getThemesBaseDir(): string
    {
        static $themesDir = null;
        if ($themesDir === null) {
            $dir = $this->getRootDir() . DIRECTORY_SEPARATOR . 'themes';
            $real = realpath($dir);
            $themesDir = rtrim($real ?: $dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        return $themesDir;
    }

    private function getUpdateSettingsService(): UpdateThemeSettingsService
    {
        if (function_exists('container')) {
            return container()->make(UpdateThemeSettingsService::class);
        }

        return new UpdateThemeSettingsService(
            new ThemeSettingsRepository(),
            new ThemeRepository()
        );
    }
}

/**
 * Глобальная функция для получения менеджера тем через фасад
 *
 * @return ThemeManager
 */
function themeManager(): ThemeManager
{
    // Завантажуємо фасад Theme явно через шлях до файлу
    $facadeFile = __DIR__ . '/../facades/Theme.php';
    if (file_exists($facadeFile)) {
        // Завантажуємо Facade базовий клас, якщо потрібно
        if (! class_exists('Facade', false)) {
            require_once __DIR__ . '/../facades/Facade.php';
        }
        
        // Перевіряємо, чи Theme вже завантажений і чи це фасад
        $isThemeFacade = false;
        if (class_exists('Theme', false)) {
            try {
                $reflection = new ReflectionClass('Theme');
                $isThemeFacade = $reflection->isSubclassOf('Facade') && $reflection->hasMethod('manager');
            } catch (ReflectionException $e) {
                // Не вдалося перевірити
            }
        }
        
        // Якщо Theme не завантажений або це не фасад, завантажуємо фасад
        if (! $isThemeFacade) {
            require_once $facadeFile;
        }

        // Перевіряємо, чи клас Theme має метод manager
        if (class_exists('Theme') && method_exists('Theme', 'manager')) {
            try {
                return Theme::manager();
            } catch (RuntimeException | Exception | Error $e) {
                // Контейнер не готовий або помилка - використовуємо fallback
            }
        }
    }

    return ThemeManager::getInstance();
}
