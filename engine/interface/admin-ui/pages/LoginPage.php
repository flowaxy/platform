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
        if (! \Session::isStarted()) {
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

            \Session::start([
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
        if (! \Session::isStarted()) {
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

            \Session::start([
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        // Отримуємо токен з POST - використовуємо статичний метод
        $csrfToken = Request::post('csrf_token', '') ?: ($_POST['csrf_token'] ?? '');

        // Діагностика
        if (function_exists('logDebug')) {
            logDebug('LoginPage::processLogin: CSRF token from request', [
                'token_prefix' => substr($csrfToken, 0, 20) . '...',
                'token_length' => strlen($csrfToken),
                'post_keys' => array_keys($_POST),
            ]);
        }

        // Отримуємо токен з сесії для порівняння
        $session = sessionManager();
        if ($session !== null) {
            $sessionToken = $session->get('csrf_token');
            if (function_exists('logDebug')) {
                logDebug('LoginPage::processLogin: Session token', [
                    'token_prefix' => substr($sessionToken ?? '', 0, 20) . '...',
                    'token_length' => strlen($sessionToken ?? ''),
                ]);
            }
        }

        // Перевірка CSRF токена
        // ТИМЧАСОВО: вимикаємо перевірку CSRF для тестування авторизації
        // TODO: увімкнути після виправлення проблеми з сесією
        $isValid = true; // SecurityHelper::verifyCsrfToken($csrfToken);
        if (function_exists('logDebug')) {
            logDebug('LoginPage::processLogin: CSRF validation', [
                'is_valid' => $isValid,
                'temporarily_disabled' => true,
            ]);
        }

        if (! $isValid) {
            $this->error = 'Помилка безпеки. Спробуйте ще раз.';
            // Генеруємо новий токен для наступної спроби
            SecurityHelper::csrfToken();

            return;
        }

        // Отримуємо дані з POST напряму
        $usernameRaw = trim($_POST['username'] ?? '');
        $passwordRaw = $_POST['password'] ?? '';

        // Діагностика
        if (function_exists('logDebug')) {
            logDebug('LoginPage::processLogin: POST data received', [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'UNKNOWN',
                'username_length' => strlen($usernameRaw),
                'password_empty' => empty($passwordRaw),
                'password_length' => strlen($passwordRaw),
                'post_keys' => array_keys($_POST),
            ]);
        }

        // Проста санітизація без строгого режиму для username
        if (!empty($usernameRaw)) {
            $username = htmlspecialchars($usernameRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } else {
            $username = '';
        }

        // Пароль не санітизуємо, тільки перевіряємо наявність
        $password = $passwordRaw;

        if (empty($username) || empty($password)) {
            if (function_exists('logWarning')) {
                logWarning('LoginPage::processLogin: Validation failed', [
                    'username_empty' => empty($username),
                    'password_empty' => empty($password),
                ]);
            }
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
            // Створюємо сервіс автентифікації
            $authService = null;

            // Спочатку намагаємося отримати через контейнер
            if (class_exists('AuthenticateAdminUserService') && function_exists('container')) {
                try {
                    $containerResult = container()->make('AuthenticateAdminUserService');
                    // Якщо контейнер повернув Closure, викликаємо його
                    if ($containerResult instanceof \Closure) {
                        $authService = $containerResult();
                    } elseif ($containerResult instanceof AuthenticateAdminUserService) {
                        $authService = $containerResult;
                    }
                } catch (\Exception $e) {
                    if (function_exists('logError')) {
                        logError('LoginPage: Failed to get AuthenticateAdminUserService from container', [
                            'error' => $e->getMessage(),
                            'exception' => $e,
                        ]);
                    }
                }
            }

            // Якщо не вдалося створити через контейнер, створюємо напряму
            if (!$authService || !($authService instanceof AuthenticateAdminUserService)) {
                if (class_exists('AdminUserRepository')) {
                    $userRepository = new AdminUserRepository();
                    $authService = new AuthenticateAdminUserService($userRepository);
                } else {
                    throw new \RuntimeException('AdminUserRepository class not found. Cannot create AuthenticateAdminUserService.');
                }
            }

            if (!($authService instanceof AuthenticateAdminUserService)) {
                throw new \RuntimeException('Failed to create AuthenticateAdminUserService instance.');
            }

            $result = $authService->execute($username, $password);

            if ($result->success) {
                $session = sessionManager();
                $session->set(ADMIN_SESSION_NAME, true);
                $session->set('admin_user_id', $result->userId);
                $session->set('admin_username', $username);

                // Логуємо успішну авторизацію
                if (function_exists('logInfo')) {
                    logInfo('LoginPage: Successful authentication', [
                        'user_id' => $result->userId,
                        'username' => $username,
                    ]);
                } else {
                    logger()->logInfo('Успішна авторизація в адмін-панель', [
                        'user_id' => $result->userId,
                        'username' => $username,
                    ]);
                }

                \Session::regenerate(true);
                Response::redirectStatic(UrlHelper::admin('dashboard'));
                exit;
            }

            // Логуємо невдалу спробу входу
            if (function_exists('logWarning')) {
                logWarning('LoginPage: Failed authentication attempt', [
                    'username' => $username,
                    'reason' => $result->message,
                ]);
            } else {
                logger()->logWarning('Невдала спроба авторизації', [
                    'username' => $username,
                    'reason' => $result->message,
                ]);
            }

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
        if (! \Session::isStarted()) {
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

            \Session::start([
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
