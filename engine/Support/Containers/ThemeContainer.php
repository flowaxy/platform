<?php

/**
 * Ізольований контейнер для теми
 *
 * Забезпечує ізоляцію теми від ядра та інших тем.
 * Тема може взаємодіяти з системою тільки через хуки та фільтри.
 *
 * @package Flowaxy\Core\Support\Containers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Support\Containers;

require_once __DIR__ . '/../../core/system/Container.php';
require_once __DIR__ . '/../../Contracts/ContainerInterface.php';

use Flowaxy\Core\System\Container;
use Flowaxy\Core\Contracts\ContainerInterface;

final class ThemeContainer
{
    /**
     * @var ContainerInterface Власний DI контейнер теми
     */
    private ContainerInterface $container;

    /**
     * @var string Slug теми
     */
    private string $themeSlug;

    /**
     * @var string Директорія теми
     */
    private string $themeDir;

    /**
     * @var array<string, mixed> Конфігурація теми
     */
    private array $config;

    /**
     * @var bool Чи тема активна
     */
    private bool $isActive = false;

    /**
     * Конструктор
     *
     * @param string $themeSlug Slug теми
     * @param string $themeDir Директорія теми
     * @param array<string, mixed> $config Конфігурація теми
     */
    public function __construct(string $themeSlug, string $themeDir, array $config = [])
    {
        $this->themeSlug = $themeSlug;
        $this->themeDir = rtrim($themeDir, '/\\') . DIRECTORY_SEPARATOR;
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
        // Реєструємо базові сервіси для теми
        $this->container->singleton('theme.slug', fn() => $this->themeSlug);
        $this->container->singleton('theme.dir', fn() => $this->themeDir);
        $this->container->singleton('theme.config', fn() => $this->config);

        // Реєструємо обмежений доступ до хуків (тільки через HookManager)
        // Тема не може напряму звертатися до ядра
    }

    /**
     * Отримання DI контейнера теми
     *
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Отримання slug теми
     *
     * @return string
     */
    public function getThemeSlug(): string
    {
        return $this->themeSlug;
    }

    /**
     * Отримання директорії теми
     *
     * @return string
     */
    public function getThemeDir(): string
    {
        return $this->themeDir;
    }

    /**
     * Отримання конфігурації теми
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Активування теми
     *
     * @return void
     */
    public function activate(): void
    {
        $this->isActive = true;
    }

    /**
     * Деактивування теми
     *
     * @return void
     */
    public function deactivate(): void
    {
        $this->isActive = false;
    }

    /**
     * Перевірка чи тема активна
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Отримання шляху до файлу в темі
     *
     * @param string $filePath Відносний шлях від директорії теми
     * @return string
     */
    public function getThemePath(string $filePath = ''): string
    {
        if (empty($filePath)) {
            return $this->themeDir;
        }

        $filePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath), DIRECTORY_SEPARATOR);
        $fullPath = $this->themeDir . $filePath;

        // Захист від path traversal
        $realThemeDir = realpath($this->themeDir);
        $realFullPath = realpath($fullPath);

        if ($realThemeDir === false || $realFullPath === false) {
            throw new \RuntimeException("Invalid theme path: {$filePath}");
        }

        if (!str_starts_with($realFullPath, $realThemeDir)) {
            throw new \RuntimeException("Path traversal detected: {$filePath}");
        }

        return $realFullPath;
    }

    /**
     * Отримання шляху до шаблонів теми
     *
     * @param string $templateName Ім'я шаблону
     * @return string
     */
    public function getTemplatePath(string $templateName = ''): string
    {
        $templatesDir = $this->getThemePath('templates');
        if (empty($templateName)) {
            return $templatesDir;
        }

        return $this->getThemePath('templates' . DIRECTORY_SEPARATOR . $templateName);
    }

    /**
     * Отримання шляху до assets теми
     *
     * @param string $assetPath Відносний шлях до asset
     * @return string
     */
    public function getAssetPath(string $assetPath = ''): string
    {
        $assetsDir = $this->getThemePath('assets');
        if (empty($assetPath)) {
            return $assetsDir;
        }

        return $this->getThemePath('assets' . DIRECTORY_SEPARATOR . $assetPath);
    }

    /**
     * Отримання шляху до components теми
     *
     * @param string $componentName Ім'я компонента
     * @return string
     */
    public function getComponentPath(string $componentName = ''): string
    {
        $componentsDir = $this->getThemePath('components');
        if (empty($componentName)) {
            return $componentsDir;
        }

        return $this->getThemePath('components' . DIRECTORY_SEPARATOR . $componentName);
    }

    /**
     * Перевірка чи існує файл в темі
     *
     * @param string $filePath Відносний шлях від директорії теми
     * @return bool
     */
    public function hasFile(string $filePath): bool
    {
        try {
            $fullPath = $this->getThemePath($filePath);
            return file_exists($fullPath);
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Очищення контейнера
     *
     * @return void
     */
    public function clear(): void
    {
        $this->isActive = false;
        // Контейнер залишається для можливості повторного використання
    }
}
