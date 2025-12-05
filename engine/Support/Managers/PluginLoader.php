<?php

/**
 * Оптимізований завантажувач плагінів
 *
 * Забезпечує кешування, lazy loading, оптимізацію порядку завантаження
 * та інтеграцію з PluginContainer для ізоляції.
 *
 * @package Flowaxy\Core\Support\Managers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Support\Managers;

use Flowaxy\Core\Support\Containers\PluginContainer;
use Flowaxy\Core\Support\Containers\PluginContainerFactory;
use Flowaxy\Core\Support\Validators\PluginStructureValidator;
use Flowaxy\Core\Support\Base\BasePlugin;

final class PluginLoader
{
    /**
     * @var array<string, PluginContainer> Кеш завантажених контейнерів
     */
    private static array $containers = [];

    /**
     * @var array<string, BasePlugin> Кеш завантажених плагінів
     */
    private static array $plugins = [];

    /**
     * @var array<string, array<string, mixed>> Кеш метаданих плагінів
     */
    private static array $metadata = [];

    /**
     * @var bool Чи ініціалізовано
     */
    private static bool $initialized = false;

    /**
     * @var string Директорія плагінів
     */
    private static string $pluginsDir = '';

    /**
     * @var PluginContainerFactory|null Фабрика контейнерів
     */
    private static ?PluginContainerFactory $factory = null;

    /**
     * Ініціалізація завантажувача
     *
     * @param string $pluginsDir Директорія плагінів
     * @return void
     */
    public static function initialize(string $pluginsDir): void
    {
        if (self::$initialized) {
            return;
        }

        self::$pluginsDir = rtrim($pluginsDir, '/\\') . DIRECTORY_SEPARATOR;
        self::$factory = new PluginContainerFactory(self::$pluginsDir);
        self::$initialized = true;
    }

    /**
     * Завантаження плагіна з кешуванням та ізоляцією
     *
     * @param string $pluginSlug Slug плагіна
     * @param array<string, mixed> $pluginData Дані плагіна з БД
     * @return BasePlugin|null Екземпляр плагіна
     */
    public static function load(string $pluginSlug, array $pluginData): ?BasePlugin
    {
        // Перевіряємо кеш
        if (isset(self::$plugins[$pluginSlug])) {
            return self::$plugins[$pluginSlug];
        }

        // Отримуємо або створюємо контейнер
        $container = self::getOrCreateContainer($pluginSlug, $pluginData);

        if ($container === null) {
            return null;
        }

        // Завантажуємо плагін
        $plugin = self::loadPluginInstance($pluginSlug, $container);

        if ($plugin !== null) {
            self::$plugins[$pluginSlug] = $plugin;
            $container->setPluginInstance($plugin);
        }

        return $plugin;
    }

    /**
     * Отримання або створення контейнера плагіна
     *
     * @param string $pluginSlug Slug плагіна
     * @param array<string, mixed> $pluginData Дані плагіна
     * @return PluginContainer|null
     */
    private static function getOrCreateContainer(string $pluginSlug, array $pluginData): ?PluginContainer
    {
        // Перевіряємо кеш
        if (isset(self::$containers[$pluginSlug])) {
            return self::$containers[$pluginSlug];
        }

        if (self::$factory === null) {
            return null;
        }

        try {
            // Завантажуємо конфігурацію плагіна
            $config = self::loadPluginConfig($pluginSlug);

            // Створюємо контейнер
            $container = self::$factory->create($pluginSlug, self::$pluginsDir . $pluginSlug, $config);

            // Зберігаємо в кеш
            self::$containers[$pluginSlug] = $container;

            return $container;
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger()->logError("Error creating container for plugin {$pluginSlug}: " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Завантаження екземпляра плагіна
     *
     * @param string $pluginSlug Slug плагіна
     * @param PluginContainer $container Контейнер плагіна
     * @return BasePlugin|null
     */
    private static function loadPluginInstance(string $pluginSlug, PluginContainer $container): ?BasePlugin
    {
        $pluginDir = $container->getPluginDir();

        // Шукаємо головний файл плагіна
        $pluginFile = self::findPluginFile($pluginDir, $pluginSlug);

        if ($pluginFile === null) {
            return null;
        }

        try {
            // Завантажуємо файл
            require_once $pluginFile;

            // Шукаємо клас плагіна
            $pluginClass = self::findPluginClass($pluginSlug, $pluginFile);

            if ($pluginClass === null || !class_exists($pluginClass)) {
                return null;
            }

            // Створюємо екземпляр з контейнером
            $reflection = new \ReflectionClass($pluginClass);
            if ($reflection->isSubclassOf(BasePlugin::class)) {
                $plugin = $reflection->newInstance($container);
                return $plugin;
            }
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger()->logError("Error loading plugin instance {$pluginSlug}: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Пошук головного файлу плагіна
     *
     * @param string $pluginDir Директорія плагіна
     * @param string $pluginSlug Slug плагіна
     * @return string|null Шлях до файлу
     */
    private static function findPluginFile(string $pluginDir, string $pluginSlug): ?string
    {
        // Стандартні варіанти
        $files = [
            $pluginDir . 'Plugin.php',
            $pluginDir . 'init.php',
            $pluginDir . ucfirst(str_replace('-', '', $pluginSlug)) . 'Plugin.php',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Пошук класу плагіна
     *
     * @param string $pluginSlug Slug плагіна
     * @param string $pluginFile Шлях до файлу
     * @return string|null Ім'я класу
     */
    private static function findPluginClass(string $pluginSlug, string $pluginFile): ?string
    {
        // Читаємо файл для пошуку класу
        $content = file_get_contents($pluginFile);
        if ($content === false) {
            return null;
        }

        // Шукаємо клас, що розширює BasePlugin
        if (preg_match('/class\s+(\w+)\s+extends\s+BasePlugin/', $content, $matches)) {
            return $matches[1];
        }

        // Шукаємо будь-який клас
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }

        // Генеруємо ім'я класу з slug
        $parts = explode('-', $pluginSlug);
        $className = '';
        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }
        $className .= 'Plugin';

        return class_exists($className) ? $className : null;
    }

    /**
     * Завантаження конфігурації плагіна
     *
     * @param string $pluginSlug Slug плагіна
     * @return array<string, mixed>
     */
    private static function loadPluginConfig(string $pluginSlug): array
    {
        // Перевіряємо кеш
        if (isset(self::$metadata[$pluginSlug])) {
            return self::$metadata[$pluginSlug];
        }

        $configFile = self::$pluginsDir . $pluginSlug . DIRECTORY_SEPARATOR . 'plugin.json';

        if (!file_exists($configFile)) {
            return [];
        }

        $configContent = file_get_contents($configFile);
        if ($configContent === false) {
            return [];
        }

        $config = json_decode($configContent, true);
        if (!is_array($config)) {
            return [];
        }

        // Зберігаємо в кеш
        self::$metadata[$pluginSlug] = $config;

        return $config;
    }

    /**
     * Отримання завантаженого плагіна
     *
     * @param string $pluginSlug Slug плагіна
     * @return BasePlugin|null
     */
    public static function get(string $pluginSlug): ?BasePlugin
    {
        return self::$plugins[$pluginSlug] ?? null;
    }

    /**
     * Отримання контейнера плагіна
     *
     * @param string $pluginSlug Slug плагіна
     * @return PluginContainer|null
     */
    public static function getContainer(string $pluginSlug): ?PluginContainer
    {
        return self::$containers[$pluginSlug] ?? null;
    }

    /**
     * Очищення кешу
     *
     * @param string|null $pluginSlug Slug плагіна (null для очищення всього)
     * @return void
     */
    public static function clearCache(?string $pluginSlug = null): void
    {
        if ($pluginSlug !== null) {
            unset(self::$plugins[$pluginSlug]);
            unset(self::$containers[$pluginSlug]);
            unset(self::$metadata[$pluginSlug]);
        } else {
            self::$plugins = [];
            self::$containers = [];
            self::$metadata = [];
        }
    }

    /**
     * Отримання списку завантажених плагінів
     *
     * @return array<string, BasePlugin>
     */
    public static function getLoadedPlugins(): array
    {
        return self::$plugins;
    }

    /**
     * Перевірка, чи завантажений плагін
     *
     * @param string $pluginSlug Slug плагіна
     * @return bool
     */
    public static function isLoaded(string $pluginSlug): bool
    {
        return isset(self::$plugins[$pluginSlug]);
    }
}
