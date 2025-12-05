<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests;

use Flowaxy\Core\Support\Containers\ThemeContainer;
use Flowaxy\Core\Support\Containers\ThemeContainerFactory;
use TestCase;

/**
 * Тести для ThemeContainer
 */
final class ThemeContainerTest extends TestCase
{
    private string $testThemeDir;
    private string $testThemeSlug = 'test-theme';

    protected function setUp(): void
    {
        parent::setUp();
        $this->testThemeDir = sys_get_temp_dir() . '/flowaxy_test_theme_' . uniqid();
        if (!is_dir($this->testThemeDir)) {
            mkdir($this->testThemeDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        ThemeContainerFactory::clearAll();
        if (is_dir($this->testThemeDir)) {
            $this->removeDirectory($this->testThemeDir);
        }
    }

    public function testCreateContainerReturnsInstance(): void
    {
        $container = ThemeContainerFactory::create(
            $this->testThemeSlug,
            $this->testThemeDir,
            ['name' => 'Test Theme']
        );

        $this->assertInstanceOf(ThemeContainer::class, $container);
    }

    public function testContainerStoresThemeSlug(): void
    {
        $container = ThemeContainerFactory::create(
            $this->testThemeSlug,
            $this->testThemeDir
        );

        $this->assertEquals($this->testThemeSlug, $container->getThemeSlug());
    }

    public function testContainerStoresThemeDir(): void
    {
        $container = ThemeContainerFactory::create(
            $this->testThemeSlug,
            $this->testThemeDir
        );

        $this->assertEquals($this->testThemeDir . DIRECTORY_SEPARATOR, $container->getThemeDir());
    }

    public function testContainerHasIsolatedDI(): void
    {
        $container = ThemeContainerFactory::create(
            $this->testThemeSlug,
            $this->testThemeDir
        );

        $diContainer = $container->getContainer();
        $this->assertNotNull($diContainer);
        $this->assertTrue($diContainer->has('theme.slug'));
    }

    public function testGetOrCreateReturnsSameInstance(): void
    {
        $container1 = ThemeContainerFactory::create(
            $this->testThemeSlug,
            $this->testThemeDir
        );
        $container2 = ThemeContainerFactory::get($this->testThemeSlug);

        $this->assertSame($container1, $container2);
    }

    public function testGetTemplatePathReturnsCorrectPath(): void
    {
        $container = ThemeContainerFactory::create(
            $this->testThemeSlug,
            $this->testThemeDir
        );

        $path = $container->getTemplatePath('index.php');
        $expected = $this->testThemeDir . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'index.php';

        $this->assertEquals($expected, $path);
    }

    public function testGetAssetPathReturnsCorrectPath(): void
    {
        $container = ThemeContainerFactory::create(
            $this->testThemeSlug,
            $this->testThemeDir
        );

        $path = $container->getAssetPath('style.css');
        $expected = $this->testThemeDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'style.css';

        $this->assertEquals($expected, $path);
    }

    public function testActivateSetsActiveState(): void
    {
        $container = ThemeContainerFactory::create(
            $this->testThemeSlug,
            $this->testThemeDir
        );

        $this->assertFalse($container->isActive());
        $container->activate();
        $this->assertTrue($container->isActive());
    }

    public function testDeactivateUnsetsActiveState(): void
    {
        $container = ThemeContainerFactory::create(
            $this->testThemeSlug,
            $this->testThemeDir
        );

        $container->activate();
        $this->assertTrue($container->isActive());
        $container->deactivate();
        $this->assertFalse($container->isActive());
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
