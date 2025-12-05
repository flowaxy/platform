<?php

/**
 * Інтерфейс драйвера черги
 * 
 * @package Engine\System\Queue
 * @version 1.0.0
 */

declare(strict_types=1);

interface QueueDriverInterface
{
    /**
     * Додавання завдання в чергу
     * 
     * @param string $queue Назва черги
     * @param mixed $job Завдання
     * @param int $delay Затримка в секундах
     * @return bool
     */
    public function push(string $queue, mixed $job, int $delay = 0): bool;

    /**
     * Отримання завдання з черги
     * 
     * @param string $queue Назва черги
     * @return mixed|null
     */
    public function pop(string $queue): mixed;

    /**
     * Отримання розміру черги
     * 
     * @param string $queue Назва черги
     * @return int
     */
    public function size(string $queue): int;

    /**
     * Очищення черги
     * 
     * @param string $queue Назва черги
     * @return bool
     */
    public function clear(string $queue): bool;
}

