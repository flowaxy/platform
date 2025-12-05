<?php

/**
 * Валідатор структури плагіна
 *
 * Перевіряє, чи плагін відповідає стандартній структурі директорій.
 *
 * @package Flowaxy\Core\Support\Validators
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Support\Validators;

final class PluginStructureValidator
{
    /**
     * Стандартна структура плагіна
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $standardStructure = [
        'required' => [
            'plugin.json' => 'Конфігураційний файл плагіна',
            'Plugin.php' => 'Головний клас плагіна (рекомендовано)',
        ],
        'optional' => [
            'Controllers/' => 'Контролери плагіна',
            'Views/' => 'Представлення (шаблони)',
            'Models/' => 'Моделі даних',
            'Assets/' => 'Ресурси (CSS, JS, зображення)',
            'routes.php' => 'Маршрути плагіна',
            'includes/' => 'Допоміжні файли',
            'templates/' => 'Шаблони (альтернатива Views)',
            'src/' => 'Вихідний код (альтернатива)',
            'tests/' => 'Тести',
            'updates/' => 'Файли оновлень',
            'lang/' => 'Файли локалізації',
            'config/' => 'Додаткові конфігурації',
        ],
    ];

    /**
     * Перевірка структури плагіна
     *
     * @param string $pluginDir Директорія плагіна
     * @return array<string, mixed> Результат валідації
     */
    public static function validate(string $pluginDir): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'structure' => [],
        ];

        if (!is_dir($pluginDir)) {
            $result['valid'] = false;
            $result['errors'][] = "Директорія плагіна не існує: {$pluginDir}";
            return $result;
        }

        // Перевіряємо обов'язкові файли
        foreach (self::$standardStructure['required'] as $file => $description) {
            $filePath = $pluginDir . DIRECTORY_SEPARATOR . $file;
            if (!file_exists($filePath)) {
                // plugin.json є обов'язковим
                if ($file === 'plugin.json') {
                    $result['valid'] = false;
                    $result['errors'][] = "Відсутній обов'язковий файл: {$file} ({$description})";
                } else {
                    // Plugin.php рекомендований, але не обов'язковий (може бути init.php)
                    $result['warnings'][] = "Рекомендований файл відсутній: {$file} ({$description})";
                }
            } else {
                $result['structure'][$file] = 'exists';
            }
        }

        // Перевіряємо опціональні директорії/файли
        foreach (self::$standardStructure['optional'] as $item => $description) {
            $itemPath = $pluginDir . DIRECTORY_SEPARATOR . $item;
            if (file_exists($itemPath)) {
                $result['structure'][$item] = is_dir($itemPath) ? 'directory' : 'file';
            }
        }

        // Перевіряємо наявність альтернативних файлів
        $initPath = $pluginDir . DIRECTORY_SEPARATOR . 'init.php';
        if (file_exists($initPath) && !isset($result['structure']['Plugin.php'])) {
            $result['structure']['init.php'] = 'exists';
            $result['warnings'][] = "Використовується init.php замість Plugin.php (рекомендовано мігрувати)";
        }

        return $result;
    }

    /**
     * Отримання стандартної структури
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getStandardStructure(): array
    {
        return self::$standardStructure;
    }

    /**
     * Створення стандартної структури для нового плагіна
     *
     * @param string $pluginDir Директорія плагіна
     * @param string $pluginSlug Slug плагіна
     * @return bool Успіх операції
     */
    public static function createStandardStructure(string $pluginDir, string $pluginSlug): bool
    {
        if (!is_dir($pluginDir)) {
            if (!mkdir($pluginDir, 0755, true)) {
                return false;
            }
        }

        // Створюємо опціональні директорії
        $optionalDirs = array_filter(
            array_keys(self::$standardStructure['optional']),
            fn($item) => str_ends_with($item, '/')
        );

        foreach ($optionalDirs as $dir) {
            $dirPath = $pluginDir . DIRECTORY_SEPARATOR . trim($dir, '/');
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
        }

        // Створюємо базовий plugin.json, якщо не існує
        $pluginJsonPath = $pluginDir . DIRECTORY_SEPARATOR . 'plugin.json';
        if (!file_exists($pluginJsonPath)) {
            $defaultConfig = [
                'name' => ucfirst(str_replace('-', ' ', $pluginSlug)),
                'slug' => $pluginSlug,
                'version' => '1.0.0',
                'description' => '',
                'author' => '',
                'requires' => '1.0.0',
                'tested' => '1.0.0',
            ];
            file_put_contents($pluginJsonPath, json_encode($defaultConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return true;
    }

    /**
     * Отримання списку файлів плагіна за стандартною структурою
     *
     * @param string $pluginDir Директорія плагіна
     * @return array<string, array<string, mixed>> Структура файлів
     */
    public static function getPluginFiles(string $pluginDir): array
    {
        $files = [
            'controllers' => [],
            'views' => [],
            'models' => [],
            'assets' => [],
            'routes' => null,
            'plugin' => null,
        ];

        // Шукаємо контролери
        $controllersDirs = ['Controllers', 'src/Controllers', 'src/admin/pages'];
        foreach ($controllersDirs as $dir) {
            $dirPath = $pluginDir . DIRECTORY_SEPARATOR . $dir;
            if (is_dir($dirPath)) {
                $files['controllers'] = array_merge(
                    $files['controllers'],
                    glob($dirPath . DIRECTORY_SEPARATOR . '*.php')
                );
            }
        }

        // Шукаємо представлення
        $viewsDirs = ['Views', 'templates', 'src/Views'];
        foreach ($viewsDirs as $dir) {
            $dirPath = $pluginDir . DIRECTORY_SEPARATOR . $dir;
            if (is_dir($dirPath)) {
                $files['views'] = array_merge(
                    $files['views'],
                    glob($dirPath . DIRECTORY_SEPARATOR . '**/*.php', GLOB_BRACE)
                );
            }
        }

        // Шукаємо моделі
        $modelsDirs = ['Models', 'src/Models', 'includes'];
        foreach ($modelsDirs as $dir) {
            $dirPath = $pluginDir . DIRECTORY_SEPARATOR . $dir;
            if (is_dir($dirPath)) {
                $files['models'] = array_merge(
                    $files['models'],
                    glob($dirPath . DIRECTORY_SEPARATOR . '*.php')
                );
            }
        }

        // Шукаємо assets
        $assetsDirs = ['Assets', 'assets'];
        foreach ($assetsDirs as $dir) {
            $dirPath = $pluginDir . DIRECTORY_SEPARATOR . $dir;
            if (is_dir($dirPath)) {
                $files['assets'] = array_merge(
                    $files['assets'],
                    glob($dirPath . DIRECTORY_SEPARATOR . '**/*', GLOB_BRACE)
                );
            }
        }

        // Шукаємо routes.php
        $routesPath = $pluginDir . DIRECTORY_SEPARATOR . 'routes.php';
        if (file_exists($routesPath)) {
            $files['routes'] = $routesPath;
        }

        // Шукаємо головний клас плагіна
        $pluginFiles = ['Plugin.php', 'init.php'];
        foreach ($pluginFiles as $file) {
            $filePath = $pluginDir . DIRECTORY_SEPARATOR . $file;
            if (file_exists($filePath)) {
                $files['plugin'] = $filePath;
                break;
            }
        }

        return $files;
    }
}
