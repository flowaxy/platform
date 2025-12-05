<?php

/**
 * Сторінка відлагодження хуків
 * 
 * Візуалізація виконання хуків, метрик продуктивності та статистики
 * 
 * @package Engine\Interface\AdminUI\Pages
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/AdminPage.php';

class HooksDebugPage extends AdminPage
{
    public function __construct()
    {
        parent::__construct();

        $this->pageTitle = 'Відлагодження хуків - Flowaxy CMS';
        $this->pageHeaderTitle = 'Відлагодження хуків';
        $this->pageHeaderDescription = 'Відстеження виконання хуків та метрик продуктивності';
        $this->pageHeaderIcon = 'fa-code-branch';
        $this->templateName = 'hooks-debug';
        
        $this->additionalCSS[] = '/admin-ui/assets/styles/hooks-debug.css';
        $this->additionalJS[] = '/admin-ui/assets/scripts/hooks-debug.js';
    }

    public function handle()
    {
        $hookManager = hooks();
        
        if (!$hookManager) {
            $this->message = 'HookManager недоступний';
            $this->messageType = 'error';
            $this->render([]);
            return;
        }

        $registry = $hookManager->getRegistry();
        $performanceMonitor = $hookManager->getPerformanceMonitor();
        $stats = $hookManager->getStats();

        $data = [
            'all_hooks' => $registry->getAll(),
            'performance_metrics' => $performanceMonitor->getAllMetrics(),
            'slowest_hooks' => $performanceMonitor->getSlowestHooks(10),
            'most_called_hooks' => $performanceMonitor->getMostCalledHooks(10),
            'performance_summary' => $performanceMonitor->getSummary(),
            'hook_stats' => $stats,
            'actions' => $registry->getByType(\HookType::Action),
            'filters' => $registry->getByType(\HookType::Filter),
        ];

        $this->render($data);
    }
}

