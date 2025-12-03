<?php

/**
 * Сторінка AJAX очищення кешу
 * Обробляє AJAX запити для очищення всього або простроченого кешу
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/AdminPage.php';

class CacheClearPage extends AdminPage
{
    public function __construct()
    {
        // Ця сторінка доступна тільки через AJAX
        // Якщо хтось заходить по прямому посиланню - перенаправляємо на dashboard
        if (!AjaxHandler::isAjax()) {
            // Базова перевірка авторизації перед перенаправленням
            SecurityHelper::requireAdmin();
            Response::redirectStatic(UrlHelper::admin('dashboard'));
            exit;
        }
        
        // Викликаємо батьківський конструктор тільки для AJAX запитів
        parent::__construct();
    }

    public function handle(): void
    {
        // Перевіряємо, що це AJAX запит (подвійна перевірка)
        if (!AjaxHandler::isAjax()) {
            Response::redirectStatic(UrlHelper::admin('dashboard'));
            exit;
        }

        // Перевірка прав доступу
        $session = sessionManager();
        $userId = (int)($session->get('admin_user_id') ?? 0);
        $hasAccess = ($userId === 1) || (function_exists('current_user_can') && current_user_can('admin.access'));
        
        if (!$hasAccess) {
            $this->sendJsonResponse([
                'success' => false,
                'error' => 'У вас немає прав на очищення кешу'
            ], 403);
            exit;
        }

        // Перевірка CSRF токену
        if (!$this->verifyCsrf()) {
            $this->sendJsonResponse([
                'success' => false,
                'error' => 'Помилка безпеки (CSRF токен не валідний)'
            ], 403);
            exit;
        }

        // Отримуємо дію
        $action = SecurityHelper::sanitizeInput($_POST['cache_action'] ?? '');
        
        if (empty($action)) {
            $this->sendJsonResponse([
                'success' => false,
                'error' => 'Дія не вказана'
            ], 400);
            exit;
        }

        try {
            match ($action) {
                'clear_all' => $this->clearAllCache(),
                'clear_expired' => $this->clearExpiredCache(),
                default => $this->sendJsonResponse([
                    'success' => false,
                    'error' => 'Невідома дія: ' . htmlspecialchars($action)
                ], 400)
            };
        } catch (Exception $e) {
            logger()->logError('CacheClearPage error: ' . $e->getMessage(), ['exception' => $e]);
            
            $this->sendJsonResponse([
                'success' => false,
                'error' => 'Помилка при очищенні кешу: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Очищення всього кешу
     */
    private function clearAllCache(): void
    {
        $cache = cache();
        
        // Очищаємо файловий кеш
        $result = $cache->clear();
        
        if ($result) {
            logger()->logInfo('Кеш повністю очищено');
            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Весь кеш успішно очищено'
            ], 200);
        } else {
            $this->sendJsonResponse([
                'success' => false,
                'error' => 'Помилка при очищенні кешу'
            ], 500);
        }
    }

    /**
     * Очищення простроченого кешу
     */
    private function clearExpiredCache(): void
    {
        $cache = cache();
        
        // Очищаємо прострочений кеш
        $cleaned = $cache->cleanup();
        
        logger()->logInfo('Прострочений кеш очищено', ['cleaned_files' => $cleaned]);
        $this->sendJsonResponse([
            'success' => true,
            'message' => 'Прострочений кеш очищено. Видалено файлів: ' . $cleaned,
            'cleaned_count' => $cleaned
        ], 200);
    }
}

