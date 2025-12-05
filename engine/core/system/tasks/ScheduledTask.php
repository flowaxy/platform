<?php

/**
 * Клас планового завдання
 *
 * @package Engine\System\Tasks
 * @version 1.0.0
 */

declare(strict_types=1);

final class ScheduledTask
{
    private string $name;
    /** @var callable */
    private mixed $callback;
    private string $cronExpression;
    private bool $enabled = true;
    private ?int $lastRun = null;
    private ?int $nextRun = null;

    public function __construct(string $name, callable $callback, string $cronExpression)
    {
        $this->name = $name;
        $this->callback = $callback;
        $this->cronExpression = $cronExpression;
        $this->calculateNextRun();
    }

    /**
     * Виконання завдання
     *
     * @return void
     */
    public function run(): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            ($this->callback)();
            $this->lastRun = time();
            $this->calculateNextRun();
        } catch (Throwable $e) {
            if (function_exists('logger')) {
                logger()->logError("Scheduled task '{$this->name}' failed: " . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Перевірка, чи потрібно виконувати завдання
     *
     * @return bool
     */
    public function isDue(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        return $this->nextRun !== null && $this->nextRun <= time();
    }

    /**
     * Розрахунок наступного виконання
     *
     * @return void
     */
    private function calculateNextRun(): void
    {
        $this->nextRun = $this->parseCronExpression($this->cronExpression);
    }

    /**
     * Парсинг cron expression
     *
     * @param string $expression Cron expression
     * @return int Timestamp наступного виконання
     */
    private function parseCronExpression(string $expression): int
    {
        $parts = explode(' ', trim($expression));

        if (count($parts) !== 5) {
            throw new InvalidArgumentException("Invalid cron expression: {$expression}");
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;
        $now = time();
        $currentMinute = (int)date('i', $now);
        $currentHour = (int)date('G', $now);
        $currentDay = (int)date('j', $now);
        $currentMonth = (int)date('n', $now);
        $currentWeekday = (int)date('w', $now);

        // Парсинг значень
        $minutes = $this->parseField($minute, 0, 59);
        $hours = $this->parseField($hour, 0, 23);
        $days = $this->parseField($day, 1, 31);
        $months = $this->parseField($month, 1, 12);
        $weekdays = $this->parseField($weekday, 0, 6);

        // Знаходимо наступний час виконання
        $nextRun = $now;
        $maxIterations = 365 * 24 * 60; // Максимум 1 рік
        $iterations = 0;

        while ($iterations < $maxIterations) {
            $nextRun += 60; // Додаємо 1 хвилину
            $iterations++;

            $m = (int)date('i', $nextRun);
            $h = (int)date('G', $nextRun);
            $d = (int)date('j', $nextRun);
            $mo = (int)date('n', $nextRun);
            $w = (int)date('w', $nextRun);

            if (in_array($mo, $months) &&
                in_array($d, $days) &&
                in_array($w, $weekdays) &&
                in_array($h, $hours) &&
                in_array($m, $minutes)) {
                return $nextRun;
            }
        }

        // Якщо не знайдено, повертаємо через 1 хвилину
        return $now + 60;
    }

    /**
     * Парсинг поля cron expression
     *
     * @param string $field Поле (наприклад, "*\/5", "1-10", "1,2,3")
     * @param int $min Мінімальне значення
     * @param int $max Максимальне значення
     * @return array<int> Масив значень
     */
    private function parseField(string $field, int $min, int $max): array
    {
        if ($field === '*') {
            return range($min, $max);
        }

        // Обробка діапазонів з кроком (наприклад, "*/5", "1-10/2")
        if (str_contains($field, '/')) {
            [$range, $step] = explode('/', $field, 2);
            $step = (int)$step;

            if ($range === '*') {
                $values = range($min, $max);
            } elseif (str_contains($range, '-')) {
                [$start, $end] = explode('-', $range, 2);
                $values = range((int)$start, (int)$end);
            } else {
                $values = [(int)$range];
            }

            return array_filter($values, fn($v) => $v % $step === 0);
        }

        // Обробка діапазонів (наприклад, "1-10")
        if (str_contains($field, '-')) {
            [$start, $end] = explode('-', $field, 2);
            return range((int)$start, (int)$end);
        }

        // Обробка списків (наприклад, "1,2,3")
        if (str_contains($field, ',')) {
            return array_map('intval', explode(',', $field));
        }

        // Одиночне значення
        return [(int)$field];
    }

    /**
     * Встановлення розкладу через cron
     */
    public function cron(string $expression): self
    {
        $this->cronExpression = $expression;
        $this->calculateNextRun();
        return $this;
    }

    /**
     * Виконання щодня
     */
    public function daily(int $hour = 0, int $minute = 0): self
    {
        $this->cronExpression = "{$minute} {$hour} * * *";
        $this->calculateNextRun();
        return $this;
    }

    /**
     * Виконання щогодини
     */
    public function hourly(int $minute = 0): self
    {
        $this->cronExpression = "{$minute} * * * *";
        $this->calculateNextRun();
        return $this;
    }

    /**
     * Отримання назви завдання
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Отримання наступного виконання
     */
    public function getNextRun(): ?int
    {
        return $this->nextRun;
    }

    /**
     * Отримання останнього виконання
     */
    public function getLastRun(): ?int
    {
        return $this->lastRun;
    }
}
