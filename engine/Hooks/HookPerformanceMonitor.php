<?php

/**
 * Моніторинг продуктивності хуків
 *
 * Відстежує час виконання кожного хука та збирає метрики
 *
 * @package Engine\System\Hooks
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Hooks;

final class HookPerformanceMonitor
{
    /**
     * @var array<string, array{
     *     calls: int,
     *     total_time: float,
     *     min_time: float,
     *     max_time: float,
     *     avg_time: float
     * }>
     */
    private array $metrics = [];

    /**
     * Початок вимірювання часу виконання хука
     *
     * @param string $hookName Назва хука
     * @return float Timestamp початку виконання
     */
    public function start(string $hookName): float
    {
        if (function_exists('logDebug')) {
            logDebug('HookPerformanceMonitor::start: Starting performance measurement', ['hook' => $hookName]);
        }
        return microtime(true);
    }

    /**
     * Завершення вимірювання та збереження метрик
     *
     * @param string $hookName Назва хука
     * @param float $startTime Timestamp початку виконання
     * @return float Час виконання в секундах
     */
    public function end(string $hookName, float $startTime): float
    {
        $executionTime = microtime(true) - $startTime;

        if (!isset($this->metrics[$hookName])) {
            $this->metrics[$hookName] = [
                'calls' => 0,
                'total_time' => 0.0,
                'min_time' => PHP_FLOAT_MAX,
                'max_time' => 0.0,
                'avg_time' => 0.0,
            ];
        }

        $metrics = &$this->metrics[$hookName];
        $metrics['calls']++;
        $metrics['total_time'] += $executionTime;
        $metrics['min_time'] = min($metrics['min_time'], $executionTime);
        $metrics['max_time'] = max($metrics['max_time'], $executionTime);
        $metrics['avg_time'] = $metrics['total_time'] / $metrics['calls'];

        // Логуємо повільні хуки як WARNING
        $slowThreshold = 0.1; // 100ms
        if ($executionTime >= $slowThreshold && function_exists('logWarning')) {
            logWarning('HookPerformanceMonitor::end: Slow hook detected', [
                'hook' => $hookName,
                'execution_time' => round($executionTime, 4),
                'threshold' => $slowThreshold,
                'calls' => $metrics['calls'],
                'avg_time' => round($metrics['avg_time'], 4),
            ]);
        } elseif (function_exists('logDebug')) {
            logDebug('HookPerformanceMonitor::end: Performance measurement completed', [
                'hook' => $hookName,
                'execution_time' => round($executionTime, 4),
            ]);
        }

        return $executionTime;
    }

    /**
     * Отримання метрик для конкретного хука
     *
     * @param string $hookName Назва хука
     * @return array<string, mixed>|null Метрики або null якщо хук не знайдено
     */
    public function getMetrics(string $hookName): ?array
    {
        return $this->metrics[$hookName] ?? null;
    }

    /**
     * Отримання всіх метрик
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Отримання найповільніших хуків
     *
     * @param int $limit Кількість хуків для повернення
     * @return array<string, array<string, mixed>>
     */
    public function getSlowestHooks(int $limit = 10): array
    {
        if (function_exists('logDebug')) {
            logDebug('HookPerformanceMonitor::getSlowestHooks: Retrieving slowest hooks', ['limit' => $limit]);
        }

        $sorted = $this->metrics;

        uasort($sorted, function (array $a, array $b) {
            return $b['avg_time'] <=> $a['avg_time'];
        });

        $result = array_slice($sorted, 0, $limit, true);

        if (!empty($result) && function_exists('logInfo')) {
            logInfo('HookPerformanceMonitor::getSlowestHooks: Slowest hooks retrieved', [
                'count' => count($result),
            ]);
        }

        return $result;
    }

    /**
     * Отримання найчастіше викликаних хуків
     *
     * @param int $limit Кількість хуків для повернення
     * @return array<string, array<string, mixed>>
     */
    public function getMostCalledHooks(int $limit = 10): array
    {
        $sorted = $this->metrics;

        uasort($sorted, function (array $a, array $b) {
            return $b['calls'] <=> $a['calls'];
        });

        return array_slice($sorted, 0, $limit, true);
    }

    /**
     * Очищення метрик
     *
     * @return void
     */
    public function clear(): void
    {
        $this->metrics = [];
    }

    /**
     * Отримання загальної статистики
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        $totalCalls = 0;
        $totalTime = 0.0;
        $hookCount = count($this->metrics);

        foreach ($this->metrics as $metrics) {
            $totalCalls += $metrics['calls'];
            $totalTime += $metrics['total_time'];
        }

        return [
            'total_hooks' => $hookCount,
            'total_calls' => $totalCalls,
            'total_time' => round($totalTime, 4),
            'average_time_per_call' => $totalCalls > 0 ? round($totalTime / $totalCalls, 4) : 0.0,
        ];
    }
}
