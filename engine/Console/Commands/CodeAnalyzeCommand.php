<?php

/**
 * Команда аналізу коду
 *
 * @package Flowaxy\Core\System\Commands
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Commands;

class CodeAnalyzeCommand extends MakeCommand
{
    /**
     * Виконання аналізу коду
     *
     * @param array $args Аргументи команди
     * @return void
     */
    public function run(array $args): void
    {
        $path = $args[0] ?? $this->rootDir;

        echo "Аналіз коду: {$path}\n";
        echo str_repeat("=", 80) . "\n\n";

        $stats = [
            'files' => 0,
            'classes' => 0,
            'functions' => 0,
            'lines' => 0,
            'complexity' => 0,
        ];

        $files = $this->getPhpFiles($path);
        $stats['files'] = count($files);

        foreach ($files as $file) {
            $fileStats = $this->analyzeFile($file);
            $stats['classes'] += $fileStats['classes'];
            $stats['functions'] += $fileStats['functions'];
            $stats['lines'] += $fileStats['lines'];
            $stats['complexity'] += $fileStats['complexity'];
        }

        // Виводимо статистику
        echo "Статистика:\n";
        echo "  Файлів: {$stats['files']}\n";
        echo "  Класів: {$stats['classes']}\n";
        echo "  Функцій: {$stats['functions']}\n";
        echo "  Рядків коду: {$stats['lines']}\n";
        echo "  Середня складність: " . ($stats['files'] > 0 ? round($stats['complexity'] / $stats['files'], 2) : 0) . "\n";
    }

    /**
     * Аналіз файлу
     *
     * @param string $file
     * @return array<string, int>
     */
    private function analyzeFile(string $file): array
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return ['classes' => 0, 'functions' => 0, 'lines' => 0, 'complexity' => 0];
        }

        $stats = [
            'classes' => preg_match_all('/\b(?:class|interface|trait)\s+\w+/', $content),
            'functions' => preg_match_all('/\bfunction\s+\w+\s*\(/', $content),
            'lines' => substr_count($content, "\n") + 1,
            'complexity' => $this->calculateComplexity($content),
        ];

        return $stats;
    }

    /**
     * Розрахунок складності коду
     *
     * @param string $content
     * @return int
     */
    private function calculateComplexity(string $content): int
    {
        $complexity = 1; // Базова складність

        // Підрахунок операторів, що збільшують складність
        $patterns = [
            '/\bif\s*\(/',
            '/\belseif\s*\(/',
            '/\belse\b/',
            '/\bswitch\s*\(/',
            '/\bcase\s+/',
            '/\bwhile\s*\(/',
            '/\bfor\s*\(/',
            '/\bforeach\s*\(/',
            '/\bcatch\s*\(/',
            '/\?\s*:/', // Тернарний оператор
            '/\|\|/', // Логічне АБО
            '/&&/', // Логічне І
        ];

        foreach ($patterns as $pattern) {
            $complexity += preg_match_all($pattern, $content);
        }

        return $complexity;
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
