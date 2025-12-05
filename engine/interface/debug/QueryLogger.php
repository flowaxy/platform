<?php

/**
 * Логер SQL запитів для Debug Bar
 * 
 * @package Engine\Interface\Debug
 * @version 1.0.0
 */

declare(strict_types=1);

final class QueryLogger
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private static array $queries = [];

    /**
     * Додавання запиту до логу
     * 
     * @param string $query SQL запит
     * @param array<string|int, mixed> $params Параметри
     * @param float $executionTime Час виконання
     * @return void
     */
    public static function log(string $query, array $params = [], float $executionTime = 0.0): void
    {
        self::$queries[] = [
            'query' => $query,
            'params' => $params,
            'time' => $executionTime,
            'trace' => self::getTrace(),
        ];
    }

    /**
     * Отримання всіх запитів
     * 
     * @return array<int, array<string, mixed>>
     */
    public static function getQueries(): array
    {
        return self::$queries;
    }

    /**
     * Отримання статистики запитів
     * 
     * @return array<string, mixed>
     */
    public static function getStats(): array
    {
        $totalTime = 0.0;
        $slowQueries = 0;

        foreach (self::$queries as $query) {
            $totalTime += $query['time'];
            if ($query['time'] > 0.1) {
                $slowQueries++;
            }
        }

        return [
            'total' => count(self::$queries),
            'total_time' => round($totalTime, 4),
            'average_time' => count(self::$queries) > 0 ? round($totalTime / count(self::$queries), 4) : 0.0,
            'slow_queries' => $slowQueries,
        ];
    }

    /**
     * Очищення логу
     * 
     * @return void
     */
    public static function clear(): void
    {
        self::$queries = [];
    }

    /**
     * Отримання трасування виклику
     * 
     * @return array<int, array<string, mixed>>
     */
    private static function getTrace(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        // Видаляємо виклики самого QueryLogger
        return array_filter($trace, function ($frame) {
            return !isset($frame['class']) || $frame['class'] !== self::class;
        });
    }
}

