<?php

/**
 * Драйвер кешу в пам'яті
 *
 * @package Flowaxy\Core\Infrastructure\Cache\Drivers
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Flowaxy\Core\Infrastructure\Cache\Drivers;

use Flowaxy\Core\Infrastructure\Cache\CacheDriverInterface;

final class MemoryCacheDriver implements CacheDriverInterface
{
    /**
     * @var array<string, array{value: mixed, expires: int}>
     */
    private array $cache = [];

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (function_exists('logDebug')) {
            logDebug('MemoryCacheDriver::get: Retrieving from memory cache', ['key' => $key]);
        }

        if (!isset($this->cache[$key])) {
            if (function_exists('logDebug')) {
                logDebug('MemoryCacheDriver::get: Key not found in memory cache', ['key' => $key]);
            }
            return $default;
        }

        $item = $this->cache[$key];

        // Перевіряємо термін дії
        if ($item['expires'] > 0 && $item['expires'] < time()) {
            unset($this->cache[$key]);
            if (function_exists('logDebug')) {
                logDebug('MemoryCacheDriver::get: Key expired', ['key' => $key]);
            }
            return $default;
        }

        if (function_exists('logDebug')) {
            logDebug('MemoryCacheDriver::get: Retrieved from memory cache', ['key' => $key]);
        }

        return $item['value'];
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (function_exists('logDebug')) {
            logDebug('MemoryCacheDriver::set: Setting value in memory cache', ['key' => $key, 'ttl' => $ttl]);
        }

        $expires = $ttl !== null ? time() + $ttl : 0;

        $this->cache[$key] = [
            'value' => $value,
            'expires' => $expires,
        ];

        if (function_exists('logInfo')) {
            logInfo('MemoryCacheDriver::set: Value set in memory cache', ['key' => $key]);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        if (function_exists('logDebug')) {
            logDebug('MemoryCacheDriver::delete: Deleting from memory cache', ['key' => $key]);
        }

        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
            if (function_exists('logInfo')) {
                logInfo('MemoryCacheDriver::delete: Deleted from memory cache', ['key' => $key]);
            }
            return true;
        }

        if (function_exists('logDebug')) {
            logDebug('MemoryCacheDriver::delete: Key not found in memory cache', ['key' => $key]);
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        $item = $this->cache[$key];

        // Перевіряємо термін дії
        if ($item['expires'] > 0 && $item['expires'] < time()) {
            unset($this->cache[$key]);
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        if (function_exists('logDebug')) {
            logDebug('MemoryCacheDriver::clear: Clearing memory cache');
        }

        $this->cache = [];

        if (function_exists('logInfo')) {
            logInfo('MemoryCacheDriver::clear: Memory cache cleared');
        }

        return true;
    }
}
