<?php

/**
 * CLI команда для генерації class map
 * 
 * @package Engine\System
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/ClassMapGenerator.php';

final class ClassMapCommand
{
    /**
     * Виконання команди генерації class map
     * 
     * @param array<string> $args Аргументи командного рядка
     * @return void
     */
    public static function run(array $args): void
    {
        $outputFile = $args[0] ?? dirname(__DIR__, 2) . '/storage/config/classmap.php';
        $rootDir = dirname(__DIR__, 2);

        echo "Generating class map...\n";

        $generator = new ClassMapGenerator();
        
        // Додаємо основні директорії
        $generator->addDirectory($rootDir . '/core');
        $generator->addDirectory($rootDir . '/infrastructure');
        $generator->addDirectory($rootDir . '/domain');
        $generator->addDirectory($rootDir . '/interface');

        // Виключаємо тести та вендори
        $generator->excludePattern('/\/tests\//');
        $generator->excludePattern('/\/vendor\//');
        $generator->excludePattern('/\/node_modules\//');

        if ($generator->saveToFile($outputFile)) {
            $classMap = $generator->generate();
            echo "Class map generated successfully!\n";
            echo "Classes found: " . count($classMap) . "\n";
            echo "Output file: {$outputFile}\n";
        } else {
            echo "Error: Failed to save class map to {$outputFile}\n";
            exit(1);
        }
    }
}

