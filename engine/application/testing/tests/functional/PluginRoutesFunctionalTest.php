<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests\Functional;

use Flowaxy\Core\Interface\Http\Router\Router;
use TestCase;

/**
 * Функціональні тести для реєстрації маршрутів плагінами
 */
final class PluginRoutesFunctionalTest extends TestCase
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

    public function testPluginCanRegisterRoute(): void
    {
        $called = false;

        $this->router->add('GET', '/plugin/test', function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($this->router->hasRoute('GET', '/plugin/test'));
    }

    public function testPluginCanRegisterMultipleRoutes(): void
    {
        $getCalled = false;
        $postCalled = false;

        $this->router->get('/plugin/test', function () use (&$getCalled) {
            $getCalled = true;
        });

        $this->router->post('/plugin/test', function () use (&$postCalled) {
            $postCalled = true;
        });

        $this->assertTrue($this->router->hasRoute('GET', '/plugin/test'));
        $this->assertTrue($this->router->hasRoute('POST', '/plugin/test'));
    }

    public function testPluginCanRegisterRouteWithParameters(): void
    {
        $capturedId = null;

        $this->router->add('GET', '/plugin/user/{id}', function ($params) use (&$capturedId) {
            $capturedId = $params['id'] ?? null;
        });

        $this->assertTrue($this->router->hasRoute('GET', '/plugin/user/{id}'));
    }

    public function testPluginCanRegisterAdminRoute(): void
    {
        $called = false;

        $this->router->add('GET', '/admin/plugin/test', function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($this->router->hasRoute('GET', '/admin/plugin/test'));
    }

    public function testPluginRoutesAreIsolated(): void
    {
        // Перевіряємо, що маршрути плагінів не конфліктують
        $this->router->add('GET', '/plugin/route1', function () {});
        $this->router->add('GET', '/plugin/route2', function () {});

        $this->assertTrue($this->router->hasRoute('GET', '/plugin/route1'));
        $this->assertTrue($this->router->hasRoute('GET', '/plugin/route2'));
    }
}
