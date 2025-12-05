<?php

/**
 * Профілювання продуктивності
 * 
 * Відстеження повільних запитів та рекомендації з оптимізації
 * 
 * @package Engine\Interface\Debug
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/QueryLogger.php';

final class PerformanceProfiler
{
    /**
     * @var array<string, float>
     */
    private array $timers = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $profiles = [];

    /**
     * Початок вимірювання
     * 
     * @param string $name Назва операції
     * @return void
     */
    public function start(string $name): void
    {
        $this->timers[$name] = microtime(true);
    }

    /**
     * Завершення вимірювання
     * 
     * @param string $name Назва операції
     * @return float Час виконання в секундах
     */
    public function stop(string $name): float
    {
        if (!isset($this->timers[$name])) {
            return 0.0;
        }

        $duration = microtime(true) - $this->timers[$name];
        
        if (!isset($this->profiles[$name])) {
            $this->profiles[$name] = [
                'calls' => 0,
                'total_time' => 0.0,
                'min_time' => PHP_FLOAT_MAX,
                'max_time' => 0.0,
            ];
        }

        $this->profiles[$name]['calls']++;
        $this->profiles[$name]['total_time'] += $duration;
        $this->profiles[$name]['min_time'] = min($this->profiles[$name]['min_time'], $duration);
        $this->profiles[$name]['max_time'] = max($this->profiles[$name]['max_time'], $duration);

        unset($this->timers[$name]);

        return $duration;
    }

    /**
     * Отримання профілю операції
     * 
     * @param string $name Назва операції
     * @return array<string, mixed>|null
     */
    public function getProfile(string $name): ?array
    {
        return $this->profiles[$name] ?? null;
    }

    /**
     * Отримання всіх профілів
     * 
     * @return array<string, array<string, mixed>>
     */
    public function getAllProfiles(): array
    {
        return $this->profiles;
    }

    /**
     * Отримання повільних операцій
     * 
     * @param float $threshold Поріг у секундах
     * @return array<string, array<string, mixed>>
     */
    public function getSlowOperations(float $threshold = 0.1): array
    {
        $slow = [];

        foreach ($this->profiles as $name => $profile) {
            $avgTime = $profile['total_time'] / $profile['calls'];
            
            if ($avgTime >= $threshold || $profile['max_time'] >= $threshold) {
                $slow[$name] = array_merge($profile, [
                    'avg_time' => $avgTime,
                ]);
            }
        }

        uasort($slow, fn($a, $b) => $b['avg_time'] <=> $a['avg_time']);

        return $slow;
    }

    /**
     * Генерація рекомендацій з оптимізації
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getRecommendations(): array
    {
        $recommendations = [];

        // Аналіз SQL запитів
        $queries = QueryLogger::getQueries();
        $queryStats = QueryLogger::getStats();

        if ($queryStats['total'] > 50) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Багато SQL запитів',
                'message' => "Виявлено {$queryStats['total']} SQL запитів. Розгляньте можливість використання кешування або оптимізації запитів.",
                'priority' => 'high',
            ];
        }

        if ($queryStats['slow_queries'] > 0) {
            $recommendations[] = [
                'type' => 'error',
                'title' => 'Повільні SQL запити',
                'message' => "Виявлено {$queryStats['slow_queries']} повільних запитів. Перевірте індекси БД та оптимізуйте запити.",
                'priority' => 'high',
            ];
        }

        // Аналіз хуків
        if (function_exists('hooks')) {
            $hookManager = hooks();
            $performanceMonitor = $hookManager->getPerformanceMonitor();
            $slowestHooks = $performanceMonitor->getSlowestHooks(5);

            if (!empty($slowestHooks)) {
                $hookNames = array_keys($slowestHooks);
                $recommendations[] = [
                    'type' => 'info',
                    'title' => 'Повільні хуки',
                    'message' => 'Найповільніші хуки: ' . implode(', ', $hookNames),
                    'priority' => 'medium',
                ];
            }
        }

        // Аналіз профілів
        $slowOperations = $this->getSlowOperations(0.5);
        if (!empty($slowOperations)) {
            $operationNames = array_keys($slowOperations);
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Повільні операції',
                'message' => 'Виявлено повільні операції: ' . implode(', ', $operationNames),
                'priority' => 'medium',
            ];
        }

        // Перевірка кешу
        if (function_exists('cache')) {
            $cache = cache();
            $cacheStats = $cache->getStats();
            
            if ($cacheStats['expired_files'] > $cacheStats['valid_files']) {
                $recommendations[] = [
                    'type' => 'info',
                    'title' => 'Багато застарілого кешу',
                    'message' => 'Рекомендується очистити кеш для покращення продуктивності.',
                    'priority' => 'low',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Отримання звіту про продуктивність
     * 
     * @return array<string, mixed>
     */
    public function getReport(): array
    {
        $queryStats = QueryLogger::getStats();
        
        $report = [
            'queries' => $queryStats,
            'profiles' => $this->profiles,
            'slow_operations' => $this->getSlowOperations(),
            'recommendations' => $this->getRecommendations(),
        ];

        if (function_exists('hooks')) {
            $hookManager = hooks();
            $performanceMonitor = $hookManager->getPerformanceMonitor();
            $report['hooks'] = $performanceMonitor->getSummary();
        }

        return $report;
    }

    /**
     * Очищення профілів
     * 
     * @return void
     */
    public function clear(): void
    {
        $this->timers = [];
        $this->profiles = [];
        QueryLogger::clear();
    }
}

