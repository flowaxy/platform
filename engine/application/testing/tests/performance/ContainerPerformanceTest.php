<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests\Performance;

use Flowaxy\Core\System\Container;
use TestCase;

/**
 * Навантажувальні тести для DI контейнера
 */
final class ContainerPerformanceTest extends TestCase
{
    private const ITERATIONS = 1000;
    private const SERVICES_COUNT = 100;

    public function testContainerSingletonPerformance(): void
    {
        $container = new Container();

        // Реєструємо сервіси як singleton
        for ($i = 0; $i < self::SERVICES_COUNT; $i++) {
            $container->singleton("service_{$i}", fn() => new \stdClass());
        }

        $start = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $serviceIndex = $i % self::SERVICES_COUNT;
            $container->make("service_{$serviceIndex}");
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::ITERATIONS / $duration;

        $this->assertTrue($opsPerSecond > 1000, "Container singleton should handle at least 1000 ops/sec. Got: {$opsPerSecond}");
    }

    public function testContainerBindPerformance(): void
    {
        $container = new Container();

        $start = microtime(true);

        for ($i = 0; $i < self::SERVICES_COUNT; $i++) {
            $container->bind("service_{$i}", fn() => new \stdClass());
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::SERVICES_COUNT / $duration;

        $this->assertTrue($opsPerSecond > 100, "Container bind should handle at least 100 ops/sec. Got: {$opsPerSecond}");
    }

    public function testContainerMakePerformance(): void
    {
        $container = new Container();

        // Реєструємо сервіси
        for ($i = 0; $i < self::SERVICES_COUNT; $i++) {
            $container->bind("service_{$i}", fn() => new \stdClass());
        }

        $start = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $serviceIndex = $i % self::SERVICES_COUNT;
            $container->make("service_{$serviceIndex}");
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::ITERATIONS / $duration;

        $this->assertTrue($opsPerSecond > 500, "Container make should handle at least 500 ops/sec. Got: {$opsPerSecond}");
    }
}
