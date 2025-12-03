<?php

/**
 * Хелпер для роботи з URL
 *
 * @package Engine\Classes\Helpers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class UrlHelper
{
    /**
     * Отримання протоколу з налаштувань системи
     * Використовує detectProtocol() для отримання актуального протоколу
     *
     * @return string Протокол (http:// або https://)
     */
    public static function getProtocol(): string
    {
        if (function_exists('detectProtocol')) {
            return detectProtocol();
        }

        // Fallback на автоматичне визначення, якщо функція не доступна
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return 'https://';
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            return 'https://';
        }

        $isHttps = (
            (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
            (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        );

        return $isHttps ? 'https://' : 'http://';
    }

    /**
     * Перевірка, чи використовується HTTPS протокол
     *
     * @return bool
     */
    public static function isHttps(): bool
    {
        return self::getProtocol() === 'https://';
    }

    /**
     * Отримання протокол-відносного URL (для уникнення Mixed Content)
     *
     * @param string $path Шлях
     * @return string
     */
    public static function protocolRelative(string $path = ''): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return '//' . $host . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Отримання URL завантажень з правильним протоколом
     *
     * @param string $filePath Шлях до файлу
     * @return string
     */
    public static function uploads(string $filePath = ''): string
    {
        return self::protocolRelative('uploads' . ($filePath ? '/' . ltrim($filePath, '/') : ''));
    }

    /**
     * Конвертація абсолютного URL в протокол-відносний
     *
     * @param string $url URL
     * @return string
     */
    public static function toProtocolRelative(string $url): string
    {
        if (empty($url)) {
            return $url;
        }

        // Якщо URL вже протокол-відносний, повертаємо як є
        if (str_starts_with($url, '//')) {
            return $url;
        }

        // Якщо URL відносний, повертаємо як є
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return $url;
        }

        // Конвертуємо абсолютний URL в протокол-відносний
        $parsed = parse_url($url);
        if ($parsed && isset($parsed['host'])) {
            $path = $parsed['path'] ?? '';
            $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
            $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

            return '//' . $parsed['host'] . $path . $query . $fragment;
        }

        return $url;
    }

    /**
     * Генерація URL адмінки
     *
     * @param string $path Шлях
     * @return string
     */
    public static function admin(string $path = ''): string
    {
        $protocol = self::getProtocol();
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $protocol . $host . '/admin' . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Генерація URL сайту
     *
     * @param string $path Шлях
     * @return string
     */
    public static function site(string $path = ''): string
    {
        $protocol = self::getProtocol();
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $protocol . $host . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Отримання поточного URL
     *
     * @param bool $withQuery Включати query string
     * @return string
     */
    public static function current(bool $withQuery = true): string
    {
        $protocol = self::getProtocol();
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        if (! $withQuery && str_contains($uri, '?')) {
            $uri = strstr($uri, '?', true) ?: $uri;
        }

        return $protocol . $host . $uri;
    }

    /**
     * Отримання базового URL сайту
     *
     * @param string $path Шлях (опціонально)
     * @return string
     */
    public static function base(string $path = ''): string
    {
        $protocol = self::getProtocol();
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $baseUrl = $protocol . $host;
        if (! empty($path)) {
            $baseUrl .= '/' . ltrim($path, '/');
        }

        return $baseUrl;
    }

    /**
     * Генерація URL адмінки з hash-busting через md5_file()
     *
     * @param string $path Шлях до файлу відносно admin/assets
     * @return string URL з версією
     */
    public static function adminAsset(string $path): string
    {
        $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 4);
        $assetPath = $rootDir . '/engine/interface/admin-ui/assets/' . ltrim($path, '/');

        $version = '';
        if (file_exists($assetPath)) {
            $hash = md5_file($assetPath);
            $version = '?v=' . substr($hash, 0, 8);
        }

        return self::admin('assets/' . ltrim($path, '/')) . $version;
    }

    /**
     * Генерація URL активної теми
     *
     * @param string $path Шлях відносно теми (опціонально)
     * @return string URL теми
     */
    public static function theme(string $path = ''): string
    {
        $themeSlug = '';
        
        // Спробуємо отримати slug активної теми
        if (function_exists('themeManager')) {
            $theme = themeManager()->getActiveTheme();
            if ($theme && isset($theme['slug'])) {
                $themeSlug = $theme['slug'];
            }
        }
        
        // Fallback - спробуємо з бази даних
        if (empty($themeSlug) && function_exists('settingsManager')) {
            $themeSlug = settingsManager()->get('active_theme', '');
        }
        
        if (empty($themeSlug)) {
            return '';
        }
        
        $protocol = self::getProtocol();
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        $url = $protocol . $host . '/themes/' . $themeSlug;
        
        if (!empty($path)) {
            $url .= '/' . ltrim($path, '/');
        }
        
        return $url;
    }

    /**
     * Генерація URL теми з hash-busting
     *
     * @param string $path Шлях до файлу відносно теми
     * @return string URL з версією
     */
    public static function themeAsset(string $path): string
    {
        $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 4);
        $themeSlug = '';
        
        if (function_exists('themeManager')) {
            $theme = themeManager()->getActiveTheme();
            if ($theme && isset($theme['slug'])) {
                $themeSlug = $theme['slug'];
            }
        }
        
        if (empty($themeSlug)) {
            return '';
        }
        
        $assetPath = $rootDir . '/themes/' . $themeSlug . '/' . ltrim($path, '/');
        
        $version = '';
        if (file_exists($assetPath)) {
            $hash = md5_file($assetPath);
            $version = '?v=' . substr($hash, 0, 8);
        }
        
        return self::theme($path) . $version;
    }
}
