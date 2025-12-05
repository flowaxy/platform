<?php

/**
 * Контролер API
 * Базові endpoints для API
 *
 * @package Engine\Classes\Http
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class ApiController
{
    private ApiHandler $handler;

    /**
     * Конструктор
     */
    public function __construct()
    {
        $this->handler = new ApiHandler();
    }

    /**
     * Інформація про систему
     *
     * @return void
     */
    public function info(): void
    {
        // Не потребує аутентифікації
        $info = [
            'name' => 'Flowaxy CMS',
            'version' => '1.0.0 Alpha prerelease',
            'api_version' => '1.0',
            'timestamp' => time(),
        ];

        Response::jsonResponse($info);
    }

    /**
     * Статус системи
     *
     * @return void
     */
    public function status(): void
    {
        // Не потребує аутентифікації
        try {
            $db = Database::getInstance();
            $db->getConnection();
            $dbStatus = 'connected';
        } catch (Exception $e) {
            $dbStatus = 'error';
        }

        $status = [
            'status' => 'ok',
            'database' => $dbStatus,
            'timestamp' => time(),
        ];

        Response::jsonResponse($status);
    }

    /**
     * Інформація про аутентифікований ключ
     *
     * @return void
     */
    public function me(): void
    {
        if (! $this->handler->authenticate()) {
            if (function_exists('logDebug')) {
                logDebug('ApiController: API authentication failed', ['endpoint' => '/api/me']);
            } else {
                logger()->logDebug('API аутентифікація невдала', ['endpoint' => '/api/me']);
            }
            return;
        }

        if (function_exists('logDebug')) {
            logDebug('ApiController: API request /me', ['authenticated' => true]);
        } else {
            logger()->logDebug('API запит /me', ['authenticated' => true]);
        }
        $keyData = $this->handler->getAuthenticatedKey();
        if ($keyData === null) {
            $this->handler->sendError('Не вдалося отримати дані ключа', 500);

            return;
        }

        // Не повертаємо чутливі дані
        unset($keyData['key_hash']);

        $this->handler->sendSuccess($keyData);
    }

    /**
     * Перевірка дозволу
     *
     * @param array $params Параметри маршруту
     * @return void
     */
    /**
     * @param array<string, mixed> $params
     * @return void
     */
    public function checkPermission(array $params = []): void
    {
        if (! $this->handler->authenticate()) {
            return;
        }

        $request = Request::getInstance();
        $permission = $request->query('permission', $params['permission'] ?? null);

        if (empty($permission)) {
            $this->handler->sendError('Дозвіл не вказано', 400);

            return;
        }

        $hasPermission = $this->handler->hasPermission($permission);

        $this->handler->sendSuccess([
            'permission' => $permission,
            'granted' => $hasPermission,
        ]);
    }

    /**
     * Список доступних дозволів
     *
     * @return void
     */
    public function permissions(): void
    {
        if (! $this->handler->authenticate()) {
            return;
        }

        $permissions = [
            'system.read' => 'Читання інформації про систему',
            'system.write' => 'Зміна налаштувань системи',
            'content.read' => 'Читання контенту',
            'content.write' => 'Створення та редагування контенту',
            'content.delete' => 'Видалення контенту',
            'users.read' => 'Читання інформації про користувачів',
            'users.write' => 'Управління користувачами',
            'plugins.read' => 'Читання інформації про плагіни',
            'plugins.write' => 'Управління плагінами',
            'themes.read' => 'Читання інформації про теми',
            'themes.write' => 'Управління темами',
            '*' => 'Повний доступ',
        ];

        $this->handler->sendSuccess([
            'permissions' => $permissions,
            'description' => 'Список доступних дозволів для API ключів',
        ]);
    }
}
