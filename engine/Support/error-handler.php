<?php

/**
 * Функція для відображення сторінки помилки 500 з детальною інформацією
 *
 * @param Throwable|null $exception Виняток, який викликав помилку
 * @param array|null $errorInfo Додаткова інформація про помилку (file, line, message)
 * @return void
 */
function showError500Page(?\Throwable $exception = null, ?array $errorInfo = null): void
{
    // Встановлюємо код відповіді
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    // Визначаємо, чи показувати детальну інформацію
    $showDebugInfo = false;
    $showStackTrace = false;
    $isAuthenticated = false;
    
    // Перевіряємо, чи користувач авторизований
    if (session_status() === PHP_SESSION_ACTIVE && function_exists('sessionManager')) {
        try {
            $session = sessionManager();
            if ($session && method_exists($session, 'has') && $session->has('admin_user_id')) {
                $userId = (int)$session->get('admin_user_id');
                if ($userId > 0) {
                    $isAuthenticated = true;
                }
            }
        } catch (Exception $e) {
            // Якщо не вдалося перевірити сесію, просто ігноруємо
        }
    }
    
    // Перевіряємо, чи це сторінка логіну
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $isLoginPage = str_ends_with($requestUri, '/admin/login') || str_ends_with($requestUri, '/admin/logout');
    
    // Перевіряємо DEBUG_MODE
    if (defined('DEBUG_MODE') && constant('DEBUG_MODE')) {
        $showDebugInfo = true;
        // STACK TRACE показуємо тільки для авторизованих користувачів
        if ($isAuthenticated) {
            $showStackTrace = true;
        }
    }
    
    // Перевіряємо, чи це локальне середовище
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = in_array($host, ['localhost', '127.0.0.1']) ||
               str_ends_with($host, '.local') ||
               str_contains($host, 'dev.flowaxy.com') ||
               (isset($_SERVER['SERVER_ADDR']) && in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1']));
    
    // Показуємо деталі в локальному середовищі
    if ($isLocal) {
        $showDebugInfo = true;
        // STACK TRACE показуємо тільки для авторизованих користувачів
        if ($isAuthenticated) {
            $showStackTrace = true;
        }
    }
    
    // Якщо користувач авторизований - показуємо повну інформацію включаючи STACK TRACE
    if ($isAuthenticated) {
        $showDebugInfo = true;
        $showStackTrace = true;
    }
    
    // Якщо це сторінка логіну і користувач не авторизований - не показуємо STACK TRACE
    if ($isLoginPage && !$isAuthenticated) {
        $showStackTrace = false;
    }

    // Функція для конвертації абсолютного шляху в відносний
    $getRelativePath = function(string $absolutePath): string {
        $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 3);
        $rootDir = rtrim(str_replace('\\', '/', $rootDir), '/');
        $absolutePath = str_replace('\\', '/', $absolutePath);
        
        if (str_starts_with($absolutePath, $rootDir)) {
            $relativePath = substr($absolutePath, strlen($rootDir));
            return '/' . ltrim($relativePath, '/');
        }
        
        return $absolutePath;
    };

    // Обробляємо шлях файлу
    $filePath = $exception ? $exception->getFile() : ($errorInfo['file'] ?? 'Невідомий файл');
    $relativeFilePath = $filePath !== 'Невідомий файл' ? $getRelativePath($filePath) : $filePath;

    // Обробляємо trace для нормалізації шляхів
    $trace = null;
    if ($showStackTrace && $exception) {
        $traceString = $exception->getTraceAsString();
        // Замінюємо абсолютні шляхи на відносні в trace
        $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 3);
        $rootDirNormalized = str_replace('\\', '/', rtrim($rootDir, '/\\'));
        
        // Замінюємо абсолютний шлях на відносний
        $traceString = str_replace('\\', '/', $traceString);
        $traceString = str_replace($rootDirNormalized, '', $traceString);
        // Виправляємо подвійні слеші
        $traceString = preg_replace('#(/+)#', '/', $traceString);
        $trace = $traceString;
    }

    // Збираємо інформацію про помилку
    $errorDetails = [
        'message' => $exception ? $exception->getMessage() : ($errorInfo['message'] ?? 'Невідома помилка'),
        'file' => $relativeFilePath,
        'line' => $exception ? $exception->getLine() : ($errorInfo['line'] ?? 0),
        'code' => $exception ? $exception->getCode() : ($errorInfo['code'] ?? 0),
        'trace' => $trace,
        'type' => $exception ? get_class($exception) : ($errorInfo['type'] ?? 'Error'),
        'showDebug' => $showDebugInfo,
    ];

    // Шлях до шаблону
    $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 3);
    $templateFile = $rootDir . DIRECTORY_SEPARATOR . 'engine' . DIRECTORY_SEPARATOR . 'interface' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'errors.php';
    
    if (file_exists($templateFile)) {
        // Екстрагуємо змінні для використання в шаблоні
        extract($errorDetails, EXTR_SKIP);
        $httpCode = 500;
        include $templateFile;
    } else {
        // Fallback якщо шаблон не знайдено
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>500 - Внутрішня помилка сервера</title></head><body>';
        echo '<h1>Внутрішня помилка сервера</h1>';
        if ($showDebugInfo && $errorDetails['message']) {
            echo '<pre>' . htmlspecialchars($errorDetails['message'], ENT_QUOTES, 'UTF-8') . '</pre>';
        }
        echo '</body></html>';
    }
    
    exit;
}

