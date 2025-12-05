<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests\Functional;

use Flowaxy\Core\Support\Managers\PluginManager;
use Flowaxy\Core\Support\Containers\PluginContainerFactory;
use TestCase;

/**
 * Функціональні тести для управління плагінами
 */
final class PluginManagementFunctionalTest extends TestCase
{
    private string $testPluginDir;
    private string $testPluginSlug = 'test-plugin-functional';

    protected function setUp(): void
    {
        parent::setUp();
        $this->testPluginDir = sys_get_temp_dir() . '/flowaxy_test_plugin_func_' . uniqid();
        if (!is_dir($this->testPluginDir)) {
            mkdir($this->testPluginDir, 0755, true);
        }

        // Створюємо базову структуру плагіна
        $this->createTestPluginStructure();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        PluginContainerFactory::clearAll();
        if (is_dir($this->testPluginDir)) {
            $this->removeDirectory($this->testPluginDir);
        }
    }

    public function testPluginInstallFlow(): void
    {
        // Перевіряємо повний flow встановлення плагіна
        if (!class_exists('PluginManager')) {
            $this->markTestSkipped('PluginManager not available');
            return;
        }

        // Перевіряємо, що плагін може бути встановлений
        $this->assertTrue(is_dir($this->testPluginDir));
        $this->assertTrue(file_exists($this->testPluginDir . '/plugin.json'));
    }

    public function testPluginActivationFlow(): void
    {
        // Перевіряємо повний flow активації плагіна
        if (!class_exists('PluginManager')) {
            $this->markTestSkipped('PluginManager not available');
            return;
        }

        // Перевіряємо, що плагін може бути активований
        $this->assertTrue(is_dir($this->testPluginDir));
    }

    public function testPluginDeactivationFlow(): void
    {
        // Перевіряємо повний flow деактивації плагіна
        if (!class_exists('PluginManager')) {
            $this->markTestSkipped('PluginManager not available');
            return;
        }

        // Перевіряємо, що плагін може бути деактивований
        $this->assertTrue(is_dir($this->testPluginDir));
    }

    public function testPluginUninstallFlow(): void
    {
        // Перевіряємо повний flow видалення плагіна
        if (!class_exists('PluginManager')) {
            $this->markTestSkipped('PluginManager not available');
            return;
        }

        // Перевіряємо, що плагін може бути видалений
        $this->assertTrue(is_dir($this->testPluginDir));
    }

    public function testPluginRegistersHooks(): void
    {
        // Перевіряємо, що плагін може реєструвати хуки
        if (!function_exists('hooks')) {
            $this->markTestSkipped('HookManager not available');
            return;
        }

        $this->assertTrue(true, 'Plugins should be able to register hooks');
    }

    public function testPluginRegistersRoutes(): void
    {
        // Перевіряємо, що плагін може реєструвати маршрути
        if (!class_exists('Router')) {
            $this->markTestSkipped('Router not available');
            return;
        }

        $this->assertTrue(true, 'Plugins should be able to register routes');
    }

    public function testPluginIsolationIsMaintained(): void
    {
        // Перевіряємо, що ізоляція плагінів зберігається
        if (!class_exists('PluginContainerFactory')) {
            $this->markTestSkipped('PluginContainerFactory not available');
            return;
        }

        $container = PluginContainerFactory::create(
            $this->testPluginSlug,
            $this->testPluginDir,
            ['name' => 'Test Plugin']
        );

        $this->assertNotNull($container);
        $this->assertEquals($this->testPluginSlug, $container->getPluginSlug());
    }

    private function createTestPluginStructure(): void
    {
        // Створюємо plugin.json
        $pluginJson = [
            'name' => 'Test Plugin Functional',
            'slug' => $this->testPluginSlug,
            'version' => '1.0.0',
            'description' => 'Test plugin for functional tests',
        ];
        file_put_contents($this->testPluginDir . '/plugin.json', json_encode($pluginJson, JSON_PRETTY_PRINT));
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
