<?php

/**
 * Базовий клас для команд генерації (make:*)
 *
 * @package Flowaxy\Core\System\Commands
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Commands;

class MakeCommand
{
    protected string $rootDir;
    protected string $projectRoot;

    public function __construct(string $rootDir)
    {
        $this->rootDir = rtrim($rootDir, '/\\');
        $this->projectRoot = dirname($this->rootDir);
    }

    /**
     * Нормалізація імені класу
     *
     * @param string $name
     * @return string
     */
    protected function normalizeClassName(string $name): string
    {
        // Видаляємо зайві символи та перетворюємо на PascalCase
        $name = preg_replace('/[^a-zA-Z0-9]/', '', $name);
        return ucfirst($name);
    }

    /**
     * Нормалізація slug
     *
     * @param string $name
     * @return string
     */
    protected function normalizeSlug(string $name): string
    {
        // Перетворюємо на lowercase з дефісами
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9-]/', '', $name);
        $name = preg_replace('/-+/', '-', $name);
        return trim($name, '-');
    }

    /**
     * Створення директорії, якщо не існує
     *
     * @param string $dir
     * @return bool
     */
    protected function ensureDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return mkdir($dir, 0755, true);
        }
        return true;
    }

    /**
     * Запис файлу
     *
     * @param string $filePath
     * @param string $content
     * @return bool
     */
    protected function writeFile(string $filePath, string $content): bool
    {
        $dir = dirname($filePath);
        if (!$this->ensureDirectory($dir)) {
            return false;
        }

        return file_put_contents($filePath, $content) !== false;
    }

    /**
     * Перевірка, чи файл вже існує
     *
     * @param string $filePath
     * @return bool
     */
    protected function fileExists(string $filePath): bool
    {
        return file_exists($filePath);
    }
}
