<?php

/**
 * Валідатор структури теми
 * 
 * Перевіряє, чи тема відповідає стандартній структурі директорій.
 *
 * @package Flowaxy\Core\Support\Validators
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Support\Validators;

final class ThemeStructureValidator
{
    /**
     * Стандартна структура теми
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $standardStructure = [
        'required' => [
            'theme.json' => 'Конфігураційний файл теми',
            'index.php' => 'Головний файл теми',
        ],
        'optional' => [
            'functions.php' => 'Функції теми',
            'hooks.php' => 'Хуки теми',
            'assets/' => 'Ресурси (CSS, JS, зображення)',
            'templates/' => 'Шаблони сторінок',
            'components/' => 'Компоненти теми',
            'layouts/' => 'Макети сторінок',
            'partials/' => 'Часткові шаблони (header, footer, sidebar)',
            'blocks/' => 'Блоки контенту',
            'snippets/' => 'Сніпети (перевикористовувані фрагменти)',
            'includes/' => 'Допоміжні файли',
            'config/' => 'Конфігурації теми',
            'lang/' => 'Файли локалізації',
            'customizer.php' => 'Файл кастомізатора теми',
        ],
    ];

    /**
     * Перевірка структури теми
     *
     * @param string $themeDir Директорія теми
     * @return array<string, mixed> Результат валідації
     */
    public static function validate(string $themeDir): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'structure' => [],
        ];

        if (!is_dir($themeDir)) {
            $result['valid'] = false;
            $result['errors'][] = "Директорія теми не існує: {$themeDir}";
            return $result;
        }

        // Перевіряємо обов'язкові файли
        foreach (self::$standardStructure['required'] as $file => $description) {
            $filePath = $themeDir . DIRECTORY_SEPARATOR . $file;
            if (!file_exists($filePath)) {
                // theme.json є обов'язковим
                if ($file === 'theme.json') {
                    $result['valid'] = false;
                    $result['errors'][] = "Відсутній обов'язковий файл: {$file} ({$description})";
                } else {
                    // index.php рекомендований, але не обов'язковий
                    $result['warnings'][] = "Рекомендований файл відсутній: {$file} ({$description})";
                }
            } else {
                $result['structure'][$file] = 'exists';
            }
        }

        // Перевіряємо опціональні директорії/файли
        foreach (self::$standardStructure['optional'] as $item => $description) {
            $itemPath = $themeDir . DIRECTORY_SEPARATOR . $item;
            if (file_exists($itemPath)) {
                $result['structure'][$item] = is_dir($itemPath) ? 'directory' : 'file';
            }
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
     * Створення стандартної структури для нової теми
     *
     * @param string $themeDir Директорія теми
     * @param string $themeSlug Slug теми
     * @return bool Успіх операції
     */
    public static function createStandardStructure(string $themeDir, string $themeSlug): bool
    {
        if (!is_dir($themeDir)) {
            if (!mkdir($themeDir, 0755, true)) {
                return false;
            }
        }

        // Створюємо опціональні директорії
        $optionalDirs = array_filter(
            array_keys(self::$standardStructure['optional']),
            fn($item) => str_ends_with($item, '/')
        );

        foreach ($optionalDirs as $dir) {
            $dirPath = $themeDir . DIRECTORY_SEPARATOR . trim($dir, '/');
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
        }

        // Створюємо базовий theme.json, якщо не існує
        $themeJsonPath = $themeDir . DIRECTORY_SEPARATOR . 'theme.json';
        if (!file_exists($themeJsonPath)) {
            $defaultConfig = [
                'name' => ucfirst(str_replace('-', ' ', $themeSlug)),
                'slug' => $themeSlug,
                'version' => '1.0.0',
                'description' => '',
                'author' => '',
                'requires' => '1.0.0',
                'tested' => '1.0.0',
            ];
            file_put_contents($themeJsonPath, json_encode($defaultConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // Створюємо базовий index.php, якщо не існує
        $indexPath = $themeDir . DIRECTORY_SEPARATOR . 'index.php';
        if (!file_exists($indexPath)) {
            $indexContent = "<?php\n\ndeclare(strict_types=1);\n\n// Тема: {$themeSlug}\n";
            file_put_contents($indexPath, $indexContent);
        }

        // Створюємо базовий functions.php, якщо не існує
        $functionsPath = $themeDir . DIRECTORY_SEPARATOR . 'functions.php';
        if (!file_exists($functionsPath)) {
            $functionsContent = "<?php\n\ndeclare(strict_types=1);\n\n// Функції теми {$themeSlug}\n";
            file_put_contents($functionsPath, $functionsContent);
        }

        return true;
    }

    /**
     * Отримання списку файлів теми за стандартною структурою
     *
     * @param string $themeDir Директорія теми
     * @return array<string, array<string, mixed>> Структура файлів
     */
    public static function getThemeFiles(string $themeDir): array
    {
        $files = [
            'templates' => [],
            'components' => [],
            'layouts' => [],
            'partials' => [],
            'blocks' => [],
            'snippets' => [],
            'assets' => [],
            'functions' => null,
            'hooks' => null,
            'customizer' => null,
        ];

        // Шукаємо шаблони
        $templatesDir = $themeDir . DIRECTORY_SEPARATOR . 'templates';
        if (is_dir($templatesDir)) {
            $files['templates'] = glob($templatesDir . DIRECTORY_SEPARATOR . '*.php');
        }

        // Шукаємо компоненти
        $componentsDir = $themeDir . DIRECTORY_SEPARATOR . 'components';
        if (is_dir($componentsDir)) {
            $files['components'] = glob($componentsDir . DIRECTORY_SEPARATOR . '**/*.php', GLOB_BRACE);
        }

        // Шукаємо макети
        $layoutsDir = $themeDir . DIRECTORY_SEPARATOR . 'layouts';
        if (is_dir($layoutsDir)) {
            $files['layouts'] = glob($layoutsDir . DIRECTORY_SEPARATOR . '*.php');
        }

        // Шукаємо часткові шаблони
        $partialsDir = $themeDir . DIRECTORY_SEPARATOR . 'partials';
        if (is_dir($partialsDir)) {
            $files['partials'] = glob($partialsDir . DIRECTORY_SEPARATOR . '*.php');
        }

        // Шукаємо блоки
        $blocksDir = $themeDir . DIRECTORY_SEPARATOR . 'blocks';
        if (is_dir($blocksDir)) {
            $files['blocks'] = glob($blocksDir . DIRECTORY_SEPARATOR . '*.php');
        }

        // Шукаємо сніпети
        $snippetsDir = $themeDir . DIRECTORY_SEPARATOR . 'snippets';
        if (is_dir($snippetsDir)) {
            $files['snippets'] = glob($snippetsDir . DIRECTORY_SEPARATOR . '*.php');
        }

        // Шукаємо assets
        $assetsDirs = ['assets', 'Assets'];
        foreach ($assetsDirs as $dir) {
            $assetsPath = $themeDir . DIRECTORY_SEPARATOR . $dir;
            if (is_dir($assetsPath)) {
                $files['assets'] = array_merge(
                    $files['assets'],
                    glob($assetsPath . DIRECTORY_SEPARATOR . '**/*', GLOB_BRACE)
                );
            }
        }

        // Шукаємо functions.php
        $functionsPath = $themeDir . DIRECTORY_SEPARATOR . 'functions.php';
        if (file_exists($functionsPath)) {
            $files['functions'] = $functionsPath;
        }

        // Шукаємо hooks.php
        $hooksPath = $themeDir . DIRECTORY_SEPARATOR . 'hooks.php';
        if (file_exists($hooksPath)) {
            $files['hooks'] = $hooksPath;
        }

        // Шукаємо customizer.php
        $customizerPath = $themeDir . DIRECTORY_SEPARATOR . 'customizer.php';
        if (file_exists($customizerPath)) {
            $files['customizer'] = $customizerPath;
        }

        return $files;
    }
}

