<?php

/**
 * Багаторівневий кеш
 * 
 * Автоматичний fallback між рівнями: Memory → File → Database
 * 
 * @package Flowaxy\Core\Infrastructure\Cache
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Flowaxy\Core\Infrastructure\Cache;

use Flowaxy\Core\Infrastructure\Cache\Drivers\MemoryCacheDriver;
use Flowaxy\Core\Infrastructure\Cache\Drivers\FileCacheDriver;
use Flowaxy\Core\Infrastructure\Cache\Drivers\DatabaseCacheDriver;

final class MultiLevelCache implements CacheDriverInterface
{
    /**
     * @var array<int, CacheDriverInterface>
     */
    private array $drivers = [];

    /**
     * Конструктор
     * 
     * @param array<CacheDriverInterface> $drivers Масив драйверів у порядку пріоритету
     */
    public function __construct(array $drivers = [])
    {
        if (empty($drivers)) {
            // Створюємо драйвери за замовчуванням
            $cacheDir = defined('CACHE_DIR') ? CACHE_DIR : dirname(__DIR__, 3) . '/storage/cache/';
            
            $this->drivers = [
                new MemoryCacheDriver(),
                new FileCacheDriver($cacheDir),
            ];

            // Додаємо Database драйвер, якщо доступний
            if (class_exists('Flowaxy\Core\Infrastructure\Persistence\Database')) {
                try {
                    $this->drivers[] = new DatabaseCacheDriver();
                } catch (\Exception $e) {
                    // Ігноруємо помилку, якщо БД недоступна
                }
            }
        } else {
            $this->drivers = $drivers;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (function_exists('logDebug')) {
            logDebug('MultiLevelCache::get: Retrieving from multi-level cache', [
                'key' => $key,
                'driver_count' => count($this->drivers),
            ]);
        }

        // Шукаємо в драйверах по порядку
        $driverIndex = 0;
        foreach ($this->drivers as $driver) {
            $value = $driver->get($key);
            
            if ($value !== null || $driver->has($key)) {
                // Знайдено значення, заповнюємо попередні рівні
                if (function_exists('logDebug')) {
                    logDebug('MultiLevelCache::get: Found in driver, warming upper levels', [
                        'key' => $key,
                        'driver_index' => $driverIndex,
                        'driver_class' => get_class($driver),
                    ]);
                }
                $this->warmUpperLevels($key, $value, $driver);
                if (function_exists('logInfo')) {
                    logInfo('MultiLevelCache::get: Retrieved from multi-level cache', [
                        'key' => $key,
                        'driver_index' => $driverIndex,
                    ]);
                }
                return $value ?? $default;
            }
            $driverIndex++;
        }

        if (function_exists('logDebug')) {
            logDebug('MultiLevelCache::get: Key not found in any driver', ['key' => $key]);
        }

        return $default;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (function_exists('logDebug')) {
            logDebug('MultiLevelCache::set: Setting value in multi-level cache', [
                'key' => $key,
                'ttl' => $ttl,
                'driver_count' => count($this->drivers),
            ]);
        }

        $success = true;
        $failedDrivers = [];

        // Зберігаємо у всіх драйверах
        $driverIndex = 0;
        foreach ($this->drivers as $driver) {
            if (!$driver->set($key, $value, $ttl)) {
                $success = false;
                $failedDrivers[] = get_class($driver);
            }
            $driverIndex++;
        }

        if ($success && function_exists('logInfo')) {
            logInfo('MultiLevelCache::set: Value set in all drivers', ['key' => $key]);
        } elseif (!$success && function_exists('logWarning')) {
            logWarning('MultiLevelCache::set: Some drivers failed to set value', [
                'key' => $key,
                'failed_drivers' => $failedDrivers,
            ]);
        }

        return $success;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        if (function_exists('logDebug')) {
            logDebug('MultiLevelCache::delete: Deleting from multi-level cache', [
                'key' => $key,
                'driver_count' => count($this->drivers),
            ]);
        }

        $success = true;
        $failedDrivers = [];

        // Видаляємо з усіх драйверів
        foreach ($this->drivers as $driver) {
            if (!$driver->delete($key)) {
                $success = false;
                $failedDrivers[] = get_class($driver);
            }
        }

        if ($success && function_exists('logInfo')) {
            logInfo('MultiLevelCache::delete: Deleted from all drivers', ['key' => $key]);
        } elseif (!$success && function_exists('logWarning')) {
            logWarning('MultiLevelCache::delete: Some drivers failed to delete', [
                'key' => $key,
                'failed_drivers' => $failedDrivers,
            ]);
        }

        return $success;
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        // Перевіряємо в драйверах по порядку
        foreach ($this->drivers as $driver) {
            if ($driver->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        if (function_exists('logDebug')) {
            logDebug('MultiLevelCache::clear: Clearing multi-level cache', [
                'driver_count' => count($this->drivers),
            ]);
        }

        $success = true;
        $failedDrivers = [];

        // Очищаємо всі драйвери
        foreach ($this->drivers as $driver) {
            if (!$driver->clear()) {
                $success = false;
                $failedDrivers[] = get_class($driver);
            }
        }

        if ($success && function_exists('logInfo')) {
            logInfo('MultiLevelCache::clear: All drivers cleared successfully');
        } elseif (!$success && function_exists('logWarning')) {
            logWarning('MultiLevelCache::clear: Some drivers failed to clear', [
                'failed_drivers' => $failedDrivers,
            ]);
        }

        return $success;
    }

    /**
     * Заповнення верхніх рівнів кешу значенням з нижнього рівня
     * 
     * @param string $key Ключ
     * @param mixed $value Значення
     * @param CacheDriverInterface $foundDriver Драйвер, де знайдено значення
     * @return void
     */
    private function warmUpperLevels(string $key, mixed $value, CacheDriverInterface $foundDriver): void
    {
        $foundIndex = array_search($foundDriver, $this->drivers, true);
        
        if ($foundIndex === false || $foundIndex === 0) {
            return; // Вже на найвищому рівні
        }

        // Заповнюємо верхні рівні
        for ($i = 0; $i < $foundIndex; $i++) {
            $this->drivers[$i]->set($key, $value);
        }
    }
}
