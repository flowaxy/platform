<?php

/**
 * Хелпер для роботи з безпекою
 * Обгортка над Security класом
 *
 * @package Engine\Classes\Helpers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class SecurityHelper
{
    /**
     * Генерація CSRF токена
     *
     * @return string
     */
    public static function csrfToken(): string
    {
        if (! class_exists('Security')) {
            return '';
        }
        // Переконуємося, що Session ініціалізовано
        if (session_status() !== PHP_SESSION_ACTIVE && class_exists('Session')) {
            Session::start();
        }

        return Security::csrfToken();
    }

    /**
     * Перевірка CSRF токена
     *
     * @param string|null $token Токен для перевірки
     * @return bool
     */
    public static function verifyCsrfToken(?string $token = null): bool
    {
        if (! class_exists('Security')) {
            return false;
        }
        // Переконуємося, що Session ініціалізовано
        if (session_status() !== PHP_SESSION_ACTIVE && class_exists('Session')) {
            Session::start();
        }

        return Security::verifyCsrfToken($token);
    }

    /**
     * Генерація CSRF поля для форми
     *
     * @return string
     */
    public static function csrfField(): string
    {
        if (! class_exists('Security')) {
            return '';
        }
        // Переконуємося, що Session ініціалізовано
        if (session_status() !== PHP_SESSION_ACTIVE && class_exists('Session')) {
            Session::start();
        }

        return Security::csrfField();
    }

    /**
     * Перевірка, чи залогінений адмін
     * Перевірка тільки через базу даних (без перевірки сесії)
     *
     * @return bool
     */
    public static function isAdminLoggedIn(): bool
    {
        // Перевіряємо авторизацію тільки через базу даних
        // Сесія потрібна тільки для отримання ID користувача
        if (! class_exists('Session') || ! Session::isStarted()) {
            return false;
        }

        $session = sessionManager();
        $userId = (int)$session->get('admin_user_id');

        if ($userId <= 0) {
            return false;
        }

        // Перевіряємо авторизацію через доменний репозиторій
        try {
            $repository = class_exists('AdminUserRepositoryInterface')
                ? container()->make(AdminUserRepositoryInterface::class)
                : new AdminUserRepository();
            if ($repository) {
                // Отримуємо час життя сесії з налаштувань
                $sessionLifetime = 7200; // За замовчуванням 2 години
                if (class_exists('SystemConfig')) {
                    $systemConfig = SystemConfig::getInstance();
                    $sessionLifetime = $systemConfig->getSessionLifetime();
                }

                $user = $repository->findById($userId);

                if (! $user) {
                    // Користувача не знайдено
                    self::logout();

                    return false;
                }

                // Перевіряємо, чи активний користувач
                if (! $user->isActive) {
                    // Користувач неактивний
                    self::logout();

                    return false;
                }

                // Перевіряємо наявність токена сесії
                if (empty($user->sessionToken)) {
                    // Токен відсутній - користувач не авторизований
                    self::logout();

                    return false;
                }

                // Перевіряємо валідність сесії за часом останньої активності
                if (! empty($user->lastActivity)) {
                    $lastActivity = strtotime($user->lastActivity);
                    $currentTime = time();
                    $timeDiff = $currentTime - $lastActivity;

                    // Якщо пройшло більше часу життя сесії - сесія закінчилася
                    if ($timeDiff > $sessionLifetime) {
                        // Сесія закінчилася - позначаємо користувача як неактивного
                        $repository->clearSession($userId);
                        self::logout();

                        return false;
                    }

                    $repository->updateLastActivity($userId, date('Y-m-d H:i:s'));
                } elseif (! empty($user->sessionToken)) {
                    // Якщо last_activity відсутня, але є токен - встановлюємо час активності
                    $now = date('Y-m-d H:i:s');
                    $repository->updateLastActivity($userId, $now);
                }

                return true;
            }
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('Помилка перевірки авторизації адміна', ['error' => $e->getMessage()]);
            }

            // У разі помилки БД дозволяємо доступ (щоб не блокувати користувача)
            return true;
        }

        return false;
    }

    /**
     * Вихід з системи (очищення сесії та позначка користувача як неактивного)
     *
     * @return void
     */
    public static function logout(): void
    {
        $session = sessionManager();
        $userId = (int)$session->get('admin_user_id');

        // Позначаємо користувача як неактивного та очищаємо токен у базі даних
        if ($userId > 0) {
            try {
                $logoutService = null;
                if (class_exists('LogoutAdminUserService')) {
                    $logoutService = function_exists('container')
                        ? container()->make(LogoutAdminUserService::class)
                        : new LogoutAdminUserService(new AdminUserRepository());
                }

                if ($logoutService) {
                    $logoutService->execute($userId);
                } else {
                    $repository = class_exists('AdminUserRepositoryInterface')
                        ? container()->make(AdminUserRepositoryInterface::class)
                        : new AdminUserRepository();
                    $repository?->clearSession($userId);
                }
            } catch (Exception $e) {
                if (class_exists('Logger')) {
                    Logger::getInstance()->logError('Помилка очищення сесії', ['error' => $e->getMessage()]);
                }
            }
        }

        // Очищаємо сесію
        $session->remove(ADMIN_SESSION_NAME);
        $session->remove('admin_user_id');
        $session->remove('admin_username');
    }

    /**
     * Вимагає авторизації адміна
     *
     * @return void
     */
    public static function requireAdmin(): void
    {
        if (! self::isAdminLoggedIn()) {
            // Для AJAX запитів повертаємо JSON помилку
            if (class_exists('AjaxHandler') && AjaxHandler::isAjax()) {
                // Очищаємо буфер виводу
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                if (class_exists('Response')) {
                    Response::jsonResponse([
                        'success' => false,
                        'error' => 'Потрібна авторизація',
                        'auth_required' => true,
                    ], 401);
                } else {
                    http_response_code(401);
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode([
                        'success' => false,
                        'error' => 'Потрібна авторизація',
                        'auth_required' => true,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
            }

            // Для звичайних запитів робимо редірект
            if (class_exists('Response')) {
                $adminLoginUrl = class_exists('UrlHelper') ? UrlHelper::admin('login') : (defined('ADMIN_URL') ? ADMIN_URL . '/login' : '/admin/login');
                Response::redirectStatic($adminLoginUrl);
            } else {
                $adminLoginUrl = class_exists('UrlHelper') ? UrlHelper::admin('login') : (defined('ADMIN_URL') ? ADMIN_URL . '/login' : '/admin/login');
                header('Location: ' . $adminLoginUrl);
                exit;
            }
        }
    }

    /**
     * Безпечний вивід HTML
     *
     * @param mixed $value Значення для виводу
     * @param string $default Значення за замовчуванням
     * @return string
     */
    public static function safeHtml($value, string $default = ''): string
    {
        if (is_array($value) || is_object($value)) {
            return Security::clean(Json::stringify($value));
        }

        return Security::clean((string)($value ?: $default));
    }

    /**
     * Санітизація вхідних даних
     *
     * @param mixed $input Вхідні дані
     * @return string
     */
    public static function sanitizeInput($input): string
    {
        if (is_string($input)) {
            return Security::clean(trim($input), true);
        }

        if (is_numeric($input)) {
            return (string)$input;
        }

        if (is_array($input)) {
            try {
                return Json::stringify($input);
            } catch (Exception $e) {
                if (class_exists('Logger')) {
                    Logger::getInstance()->logError('Помилка кодування JSON', ['error' => $e->getMessage()]);
                }

                return '';
            }
        }

        if (is_bool($input)) {
            return $input ? '1' : '0';
        }

        return '';
    }
}
