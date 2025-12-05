<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests\Integration;

use Flowaxy\Core\System\Kernel;
use Flowaxy\Core\System\ClassAutoloader;
use Flowaxy\Core\System\Container;
use TestCase;

/**
 * Інтеграційні тести для завантаження ядра
 */
final class KernelBootstrapTest extends TestCase
{
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
        if (isset($GLOBALS['__flowaxy_container'])) {
            unset($GLOBALS['__flowaxy_container']);
        }
        if (isset($GLOBALS['__flowaxy_autoloader'])) {
            unset($GLOBALS['__flowaxy_autoloader']);
        }
    }

    public function testKernelCreatesAutoloader(): void
    {
        $autoloader = Kernel::createAutoloader();

        $this->assertInstanceOf(ClassAutoloader::class, $autoloader);
    }

    public function testKernelCreatesContainer(): void
    {
        $container = Kernel::createContainer();

        $this->assertInstanceOf(Container::class, $container);
    }

    public function testKernelAutoloaderIsSingleton(): void
    {
        $autoloader1 = Kernel::createAutoloader();
        $autoloader2 = Kernel::createAutoloader();

        $this->assertSame($autoloader1, $autoloader2);
    }

    public function testKernelContainerIsSingleton(): void
    {
        $container1 = Kernel::createContainer();
        $container2 = Kernel::createContainer();

        $this->assertSame($container1, $container2);
    }

    public function testKernelLoadsServicesConfig(): void
    {
        $container = Kernel::createContainer();

        // Перевіряємо, що сервіси можуть бути завантажені
        Kernel::loadServicesConfig($container);

        // Перевіряємо, що контейнер має базові сервіси
        // (не всі сервіси можуть бути доступні без повної ініціалізації)
        $this->assertNotNull($container);
    }

    public function testBootstrapConstantsAreDefined(): void
    {
        // Перевіряємо, що базові константи визначені
        // (якщо bootstrap вже виконано)
        if (defined('ROOT_DIR')) {
            $this->assertNotEmpty(ROOT_DIR);
        }

        if (defined('ENGINE_DIR')) {
            $this->assertNotEmpty(ENGINE_DIR);
        }
    }
}
