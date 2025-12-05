<?php

/**
 * Драйвер черги на Redis
 * 
 * @package Engine\System\Queue\Drivers
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../QueueDriverInterface.php';

final class RedisQueue implements QueueDriverInterface
{
    private ?Redis $redis = null;
    private string $prefix = 'queue:';

    public function __construct(?Redis $redis = null, string $prefix = 'queue:')
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
    }

    /**
     * {@inheritDoc}
     */
    public function push(string $queue, mixed $job, int $delay = 0): bool
    {
        try {
            $redis = $this->getRedis();
            if (!$redis) {
                return false;
            }

            $payload = is_string($job) ? $job : serialize($job);
            $key = $this->getQueueKey($queue);

            if ($delay > 0) {
                // Використовуємо sorted set для затримок
                $redis->zAdd($key . ':delayed', time() + $delay, $payload);
            } else {
                // Додаємо в список
                $redis->rPush($key, $payload);
            }

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
            $redis = $this->getRedis();
            if (!$redis) {
                return null;
            }

            $key = $this->getQueueKey($queue);

            // Спочатку перевіряємо затримки
            $delayedKey = $key . ':delayed';
            $ready = $redis->zRangeByScore($delayedKey, 0, time(), ['limit' => [0, 1]]);
            
            if (!empty($ready)) {
                $payload = $ready[0];
                $redis->zRem($delayedKey, $payload);
                $redis->rPush($key, $payload);
            }

            // Отримуємо завдання
            $payload = $redis->lPop($key);

            return $payload;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function size(string $queue): int
    {
        try {
            $redis = $this->getRedis();
            if (!$redis) {
                return 0;
            }

            $key = $this->getQueueKey($queue);
            $delayedKey = $key . ':delayed';

            $size = $redis->lLen($key);
            $delayedSize = $redis->zCount($delayedKey, 0, time());

            return $size + $delayedSize;
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
            $redis = $this->getRedis();
            if (!$redis) {
                return false;
            }

            $key = $this->getQueueKey($queue);
            $redis->del($key);
            $redis->del($key . ':delayed');

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Отримання ключа черги
     */
    private function getQueueKey(string $queue): string
    {
        return $this->prefix . $queue;
    }

    /**
     * Отримання підключення Redis
     */
    private function getRedis(): ?Redis
    {
        if ($this->redis !== null) {
            return $this->redis;
        }

        // Спробуємо створити підключення, якщо Redis доступний
        if (extension_loaded('redis')) {
            try {
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                return $redis;
            } catch (Exception $e) {
                return null;
            }
        }

        return null;
    }
}

