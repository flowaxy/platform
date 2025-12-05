<?php

/**
 * Lazy завантажувач модулів
 *
 * Завантажує модулі тільки коли вони потрібні, а не всі одразу.
 * Це покращує продуктивність при старті системи.
 *
 * @package Flowaxy\Core\System
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\System;

final class LazyModuleLoader
{
    /**
     * @var array<string, bool> Завантажені модулі
     */
    private array $loadedModules = [];

    /**
     * @var array<string, callable> Callback для завантаження модулів
     */
    private array $loaders = [];

    /**
     * @var array<string, mixed> Кешовані екземпляри модулів
     */
    private array $instances = [];

    /**
     * Реєстрація loader для модуля
     *
     * @param string $moduleName Назва модуля
     * @param callable $loader Callback для завантаження
     * @return void
     */
    public function registerLoader(string $moduleName, callable $loader): void
    {
        $this->loaders[$moduleName] = $loader;
    }

    /**
     * Завантаження модуля (lazy)
     *
     * @param string $moduleName Назва модуля
     * @return mixed Екземпляр модуля або null
     */
    public function load(string $moduleName): mixed
    {
        // Якщо вже завантажений, повертаємо з кешу
        if (isset($this->instances[$moduleName])) {
            return $this->instances[$moduleName];
        }

        // Якщо вже завантажувався, але не вдалося - не повторюємо
        if (isset($this->loadedModules[$moduleName]) && !$this->loadedModules[$moduleName]) {
            return null;
        }

        // Перевіряємо, чи є loader
        if (!isset($this->loaders[$moduleName])) {
            $this->loadedModules[$moduleName] = false;
            return null;
        }

        try {
            // Викликаємо loader
            $instance = ($this->loaders[$moduleName])();

            // Зберігаємо в кеш
            $this->instances[$moduleName] = $instance;
            $this->loadedModules[$moduleName] = true;

            return $instance;
        } catch (\Throwable $e) {
            $this->loadedModules[$moduleName] = false;

            if (function_exists('logger')) {
                logger()->logError("Помилка завантаження модуля '{$moduleName}': " . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }

            return null;
        }
    }

    /**
     * Перевірка, чи завантажений модуль
     *
     * @param string $moduleName Назва модуля
     * @return bool
     */
    public function isLoaded(string $moduleName): bool
    {
        return isset($this->loadedModules[$moduleName]) && $this->loadedModules[$moduleName];
    }

    /**
     * Отримання завантаженого екземпляра модуля
     *
     * @param string $moduleName Назва модуля
     * @return mixed Екземпляр модуля або null
     */
    public function getInstance(string $moduleName): mixed
    {
        return $this->instances[$moduleName] ?? null;
    }

    /**
     * Отримання списку завантажених модулів
     *
     * @return array<string>
     */
    public function getLoadedModules(): array
    {
        return array_keys(array_filter($this->loadedModules));
    }

    /**
     * Очищення кешу модуля
     *
     * @param string $moduleName Назва модуля
     * @return void
     */
    public function unload(string $moduleName): void
    {
        unset($this->instances[$moduleName]);
        unset($this->loadedModules[$moduleName]);
    }

    /**
     * Очищення всіх модулів
     *
     * @return void
     */
    public function clear(): void
    {
        $this->instances = [];
        $this->loadedModules = [];
    }
}
