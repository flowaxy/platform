<?php

/**
 * Драйвер черги на базі даних
 * 
 * @package Engine\System\Queue\Drivers
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../QueueDriverInterface.php';

final class DatabaseQueue implements QueueDriverInterface
{
    private ?object $db = null;
    private string $tableName = 'queue_jobs';

    public function __construct(?object $db = null, string $tableName = 'queue_jobs')
    {
        $this->db = $db;
        $this->tableName = $tableName;
        $this->ensureTable();
    }

    /**
     * {@inheritDoc}
     */
    public function push(string $queue, mixed $job, int $delay = 0): bool
    {
        try {
            $db = $this->getDatabase();
            if (!$db) {
                return false;
            }

            $payload = is_string($job) ? $job : serialize($job);
            $availableAt = time() + $delay;

            $db->insert(
                "INSERT INTO {$this->tableName} (queue, payload, attempts, available_at, created_at) 
                 VALUES (?, ?, 0, ?, ?)",
                [$queue, $payload, $availableAt, time()]
            );

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function pop(string $queue): mixed
    {
        try {
            $db = $this->getDatabase();
            if (!$db) {
                return null;
            }

            // Блокуємо рядок для обробки
            $db->query("SET autocommit = 0");
            $db->query("START TRANSACTION");

            $row = $db->getRow(
                "SELECT * FROM {$this->tableName} 
                 WHERE queue = ? AND available_at <= ? AND reserved_at IS NULL
                 ORDER BY id ASC
                 LIMIT 1
                 FOR UPDATE",
                [$queue, time()]
            );

            if (!$row) {
                $db->query("COMMIT");
                return null;
            }

            // Резервуємо завдання
            $db->execute(
                "UPDATE {$this->tableName} SET reserved_at = ? WHERE id = ?",
                [time(), $row['id']]
            );

            $db->query("COMMIT");
            $db->query("SET autocommit = 1");

            return $row['payload'];
        } catch (Exception $e) {
            if ($db) {
                $db->query("ROLLBACK");
                $db->query("SET autocommit = 1");
            }
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function size(string $queue): int
    {
        try {
            $db = $this->getDatabase();
            if (!$db) {
                return 0;
            }

            return (int)$db->getValue(
                "SELECT COUNT(*) FROM {$this->tableName} 
                 WHERE queue = ? AND available_at <= ? AND reserved_at IS NULL",
                [$queue, time()]
            );
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function clear(string $queue): bool
    {
        try {
            $db = $this->getDatabase();
            if (!$db) {
                return false;
            }

            $db->execute("DELETE FROM {$this->tableName} WHERE queue = ?", [$queue]);
            return true;
        } catch (Exception $e) {
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

        if (class_exists('Database')) {
            return Database::getInstance();
        }

        return null;
    }

    /**
     * Створення таблиці черги, якщо не існує
     */
    private function ensureTable(): void
    {
        try {
            $db = $this->getDatabase();
            if (!$db) {
                return;
            }

            $db->query("
                CREATE TABLE IF NOT EXISTS {$this->tableName} (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    queue VARCHAR(255) NOT NULL,
                    payload LONGTEXT NOT NULL,
                    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    reserved_at INT UNSIGNED NULL,
                    available_at INT UNSIGNED NOT NULL,
                    created_at INT UNSIGNED NOT NULL,
                    INDEX idx_queue_available (queue, available_at),
                    INDEX idx_reserved (reserved_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Exception $e) {
            // Таблиця вже існує або помилка створення
        }
    }
}

