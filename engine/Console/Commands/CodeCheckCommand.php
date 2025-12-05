<?php

/**
 * Команда перевірки коду
 *
 * @package Flowaxy\Core\System\Commands
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Commands;

class CodeCheckCommand extends MakeCommand
{
    /**
     * Виконання перевірки коду
     *
     * @param array $args Аргументи команди
     * @return void
     */
    public function run(array $args): void
    {
        $path = $args[0] ?? $this->rootDir;
        $checkSyntax = !isset($args['no-syntax']);
        $checkStyle = !isset($args['no-style']);

        echo "Перевірка коду: {$path}\n";
        echo str_repeat("=", 80) . "\n\n";

        $errors = [];
        $warnings = [];

        if ($checkSyntax) {
            echo "Перевірка синтаксису PHP...\n";
            $syntaxErrors = $this->checkSyntax($path);
            $errors = array_merge($errors, $syntaxErrors);
        }

        if ($checkStyle) {
            echo "Перевірка стилю коду...\n";
            $styleIssues = $this->checkStyle($path);
            $warnings = array_merge($warnings, $styleIssues);
        }

        // Виводимо результати
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "Результати перевірки:\n";
        echo "  Помилки: " . count($errors) . "\n";
        echo "  Попередження: " . count($warnings) . "\n\n";

        if (!empty($errors)) {
            echo "Помилки:\n";
            foreach ($errors as $error) {
                echo "  ✗ {$error}\n";
            }
            echo "\n";
        }

        if (!empty($warnings)) {
            echo "Попередження:\n";
            foreach ($warnings as $warning) {
                echo "  ⚠ {$warning}\n";
            }
            echo "\n";
        }

        if (empty($errors) && empty($warnings)) {
            echo "✓ Перевірка коду пройшла успішно!\n";
            exit(0);
        } else {
            exit(empty($errors) ? 0 : 1);
        }
    }

    /**
     * Перевірка синтаксису PHP
     *
     * @param string $path
     * @return array<string>
     */
    private function checkSyntax(string $path): array
    {
        $errors = [];
        $files = $this->getPhpFiles($path);

        foreach ($files as $file) {
            $output = [];
            $returnVar = 0;
            exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $returnVar);

            if ($returnVar !== 0) {
                $errors[] = $file . ": " . implode("\n", $output);
            }
        }

        return $errors;
    }

    /**
     * Перевірка стилю коду
     *
     * @param string $path
     * @return array<string>
     */
    private function checkStyle(string $path): array
    {
        $warnings = [];
        $files = $this->getPhpFiles($path);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            // Перевірка на наявність declare(strict_types=1)
            if (!str_contains($content, 'declare(strict_types=1);')) {
                $warnings[] = "{$file}: Відсутній declare(strict_types=1)";
            }

            // Перевірка на наявність PHPDoc для класів
            if (preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+\w+/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $offset = $matches[0][1];
                $beforeClass = substr($content, max(0, $offset - 500), $offset);
                if (!preg_match('/\/\*\*[\s\S]*?\*\//', $beforeClass)) {
                    $warnings[] = "{$file}: Клас без PHPDoc коментаря";
                }
            }
        }

        return $warnings;
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