/**
 * Перевірка стану системи (БД, таблиці) перед обробкою запитів
 * Якщо система не готова - показує 500 помилку
 * 
 * @return void
 */
function checkSystemState(): void
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
    
    // Не перевіряємо стан системи для встановлювача
    if (str_starts_with($path, '/install')) {
        return;
    }
    
    $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 3);
    $installedFlagFile = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.flag';
    
    // Якщо файл-маркер installed.flag не існує - система не встановлена, показуємо сторінку з кнопкою установки
    $isInstalled = file_exists($installedFlagFile);
    
    if (! $isInstalled) {
        if (php_sapi_name() !== 'cli') {
            if (function_exists('showInstallationRequired')) {
                showInstallationRequired();
                // Функція викликає exit, але на всяк випадок додаємо exit тут
                exit;
            } else {
                // Fallback - показуємо просту сторінку замість переадресації
                if (!headers_sent()) {
                    http_response_code(503);
                    header('Content-Type: text/html; charset=UTF-8');
                }
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Потрібна установка</title></head><body>';
                echo '<h1>Потрібна установка системи</h1>';
                echo '<p>База даних не налаштована. Для початку роботи необхідно встановити Flowaxy CMS.</p>';
                echo '<p><a href="/install">Встановити систему</a></p>';
                echo '</body></html>';
                exit;
            }
        }
        
        return;
    }

    // Перевіряємо доступність БД
    try {
        // Перевіряємо чи визначені константи БД
        if (! defined('DB_HOST') || empty(DB_HOST) || ! defined('DB_NAME') || empty(DB_NAME)) {
                $errorInfo = [
                    'message' => 'Конфігурація БД не визначена.',
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'code' => 0,
                    'type' => 'DatabaseError',
                    'showDebug' => true,
                ];
            showError500Page(null, $errorInfo);
            exit;
        }

        // Перевіряємо чи доступна БД
        if (! class_exists('DatabaseHelper')) {
            $helperFile = __DIR__ . '/Helpers/DatabaseHelper.php';
            if (file_exists($helperFile)) {
                require_once $helperFile;
            }
        }
        
        if (class_exists('DatabaseHelper')) {
            if (! DatabaseHelper::isAvailable()) {
                $errorInfo = [
                    'message' => 'База даних недоступна.',
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'code' => 0,
                    'type' => 'DatabaseError',
                    'showDebug' => true,
                ];
                showError500Page(null, $errorInfo);
                exit;
            }

            // Перевіряємо чи існують обов'язкові таблиці (users - основна таблиця)
            try {
                if (! DatabaseHelper::tableExists('users')) {
                    $errorInfo = [
                        'message' => 'База даних порожня. Потрібна установка системи.',
                        'file' => __FILE__,
                        'line' => __LINE__,
                        'code' => 0,
                        'type' => 'DatabaseError',
                        'showDebug' => true,
                    ];
                    showError500Page(null, $errorInfo);
                    exit;
                }
            } catch (Exception $tableException) {
                // Якщо перевірка таблиці викликала помилку - це означає проблему з БД
                $errorInfo = [
                    'message' => 'Помилка перевірки структури БД: ' . $tableException->getMessage(),
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'code' => $tableException->getCode(),
                    'type' => 'DatabaseError',
                    'showDebug' => true,
                ];
                showError500Page(null, $errorInfo);
                exit;
            }
        }
    } catch (Exception $e) {
        // Якщо виникла помилка при перевірці БД - показуємо 500 помилку
                $errorInfo = [
                    'message' => 'Критична помилка БД: ' . $e->getMessage(),
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'code' => $e->getCode(),
                    'type' => 'DatabaseError',
                    'showDebug' => true,
                ];
        showError500Page(null, $errorInfo);
        exit;
    }
}

