<?php

/**
 * Фабрика для створення ізольованих контейнерів плагінів
 *
 * Створює та керує контейнерами для плагінів, забезпечуючи їх ізоляцію.
 *
 * @package Flowaxy\Core\Support\Containers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Support\Containers;

require_once __DIR__ . '/PluginContainer.php';

final class PluginContainerFactory
{
    /**
     * @var array<string, PluginContainer> Кеш створених контейнерів
     */
    private static array $containers = [];

    /**
     * Створення контейнера для плагіна
     *
     * @param string $pluginSlug Slug плагіна
     * @param string $pluginDir Директорія плагіна
     * @param array<string, mixed> $config Конфігурація плагіна
     * @param bool $forceNew Чи створити новий контейнер навіть якщо він вже існує
     * @return PluginContainer
     */
    public static function create(
        string $pluginSlug,
        string $pluginDir,
        array $config = [],
        bool $forceNew = false
    ): PluginContainer {
        // Нормалізуємо slug
        $pluginSlug = self::normalizeSlug($pluginSlug);

        // Перевіряємо кеш
        if (!$forceNew && isset(self::$containers[$pluginSlug])) {
            return self::$containers[$pluginSlug];
        }

        // Перевіряємо існування директорії
        if (!is_dir($pluginDir)) {
            throw new \RuntimeException("Plugin directory does not exist: {$pluginDir}");
        }

        // Завантажуємо конфігурацію з plugin.json якщо не передана
        if (empty($config)) {
            $config = self::loadPluginConfig($pluginDir);
        }

        // Створюємо контейнер
        $container = new PluginContainer($pluginSlug, $pluginDir, $config);

        // Зберігаємо в кеш
        self::$containers[$pluginSlug] = $container;

        return $container;
    }

    /**
     * Отримання контейнера плагіна
     *
     * @param string $pluginSlug Slug плагіна
     * @return PluginContainer|null
     */
    public static function get(string $pluginSlug): ?PluginContainer
    {
        $pluginSlug = self::normalizeSlug($pluginSlug);
        return self::$containers[$pluginSlug] ?? null;
    }

    /**
     * Перевірка чи існує контейнер для плагіна
     *
     * @param string $pluginSlug Slug плагіна
     * @return bool
     */
    public static function has(string $pluginSlug): bool
    {
        $pluginSlug = self::normalizeSlug($pluginSlug);
        return isset(self::$containers[$pluginSlug]);
    }

    /**
     * Видалення контейнера плагіна
     *
     * @param string $pluginSlug Slug плагіна
     * @return void
     */
    public static function remove(string $pluginSlug): void
    {
        $pluginSlug = self::normalizeSlug($pluginSlug);
        if (isset(self::$containers[$pluginSlug])) {
            self::$containers[$pluginSlug]->clear();
            unset(self::$containers[$pluginSlug]);
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
    }

    /**
     * Отримання всіх контейнерів
     *
     * @return array<string, PluginContainer>
     */
    public static function getAll(): array
    {
        return self::$containers;
    }

    /**
     * Нормалізація slug плагіна
     *
     * @param string $slug
     * @return string
     */
    private static function normalizeSlug(string $slug): string
    {
        return strtolower(trim($slug));
    }

    /**
     * Завантаження конфігурації плагіна з plugin.json
     *
     * @param string $pluginDir Директорія плагіна
     * @return array<string, mixed>
     */
    private static function loadPluginConfig(string $pluginDir): array
    {
        $configFile = rtrim($pluginDir, '/\\') . DIRECTORY_SEPARATOR . 'plugin.json';

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
