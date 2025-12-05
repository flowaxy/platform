<?php

/**
 * Драйвер файлового кешу
 *
 * @package Flowaxy\Core\Infrastructure\Cache\Drivers
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Flowaxy\Core\Infrastructure\Cache\Drivers;

use Flowaxy\Core\Infrastructure\Cache\CacheDriverInterface;

final class FileCacheDriver implements CacheDriverInterface
{
    private string $cacheDir;
    private const CACHE_FILE_EXTENSION = '.cache';

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/') . '/';
        $this->ensureCacheDir();

        if (function_exists('logDebug')) {
            logDebug('FileCacheDriver::__construct: File cache driver initialized', ['cache_dir' => $this->cacheDir]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (function_exists('logDebug')) {
            logDebug('FileCacheDriver::get: Retrieving from file cache', ['key' => $key]);
        }

        $filename = $this->getFilename($key);

        if (!file_exists($filename) || !is_readable($filename)) {
            if (function_exists('logDebug')) {
                logDebug('FileCacheDriver::get: Cache file does not exist or is not readable', ['key' => $key, 'filename' => $filename]);
            }
            return $default;
        }

        $data = @file_get_contents($filename);
        if ($data === false) {
            if (function_exists('logWarning')) {
                logWarning('FileCacheDriver::get: Failed to read cache file', ['key' => $key, 'filename' => $filename]);
            }
            return $default;
        }

        try {
            $cached = unserialize($data, ['allowed_classes' => false]);

            if (!is_array($cached) || !isset($cached['expires']) || !isset($cached['data'])) {
                @unlink($filename);
                if (function_exists('logWarning')) {
                    logWarning('FileCacheDriver::get: Invalid cache file structure, removed', ['key' => $key]);
                }
                return $default;
            }

            if ($cached['expires'] !== 0 && $cached['expires'] < time()) {
                $this->delete($key);
                if (function_exists('logDebug')) {
                    logDebug('FileCacheDriver::get: Cache expired, deleted', ['key' => $key]);
                }
                return $default;
            }

            if (function_exists('logDebug')) {
                logDebug('FileCacheDriver::get: Retrieved from file cache', ['key' => $key]);
            }

            return $cached['data'];
        } catch (\Exception $e) {
            @unlink($filename);
            if (function_exists('logError')) {
                logError('FileCacheDriver::get: Deserialization error', [
                    'key' => $key,
                    'filename' => $filename,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
            return $default;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (function_exists('logDebug')) {
            logDebug('FileCacheDriver::set: Setting value in file cache', ['key' => $key, 'ttl' => $ttl]);
        }

        $expires = $ttl !== null ? time() + $ttl : 0;

        $cached = [
            'data' => $value,
            'expires' => $expires,
            'created' => time(),
        ];

        try {
            $serialized = serialize($cached);
        } catch (\Exception $e) {
            if (function_exists('logError')) {
                logError('FileCacheDriver::set: Serialization error', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
            return false;
        }

        $filename = $this->getFilename($key);
        $result = @file_put_contents($filename, $serialized, LOCK_EX);

        if ($result !== false) {
            if (function_exists('logInfo')) {
                logInfo('FileCacheDriver::set: Value set in file cache', ['key' => $key]);
            }
        } else {
            if (function_exists('logError')) {
                logError('FileCacheDriver::set: Failed to write cache file', [
                    'key' => $key,
                    'filename' => $filename,
                ]);
            }
        }

        return $result !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        if (function_exists('logDebug')) {
            logDebug('FileCacheDriver::delete: Deleting from file cache', ['key' => $key]);
        }

        $filename = $this->getFilename($key);

        if (file_exists($filename)) {
            $result = @unlink($filename);
            if ($result && function_exists('logInfo')) {
                logInfo('FileCacheDriver::delete: Deleted from file cache', ['key' => $key]);
            } elseif (!$result && function_exists('logWarning')) {
                logWarning('FileCacheDriver::delete: Failed to delete cache file', ['key' => $key, 'filename' => $filename]);
            }
            return $result;
        }

        if (function_exists('logDebug')) {
            logDebug('FileCacheDriver::delete: Cache file does not exist', ['key' => $key]);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        $filename = $this->getFilename($key);

        if (!file_exists($filename) || !is_readable($filename)) {
            return false;
        }

        $data = @file_get_contents($filename);
        if ($data === false) {
            return false;
        }

        try {
            $cached = unserialize($data, ['allowed_classes' => false]);

            if (!is_array($cached) || !isset($cached['expires'])) {
                @unlink($filename);
                return false;
            }

            if ($cached['expires'] !== 0 && $cached['expires'] < time()) {
                $this->delete($key);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            @unlink($filename);
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        if (function_exists('logDebug')) {
            logDebug('FileCacheDriver::clear: Clearing file cache', ['cache_dir' => $this->cacheDir]);
        }

        $pattern = $this->cacheDir . '*' . self::CACHE_FILE_EXTENSION;
        $files = glob($pattern);

        if ($files === false) {
            if (function_exists('logError')) {
                logError('FileCacheDriver::clear: Failed to glob cache files', ['pattern' => $pattern]);
            }
            return false;
        }

        $success = true;
        $deletedCount = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                if (@unlink($file)) {
                    $deletedCount++;
                } else {
                    $success = false;
                }
            }
        }

        if ($success && function_exists('logInfo')) {
            logInfo('FileCacheDriver::clear: File cache cleared', ['deleted_files' => $deletedCount]);
        } elseif (!$success && function_exists('logWarning')) {
            logWarning('FileCacheDriver::clear: Some cache files could not be deleted', ['deleted_files' => $deletedCount]);
        }

        return $success;
    }

    /**
     * Отримання імені файлу для ключа
     */
    private function getFilename(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDir . $hash . self::CACHE_FILE_EXTENSION;
    }

    /**
     * Створення директорії кешу
     */
    private function ensureCacheDir(): void
    {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }
}
