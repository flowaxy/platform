<?php

/**
 * Пул з'єднань для оптимізації
 *
 * Перевикористання з'єднань для зменшення навантаження
 *
 * @package Flowaxy\Core\Infrastructure\Persistence
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Infrastructure\Persistence;

final class ConnectionPool
{
    /**
     * @var array<int, \PDO>
     */
    private array $pool = [];

    private int $maxSize;
    private int $currentSize = 0;
    private string $dsn;
    private string $username;
    private string $password;
    private array $options;

    public function __construct(
        string $dsn,
        string $username,
        string $password,
        array $options = [],
        int $maxSize = 10
    ) {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
        $this->maxSize = $maxSize;

        if (function_exists('logDebug')) {
            logDebug('ConnectionPool::__construct: Connection pool created', [
                'max_size' => $maxSize,
                'dsn' => $dsn,
            ]);
        }
    }

    public function get(): \PDO
    {
        if (!empty($this->pool)) {
            $connection = array_pop($this->pool);
            if (function_exists('logDebug')) {
                logDebug('ConnectionPool::get: Connection retrieved from pool', [
                    'available_count' => count($this->pool),
                    'current_size' => $this->currentSize,
                ]);
            }
            return $connection;
        }

        if ($this->currentSize < $this->maxSize) {
            $this->currentSize++;
            if (function_exists('logDebug')) {
                logDebug('ConnectionPool::get: Creating new connection within pool limit', [
                    'current_size' => $this->currentSize,
                    'max_size' => $this->maxSize,
                ]);
            }
            try {
                $connection = $this->createConnection();
                if (function_exists('logInfo')) {
                    logInfo('ConnectionPool::get: New connection created successfully', [
                        'current_size' => $this->currentSize,
                    ]);
                }
                return $connection;
            } catch (\Exception $e) {
                $this->currentSize--;
                if (function_exists('logError')) {
                    logError('ConnectionPool::get: Failed to create connection', [
                        'error' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                }
                throw $e;
            }
        }

        // Переповнення пулу - створюємо тимчасове з'єднання
        if (function_exists('logWarning')) {
            logWarning('ConnectionPool::get: Pool overflow, creating temporary connection', [
                'current_size' => $this->currentSize,
                'max_size' => $this->maxSize,
            ]);
        }
        try {
            $connection = $this->createConnection();
            return $connection;
        } catch (\Exception $e) {
            if (function_exists('logError')) {
                logError('ConnectionPool::get: Failed to create temporary connection', [
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
            throw $e;
        }
    }

    public function release(\PDO $connection): void
    {
        try {
            $connection->query('SELECT 1');
        } catch (\PDOException $e) {
            $this->currentSize--;
            if (function_exists('logWarning')) {
                logWarning('ConnectionPool::release: Connection is invalid, removing from pool', [
                    'error' => $e->getMessage(),
                    'current_size' => $this->currentSize,
                ]);
            }
            return;
        }

        if (count($this->pool) < $this->maxSize) {
            $this->pool[] = $connection;
            if (function_exists('logDebug')) {
                logDebug('ConnectionPool::release: Connection returned to pool', [
                    'available_count' => count($this->pool),
                    'current_size' => $this->currentSize,
                ]);
            }
        } else {
            $connection = null;
            $this->currentSize--;
            if (function_exists('logDebug')) {
                logDebug('ConnectionPool::release: Pool is full, connection closed', [
                    'current_size' => $this->currentSize,
                ]);
            }
        }
    }

    private function createConnection(): \PDO
    {
        try {
            if (function_exists('logDebug')) {
                logDebug('ConnectionPool::createConnection: Creating new PDO connection', [
                    'dsn' => $this->dsn,
                ]);
            }
            $connection = new \PDO($this->dsn, $this->username, $this->password, $this->options);
            if (function_exists('logInfo')) {
                logInfo('ConnectionPool::createConnection: Connection created successfully');
            }
            return $connection;
        } catch (\PDOException $e) {
            if (function_exists('logError')) {
                logError('ConnectionPool::createConnection: Failed to create connection', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'exception' => $e,
                ]);
            }
            throw $e;
        }
    }

    public function getCurrentSize(): int
    {
        return $this->currentSize;
    }

    public function getAvailableCount(): int
    {
        return count($this->pool);
    }

    public function clear(): void
    {
        if (function_exists('logDebug')) {
            logDebug('ConnectionPool::clear: Clearing connection pool', [
                'pool_size' => count($this->pool),
                'current_size' => $this->currentSize,
            ]);
        }
        $this->pool = [];
        $this->currentSize = 0;
        if (function_exists('logInfo')) {
            logInfo('ConnectionPool::clear: Connection pool cleared');
        }
    }

    public function close(): void
    {
        if (function_exists('logDebug')) {
            logDebug('ConnectionPool::close: Closing all connections', [
                'pool_size' => count($this->pool),
            ]);
        }
        foreach ($this->pool as $connection) {
            $connection = null;
        }

        $this->pool = [];
        $this->currentSize = 0;
    }
}
