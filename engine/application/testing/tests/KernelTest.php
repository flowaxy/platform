<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests;

use Flowaxy\Core\System\Kernel;
use Flowaxy\Core\System\Container;
use Flowaxy\Core\System\ClassAutoloader;
use TestCase;

/**
 * Тести для Kernel
 */
final class KernelTest extends TestCase
{
    private ?Kernel $kernel = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Очищаємо глобальні змінні перед кожним тестом
        if (isset($GLOBALS['__flowaxy_container'])) {
            unset($GLOBALS['__flowaxy_container']);
        }
        if (isset($GLOBALS['__flowaxy_autoloader'])) {
            unset($GLOBALS['__flowaxy_autoloader']);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->kernel = null;
        if (isset($GLOBALS['__flowaxy_container'])) {
            unset($GLOBALS['__flowaxy_container']);
        }
        if (isset($GLOBALS['__flowaxy_autoloader'])) {
            unset($GLOBALS['__flowaxy_autoloader']);
        }
    }

    public function testCreateAutoloaderReturnsInstance(): void
    {
        $autoloader = Kernel::createAutoloader();
        $this->assertInstanceOf(ClassAutoloader::class, $autoloader);
    }

    public function testCreateAutoloaderReturnsSameInstance(): void
    {
        $autoloader1 = Kernel::createAutoloader();
        $autoloader2 = Kernel::createAutoloader();
        $this->assertSame($autoloader1, $autoloader2);
    }

    public function testCreateContainerReturnsInstance(): void
    {
        $container = Kernel::createContainer();
        $this->assertInstanceOf(Container::class, $container);
    }

    public function testCreateContainerReturnsSameInstance(): void
    {
        $container1 = Kernel::createContainer();
        $container2 = Kernel::createContainer();
        $this->assertSame($container1, $container2);
    }

    public function testLoadServicesConfigRegistersServices(): void
    {
        $container = Kernel::createContainer();
        Kernel::loadServicesConfig($container);

        // Перевіряємо, що базові сервіси зареєстровані
        $this->assertTrue($container->has('hookManager') || $container->has(\Flowaxy\Core\Contracts\HookManagerInterface::class));
    }
}
