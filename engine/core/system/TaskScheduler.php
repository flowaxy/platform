<?php

/**
 * Планувальник завдань (cron-like)
 * 
 * @package Engine\System
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/tasks/ScheduledTask.php';
require_once __DIR__ . '/tasks/TaskRunner.php';

final class TaskScheduler
{
    private TaskRunner $runner;

    public function __construct()
    {
        $this->runner = new TaskRunner();
    }

    /**
     * Реєстрація планового завдання
     * 
     * @param string $name Назва завдання
     * @param callable $callback Callback функція
     * @param string $cronExpression Cron expression
     * @return ScheduledTask
     */
    public function schedule(string $name, callable $callback, string $cronExpression): ScheduledTask
    {
        $task = new ScheduledTask($name, $callback, $cronExpression);
        $this->runner->add($task);
        
        return $task;
    }

    /**
     * Виконання готових завдань
     * 
     * @return void
     */
    public function run(): void
    {
        $this->runner->run();
    }

    /**
     * Отримання виконавця завдань
     * 
     * @return TaskRunner
     */
    public function getRunner(): TaskRunner
    {
        return $this->runner;
    }
}

