<?php

/**
 * Виконавець планових завдань
 * 
 * @package Engine\System\Tasks
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/ScheduledTask.php';

final class TaskRunner
{
    /**
     * @var array<string, ScheduledTask>
     */
    private array $tasks = [];

    /**
     * Додавання завдання
     * 
     * @param ScheduledTask $task Завдання
     * @return void
     */
    public function add(ScheduledTask $task): void
    {
        $this->tasks[$task->getName()] = $task;
    }

    /**
     * Виконання всіх готових завдань
     * 
     * @return void
     */
    public function run(): void
    {
        foreach ($this->tasks as $task) {
            if ($task->isDue()) {
                $task->run();
            }
        }
    }

    /**
     * Отримання всіх завдань
     * 
     * @return array<string, ScheduledTask>
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }
}

