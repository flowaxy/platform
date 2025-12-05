<?php

/**
 * Панель відлагодження для розробників
 * 
 * Відображення SQL запитів, хуків, кешу та інших метрик
 * 
 * @package Engine\Interface\Debug
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/QueryLogger.php';

final class DebugBar
{
    private bool $enabled = false;
    private array $tabs = [];

    public function __construct()
    {
        // Увімкнення тільки в режимі розробки
        $this->enabled = defined('DEBUG') && DEBUG === true;
    }

    /**
     * Додавання табу
     * 
     * @param string $id Ідентифікатор табу
     * @param string $title Назва табу
     * @param callable $contentCallback Callback для генерації контенту
     * @return void
     */
    public function addTab(string $id, string $title, callable $contentCallback): void
    {
        $this->tabs[$id] = [
            'title' => $title,
            'content' => $contentCallback,
        ];
    }

    /**
     * Рендеринг Debug Bar
     * 
     * @return string HTML код
     */
    public function render(): string
    {
        if (!$this->enabled) {
            return '';
        }

        $html = '<div id="flowaxy-debug-bar" class="flowaxy-debug-bar">';
        $html .= '<div class="debug-bar-header">';
        $html .= '<span class="debug-bar-title">Flowaxy Debug Bar</span>';
        
        // Таби
        $html .= '<div class="debug-bar-tabs">';
        foreach ($this->tabs as $id => $tab) {
            $html .= '<button class="debug-tab" data-tab="' . htmlspecialchars($id) . '">' . htmlspecialchars($tab['title']) . '</button>';
        }
        $html .= '</div>';
        
        $html .= '<button class="debug-bar-toggle">×</button>';
        $html .= '</div>';
        
        // Контент табів
        $html .= '<div class="debug-bar-content">';
        foreach ($this->tabs as $id => $tab) {
            $html .= '<div id="debug-tab-' . htmlspecialchars($id) . '" class="debug-tab-content">';
            $html .= call_user_func($tab['content']);
            $html .= '</div>';
        }
        $html .= '</div>';
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Ініціалізація стандартних табів
     * 
     * @return void
     */
    public function initDefaultTabs(): void
    {
        // Таб SQL запитів
        $this->addTab('queries', 'SQL Queries', function () {
            $queries = QueryLogger::getQueries();
            $stats = QueryLogger::getStats();
            
            $html = '<div class="debug-stats">';
            $html .= '<div>Total: ' . $stats['total'] . '</div>';
            $html .= '<div>Time: ' . $stats['total_time'] . 's</div>';
            $html .= '<div>Slow: ' . $stats['slow_queries'] . '</div>';
            $html .= '</div>';
            
            $html .= '<table class="debug-table">';
            $html .= '<thead><tr><th>Query</th><th>Params</th><th>Time</th></tr></thead>';
            $html .= '<tbody>';
            
            foreach ($queries as $query) {
                $html .= '<tr>';
                $html .= '<td><code>' . htmlspecialchars($query['query']) . '</code></td>';
                $html .= '<td><pre>' . htmlspecialchars(json_encode($query['params'], JSON_PRETTY_PRINT)) . '</pre></td>';
                $html .= '<td>' . number_format($query['time'], 4) . 's</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
            
            return $html;
        });

        // Таб хуків
        $this->addTab('hooks', 'Hooks', function () {
            if (!function_exists('hooks')) {
                return '<p>HookManager недоступний</p>';
            }

            $hookManager = hooks();
            $stats = $hookManager->getStats();
            $performanceMonitor = $hookManager->getPerformanceMonitor();
            $metrics = $performanceMonitor->getAllMetrics();
            
            $html = '<div class="debug-stats">';
            $html .= '<div>Total Hooks: ' . $stats['total_hooks'] . '</div>';
            $html .= '</div>';
            
            $html .= '<table class="debug-table">';
            $html .= '<thead><tr><th>Hook</th><th>Calls</th><th>Avg Time</th><th>Max Time</th></tr></thead>';
            $html .= '<tbody>';
            
            foreach ($metrics as $hookName => $metric) {
                $html .= '<tr>';
                $html .= '<td><code>' . htmlspecialchars($hookName) . '</code></td>';
                $html .= '<td>' . $metric['calls'] . '</td>';
                $html .= '<td>' . number_format($metric['avg_time'], 4) . 's</td>';
                $html .= '<td>' . number_format($metric['max_time'], 4) . 's</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
            
            return $html;
        });

        // Таб кешу
        $this->addTab('cache', 'Cache', function () {
            if (!function_exists('cache')) {
                return '<p>Cache недоступний</p>';
            }

            $cache = cache();
            $stats = $cache->getStats();
            
            $html = '<div class="debug-stats">';
            $html .= '<div>Total Files: ' . $stats['total_files'] . '</div>';
            $html .= '<div>Valid: ' . $stats['valid_files'] . '</div>';
            $html .= '<div>Expired: ' . $stats['expired_files'] . '</div>';
            $html .= '<div>Size: ' . number_format($stats['total_size'] / 1024, 2) . ' KB</div>';
            $html .= '</div>';
            
            return $html;
        });
    }
}