/**
 * Відображення сторінки помилки за HTTP кодом
 * 
 * @param int $httpCode HTTP код помилки (400, 404, 500, тощо)
 * @param string|null $customTitle Користувацький заголовок
 * @param string|null $customMessage Користувацьке повідомлення
 * @param array|null $debugInfo Debug інформація (для 500+)
 * @return void
 */
function showHttpError(int $httpCode, ?string $customTitle = null, ?string $customMessage = null, ?array $debugInfo = null): void
{
    // Встановлюємо код відповіді
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: text/html; charset=UTF-8');
    }

    // Шлях до шаблону
    $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 3);
    $templateFile = $rootDir . DIRECTORY_SEPARATOR . 'engine' . DIRECTORY_SEPARATOR . 'interface' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'errors.php';
    
    if (file_exists($templateFile)) {
        // Передаємо змінні в шаблон
        $customTitle = $customTitle;
        $customMessage = $customMessage;
        if ($debugInfo) {
            extract($debugInfo, EXTR_SKIP);
        }
        include $templateFile;
    } else {
        // Fallback
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $httpCode . ' - Помилка</title></head><body>';
        echo '<h1>' . $httpCode . ' - Помилка</h1>';
        if ($customMessage) {
            echo '<p>' . htmlspecialchars($customMessage, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        echo '</body></html>';
    }
    
    exit;
}

/**
 * Показує сторінку з повідомленням про необхідність установки системи
 * Замість автоматичної переадресації на /install показує сторінку з кнопкою
 * 
 * @return void
 */
function showInstallationRequired(): void
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
    
    // Дозволяємо доступ до встановлювача
    if (str_starts_with($path, '/install')) {
        return;
    }
    
    // Зупиняємо всі буфери виводу
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Встановлюємо код відповіді
    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    
    // Шлях до шаблону
    $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 3);
    $templateFile = $rootDir . DIRECTORY_SEPARATOR . 'engine' . DIRECTORY_SEPARATOR . 'interface' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'exceptions' . DIRECTORY_SEPARATOR . 'installation-required.php';
    
    if (file_exists($templateFile) && is_readable($templateFile)) {
        include $templateFile;
    } else {
        // Fallback якщо шаблон недоступний
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Потрібна установка</title></head><body>';
        echo '<h1>Потрібна установка системи</h1>';
        echo '<p>База даних не налаштована. Для початку роботи необхідно встановити Flowaxy CMS.</p>';
        echo '<p><a href="/install">Встановити систему</a></p>';
        echo '</body></html>';
    }
    
    // Обов'язковий вихід
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    exit(0);
}
