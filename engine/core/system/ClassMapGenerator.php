<?php

/**
 * Генератор class map для оптимізації автозавантаження
 *
 * @package Flowaxy\Core\System
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\System;

final class ClassMapGenerator
{
    private array $classMap = [];
    private array $directories = [];
    private array $excludePatterns = [];

    /**
     * Додавання директорії для сканування
     *
     * @param string $directory Директорія
     * @return self
     */
    public function addDirectory(string $directory): self
    {
        $this->directories[] = rtrim($directory, '/') . '/';
        return $this;
    }

    /**
     * Додавання паттерну виключення
     *
     * @param string $pattern Паттерн (regex)
     * @return self
     */
    public function excludePattern(string $pattern): self
    {
        $this->excludePatterns[] = $pattern;
        return $this;
    }

    /**
     * Генерація class map
     *
     * @return array<string, string> Class map (клас => файл)
     */
    public function generate(): array
    {
        $this->classMap = [];

        foreach ($this->directories as $directory) {
            $this->scanDirectory($directory);
        }

        return $this->classMap;
    }

    /**
     * Сканування директорії
     *
     * @param string $directory Директорія
     * @return void
     */
    private function scanDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();

            // Перевіряємо виключення
            if ($this->shouldExclude($filePath)) {
                continue;
            }

            $this->extractClassesFromFile($filePath);
        }
    }

    /**
     * Перевірка, чи потрібно виключити файл
     *
     * @param string $filePath Шлях до файлу
     * @return bool
     */
    private function shouldExclude(string $filePath): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (preg_match($pattern, $filePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Витягування класів з файлу
     *
     * @param string $filePath Шлях до файлу
     * @return void
     */
    private function extractClassesFromFile(string $filePath): void
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return;
        }

        $tokens = token_get_all($content);
        $namespace = '';
        $inNamespace = false;

        foreach ($tokens as $index => $token) {
            if (is_array($token)) {
                [$id, $text] = $token;

                if ($id === T_NAMESPACE) {
                    $inNamespace = true;
                    $namespace = '';
                } elseif ($inNamespace && $id === T_STRING) {
                    $namespace .= $text;
                } elseif ($inNamespace && $id === T_NS_SEPARATOR) {
                    $namespace .= '\\';
                } elseif ($inNamespace && ($id === T_WHITESPACE || $id === T_SEMICOLON)) {
                    if ($id === T_SEMICOLON) {
                        $inNamespace = false;
                    }
                }
            } elseif ($token === '{' || $token === ';') {
                $inNamespace = false;
            }

            // Шукаємо оголошення класів, інтерфейсів, трейтів
            if (is_array($token) && in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
                $className = $this->extractClassName($tokens, $index);

                if ($className) {
                    $fullClassName = $namespace ? $namespace . '\\' . $className : $className;
                    $this->classMap[$fullClassName] = $filePath;
                }
            }
        }
    }

    /**
     * Витягування назви класу з токенів
     *
     * @param array<int, mixed> $tokens Масив токенів
     * @param int $index Індекс токена class/interface/trait
     * @return string|null
     */
    private function extractClassName(array $tokens, int $index): ?string
    {
        $index++;

        // Пропускаємо пробіли
        while (isset($tokens[$index]) && is_array($tokens[$index]) && $tokens[$index][0] === T_WHITESPACE) {
            $index++;
        }

        if (!isset($tokens[$index]) || !is_array($tokens[$index])) {
            return null;
        }

        $token = $tokens[$index];

        if ($token[0] === T_STRING) {
            return $token[1];
        }

        return null;
    }

    /**
     * Збереження class map у файл
     *
     * @param string $outputPath Шлях до файлу виводу
     * @return bool
     */
    public function saveToFile(string $outputPath): bool
    {
        $classMap = $this->generate();
        $content = "<?php\n\nreturn " . var_export($classMap, true) . ";\n";

        return file_put_contents($outputPath, $content) !== false;
    }
}
