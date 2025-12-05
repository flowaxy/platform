<?php

/**
 * Клас для роботи з директоріями
 * Створення, видалення, копіювання директорій та робота з файлами всередині
 *
 * @package Engine\Classes\Files
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

// Використовуємо namespace для уникнення конфлікту з вбудованим PHP класом Directory

namespace Engine\Classes\Files;

class Directory
{
    private string $path;

    /**
     * Конструктор
     *
     * @param string|null $path Шлях до директорії
     */
    public function __construct(?string $path = null)
    {
        if ($path !== null) {
            $this->setPath($path);
        }
    }

    /**
     * Встановлення шляху до директорії
     *
     * @param string $path Шлях до директорії
     * @return self
     */
    public function setPath(string $path): self
    {
        $this->path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;

        return $this;
    }

    /**
     * Отримання шляху до директорії
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Перевірка існування директорії
     *
     * @return bool
     */
    public function exists(): bool
    {
        return is_dir($this->path);
    }

    /**
     * Перевірка, чи є директорія читабельною
     *
     * @return bool
     */
    public function isReadable(): bool
    {
        return $this->exists() && is_readable($this->path);
    }

    /**
     * Перевірка, чи є директорія доступною для запису
     *
     * @return bool
     */
    public function isWritable(): bool
    {
        return $this->exists() && is_writable($this->path);
    }

    /**
     * Створення директорії
     *
     * @param int $mode Права доступу (за замовчуванням 0755)
     * @param bool $recursive Створювати чи вкладені директорії
     * @return bool
     * @throws Exception Якщо не вдалося створити
     */
    public function create(int $mode = 0755, bool $recursive = true): bool
    {
        if ($this->exists()) {
            return true; // Директорія вже існує
        }

        if (! @mkdir($this->path, $mode, $recursive)) {
            throw new \Exception("Не вдалося створити директорію: {$this->path}");
        }

        return true;
    }

    /**
     * Видалення директорії
     *
     * @param bool $recursive Видаляти чи вміст рекурсивно
     * @return bool
     * @throws Exception Якщо не вдалося видалити
     */
    public function delete(bool $recursive = true): bool
    {
        if (! $this->exists()) {
            return true; // Директорія вже не існує
        }

        if ($recursive) {
            return $this->removeRecursive($this->path);
        } else {
            if (! @rmdir($this->path)) {
                throw new \Exception("Не вдалося видалити директорію: {$this->path}");
            }

            return true;
        }
    }

    /**
     * Рекурсивне видалення директорії
     *
     * @param string $dir Шлях до директорії
     * @return bool
     */
    private function removeRecursive(string $dir): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($filePath)) {
                $this->removeRecursive($filePath);
            } else {
                @unlink($filePath);
            }
        }

        return @rmdir($dir);
    }

    /**
     * Копіювання директорії
     *
     * @param string $destinationPath Шлях призначення
     * @param bool $overwrite Перезаписувати чи існуючі файли
     * @return bool
     * @throws Exception Якщо не вдалося скопіювати
     */
    public function copy(string $destinationPath, bool $overwrite = true): bool
    {
        if (! $this->exists()) {
            throw new \Exception("Вихідна директорія не існує: {$this->path}");
        }

        $destinationPath = rtrim($destinationPath, '/\\') . DIRECTORY_SEPARATOR;

        // Створюємо директорію призначення
        if (! is_dir($destinationPath)) {
            if (! @mkdir($destinationPath, 0755, true)) {
                throw new \Exception("Не вдалося створити директорію призначення: {$destinationPath}");
            }
        }

        return $this->copyRecursive($this->path, $destinationPath, $overwrite);
    }

    /**
     * Рекурсивне копіювання
     *
     * @param string $source Вихідний шлях
     * @param string $destination Шлях призначення
     * @param bool $overwrite Перезаписувати чи
     * @return bool
     */
    private function copyRecursive(string $source, string $destination, bool $overwrite): bool
    {
        if (! is_dir($source)) {
            return false;
        }

        if (! is_dir($destination)) {
            if (! @mkdir($destination, 0755, true)) {
                return false;
            }
        }

        $files = array_diff(scandir($source), ['.', '..']);

        foreach ($files as $file) {
            $sourcePath = $source . $file;
            $destinationPath = $destination . $file;

            if (is_dir($sourcePath)) {
                if (! $this->copyRecursive($sourcePath . DIRECTORY_SEPARATOR, $destinationPath . DIRECTORY_SEPARATOR, $overwrite)) {
                    return false;
                }
            } else {
                if (! $overwrite && file_exists($destinationPath)) {
                    continue;
                }

                if (! @copy($sourcePath, $destinationPath)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Отримання списку файлів у директорії
     *
     * @param bool $recursive Рекурсивно
     * @param string|null $pattern Паттерн для фільтрації (наприклад, '*.php')
     * @return array
     */
    public function getFiles(bool $recursive = false, ?string $pattern = null): array
    {
        if (! $this->exists()) {
            return [];
        }

        $files = [];

        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $filePath = $file->getRealPath();

                    if ($pattern === null || fnmatch($pattern, $file->getFilename())) {
                        $files[] = $filePath;
                    }
                }
            }
        } else {
            $items = scandir($this->path);

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $filePath = $this->path . $item;

                if (is_file($filePath)) {
                    if ($pattern === null || fnmatch($pattern, $item)) {
                        $files[] = $filePath;
                    }
                }
            }
        }

        return $files;
    }

    /**
     * Отримання списку директорій
     *
     * @param bool $recursive Рекурсивно
     * @return array
     */
    public function getDirectories(bool $recursive = false): array
    {
        if (! $this->exists()) {
            return [];
        }

        $directories = [];

        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    $directories[] = $file->getRealPath() . DIRECTORY_SEPARATOR;
                }
            }
        } else {
            $items = scandir($this->path);

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $itemPath = $this->path . $item;

                if (is_dir($itemPath)) {
                    $directories[] = $itemPath . DIRECTORY_SEPARATOR;
                }
            }
        }

        return $directories;
    }

    /**
     * Отримання розміру директорії
     *
     * @param bool $format Форматувати чи розмір (KB, MB тощо)
     * @return int|string
     */
    public function getSize(bool $format = false)
    {
        if (! $this->exists()) {
            return $format ? '0 B' : 0;
        }

        $size = 0;
        $files = $this->getFiles(true);

        foreach ($files as $file) {
            if (file_exists($file)) {
                $size += filesize($file);
            }
        }

        if ($format) {
            return $this->formatSize($size);
        }

        return $size;
    }

    /**
     * Форматування розміру
     *
     * @param int $bytes Розмір у байтах
     * @return string
     */
    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Очищення директорії (видалення всіх файлів та піддиректорій)
     *
     * @return bool
     */
    public function clean(): bool
    {
        if (! $this->exists()) {
            return true;
        }

        return $this->delete(true) && $this->create();
    }

    /**
     * Встановлення прав доступу до директорії
     *
     * @param int $mode Права доступу
     * @return bool
     */
    public function chmod(int $mode): bool
    {
        if (! $this->exists()) {
            return false;
        }

        return @chmod($this->path, $mode);
    }

    /**
     * Статичний метод: Створення директорії
     *
     * @param string $path Шлях до директорії
     * @param int $mode Права доступу
     * @param bool $recursive Рекурсивно
     * @return bool
     */
    public static function make(string $path, int $mode = 0755, bool $recursive = true): bool
    {
        $dir = new self($path);

        try {
            return $dir->create($mode, $recursive);
        } catch (\Exception $e) {
            logger()->logError('Directory::make помилка: ' . $e->getMessage(), ['exception' => $e, 'path' => $dirPath]);
            return false;
        }
    }

    /**
     * Статичний метод: Видалення директорії
     *
     * @param string $path Шлях до директорії
     * @param bool $recursive Рекурсивно
     * @return bool
     */
    public static function remove(string $path, bool $recursive = true): bool
    {
        $dir = new self($path);

        try {
            return $dir->delete($recursive);
        } catch (\Exception $e) {
            logger()->logError('Directory::remove помилка: ' . $e->getMessage(), ['exception' => $e, 'path' => $dirPath]);
            return false;
        }
    }

    /**
     * Статичний метод: Копіювання директорії
     *
     * @param string $sourcePath Вихідний шлях
     * @param string $destinationPath Шлях призначення
     * @param bool $overwrite Перезаписувати чи
     * @return bool
     */
    public static function copyDirectory(string $sourcePath, string $destinationPath, bool $overwrite = true): bool
    {
        $dir = new self($sourcePath);

        try {
            return $dir->copy($destinationPath, $overwrite);
        } catch (\Exception $e) {
            logger()->logError('Directory::copyDirectory помилка: ' . $e->getMessage(), ['exception' => $e, 'source' => $sourcePath, 'destination' => $destinationPath]);
            return false;
        }
    }

    /**
     * Статичний метод: Перевірка існування директорії
     *
     * @param string $path Шлях до директорії
     * @return bool
     */
    public static function directoryExists(string $path): bool
    {
        return is_dir($path);
    }
}
