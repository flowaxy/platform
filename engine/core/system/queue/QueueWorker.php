<?php

/**
 * Воркер для обробки черг
 * 
 * @package Engine\System\Queue
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/QueueManager.php';
require_once __DIR__ . '/Job.php';

final class QueueWorker
{
    private QueueManager $queueManager;
    private bool $shouldStop = false;
    private int $memoryLimit = 128 * 1024 * 1024; // 128MB

    public function __construct(QueueManager $queueManager)
    {
        $this->queueManager = $queueManager;
    }

    /**
     * Запуск воркера
     * 
     * @param string|null $queue Назва черги
     * @param int $sleep Секунди очікування між ітераціями
     * @return void
     */
    public function work(?string $queue = null, int $sleep = 3): void
    {
        $this->shouldStop = false;

        while (!$this->shouldStop) {
            $job = $this->queueManager->pop($queue);

            if ($job === null) {
                sleep($sleep);
                continue;
            }

            $this->processJob($job);

            // Перевірка ліміту пам'яті
            if ($this->memoryExceeded()) {
                $this->stop();
            }
        }
    }

    /**
     * Обробка завдання
     * 
     * @param mixed $job Завдання
     * @return void
     */
    private function processJob(mixed $job): void
    {
        try {
            // Десеріалізуємо завдання, якщо потрібно
            if (is_string($job)) {
                $job = @unserialize($job);
            }

            if ($job instanceof Job) {
                $job->handle();
            } elseif (is_callable($job)) {
                $job();
            }
        } catch (Throwable $e) {
            $this->handleJobException($job, $e);
        }
    }

    /**
     * Обробка помилки завдання
     * 
     * @param mixed $job Завдання
     * @param Throwable $exception Виняток
     * @return void
     */
    private function handleJobException(mixed $job, Throwable $exception): void
    {
        if ($job instanceof Job) {
            if ($job->retry()) {
                // Повторна спроба
                $this->queueManager->push($job);
            } else {
                // Викликаємо метод failed
                $job->failed($exception);
            }
        }

        // Логуємо помилку
        if (function_exists('logger')) {
            logger()->logError('Queue job failed: ' . $exception->getMessage(), [
                'exception' => $exception,
                'job' => get_class($job),
            ]);
        }
    }

    /**
     * Зупинка воркера
     * 
     * @return void
     */
    public function stop(): void
    {
        $this->shouldStop = true;
    }

    /**
     * Перевірка перевищення ліміту пам'яті
     * 
     * @return bool
     */
    private function memoryExceeded(): bool
    {
        return memory_get_usage() >= $this->memoryLimit;
    }

    /**
     * Встановлення ліміту пам'яті
     * 
     * @param int $bytes Байти
     * @return void
     */
    public function setMemoryLimit(int $bytes): void
    {
        $this->memoryLimit = $bytes;
    }
}

