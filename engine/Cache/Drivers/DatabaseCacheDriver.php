<?php

/**
 * Драйвер кешу в базі даних
 *
 * @package Flowaxy\Core\Infrastructure\Cache\Drivers
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Flowaxy\Core\Infrastructure\Cache\Drivers;

use Flowaxy\Core\Infrastructure\Cache\CacheDriverInterface;

final class DatabaseCacheDriver implements CacheDriverInterface
{
    private ?object $db = null;
    private string $tableName = 'cache';

    public function __construct(?object $db = null, string $tableName = 'cache')
    {
        $this->db = $db;
        $this->tableName = $tableName;
        $this->ensureTable();

        if (function_exists('logDebug')) {
            logDebug('DatabaseCacheDriver::__construct: Database cache driver initialized', ['table' => $this->tableName]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (function_exists('logDebug')) {
            logDebug('DatabaseCacheDriver::get: Retrieving from database cache', ['key' => $key, 'table' => $this->tableName]);
        }

        try {
            $db = $this->getDatabase();
            if (!$db) {
                if (function_exists('logWarning')) {
                    logWarning('DatabaseCacheDriver::get: Database connection not available', ['key' => $key]);
                }
                return $default;
            }

            $stmt = $db->query(
                "SELECT value, expires FROM {$this->tableName} WHERE cache_key = ?",
                [$key]
            );

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                if (function_exists('logDebug')) {
                    logDebug('DatabaseCacheDriver::get: Key not found in database cache', ['key' => $key]);
                }
                return $default;
            }

            // Перевіряємо термін дії
            if ($row['expires'] > 0 && $row['expires'] < time()) {
                $this->delete($key);
                if (function_exists('logDebug')) {
                    logDebug('DatabaseCacheDriver::get: Cache expired, deleted', ['key' => $key]);
                }
                return $default;
            }

            if (function_exists('logDebug')) {
                logDebug('DatabaseCacheDriver::get: Retrieved from database cache', ['key' => $key]);
            }

            return unserialize($row['value'], ['allowed_classes' => false]);
        } catch (\Exception $e) {
            if (function_exists('logDbError')) {
                logDbError('DatabaseCacheDriver::get: Database error', [
                    'key' => $key,
                    'table' => $this->tableName,
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
            logDebug('DatabaseCacheDriver::set: Setting value in database cache', ['key' => $key, 'ttl' => $ttl, 'table' => $this->tableName]);
        }

        try {
            $db = $this->getDatabase();
            if (!$db) {
                if (function_exists('logWarning')) {
                    logWarning('DatabaseCacheDriver::set: Database connection not available', ['key' => $key]);
                }
                return false;
            }

            $expires = $ttl !== null ? time() + $ttl : 0;
            $serialized = serialize($value);

            $db->query(
                "INSERT INTO {$this->tableName} (cache_key, value, expires, created_at)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE value = ?, expires = ?, created_at = ?",
                [$key, $serialized, $expires, time(), $serialized, $expires, time()]
            );

            if (function_exists('logInfo')) {
                logInfo('DatabaseCacheDriver::set: Value set in database cache', ['key' => $key]);
            }

            return true;
        } catch (\Exception $e) {
            if (function_exists('logDbError')) {
                logDbError('DatabaseCacheDriver::set: Database error', [
                    'key' => $key,
                    'table' => $this->tableName,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        if (function_exists('logDebug')) {
            logDebug('DatabaseCacheDriver::delete: Deleting from database cache', ['key' => $key, 'table' => $this->tableName]);
        }

        try {
            $db = $this->getDatabase();
            if (!$db) {
                if (function_exists('logWarning')) {
                    logWarning('DatabaseCacheDriver::delete: Database connection not available', ['key' => $key]);
                }
                return false;
            }

            $db->execute("DELETE FROM {$this->tableName} WHERE cache_key = ?", [$key]);

            if (function_exists('logInfo')) {
                logInfo('DatabaseCacheDriver::delete: Deleted from database cache', ['key' => $key]);
            }

            return true;
        } catch (\Exception $e) {
            if (function_exists('logDbError')) {
                logDbError('DatabaseCacheDriver::delete: Database error', [
                    'key' => $key,
                    'table' => $this->tableName,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        try {
            $db = $this->getDatabase();
            if (!$db) {
                return false;
            }

            $stmt = $db->query(
                "SELECT expires FROM {$this->tableName} WHERE cache_key = ?",
                [$key]
            );

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                return false;
            }

            // Перевіряємо термін дії
            if ($row['expires'] > 0 && $row['expires'] < time()) {
                $this->delete($key);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            if (function_exists('logDbError')) {
                logDbError('DatabaseCacheDriver::has: Database error', [
                    'key' => $key,
                    'table' => $this->tableName,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        if (function_exists('logDebug')) {
            logDebug('DatabaseCacheDriver::clear: Clearing database cache', ['table' => $this->tableName]);
        }

        try {
            $db = $this->getDatabase();
            if (!$db) {
                if (function_exists('logWarning')) {
                    logWarning('DatabaseCacheDriver::clear: Database connection not available');
                }
                return false;
            }

            $db->execute("TRUNCATE TABLE {$this->tableName}");

            if (function_exists('logInfo')) {
                logInfo('DatabaseCacheDriver::clear: Database cache cleared', ['table' => $this->tableName]);
            }

            return true;
        } catch (\Exception $e) {
            if (function_exists('logDbError')) {
                logDbError('DatabaseCacheDriver::clear: Database error', [
                    'table' => $this->tableName,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
            return false;
        }
    }

    /**
     * Отримання підключення до БД
     */
    private function getDatabase(): ?object
    {
        if ($this->db !== null) {
            return $this->db;
        }

        // Спробуємо отримати Database через глобальну функцію або клас
        if (class_exists(\Flowaxy\Core\Infrastructure\Persistence\Database::class)) {
            return \Flowaxy\Core\Infrastructure\Persistence\Database::getInstance();
        }

        return null;
    }

    /**
     * Створення таблиці кешу, якщо не існує
     */
    private function ensureTable(): void
    {
        try {
            $db = $this->getDatabase();
            if (!$db) {
                if (function_exists('logWarning')) {
                    logWarning('DatabaseCacheDriver::ensureTable: Database connection not available');
                }
                return;
            }

            if (function_exists('logDebug')) {
                logDebug('DatabaseCacheDriver::ensureTable: Ensuring cache table exists', ['table' => $this->tableName]);
            }

            $db->query("
                CREATE TABLE IF NOT EXISTS {$this->tableName} (
                    cache_key VARCHAR(255) PRIMARY KEY,
                    value LONGTEXT NOT NULL,
                    expires INT UNSIGNED NOT NULL DEFAULT 0,
                    created_at INT UNSIGNED NOT NULL,
                    INDEX idx_expires (expires)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            );

            if (function_exists('logInfo')) {
                logInfo('DatabaseCacheDriver::ensureTable: Cache table ensured', ['table' => $this->tableName]);
            }
        } catch (\Exception $e) {
            if (function_exists('logWarning')) {
                logWarning('DatabaseCacheDriver::ensureTable: Table creation error (may already exist)', [
                    'table' => $this->tableName,
                    'error' => $e->getMessage(),
                ]);
            }
            // Таблиця вже існує або помилка створення
        }
    }
}
