<?php

/**
 * Драйвер файлової черги
 * 
 * @package Engine\System\Queue\Drivers
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../QueueDriverInterface.php';

final class FileQueue implements QueueDriverInterface
{
    private string $queueDir;

    public function __construct(string $queueDir = '')
    {
        $this->queueDir = $queueDir ?: (defined('STORAGE_DIR') ? STORAGE_DIR . '/queues' : dirname(__DIR__, 4) . '/storage/queues');
        $this->queueDir = rtrim($this->queueDir, '/') . '/';
        $this->ensureQueueDir();
    }

    /**
     * {@inheritDoc}
     */
    public function push(string $queue, mixed $job, int $delay = 0): bool
    {
        $payload = is_string($job) ? $job : serialize($job);
        $data = [
            'payload' => $payload,
            'available_at' => time() + $delay,
            'created_at' => time(),
        ];

        $filename = $this->getQueueFile($queue, uniqid('job_', true));
        $result = @file_put_contents($filename, serialize($data), LOCK_EX);

        return $result !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function pop(string $queue): mixed
    {
        $files = glob($this->getQueuePath($queue) . 'job_*');
        
        if (empty($files)) {
            return null;
        }

        // Сортуємо за часом створення
        usort($files, function ($a, $b) {
            return filemtime($a) <=> filemtime($b);
        });

        foreach ($files as $file) {
            $data = @file_get_contents($file);
            if ($data === false) {
                continue;
            }

            $jobData = @unserialize($data);
            if (!is_array($jobData)) {
                @unlink($file);
                continue;
            }

            // Перевіряємо, чи завдання готове до виконання
            if ($jobData['available_at'] > time()) {
                continue;
            }

            // Видаляємо файл після отримання
            @unlink($file);

            return $jobData['payload'];
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function size(string $queue): int
    {
        $files = glob($this->getQueuePath($queue) . 'job_*');
        
        if (empty($files)) {
            return 0;
        }

        $count = 0;
        $currentTime = time();

        foreach ($files as $file) {
            $data = @file_get_contents($file);
            if ($data === false) {
                continue;
            }

            $jobData = @unserialize($data);
            if (is_array($jobData) && $jobData['available_at'] <= $currentTime) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * {@inheritDoc}
     */
    public function clear(string $queue): bool
    {
        $files = glob($this->getQueuePath($queue) . 'job_*');
        $success = true;

        foreach ($files as $file) {
            if (!@unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Отримання шляху до файлу черги
     */
    private function getQueueFile(string $queue, string $filename): string
    {
        return $this->getQueuePath($queue) . $filename;
    }

    /**
     * Отримання шляху до директорії черги
     */
    private function getQueuePath(string $queue): string
    {
        $path = $this->queueDir . $queue . '/';
        
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        return $path;
    }

    /**
     * Створення директорії черг
     */
    private function ensureQueueDir(): void
    {
        if (!is_dir($this->queueDir)) {
            @mkdir($this->queueDir, 0755, true);
        }
    }
}

