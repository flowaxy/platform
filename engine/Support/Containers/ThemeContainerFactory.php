<?php

/**
 * Фабрика для створення ізольованих контейнерів тем
 *
 * Створює та керує контейнерами для тем, забезпечуючи їх ізоляцію.
 *
 * @package Flowaxy\Core\Support\Containers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Support\Containers;

require_once __DIR__ . '/ThemeContainer.php';

final class ThemeContainerFactory
{
    /**
     * @var array<string, ThemeContainer> Кеш створених контейнерів
     */
    private static array $containers = [];

    /**
     * @var string|null Slug активної теми
     */
    private static ?string $activeThemeSlug = null;

    /**
     * Створення контейнера для теми
     *
     * @param string $themeSlug Slug теми
     * @param string $themeDir Директорія теми
     * @param array<string, mixed> $config Конфігурація теми
     * @param bool $forceNew Чи створити новий контейнер навіть якщо він вже існує
     * @return ThemeContainer
     */
    public static function create(
        string $themeSlug,
        string $themeDir,
        array $config = [],
        bool $forceNew = false
    ): ThemeContainer {
        // Нормалізуємо slug
        $themeSlug = self::normalizeSlug($themeSlug);

        // Перевіряємо кеш
        if (!$forceNew && isset(self::$containers[$themeSlug])) {
            return self::$containers[$themeSlug];
        }

        // Перевіряємо існування директорії
        if (!is_dir($themeDir)) {
            throw new \RuntimeException("Theme directory does not exist: {$themeDir}");
        }

        // Завантажуємо конфігурацію з theme.json якщо не передана
        if (empty($config)) {
            $config = self::loadThemeConfig($themeDir);
        }

        // Створюємо контейнер
        $container = new ThemeContainer($themeSlug, $themeDir, $config);

        // Зберігаємо в кеш
        self::$containers[$themeSlug] = $container;

        return $container;
    }

    /**
     * Отримання контейнера теми
     *
     * @param string $themeSlug Slug теми
     * @return ThemeContainer|null
     */
    public static function get(string $themeSlug): ?ThemeContainer
    {
        $themeSlug = self::normalizeSlug($themeSlug);
        return self::$containers[$themeSlug] ?? null;
    }

    /**
     * Перевірка чи існує контейнер для теми
     *
     * @param string $themeSlug Slug теми
     * @return bool
     */
    public static function has(string $themeSlug): bool
    {
        $themeSlug = self::normalizeSlug($themeSlug);
        return isset(self::$containers[$themeSlug]);
    }

    /**
     * Отримання активної теми
     *
     * @return ThemeContainer|null
     */
    public static function getActive(): ?ThemeContainer
    {
        if (self::$activeThemeSlug === null) {
            return null;
        }

        return self::get(self::$activeThemeSlug);
    }

    /**
     * Встановлення активної теми
     *
     * @param string $themeSlug Slug теми
     * @return void
     */
    public static function setActive(string $themeSlug): void
    {
        $themeSlug = self::normalizeSlug($themeSlug);

        // Деактивуємо попередню активну тему
        if (self::$activeThemeSlug !== null && self::$activeThemeSlug !== $themeSlug) {
            $previousContainer = self::get(self::$activeThemeSlug);
            if ($previousContainer !== null) {
                $previousContainer->deactivate();
            }
        }

        // Активуємо нову тему
        $container = self::get($themeSlug);
        if ($container !== null) {
            $container->activate();
            self::$activeThemeSlug = $themeSlug;
        } else {
            throw new \RuntimeException("Theme container not found: {$themeSlug}");
        }
    }

    /**
     * Видалення контейнера теми
     *
     * @param string $themeSlug Slug теми
     * @return void
     */
    public static function remove(string $themeSlug): void
    {
        $themeSlug = self::normalizeSlug($themeSlug);

        // Якщо це активна тема, скидаємо активну тему
        if (self::$activeThemeSlug === $themeSlug) {
            self::$activeThemeSlug = null;
        }

        if (isset(self::$containers[$themeSlug])) {
            self::$containers[$themeSlug]->clear();
            unset(self::$containers[$themeSlug]);
        }
    }

    /**
     * Очищення всіх контейнерів
     *
     * @return void
     */
    public static function clearAll(): void
    {
        foreach (self::$containers as $container) {
            $container->clear();
        }
        self::$containers = [];
        self::$activeThemeSlug = null;
    }

    /**
     * Отримання всіх контейнерів
     *
     * @return array<string, ThemeContainer>
     */
    public static function getAll(): array
    {
        return self::$containers;
    }

    /**
     * Нормалізація slug теми
     *
     * @param string $slug
     * @return string
     */
    private static function normalizeSlug(string $slug): string
    {
        return strtolower(trim($slug));
    }

    /**
     * Завантаження конфігурації теми з theme.json
     *
     * @param string $themeDir Директорія теми
     * @return array<string, mixed>
     */
    private static function loadThemeConfig(string $themeDir): array
    {
        $configFile = rtrim($themeDir, '/\\') . DIRECTORY_SEPARATOR . 'theme.json';

        if (!file_exists($configFile)) {
            return [];
        }

        $configContent = @file_get_contents($configFile);
        if ($configContent === false) {
            return [];
        }

        $config = @json_decode($configContent, true);
        if (!is_array($config)) {
            return [];
        }

        return $config;
    }
}
