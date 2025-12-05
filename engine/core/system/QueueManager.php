<?php

/**
 * Менеджер черг з підтримкою різних драйверів
 * 
 * @package Engine\System
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/queue/QueueDriverInterface.php';
require_once __DIR__ . '/queue/Job.php';

final class QueueManager
{
    private QueueDriverInterface $driver;
    private string $defaultQueue = 'default';

    public function __construct(QueueDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Додавання завдання в чергу
     * 
     * @param mixed $job Завдання
     * @param string|null $queue Назва черги
     * @param int $delay Затримка в секундах
     * @return bool
     */
    public function push(mixed $job, ?string $queue = null, int $delay = 0): bool
    {
        $queue = $queue ?? $this->defaultQueue;
        
        // Серіалізуємо завдання, якщо це об'єкт
        if (is_object($job)) {
            $job = serialize($job);
        }

        return $this->driver->push($queue, $job, $delay);
    }

    /**
     * Отримання завдання з черги
     * 
     * @param string|null $queue Назва черги
     * @return mixed|null
     */
    public function pop(?string $queue = null): mixed
    {
        $queue = $queue ?? $this->defaultQueue;
        $job = $this->driver->pop($queue);

        if ($job === null) {
            return null;
        }

        // Десеріалізуємо завдання, якщо це рядок
        if (is_string($job)) {
            $unserialized = @unserialize($job);
            if ($unserialized !== false) {
                return $unserialized;
            }
        }

        return $job;
    }

    /**
     * Отримання розміру черги
     * 
     * @param string|null $queue Назва черги
     * @return int
     */
    public function size(?string $queue = null): int
    {
        $queue = $queue ?? $this->defaultQueue;
        return $this->driver->size($queue);
    }

    /**
     * Очищення черги
     * 
     * @param string|null $queue Назва черги
     * @return bool
     */
    public function clear(?string $queue = null): bool
    {
        $queue = $queue ?? $this->defaultQueue;
        return $this->driver->clear($queue);
    }

    /**
     * Встановлення драйвера
     * 
     * @param QueueDriverInterface $driver
     * @return void
     */
    public function setDriver(QueueDriverInterface $driver): void
    {
        $this->driver = $driver;
    }

    /**
     * Встановлення черги за замовчуванням
     * 
     * @param string $queue
     * @return void
     */
    public function setDefaultQueue(string $queue): void
    {
        $this->defaultQueue = $queue;
    }
}

