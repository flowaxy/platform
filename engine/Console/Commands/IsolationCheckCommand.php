<?php

/**
 * Команда перевірки ізоляції
 *
 * @package Flowaxy\Core\System\Commands
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Commands;

class IsolationCheckCommand extends MakeCommand
{
    /**
     * Виконання перевірки ізоляції
     *
     * @param array $args Аргументи команди
     * @return void
     */
    public function run(array $args): void
    {
        $pluginSlug = $args[0] ?? null;

        echo "Перевірка ізоляції\n";
        echo str_repeat("=", 80) . "\n\n";

        if ($pluginSlug !== null) {
            $this->checkPluginIsolation($pluginSlug);
        } else {
            $this->checkAllPluginsIsolation();
        }
    }

    /**
     * Перевірка ізоляції конкретного плагіна
     *
     * @param string $pluginSlug
     * @return void
     */
    private function checkPluginIsolation(string $pluginSlug): void
    {
        $pluginDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $pluginSlug;

        if (!is_dir($pluginDir)) {
            echo "Помилка: плагін {$pluginSlug} не знайдено\n";
            exit(1);
        }

        echo "Перевірка плагіна: {$pluginSlug}\n\n";

        $issues = $this->scanPluginForIsolationIssues($pluginDir, $pluginSlug);

        if (empty($issues)) {
            echo "✓ Ізоляція плагіна перевірена успішно\n";
        } else {
            echo "Знайдено проблеми ізоляції:\n";
            foreach ($issues as $issue) {
                echo "  ✗ {$issue}\n";
            }
            exit(1);
        }
    }

    /**
     * Перевірка ізоляції всіх плагінів
     *
     * @return void
     */
    private function checkAllPluginsIsolation(): void
    {
        $pluginsDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'plugins';

        if (!is_dir($pluginsDir)) {
            echo "Директорія плагінів не знайдена\n";
            exit(1);
        }

        $plugins = glob($pluginsDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        $totalIssues = 0;

        foreach ($plugins as $pluginDir) {
            $pluginSlug = basename($pluginDir);
            echo "Перевірка плагіна: {$pluginSlug}\n";

            $issues = $this->scanPluginForIsolationIssues($pluginDir, $pluginSlug);

            if (!empty($issues)) {
                $totalIssues += count($issues);
                foreach ($issues as $issue) {
                    echo "  ✗ {$issue}\n";
                }
            } else {
                echo "  ✓ OK\n";
            }
        }

        echo "\n" . str_repeat("=", 80) . "\n";
        echo "Всього проблем: {$totalIssues}\n";

        exit($totalIssues > 0 ? 1 : 0);
    }

    /**
     * Сканування плагіна на проблеми ізоляції
     *
     * @param string $pluginDir
     * @param string $pluginSlug
     * @return array<string>
     */
    private function scanPluginForIsolationIssues(string $pluginDir, string $pluginSlug): array
    {
        $issues = [];
        $files = $this->getPhpFiles($pluginDir);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            // Перевірка на прямі виклики класів ядра
            if (preg_match('/new\s+\\\?Flowaxy\\\Core\\\\(?!System\\\Hooks\\\\(Action|Filter)|Support\\\Base\\\BasePlugin|Support\\\Containers\\\PluginContainer)/', $content)) {
                $issues[] = "{$file}: Пряме створення екземплярів класів ядра";
            }

            // Перевірка на небезпечні функції
            $forbiddenFunctions = ['eval', 'exec', 'shell_exec', 'system', 'file_get_contents', 'file_put_contents'];
            foreach ($forbiddenFunctions as $func) {
                if (preg_match('/\b' . preg_quote($func, '/') . '\s*\(/', $content)) {
                    $issues[] = "{$file}: Використання небезпечної функції {$func}";
                }
            }

            // Перевірка на прямі звернення до файлів ядра
            if (preg_match('/["\']\.\.\/engine\//', $content) || preg_match('/["\']engine\//', $content)) {
                $issues[] = "{$file}: Пряме звернення до файлів ядра";
            }
        }

        return $issues;
    }

    /**
     * Отримання всіх PHP файлів
     *
     * @param string $path
     * @return array<string>
     */
    private function getPhpFiles(string $path): array
    {
        $files = [];

        if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            return [$path];
        }

        if (!is_dir($path)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
