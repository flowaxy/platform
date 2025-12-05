<?php

/**
 * Кешування конфігурацій та метаданих модулів
 *
 * Зберігає конфігурації модулів, їх залежності та інші метадані
 * для швидшого доступу без повторного парсингу файлів.
 *
 * @package Flowaxy\Core\System
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\System;

use Flowaxy\Core\Infrastructure\Cache\Cache;

final class ModuleCache
{
    private const CACHE_KEY_PREFIX = 'modules:';
    private const CACHE_TTL = 3600; // 1 година

    public function __construct(
        private Cache $cache
    ) {
    }

    /**
     * Отримання конфігурації модуля з кешу
     *
     * @param string $moduleName Назва модуля
     * @return array<string, mixed>|null
     */
    public function getConfig(string $moduleName): ?array
    {
        $key = self::CACHE_KEY_PREFIX . 'config:' . $moduleName;
        return $this->cache->get($key);
    }

    /**
     * Збереження конфігурації модуля в кеш
     *
     * @param string $moduleName Назва модуля
     * @param array<string, mixed> $config Конфігурація
     * @return void
     */
    public function setConfig(string $moduleName, array $config): void
    {
        $key = self::CACHE_KEY_PREFIX . 'config:' . $moduleName;
        $this->cache->set($key, $config, self::CACHE_TTL);
    }

    /**
     * Отримання всіх конфігурацій модулів з кешу
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllConfigs(): array
    {
        $key = self::CACHE_KEY_PREFIX . 'all_configs';
        return $this->cache->get($key, []);
    }

    /**
     * Збереження всіх конфігурацій модулів в кеш
     *
     * @param array<string, array<string, mixed>> $configs Конфігурації
     * @return void
     */
    public function setAllConfigs(array $configs): void
    {
        $key = self::CACHE_KEY_PREFIX . 'all_configs';
        $this->cache->set($key, $configs, self::CACHE_TTL);
    }

    /**
     * Отримання відсортованого списку модулів з кешу
     *
     * @return array<string>|null
     */
    public function getSortedModules(): ?array
    {
        $key = self::CACHE_KEY_PREFIX . 'sorted';
        return $this->cache->get($key);
    }

    /**
     * Збереження відсортованого списку модулів в кеш
     *
     * @param array<string> $modules Список модулів
     * @return void
     */
    public function setSortedModules(array $modules): void
    {
        $key = self::CACHE_KEY_PREFIX . 'sorted';
        $this->cache->set($key, $modules, self::CACHE_TTL);
    }

    /**
     * Отримання залежностей модуля з кешу
     *
     * @param string $moduleName Назва модуля
     * @return array<string>|null
     */
    public function getDependencies(string $moduleName): ?array
    {
        $key = self::CACHE_KEY_PREFIX . 'deps:' . $moduleName;
        return $this->cache->get($key);
    }

    /**
     * Збереження залежностей модуля в кеш
     *
     * @param string $moduleName Назва модуля
     * @param array<string> $dependencies Залежності
     * @return void
     */
    public function setDependencies(string $moduleName, array $dependencies): void
    {
        $key = self::CACHE_KEY_PREFIX . 'deps:' . $moduleName;
        $this->cache->set($key, $dependencies, self::CACHE_TTL);
    }

    /**
     * Очищення кешу модуля
     *
     * @param string $moduleName Назва модуля (опціонально, якщо null - очищає весь кеш модулів)
     * @return void
     */
    public function clear(?string $moduleName = null): void
    {
        if ($moduleName !== null) {
            $keys = [
                self::CACHE_KEY_PREFIX . 'config:' . $moduleName,
                self::CACHE_KEY_PREFIX . 'deps:' . $moduleName,
            ];

            foreach ($keys as $key) {
                $this->cache->delete($key);
            }
        } else {
            // Очищаємо весь кеш модулів
            $this->cache->tags(['modules'])->clear();
        }

        // Очищаємо загальні ключі
        $this->cache->delete(self::CACHE_KEY_PREFIX . 'all_configs');
        $this->cache->delete(self::CACHE_KEY_PREFIX . 'sorted');
    }

    /**
     * Перевірка, чи є конфігурація в кеші
     *
     * @param string $moduleName Назва модуля
     * @return bool
     */
    public function hasConfig(string $moduleName): bool
    {
        $key = self::CACHE_KEY_PREFIX . 'config:' . $moduleName;
        return $this->cache->has($key);
    }
}
