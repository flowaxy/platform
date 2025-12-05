<?php

/**
 * Приклад ScheduledTask: Очищення кешу
 * 
 * @package Engine\System\Tasks\Examples
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/ScheduledTask.php';
require_once dirname(__DIR__, 2) . '/TaskScheduler.php';

/**
 * Приклад використання ScheduledTask через TaskScheduler
 */
final class CleanupCacheTask
{
    /**
     * Реєстрація завдання через TaskScheduler
     */
    public static function register(TaskScheduler $scheduler): void
    {
        $scheduler->schedule(
            'cleanup_cache',
            [self::class, 'handle'],
            '0 2 * * *' // Щодня о 2:00
        );
    }

    /**
     * Виконання завдання
     */
    public static function handle(): void
    {
        if (function_exists('cache')) {
            $cache = cache();
            
            // Очищення застарілих записів кешу
            // Тут буде логіка очищення
            
            if (function_exists('logger')) {
                logger()->logInfo('Cache cleanup task executed');
            }
        }
    }
}

