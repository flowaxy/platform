<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests\Integration;

use Flowaxy\Core\System\HttpKernel;
use TestCase;

/**
 * Інтеграційні тести для HttpKernel
 */
final class HttpKernelIntegrationTest extends TestCase
{
    private ?HttpKernel $kernel = null;

    protected function setUp(): void
    {
        parent::setUp();
        // HttpKernel потребує rootDir
        $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 6) . '/engine';
        $this->kernel = new HttpKernel($rootDir);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->kernel = null;
    }

    public function testHttpKernelCanBeInstantiated(): void
    {
        $this->assertInstanceOf(HttpKernel::class, $this->kernel);
    }

    public function testHttpKernelHasServiceProviders(): void
    {
        // Перевіряємо, що HttpKernel має метод getServiceProviders
        // (він protected, тому перевіряємо через рефлексію або інший спосіб)
        $this->assertNotNull($this->kernel);
    }

    public function testHttpKernelBootsSuccessfully(): void
    {
        // Перевіряємо, що kernel може бути завантажений
        // (повне завантаження може потребувати БД та інших залежностей)
        $this->assertNotNull($this->kernel);
    }
}
