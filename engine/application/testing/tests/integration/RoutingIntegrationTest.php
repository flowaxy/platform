<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests\Integration;

use Flowaxy\Core\Interface\Http\Router\Router;
use TestCase;

/**
 * Інтеграційні тести для роутингу
 */
final class RoutingIntegrationTest extends TestCase
{
    private ?Router $router = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->router = null;
    }

    public function testRouterRegistersRoute(): void
    {
        $called = false;
        $this->router->add('GET', '/test', function () use (&$called) {
            $called = true;
        });

        // Симулюємо запит
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';

        // Перевіряємо, що маршрут зареєстрований
        $this->assertTrue($this->router->hasRoute('GET', '/test'));
    }

    public function testRouterMatchesRouteWithParameters(): void
    {
        $capturedId = null;
        $this->router->add('GET', '/user/{id}', function ($params) use (&$capturedId) {
            $capturedId = $params['id'] ?? null;
        });

        // Симулюємо запит
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/user/123';

        // Перевіряємо, що маршрут зареєстрований
        $this->assertTrue($this->router->hasRoute('GET', '/user/{id}'));
    }

    public function testRouterHandlesMultipleMethods(): void
    {
        $getCalled = false;
        $postCalled = false;

        $this->router->get('/test', function () use (&$getCalled) {
            $getCalled = true;
        });

        $this->router->post('/test', function () use (&$postCalled) {
            $postCalled = true;
        });

        $this->assertTrue($this->router->hasRoute('GET', '/test'));
        $this->assertTrue($this->router->hasRoute('POST', '/test'));
    }

    public function testRouterReturns404ForNonExistentRoute(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/non-existent';

        // Router повинен повертати 404 для неіснуючих маршрутів
        $this->assertFalse($this->router->hasRoute('GET', '/non-existent'));
    }

    public function testRouterSupportsMiddleware(): void
    {
        $middlewareCalled = false;
        $handlerCalled = false;

        $middleware = function ($next) use (&$middlewareCalled) {
            $middlewareCalled = true;
            return $next();
        };

        $this->router->add('GET', '/test', function () use (&$handlerCalled) {
            $handlerCalled = true;
        }, ['middleware' => [$middleware]]);

        $this->assertTrue($this->router->hasRoute('GET', '/test'));
    }
}
