<?php

declare(strict_types=1);

final class TestService
{
    private string $testCasesDir;
    private string $testsDir;

    public function __construct(?string $testCasesDir = null)
    {
        // Тести знаходяться в engine/application/testing/
        $this->testsDir = __DIR__ . '/../../application/testing';
        $this->testCasesDir = $testCasesDir ?? $this->testsDir . '/tests';

        // Завантажуємо необхідні файли
        require_once $this->testsDir . '/core/TestCase.php';
        require_once $this->testsDir . '/core/AssertionFailed.php';

        // Завантажуємо моки якщо вони є
        $mocksDir = $this->testsDir . '/mocks';
        if (is_dir($mocksDir)) {
            foreach (glob($mocksDir . '/*.php') ?: [] as $file) {
                require_once $file;
            }
        }
    }

    /**
     * @return array{success:bool,tests:array<int,array{class:string,method:string,status:string,message?:string,time:float}>,summary:array{total:int,passed:int,failed:int,time:float}}
     */
    public function run(?string $filter = null): array
    {
        // Не використовуємо TestRunner напряму, щоб уникнути завантаження bootstrap.php
        // Замість цього використовуємо власну логіку
        $tests = $this->collectTests($filter);

        if (empty($tests)) {
            return [
                'success' => true,
                'tests' => [],
                'summary' => [
                    'total' => 0,
                    'passed' => 0,
                    'failed' => 0,
                    'time' => 0.0,
                ],
            ];
        }

        $start = microtime(true);
        $results = [
            'tests' => [],
            'summary' => [
                'total' => count($tests),
                'passed' => 0,
                'failed' => 0,
                'time' => 0.0,
            ],
        ];

        foreach ($tests as $test) {
            $testStart = microtime(true);
            $instance = new $test['class']();
            $method = $test['method'];

            try {
                $instance->runTestMethod($method);
                $results['summary']['passed']++;
                $results['tests'][] = [
                    'class' => $test['class'],
                    'method' => $method,
                    'status' => 'passed',
                    'time' => microtime(true) - $testStart,
                ];
            } catch (AssertionFailed $e) {
                $results['summary']['failed']++;
                $results['tests'][] = [
                    'class' => $test['class'],
                    'method' => $method,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                    'time' => microtime(true) - $testStart,
                ];
            } catch (\Throwable $e) {
                $results['summary']['failed']++;
                $results['tests'][] = [
                    'class' => $test['class'],
                    'method' => $method,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'time' => microtime(true) - $testStart,
                ];
            }
        }

        $results['summary']['time'] = microtime(true) - $start;
        $results['success'] = $results['summary']['failed'] === 0;

        return $results;
    }

    /**
     * @return array<int,array{class:string,method:string}>
     */
    public function listTests(?string $filter = null): array
    {
        return $this->collectTests($filter);
    }

    /**
     * @return array<int,array{class:string,method:string}>
     */
    private function collectTests(?string $filter = null): array
    {
        // Завантажуємо тести з основної директорії
        foreach (glob($this->testCasesDir . '/*Test.php') ?: [] as $file) {
            require_once $file;
        }

        // Завантажуємо тести з плагінів
        // Шлях: engine/core/system -> engine -> корінь проекту -> plugins
        $rootDir = dirname(__DIR__, 3);
        $pluginsDir = $rootDir . '/plugins';
        if (is_dir($pluginsDir)) {
            foreach (glob($pluginsDir . '/*/tests/*Test.php') ?: [] as $file) {
                require_once $file;
            }
        }

        $tests = [];
        foreach (get_declared_classes() as $class) {
            if (! is_subclass_of($class, TestCase::class)) {
                continue;
            }

            $instance = new $class();
            foreach ($instance->getTestMethods() as $method) {
                $label = "{$class}::{$method}";
                if ($filter !== null && stripos($label, $filter) === false) {
                    continue;
                }
                $tests[] = ['class' => $class, 'method' => $method];
            }
        }

        return $tests;
    }

    /**
     * Запуск тестів для конкретного плагіна
     * 
     * Шукає тести, що містять slug плагіна в назві класу
     * 
     * @param string $pluginSlug Slug плагіна (наприклад, 'bot-blocker')
     * @return array Результати тестування
     */
    public function runPluginTests(string $pluginSlug): array
    {
        // Конвертуємо slug в назву для пошуку: 'bot-blocker' -> 'BotBlocker'
        $pluginName = str_replace([' ', '-'], '', ucwords(str_replace('-', ' ', $pluginSlug)));
        
        // Шукаємо тести, що містять назву плагіна (наприклад, 'BotBlocker')
        $filter = $pluginName;
        $results = $this->run($filter);

        return $results;
    }

    /**
     * Запуск тестів для конкретної теми
     */
    public function runThemeTests(string $themeSlug): array
    {
        // Шукаємо тести, що містять "Theme" в назві класу
        $filter = 'Theme';
        $results = $this->run($filter);

        // Додатково шукаємо тести, специфічні для цієї теми (якщо вони є)
        $themeSpecificFilter = "Theme.*{$themeSlug}";
        $themeResults = $this->run($themeSpecificFilter);

        // Об'єднуємо результати
        if (! empty($themeResults['tests'])) {
            $results['tests'] = array_merge($results['tests'] ?? [], $themeResults['tests']);
            $results['summary']['total'] = count($results['tests']);
            $results['summary']['passed'] = count(array_filter($results['tests'], fn ($t) => $t['status'] === 'passed'));
            $results['summary']['failed'] = count(array_filter($results['tests'], fn ($t) => $t['status'] !== 'passed'));
        }

        return $results;
    }
}
