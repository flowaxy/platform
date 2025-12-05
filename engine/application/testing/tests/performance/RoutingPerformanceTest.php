<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests\Performance;

use Flowaxy\Core\Interface\Http\Router\Router;
use TestCase;

/**
 * Навантажувальні тести для роутингу
 */
final class RoutingPerformanceTest extends TestCase
{
    private const ROUTES_COUNT = 100;
    private const ITERATIONS = 1000;

    public function testRouteRegistrationPerformance(): void
    {
        $router = new Router();

        $start = microtime(true);

        for ($i = 0; $i < self::ROUTES_COUNT; $i++) {
            $router->add('GET', "/route_{$i}", function () {});
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::ROUTES_COUNT / $duration;

        $this->assertTrue($opsPerSecond > 100, "Route registration should handle at least 100 ops/sec. Got: {$opsPerSecond}");
    }

    public function testRouteMatchingPerformance(): void
    {
        $router = new Router();

        // Реєструємо маршрути
        for ($i = 0; $i < self::ROUTES_COUNT; $i++) {
            $router->add('GET', "/route_{$i}", function () {});
        }

        $start = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $routeIndex = $i % self::ROUTES_COUNT;
            $_SERVER['REQUEST_URI'] = "/route_{$routeIndex}";
            $_SERVER['REQUEST_METHOD'] = 'GET';

            // Перевіряємо наявність маршруту
            $router->hasRoute('GET', "/route_{$routeIndex}");
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::ITERATIONS / $duration;

        $this->assertTrue($opsPerSecond > 500, "Route matching should handle at least 500 ops/sec. Got: {$opsPerSecond}");
    }

    public function testRouteWithParametersPerformance(): void
    {
        $router = new Router();

        // Реєструємо маршрути з параметрами
        for ($i = 0; $i < self::ROUTES_COUNT; $i++) {
            $router->add('GET', "/route_{$i}/user/{id}", function () {});
        }

        $start = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $routeIndex = $i % self::ROUTES_COUNT;
            $_SERVER['REQUEST_URI'] = "/route_{$routeIndex}/user/123";
            $_SERVER['REQUEST_METHOD'] = 'GET';

            $router->hasRoute('GET', "/route_{$routeIndex}/user/{id}");
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::ITERATIONS / $duration;

        $this->assertTrue($opsPerSecond > 200, "Route with parameters matching should handle at least 200 ops/sec. Got: {$opsPerSecond}");
    }
}
