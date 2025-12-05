<?php

/**
 * Глобальний клас для обробки AJAX запитів
 * Універсальна обробка AJAX запитів з валідацією, CSRF захистом та обробкою помилок
 *
 * @package Engine\Classes\Http
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Contracts/Http/AjaxHandlerInterface.php';

class AjaxHandler implements AjaxHandlerInterface
{
    private array $actions = [];
    private bool $requireCsrf = true;
    private bool $requireAuth = true;
    /** @var callable|null */
    private $authCallback = null;
    /** @var callable|null */
    private $errorHandler = null;

    /**
     * Конструктор
     */
    public function __construct()
    {
        // Встановлюємо обробник помилок за замовчуванням
        $this->errorHandler = fn ($error, $code = 500) => [
            'success' => false,
            'error' => $error,
            'code' => $code,
        ];
    }

    /**
     * Реєстрація обробника дії
     *
     * @param string $action Назва дії
     * @param callable $handler Обробник
     * @param array $options Опції (requireCsrf, requireAuth, validate)
     * @return self
     */
    /**
     * @param string $action
     * @param callable $handler
     * @param array<string, mixed> $options
     * @return self
     */
    public function register(string $action, callable $handler, array $options = []): self
    {
        $this->actions[$action] = [
            'handler' => $handler,
            'requireCsrf' => $options['requireCsrf'] ?? $this->requireCsrf,
            'requireAuth' => $options['requireAuth'] ?? $this->requireAuth,
            'validate' => $options['validate'] ?? null,
            'method' => $options['method'] ?? 'POST', // GET або POST
        ];

        return $this;
    }

    /**
     * Встановлення обробника помилок
     *
     * @param callable $handler
     * @return self
     */
    public function setErrorHandler(callable $handler): self
    {
        $this->errorHandler = $handler;

        return $this;
    }

    /**
     * Встановлення обробника авторизації
     *
     * @param callable $handler
     * @return self
     */
    public function setAuthCallback(callable $handler): self
    {
        $this->authCallback = $handler;

        return $this;
    }

    /**
     * Перевірка чи це AJAX запит
     *
     * @return bool
     */
    public static function isAjax(): bool
    {
        return ! empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Обробка AJAX запиту
     *
     * @param string|null $action Дія (якщо null, береться з запиту)
     * @return void
     */
    public function handle(?string $action = null): void
    {
        // Встановлюємо заголовки JSON
        Response::setHeader('Content-Type', 'application/json; charset=utf-8');

        // Перевіряємо чи це AJAX запит
        if (! self::isAjax()) {
            $this->sendError('Це не AJAX запит', 400);

            return;
        }

        // Отримуємо дію
        if ($action === null) {
            $action = SecurityHelper::sanitizeInput($_POST['action'] ?? $_GET['action'] ?? '');
        }

        if (empty($action)) {
            $this->sendError('Дія не вказана', 400);

            return;
        }

        // Перевіряємо чи зареєстрована дія
        if (! isset($this->actions[$action])) {
            $this->sendError('Невідома дія: ' . $action, 404);

            return;
        }

        $actionConfig = $this->actions[$action];

        // Перевіряємо метод запиту
        $requestMethod = Request::getMethod();
        $requiredMethod = strtoupper($actionConfig['method']);
        if ($requestMethod !== $requiredMethod) {
            $this->sendError('Невірний метод запиту. Очікується: ' . $requiredMethod, 405);

            return;
        }

        // Перевірка авторизації
        if ($actionConfig['requireAuth'] && $this->authCallback) {
            $authResult = call_user_func($this->authCallback);
            if ($authResult !== true) {
                $this->sendError('Необхідна авторизація', 401);

                return;
            }
        }

        // Перевірка CSRF токену
        if ($actionConfig['requireCsrf']) {
            if (! $this->verifyCsrf()) {
                $this->sendError('Помилка безпеки (CSRF токен не валідний)', 403);

                return;
            }
        }

        // Валідація даних
        if ($actionConfig['validate'] && is_callable($actionConfig['validate'])) {
            $validationResult = call_user_func($actionConfig['validate'], $_POST, $_GET);
            if ($validationResult !== true) {
                $this->sendError(is_string($validationResult) ? $validationResult : 'Помилка валідації', 400);

                return;
            }
        }

        // Виконуємо обробник
        try {
            $result = call_user_func($actionConfig['handler'], $_POST, $_GET, $_FILES ?? []);

            // Якщо результат не масив, обгортаємо його
            if (! is_array($result)) {
                $result = ['success' => true, 'data' => $result];
            }

            // Додаємо success якщо відсутній
            if (! isset($result['success'])) {
                $result['success'] = true;
            }

            $this->sendSuccess($result);
        } catch (Exception $e) {
            logger()->logError("AjaxHandler error for action '{$action}': " . $e->getMessage(), ['exception' => $e, 'action' => $action]);
            $this->sendError('Помилка обробки запиту: ' . $e->getMessage(), 500);
        } catch (Error $e) {
            logger()->logCritical("AjaxHandler fatal error for action '{$action}': " . $e->getMessage(), ['exception' => $e, 'action' => $action]);
            $this->sendError('Критична помилка обробки запиту', 500);
        }
    }

    /**
     * Перевірка CSRF токену
     *
     * @return bool
     */
    private function verifyCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

        return SecurityHelper::verifyCsrfToken($token);
    }

    /**
     * Відправка успішної відповіді
     *
     * @param array $data Дані
     * @return void
     */
    /**
     * @param array<string, mixed> $data
     * @return void
     */
    private function sendSuccess(array $data): void
    {
        Response::jsonResponse($data, 200);
        exit;
    }

    /**
     * Відправка помилки
     *
     * @param string $error Повідомлення про помилку
     * @param int $code Код статусу
     * @return void
     */
    private function sendError(string $error, int $code = 400): void
    {
        $errorData = [
            'success' => false,
            'error' => $error,
            'code' => $code,
        ];

        // Якщо є обробник помилок, використовуємо його
        if ($this->errorHandler) {
            $errorData = call_user_func($this->errorHandler, $error, $code);
        }

        Response::jsonResponse($errorData, $code);
        exit;
    }

    /**
     * Швидка реєстрація декількох дій
     *
     * @param array $actions Масив дій ['action' => callable, ...]
     * @return self
     */
    public function registerMultiple(array $actions): self
    {
        foreach ($actions as $action => $handler) {
            if (is_array($handler)) {
                $this->register($action, $handler['handler'], $handler['options'] ?? []);
            } else {
                $this->register($action, $handler);
            }
        }

        return $this;
    }

    /**
     * Отримання санітизованих даних з запиту
     *
     * @param array $keys Ключі для отримання (якщо порожній, повертає всі)
     * @return array
     */
    public static function getSanitizedData(array $keys = []): array
    {
        $data = array_merge($_GET, $_POST);

        if (empty($keys)) {
            return array_map(function ($value) {
                return SecurityHelper::sanitizeInput($value);
            }, $data);
        }

        $result = [];
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $result[$key] = SecurityHelper::sanitizeInput($data[$key]);
            }
        }

        return $result;
    }

    /**
     * Отримання файлу з запиту
     *
     * @param string $key Ключ файлу
     * @return array|null
     */
    public static function getFile(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    /**
     * Статичний метод: Швидка перевірка AJAX
     *
     * @return bool
     */
    public static function check(): bool
    {
        return self::isAjax();
    }
}
