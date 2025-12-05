<?php

/**
 * Базовий клас завдання для черги
 * 
 * @package Engine\System\Queue
 * @version 1.0.0
 */

declare(strict_types=1);

abstract class Job
{
    public int $attempts = 0;
    public int $maxAttempts = 3;
    public int $timeout = 60;

    /**
     * Виконання завдання
     * 
     * @return void
     */
    abstract public function handle(): void;

    /**
     * Обробка помилки
     * 
     * @param Throwable $exception Виняток
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        // Можна перевизначити в дочірніх класах
    }

    /**
     * Повторна спроба виконання
     * 
     * @return bool
     */
    public function retry(): bool
    {
        if ($this->attempts >= $this->maxAttempts) {
            return false;
        }

        $this->attempts++;
        return true;
    }
}

