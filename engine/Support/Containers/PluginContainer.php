<?php

/**
 * Ізольований контейнер для плагіна
 *
 * Забезпечує ізоляцію плагіна від ядра та інших плагінів.
 * Плагін може взаємодіяти з системою тільки через хуки та фільтри.
 *
 * @package Flowaxy\Core\Support\Containers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Support\Containers;

require_once __DIR__ . '/../../core/system/Container.php';
require_once __DIR__ . '/../../Contracts/ContainerInterface.php';
require_once __DIR__ . '/../Isolation/PluginIsolation.php';

use Flowaxy\Core\System\Container;
use Flowaxy\Core\Contracts\ContainerInterface;
use Flowaxy\Core\Support\Isolation\PluginIsolation;

final class PluginContainer
{
    /**
     * @var ContainerInterface Власний DI контейнер плагіна
     */
    private ContainerInterface $container;

    /**
     * @var string Slug плагіна
     */
    private string $pluginSlug;

    /**
     * @var string Директорія плагіна
     */
    private string $pluginDir;

    /**
     * @var array<string, mixed> Конфігурація плагіна
     */
    private array $config;

    /**
     * @var object|null Екземпляр плагіна
     */
    private ?object $pluginInstance = null;

    /**
     * @var bool Чи плагін активований
     */
    private bool $isActive = false;

    /**
     * Конструктор
     *
     * @param string $pluginSlug Slug плагіна
     * @param string $pluginDir Директорія плагіна
     * @param array<string, mixed> $config Конфігурація плагіна
     */
    public function __construct(string $pluginSlug, string $pluginDir, array $config = [])
    {
        $this->pluginSlug = $pluginSlug;
        $this->pluginDir = rtrim($pluginDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->config = $config;
        $this->container = new Container();

        $this->initializeContainer();
    }

    /**
     * Ініціалізація контейнера
     *
     * @return void
     */
    private function initializeContainer(): void
    {
        // Реєструємо базові сервіси для плагіна
        $this->container->singleton('plugin.slug', fn() => $this->pluginSlug);
        $this->container->singleton('plugin.dir', fn() => $this->pluginDir);
        $this->container->singleton('plugin.config', fn() => $this->config);

        // Реєструємо обмежений доступ до хуків (тільки через HookManager)
        // Плагін не може напряму звертатися до ядра

        // Ініціалізуємо систему ізоляції
        // Визначаємо кореневу директорію проекту (4 рівні вгору від engine/core/support/containers)
        $projectRoot = dirname(dirname(dirname(dirname(__DIR__))));
        if (file_exists($projectRoot . DIRECTORY_SEPARATOR . 'index.php')) {
            PluginIsolation::initialize($projectRoot);
        }
    }

    /**
     * Отримання DI контейнера плагіна
     *
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Отримання slug плагіна
     *
     * @return string
     */
    public function getPluginSlug(): string
    {
        return $this->pluginSlug;
    }

    /**
     * Отримання директорії плагіна
     *
     * @return string
     */
    public function getPluginDir(): string
    {
        return $this->pluginDir;
    }

    /**
     * Отримання конфігурації плагіна
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Встановлення екземпляра плагіна
     *
     * @param object $instance
     * @return void
     */
    public function setPluginInstance(object $instance): void
    {
        $this->pluginInstance = $instance;
    }

    /**
     * Отримання екземпляра плагіна
     *
     * @return object|null
     */
    public function getPluginInstance(): ?object
    {
        return $this->pluginInstance;
    }

    /**
     * Активування плагіна
     *
     * @return void
     */
    public function activate(): void
    {
        $this->isActive = true;
    }

    /**
     * Деактивування плагіна
     *
     * @return void
     */
    public function deactivate(): void
    {
        $this->isActive = false;
    }

    /**
     * Перевірка чи плагін активний
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Отримання шляху до файлу в плагіні
     *
     * @param string $filePath Відносний шлях від директорії плагіна
     * @return string
     * @throws \RuntimeException Якщо шлях недійсний або виходить за межі плагіна
     */
    public function getPluginPath(string $filePath = ''): string
    {
        if (empty($filePath)) {
            return $this->pluginDir;
        }

        $filePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath), DIRECTORY_SEPARATOR);
        $fullPath = $this->pluginDir . $filePath;

        // Захист від path traversal
        $realPluginDir = realpath($this->pluginDir);
        $realFullPath = realpath($fullPath);

        if ($realPluginDir === false || $realFullPath === false) {
            throw new \RuntimeException("Invalid plugin path: {$filePath}");
        }

        if (!str_starts_with($realFullPath, $realPluginDir)) {
            throw new \RuntimeException("Path traversal detected: {$filePath}");
        }

        // Додаткова перевірка через систему ізоляції
        PluginIsolation::blockAccess($realFullPath, $this->pluginDir);

        return $realFullPath;
    }

    /**
     * Очищення контейнера
     *
     * @return void
     */
    public function clear(): void
    {
        $this->pluginInstance = null;
        $this->isActive = false;
        // Контейнер залишається для можливості повторного використання
    }
}
