<?php

/**
 * Команда тестування продуктивності
 *
 * @package Flowaxy\Core\System\Commands
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Commands;

class PerformanceTestCommand extends MakeCommand
{
    /**
     * Виконання тестування продуктивності
     *
     * @param array $args Аргументи команди
     * @return void
     */
    public function run(array $args): void
    {
        $test = $args[0] ?? 'all';

        echo "Тестування продуктивності\n";
        echo str_repeat("=", 80) . "\n\n";

        $results = [];

        if ($test === 'all' || $test === 'cache') {
            $results['cache'] = $this->testCache();
        }

        if ($test === 'all' || $test === 'database') {
            $results['database'] = $this->testDatabase();
        }

        if ($test === 'all' || $test === 'hooks') {
            $results['hooks'] = $this->testHooks();
        }

        if ($test === 'all' || $test === 'autoloader') {
            $results['autoloader'] = $this->testAutoloader();
        }

        // Виводимо результати
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "Результати тестування продуктивності:\n\n";

        foreach ($results as $testName => $result) {
            echo ucfirst($testName) . ":\n";
            foreach ($result as $metric => $value) {
                echo "  {$metric}: {$value}\n";
            }
            echo "\n";
        }
    }

    /**
     * Тестування кешу
     *
     * @return array<string, string>
     */
    private function testCache(): array
    {
        $iterations = 1000;
        $start = microtime(true);

        // Спробуємо отримати Cache
        $cache = null;
        if (class_exists(\Flowaxy\Core\Infrastructure\Cache\Cache::class)) {
            $cache = \Flowaxy\Core\Infrastructure\Cache\Cache::getInstance();
        }

        if ($cache === null) {
            return ['status' => 'Cache не доступний'];
        }

        // Тест запису
        $writeStart = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $cache->set("test_key_{$i}", "test_value_{$i}");
        }
        $writeTime = microtime(true) - $writeStart;

        // Тест читання
        $readStart = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $cache->get("test_key_{$i}");
        }
        $readTime = microtime(true) - $readStart;

        // Очищення
        for ($i = 0; $i < $iterations; $i++) {
            $cache->delete("test_key_{$i}");
        }

        $totalTime = microtime(true) - $start;

        return [
            'iterations' => (string)$iterations,
            'write_time' => round($writeTime, 4) . 's',
            'read_time' => round($readTime, 4) . 's',
            'total_time' => round($totalTime, 4) . 's',
            'ops_per_sec' => round($iterations / $totalTime, 2),
        ];
    }

    /**
     * Тестування бази даних
     *
     * @return array<string, string>
     */
    private function testDatabase(): array
    {
        if (!class_exists('DatabaseHelper')) {
            return ['status' => 'Database не доступний'];
        }

        try {
            $db = \DatabaseHelper::getConnection();
            $iterations = 100;

            $start = microtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                $stmt = $db->query("SELECT 1");
                $stmt->fetch();
            }
            $time = microtime(true) - $start;

            return [
                'iterations' => (string)$iterations,
                'time' => round($time, 4) . 's',
                'queries_per_sec' => round($iterations / $time, 2),
            ];
        } catch (\Exception $e) {
            return ['status' => 'Помилка: ' . $e->getMessage()];
        }
    }

    /**
     * Тестування хуків
     *
     * @return array<string, string>
     */
    private function testHooks(): array
    {
        $hookManager = null;
        if (function_exists('hooks')) {
            $hookManager = hooks();
        } elseif (class_exists(\Flowaxy\Core\System\HookManager::class)) {
            $hookManager = new \Flowaxy\Core\System\HookManager();
        }

        if ($hookManager === null) {
            return ['status' => 'HookManager не доступний'];
        }

        $iterations = 1000;
        $hooksCount = 10;

        // Реєстрація хуків
        $registerStart = microtime(true);
        for ($i = 0; $i < $hooksCount; $i++) {
            $hookManager->on("test_hook_{$i}", fn() => null);
        }
        $registerTime = microtime(true) - $registerStart;

        // Виклик хуків
        $dispatchStart = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $hookManager->dispatch("test_hook_" . ($i % $hooksCount));
        }
        $dispatchTime = microtime(true) - $dispatchStart;

        return [
            'hooks_count' => (string)$hooksCount,
            'iterations' => (string)$iterations,
            'register_time' => round($registerTime, 4) . 's',
            'dispatch_time' => round($dispatchTime, 4) . 's',
            'dispatches_per_sec' => round($iterations / $dispatchTime, 2),
        ];
    }

    /**
     * Тестування автозавантажувача
     *
     * @return array<string, string>
     */
    private function testAutoloader(): array
    {
        $classes = [
            'Flowaxy\Core\System\Container',
            'Flowaxy\Core\System\HookManager',
            'Flowaxy\Core\Infrastructure\Cache\Cache',
        ];

        $start = microtime(true);
        foreach ($classes as $class) {
            if (class_exists($class)) {
                // Клас вже завантажений
            }
        }
        $time = microtime(true) - $start;

        return [
            'classes' => (string)count($classes),
            'time' => round($time, 4) . 's',
            'avg_time_per_class' => round($time / count($classes), 6) . 's',
        ];
    }
}
