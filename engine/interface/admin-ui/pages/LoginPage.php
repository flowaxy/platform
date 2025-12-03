<?php

/**
 * Сторінка входу в адмінку
 */

declare(strict_types=1);

class LoginPage
{
    private ?PDO $db = null;
    private string $error = '';

    public function __construct()
    {
        // Переконуємося, що сесія ініціалізована для CSRF токена (використовуємо наші класи)
        // Session::start() тепер автоматично перевіряє налаштування протоколу з бази даних
        if (! class_exists('Session')) {
            throw new \RuntimeException('Session class not found. Make sure autoloader is initialized.');
        }
        if (! Session::isStarted()) {
            // Отримуємо налаштування протоколу з бази даних для правильної ініціалізації сесії
            $isSecure = false;
            if (class_exists('SettingsManager') && file_exists(dirname(__DIR__, 4) . '/storage/config/database.ini')) {
                try {
                    $settingsManager = settingsManager();
                    $protocolSetting = $settingsManager->get('site_protocol', 'auto');
                    if ($protocolSetting === 'https') {
                        $isSecure = true;
                    } elseif ($protocolSetting === 'http') {
                        $isSecure = false;
                    }
                } catch (Exception $e) {
                    // Ігноруємо помилки
                }
            }

            Session::start([
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $this->db = DatabaseHelper::getConnection();
    }

    public function handle()
    {
        // Якщо вже авторизований (не через POST), перенаправляємо
        // Але тільки якщо це не POST запит (щоб обробити форму входу)
        if ((Request::getMethod() !== 'POST' && empty($_POST)) && SecurityHelper::isAdminLoggedIn()) {
            Response::redirectStatic(UrlHelper::admin('dashboard'));

            return;
        }

        // Обробка форми входу (використовуємо Request безпосередньо з engine/classes)
        if (Request::getMethod() === 'POST' || ! empty($_POST)) {
            $this->processLogin();
        }

        // Завжди рендеримо сторінку (з помилкою, якщо вона є)
        $this->render();
    }

    /**
     * Обробка входу
     */
    private function processLogin()
    {
        // Переконуємося, що сесія ініціалізована
        // НЕ переініціалізуємо сесію, якщо вона вже запущена - це може скинути cookies
        if (! Session::isStarted()) {
            // Отримуємо налаштування протоколу з бази даних для правильної ініціалізації сесії
            $isSecure = false;
            if (class_exists('SettingsManager') && file_exists(dirname(__DIR__, 4) . '/storage/config/database.ini')) {
                try {
                    $settingsManager = settingsManager();
                    $protocolSetting = $settingsManager->get('site_protocol', 'auto');
                    if ($protocolSetting === 'https') {
                        $isSecure = true;
                    } elseif ($protocolSetting === 'http') {
                        $isSecure = false;
                    }
                } catch (Exception $e) {
                    // Ігноруємо помилки
                }
            }

            Session::start([
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $request = Request::getInstance();
        $csrfToken = $request->post('csrf_token', '');

        // Перевірка CSRF токена
        if (! SecurityHelper::verifyCsrfToken($csrfToken)) {
            $this->error = 'Помилка безпеки. Спробуйте ще раз.';
            SecurityHelper::csrfToken(); // Генеруємо новий токен для наступної спроби

            return;
        }

        $username = SecurityHelper::sanitizeInput($request->post('username', ''));
        $password = $request->post('password', '');

        if (empty($username) || empty($password)) {
            $this->error = 'Заповніть всі поля';

            return;
        }

        // Перевіряємо з'єднання з БД
        if ($this->db === null) {
            $this->db = DatabaseHelper::getConnection();
            if ($this->db === null) {
                $this->error = 'Помилка підключення до бази даних. Спробуйте пізніше.';

                return;
            }
        }

        try {
            $authService = class_exists('AuthenticateAdminUserService')
                ? container()->make(AuthenticateAdminUserService::class)
                : new AuthenticateAdminUserService(new AdminUserRepository());

            $result = $authService->execute($username, $password);

            if ($result->success) {
                $session = sessionManager();
                $session->set(ADMIN_SESSION_NAME, true);
                $session->set('admin_user_id', $result->userId);
                $session->set('admin_username', $username);

                // Логуємо успішну авторизацію
                logger()->logInfo('Успішна авторизація в адмін-панель', [
                    'user_id' => $result->userId,
                    'username' => $username,
                ]);

                Session::regenerate(true);
                Response::redirectStatic(UrlHelper::admin('dashboard'));
                exit;
            }

            // Логуємо невдалу спробу входу
            logger()->logWarning('Невдала спроба авторизації', [
                'username' => $username,
                'reason' => $result->message,
            ]);

            $this->error = $result->message;
        } catch (Exception $e) {
            $this->error = 'Помилка входу. Спробуйте пізніше.';
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('Login error', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Рендеринг сторінки
     */
    private function render()
    {
        // Убеждаемся, что сессия инициализирована перед генерацией токена
        if (! Session::isStarted()) {
            // Отримуємо налаштування протоколу з бази даних
            $isSecure = false;
            if (class_exists('SettingsManager') && file_exists(dirname(__DIR__, 4) . '/storage/config/database.ini')) {
                try {
                    $settingsManager = settingsManager();
                    $protocolSetting = $settingsManager->get('site_protocol', 'auto');
                    if ($protocolSetting === 'https') {
                        $isSecure = true;
                    } elseif ($protocolSetting === 'http') {
                        $isSecure = false;
                    }
                } catch (Exception $e) {
                    // Игнорируем ошибки
                }
            }

            Session::start([
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        // Определяем, используется ли HTTPS для отображения правильного сообщения
        $isHttps = false;
        if (class_exists('UrlHelper')) {
            $isHttps = UrlHelper::isHttps();
        } elseif (function_exists('detectProtocol')) {
            $protocol = detectProtocol();
            $isHttps = ($protocol === 'https://');
        } else {
            $isHttps = (
                (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
                (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            );
        }

        $error = $this->error;
        $csrfToken = SecurityHelper::csrfToken();
        $isSecureConnection = $isHttps;

        include dirname(__DIR__) . '/templates/login.php';
    }
}
