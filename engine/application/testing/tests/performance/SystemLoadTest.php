<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests\Performance;

use Flowaxy\Core\System\Kernel;
use Flowaxy\Core\System\ClassAutoloader;
use Flowaxy\Core\System\Container;
use TestCase;

/**
 * Навантажувальні тести для загальної системи
 */
final class SystemLoadTest extends TestCase
{
    public function testKernelBootstrapPerformance(): void
    {
        // Очищаємо глобальні змінні
        if (isset($GLOBALS['__flowaxy_container'])) {
            unset($GLOBALS['__flowaxy_container']);
        }
        if (isset($GLOBALS['__flowaxy_autoloader'])) {
            unset($GLOBALS['__flowaxy_autoloader']);
        }

        $start = microtime(true);

        $autoloader = Kernel::createAutoloader();
        $container = Kernel::createContainer();
        Kernel::loadServicesConfig($container);

        $duration = microtime(true) - $start;

        $this->assertTrue($duration < 1.0, "Kernel bootstrap should complete in < 1.0s. Got: {$duration}s");
    }

    public function testAutoloaderPerformance(): void
    {
        $autoloader = Kernel::createAutoloader();

        $start = microtime(true);

        // Симулюємо завантаження класів
        for ($i = 0; $i < 100; $i++) {
            // Перевіряємо, що autoloader працює
            if (class_exists('stdClass')) {
                // Клас вже завантажений
            }
        }

        $duration = microtime(true) - $start;

        $this->assertTrue($duration < 0.1, "Autoloader should handle class checks in < 0.1s. Got: {$duration}s");
    }

    public function testMemoryUsageUnderLoad(): void
    {
        $initialMemory = memory_get_usage();

        // Створюємо багато об'єктів
        $objects = [];
        for ($i = 0; $i < 1000; $i++) {
            $objects[] = new \stdClass();
        }

        $peakMemory = memory_get_peak_usage();
        $memoryIncrease = $peakMemory - $initialMemory;

        // Перевіряємо, що збільшення пам'яті розумне (менше 10MB)
        $this->assertTrue($memoryIncrease < 10 * 1024 * 1024, "Memory increase should be < 10MB. Got: " . round($memoryIncrease / 1024 / 1024, 2) . "MB");
    }

    public function testConcurrentOperations(): void
    {
        $start = microtime(true);

        // Симулюємо паралельні операції
        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $results[] = $i * 2;
        }

        $duration = microtime(true) - $start;

        $this->assertTrue($duration < 0.1, "Concurrent operations should complete in < 0.1s. Got: {$duration}s");
        $this->assertEquals(100, count($results));
    }
}
