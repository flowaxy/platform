<?php

/**
 * CLI команда для запуску воркерів черги
 * 
 * @package Engine\System\Queue
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/QueueWorker.php';
require_once __DIR__ . '/QueueManager.php';
require_once __DIR__ . '/drivers/DatabaseQueue.php';

final class WorkerCommand
{
    /**
     * Запуск воркера через CLI
     * 
     * @param array<string> $args Аргументи командного рядка
     * @return void
     */
    public static function run(array $args): void
    {
        $queue = $args[0] ?? 'default';
        $sleep = isset($args[1]) ? (int)$args[1] : 3;
        $driver = new DatabaseQueue();
        $queueManager = new QueueManager($driver);
        $worker = new QueueWorker($queueManager);

        echo "Queue worker started for queue: {$queue}\n";
        echo "Press Ctrl+C to stop\n\n";

        // Обробка сигналів для коректного завершення
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function () use ($worker) {
                $worker->stop();
            });
            pcntl_signal(SIGINT, function () use ($worker) {
                $worker->stop();
            });
        }

        $worker->work($queue, $sleep);
    }
}

