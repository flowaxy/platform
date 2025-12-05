<?php

/**
 * Клас для роботи з сесіями
 * Управління сесіями користувачів з додатковою безпекою
 *
 * @package Engine\Classes\Security
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class Session
{
    private static bool $started = false;
    private static array $config = [
        'name' => 'PHPSESSID',
        'lifetime' => 7200, // Будет переопределено из настроек
        'domain' => '',
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    /**
     * Ініціалізація сесії
     *
     * @param array<string, mixed> $config Конфігурація сесії
     * @return void
     */
    public static function start(array $config = []): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;

            return;
        }

        // Завантажуємо параметри з налаштувань, якщо доступні
        if (class_exists('SystemConfig')) {
            $systemConfig = SystemConfig::getInstance();
            $defaultConfig = [
                'name' => $systemConfig->getSessionName(),
                'lifetime' => $systemConfig->getSessionLifetime(),
            ];
            self::$config = array_merge(self::$config, $defaultConfig);
        }

        self::$config = array_merge(self::$config, $config);

        // Визначаємо secure на основі налаштувань з бази даних (якщо доступні)
        $isSecure = self::$config['secure'];

        // Перевіряємо налаштування протоколу з бази даних
        $protocolFromSettings = null;
        if (class_exists('SettingsManager') && file_exists(dirname(__DIR__, 3) . '/storage/config/database.ini')) {
            try {
                $settingsManager = settingsManager();
                $protocolSetting = $settingsManager->get('site_protocol', 'auto');
                if ($protocolSetting === 'https') {
                    $protocolFromSettings = 'https://';
                } elseif ($protocolSetting === 'http') {
                    $protocolFromSettings = 'http://';
                }
            } catch (Exception $e) {
                // Ігноруємо помилки при завантаженні налаштувань
            }
        }

        // Якщо в налаштуваннях явно вказано протокол, використовуємо його
        if ($protocolFromSettings === 'https://') {
            $isSecure = true;
        } elseif ($protocolFromSettings === 'http://') {
            $isSecure = false;
        } else {
            // Якщо налаштування 'auto' або недоступні, перевіряємо реальне з'єднання
            if ($isSecure) {
                // Додаткова перевірка реального протоколу
                $realHttps = (
                    (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                    (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
                    (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
                    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                );

                // Якщо реальне з'єднання HTTP, але secure=true, вимикаємо secure для сумісності
                if (! $realHttps) {
                    $isSecure = false;
                    if (function_exists('logWarning')) {
                        logWarning("Session::start: secure=true but connection is HTTP, disabling secure flag for compatibility");
                    } elseif (function_exists('logger')) {
                        logger()->logWarning("Session: secure=true але з'єднання HTTP, вимикаємо прапорець secure для сумісності");
                    }
                }
            }
        }

        // SameSite налаштування (важливо для Edge)
        $samesite = self::$config['samesite'] ?? 'Lax';
        // Якщо SameSite=None, але secure=false, Edge блокує - змінюємо на Lax
        if ($samesite === 'None' && ! $isSecure) {
            $samesite = 'Lax';
            if (function_exists('logWarning')) {
                logWarning('Session::start: SameSite=None requires Secure flag, changing to Lax for compatibility');
            } elseif (function_exists('logger')) {
                logger()->logWarning('Session: SameSite=None потребує прапорець Secure, змінюємо на Lax для сумісності');
            }
        }

        session_name(self::$config['name']);

        // Визначаємо domain правильно для Edge
        // Edge дуже суворий до domain - краще використовувати порожній domain для точного співпадіння
        $cookieDomain = self::$config['domain'];
        if (empty($cookieDomain)) {
            // Для Edge краще використовувати порожній domain - браузер сам визначить домен
            // Це гарантує, що cookie працюватиме для точного домену
            $cookieDomain = '';
        }

        // ВАЖЛИВО: session_set_cookie_params() має викликатися ДО session_start()
        // Це критично для Edge та інших браузерів
        session_set_cookie_params([
            'lifetime' => self::$config['lifetime'],
            'path' => self::$config['path'],
            'domain' => $cookieDomain, // Використовуємо правильно визначений domain
            'secure' => $isSecure,
            'httponly' => self::$config['httponly'],
            'samesite' => $samesite,
        ]);

        // Налаштування параметрів сесії через ini_set (для сумісності)
        ini_set('session.cookie_lifetime', self::$config['lifetime']);
        ini_set('session.cookie_path', self::$config['path']);
        ini_set('session.cookie_domain', $cookieDomain); // Використовуємо правильно визначений domain
        ini_set('session.cookie_secure', $isSecure ? '1' : '0');
        ini_set('session.cookie_httponly', self::$config['httponly'] ? '1' : '0');

        // Настройка SameSite (PHP 8.4 поддерживает напрямую)
        ini_set('session.cookie_samesite', $samesite);

        if (! headers_sent()) {
            // Зберігаємо старий session_id перед стартом, якщо сесія вже існує
            $oldSessionId = session_id();
            $sessionExists = !empty($oldSessionId);

            session_start();
            self::$started = true;

            // Логуємо, чи сесія була відновлена або створена нова
            if (function_exists('logDebug')) {
                logDebug('Session::start: Session started', [
                    'session_id' => session_id(),
                    'old_session_id' => $oldSessionId,
                    'session_restored' => $sessionExists && session_id() === $oldSessionId,
                    'new_session' => !$sessionExists,
                ]);
            }

            if (function_exists('logInfo')) {
                logInfo('Session::start: Session started successfully', [
                    'name' => self::$config['name'],
                    'lifetime' => self::$config['lifetime'],
                    'secure' => $isSecure,
                    'samesite' => $samesite,
                ]);
            }
        } else {
            if (function_exists('logWarning')) {
                logWarning('Session::start: Headers already sent, cannot start session');
            } elseif (class_exists('Logger')) {
                // Логируем только критичные ошибки
                Logger::getInstance()->logWarning('Session::start() - Headers already sent, cannot start session');
            }
        }
    }

    /**
     * Отримання значення з сесії
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        self::ensureStarted();

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Встановлення значення в сесію
     *
     * @param string $key Ключ
     * @param mixed $value Значення
     * @return void
     */
    public static function set(string $key, $value): void
    {
        self::ensureStarted();
        $_SESSION[$key] = $value;
    }

    /**
     * Перевірка наявності ключа в сесії
     *
     * @param string $key Ключ
     * @return bool
     */
    public static function has(string $key): bool
    {
        self::ensureStarted();

        return isset($_SESSION[$key]);
    }

    /**
     * Видалення значення з сесії
     *
     * @param string $key Ключ
     * @return void
     */
    public static function remove(string $key): void
    {
        self::ensureStarted();
        unset($_SESSION[$key]);
    }

    /**
     * Отримання всіх даних сесії
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        self::ensureStarted();

        return $_SESSION ?? [];
    }

    /**
     * Очищення всіх даних сесії
     *
     * @return void
     */
    public static function clear(): void
    {
        self::ensureStarted();
        $_SESSION = [];
    }

    /**
     * Видалення сесії
     *
     * @return void
     */
    public static function destroy(): void
    {
        if (! self::$started) {
            if (function_exists('logDebug')) {
                logDebug('Session::destroy: Session not started, nothing to destroy');
            }
            return;
        }

        if (function_exists('logInfo')) {
            logInfo('Session::destroy: Destroying session');
        }

        $_SESSION = [];

        // Використовуємо наш клас Cookie для видалення cookie
        if (isset($_COOKIE[session_name()])) {
            if (class_exists('Cookie')) {
                Cookie::set(session_name(), '', time() - 3600, self::$config['path'], self::$config['domain'], false, true);
            } else {
                setcookie(session_name(), '', time() - 3600, self::$config['path'], self::$config['domain']);
            }
        }

        session_destroy();
        self::$started = false;
    }

    /**
     * Регенерація ID сесії
     *
     * @param bool $deleteOldSession Видаляти стару сесію
     * @return bool
     */
    public static function regenerate(bool $deleteOldSession = true): bool
    {
        self::ensureStarted();

        // Зберігаємо CSRF токен перед регенерацією, щоб він не був втрачений
        $csrfToken = $_SESSION['csrf_token'] ?? null;

        $result = session_regenerate_id($deleteOldSession);

        // Відновлюємо CSRF токен після регенерації, якщо він був
        if ($result && $csrfToken !== null) {
            $_SESSION['csrf_token'] = $csrfToken;
            if (function_exists('logDebug')) {
                logDebug('Session::regenerate: CSRF token preserved after regeneration');
            }
        }

        if ($result && function_exists('logInfo')) {
            logInfo('Session::regenerate: Session ID regenerated', [
                'delete_old' => $deleteOldSession,
                'csrf_token_preserved' => $csrfToken !== null,
            ]);
        } elseif (!$result && function_exists('logError')) {
            logError('Session::regenerate: Failed to regenerate session ID');
        }

        return $result;
    }

    /**
     * Отримання ID сесії
     *
     * @return string
     */
    public static function getId(): string
    {
        self::ensureStarted();

        return session_id();
    }

    /**
     * Встановлення ID сесії
     *
     * @param string $id ID сесії
     * @return bool
     */
    public static function setId(string $id): bool
    {
        if (self::$started) {
            return false;
        }

        return session_id($id) !== '';
    }

    /**
     * Перевірка, чи запущена сесія
     *
     * @return bool
     */
    public static function isStarted(): bool
    {
        return self::$started && session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Отримання Flash повідомлення (читається один раз)
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public static function flash(string $key, $default = null)
    {
        $value = self::get('_flash.' . $key, $default);
        self::remove('_flash.' . $key);

        return $value;
    }

    /**
     * Встановлення Flash повідомлення
     *
     * @param string $key Ключ
     * @param mixed $value Значення
     * @return void
     */
    public static function setFlash(string $key, $value): void
    {
        self::set('_flash.' . $key, $value);
    }

    /**
     * Переконатися, що сесія запущена
     *
     * @return void
     */
    private static function ensureStarted(): void
    {
        if (! self::$started) {
            self::start();
        }
    }
}
