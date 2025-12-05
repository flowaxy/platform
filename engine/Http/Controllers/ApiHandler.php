<?php

/**
 * Обробник API запитів
 * Аутентифікація та обробка API запитів
 *
 * @package Engine\Classes\Http
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class ApiHandler
{
    private ?ApiManager $apiManager = null;
    private ?array $authenticatedKey = null;

    /**
     * Конструктор
     */
    public function __construct()
    {
        if (class_exists('ApiManager')) {
            $this->apiManager = new ApiManager();
        }
    }

    /**
     * Перевірка, чи є запит API запитом
     *
     * @return bool
     */
    public static function isApiRequest(): bool
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';

        return str_starts_with($path, '/api/') || str_starts_with($path, '/api/v1/');
    }

    /**
     * Аутентифікація за API ключем
     *
     * @return bool
     */
    public function authenticate(): bool
    {
        $request = Request::getInstance();

        if ($this->apiManager === null) {
            $this->sendError('API Keys plugin недоступний', 503);

            return false;
        }

        // Отримуємо API ключ з заголовка або параметра
        $apiKey = null;

        // Перевіряємо заголовок Authorization (Bearer token)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (! empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $apiKey = $matches[1];
        }

        // Якщо не знайдено в заголовку, перевіряємо параметр
        if (empty($apiKey)) {
            $apiKey = $request->query('api_key', $request->post('api_key', null));
        }

        if (empty($apiKey)) {
            $this->sendError('API ключ не вказано', 401);

            return false;
        }

        // Валідуємо ключ
        $keyData = $this->apiManager->validateKey($apiKey);
        if ($keyData === null) {
            $this->sendError('Невалідний або закінчений API ключ', 401);

            return false;
        }

        $this->authenticatedKey = $keyData;

        return true;
    }

    /**
     * Отримання даних аутентифікованого ключа
     *
     * @return array|null
     */
    public function getAuthenticatedKey(): ?array
    {
        return $this->authenticatedKey;
    }

    /**
     * Перевірка дозволу
     *
     * @param string $permission Дозвіл
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->authenticatedKey === null || $this->apiManager === null) {
            return false;
        }

        return $this->apiManager->hasPermission($this->authenticatedKey, $permission);
    }

    /**
     * Відправка помилки
     *
     * @param string $message Повідомлення
     * @param int $statusCode Код статусу
     */
    public function sendError(string $message, int $statusCode = 400): void
    {
        Response::jsonResponse([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $statusCode,
            ],
        ], $statusCode);
    }

    /**
     * Отправка успешного ответа
     *
     * @param mixed $data Данные
     * @param int $statusCode Код статуса
     */
    public function sendSuccess($data, int $statusCode = 200): void
    {
        Response::jsonResponse([
            'success' => true,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Middleware для проверки API ключа
     *
     * @param array $params Параметры маршрута
     * @return bool
     */
    /**
     * @param array<string, mixed> $params
     * @return bool
     */
    public static function requireAuth(array $params = []): bool
    {
        $handler = new self();

        return $handler->authenticate();
    }

    /**
     * Middleware для проверки разрешения
     *
     * @param string $permission Разрешение
     * @return callable
     */
    public static function requirePermission(string $permission): callable
    {
        /**
         * @param array<string, mixed> $params
         * @return bool
         */
        return function (array $params = []) use ($permission) {
            $handler = new self();
            if (! $handler->authenticate()) {
                return false;
            }

            if (! $handler->hasPermission($permission)) {
                $handler->sendError('Недостаточно прав доступа', 403);

                return false;
            }

            return true;
        };
    }
}
