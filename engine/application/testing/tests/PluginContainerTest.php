<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests;

use Flowaxy\Core\Support\Containers\PluginContainer;
use Flowaxy\Core\Support\Containers\PluginContainerFactory;
use Flowaxy\Core\Support\Base\BasePlugin;
use TestCase;

/**
 * Тести для PluginContainer
 */
final class PluginContainerTest extends TestCase
{
    private string $testPluginDir;
    private string $testPluginSlug = 'test-plugin';

    protected function setUp(): void
    {
        parent::setUp();
        $this->testPluginDir = sys_get_temp_dir() . '/flowaxy_test_plugin_' . uniqid();
        if (!is_dir($this->testPluginDir)) {
            mkdir($this->testPluginDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        PluginContainerFactory::clearAll();
        if (is_dir($this->testPluginDir)) {
            $this->removeDirectory($this->testPluginDir);
        }
    }

    public function testCreateContainerReturnsInstance(): void
    {
        $container = PluginContainerFactory::create(
            $this->testPluginSlug,
            $this->testPluginDir,
            ['name' => 'Test Plugin']
        );

        $this->assertInstanceOf(PluginContainer::class, $container);
    }

    public function testContainerStoresPluginSlug(): void
    {
        $container = PluginContainerFactory::create(
            $this->testPluginSlug,
            $this->testPluginDir
        );

        $this->assertEquals($this->testPluginSlug, $container->getPluginSlug());
    }

    public function testContainerStoresPluginDir(): void
    {
        $container = PluginContainerFactory::create(
            $this->testPluginSlug,
            $this->testPluginDir
        );

        $this->assertEquals($this->testPluginDir . DIRECTORY_SEPARATOR, $container->getPluginDir());
    }

    public function testContainerHasIsolatedDI(): void
    {
        $container = PluginContainerFactory::create(
            $this->testPluginSlug,
            $this->testPluginDir
        );

        $diContainer = $container->getContainer();
        $this->assertNotNull($diContainer);
        $this->assertTrue($diContainer->has('plugin.slug'));
    }

    public function testGetOrCreateReturnsSameInstance(): void
    {
        $container1 = PluginContainerFactory::create(
            $this->testPluginSlug,
            $this->testPluginDir
        );
        $container2 = PluginContainerFactory::get($this->testPluginSlug);

        $this->assertSame($container1, $container2);
    }

    public function testGetPluginPathReturnsCorrectPath(): void
    {
        $container = PluginContainerFactory::create(
            $this->testPluginSlug,
            $this->testPluginDir
        );

        $path = $container->getPluginPath('test.php');
        $expected = $this->testPluginDir . DIRECTORY_SEPARATOR . 'test.php';

        $this->assertEquals($expected, $path);
    }

    public function testGetPluginPathPreventsPathTraversal(): void
    {
        $container = PluginContainerFactory::create(
            $this->testPluginSlug,
            $this->testPluginDir
        );

        $this->expectException(\RuntimeException::class);
        $container->getPluginPath('../../etc/passwd');
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
