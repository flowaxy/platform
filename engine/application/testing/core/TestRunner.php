<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/Console.php';

final class TestRunner
{
    public function __construct(
        private readonly string $testsDir,
        private readonly ?string $filter = null
    ) {
    }

    public function listTests(): void
    {
        foreach ($this->collectTests() as $test) {
            Console::write(sprintf('%s::%s', $test['class'], $test['method']));
        }
    }

    public function run(): bool
    {
        $tests = $this->collectTests();
        if (empty($tests)) {
            Console::write('No tests found.', 'yellow');

            return true;
        }

        $start = microtime(true);
        $results = [
            'methods' => count($tests),
            'passed' => 0,
            'failed' => 0,
        ];

        foreach ($tests as $test) {
            $instance = new $test['class']();
            $method = $test['method'];

            try {
                $instance->runTestMethod($method);
                $results['passed']++;
                Console::write("[PASS] {$test['class']}::{$method}", 'green');
            } catch (AssertionFailed $e) {
                $results['failed']++;
                Console::write("[FAIL] {$test['class']}::{$method} — {$e->getMessage()}", 'red');
            } catch (\Throwable $e) {
                $results['failed']++;
                Console::write("[ERROR] {$test['class']}::{$method} — {$e->getMessage()}", 'red');
            }
        }

        $duration = microtime(true) - $start;
        Console::write(str_repeat('-', 50));
        Console::write(sprintf(
            'Tests: %d, Passed: %d, Failed: %d, Time: %.2fs',
            $results['methods'],
            $results['passed'],
            $results['failed'],
            $duration
        ), $results['failed'] === 0 ? 'green' : 'red');

        return $results['failed'] === 0;
    }

    /**
     * @return array<int,array{class:string,method:string}>
     */
    private function collectTests(): array
    {
        // Завантажуємо тести з основної директорії
        foreach (glob($this->testsDir . '/*Test.php') ?: [] as $file) {
            require_once $file;
        }

        // Завантажуємо тести з плагінів
        // Шлях: engine/application/testing/core -> engine/application/testing -> engine -> корінь проекту -> plugins
        $rootDir = dirname(__DIR__, 4);
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
                if ($this->filter !== null && stripos($label, $this->filter) === false) {
                    continue;
                }
                $tests[] = ['class' => $class, 'method' => $method];
            }
        }

        return $tests;
    }
}
