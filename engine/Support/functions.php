<?php

/**
 * Допоміжні функції системи
 *
 * @package Engine\Core\Support
 */

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/ContainerInterface.php';
require_once __DIR__ . '/../Contracts/ComponentRegistryInterface.php';
require_once __DIR__ . '/../core/system/ModuleManager.php';
require_once __DIR__ . '/../Contracts/HookManagerInterface.php';
require_once __DIR__ . '/../Hooks/HookType.php';
require_once __DIR__ . '/../Contracts/FeatureFlagManagerInterface.php';

use Flowaxy\Core\System\Hooks\HookType;
use Flowaxy\Core\Contracts\HookManagerInterface;
use Flowaxy\Core\Contracts\ContainerInterface;

// Підключаємо функції для роботи з ролями
if (file_exists(__DIR__ . '/Security/role-functions.php')) {
    require_once __DIR__ . '/Security/role-functions.php';
}

// Підключаємо класи для роботи з часовими поясами
if (file_exists(__DIR__ . '/Timezone.php')) {
    require_once __DIR__ . '/Timezone.php';
}
if (file_exists(__DIR__ . '/Managers/TimezoneManager.php')) {
    require_once __DIR__ . '/Managers/TimezoneManager.php';
}

if (! function_exists('container')) {
    /**
     * Повертає глобальний контейнер залежностей через фасад App.
     */
    function container(): ContainerInterface
    {
        if (class_exists('App')) {
            try {
                $container = App::container();
                // Переконуємося, що повертається ContainerInterface
                if ($container instanceof ContainerInterface) {
                    return $container;
                }
            } catch (RuntimeException $e) {
                // Fallback для сумісності
            }
        }

        if (isset($GLOBALS['engineContainer']) && $GLOBALS['engineContainer'] instanceof ContainerInterface) {
            return $GLOBALS['engineContainer'];
        }

        throw new RuntimeException('Container is not initialized');
    }
}

if (! function_exists('componentRegistry')) {
    /**
     * Отримання реєстру компонентів через фасад App.
     */
    function componentRegistry(): ComponentRegistryInterface
    {
        if (class_exists('App')) {
            try {
                return App::make(ComponentRegistryInterface::class);
            } catch (RuntimeException $e) {
                // Fallback для сумісності
            }
        }

        return container()->make(ComponentRegistryInterface::class);
    }
}

if (! function_exists('moduleManager')) {
    /**
     * Отримання менеджера модулів через фасад App.
     */
    function moduleManager(): ModuleManager
    {
        if (class_exists('App')) {
            try {
                return App::make(ModuleManager::class);
            } catch (RuntimeException $e) {
                // Fallback для сумісності
            }
        }

        return container()->make(ModuleManager::class);
    }
}

if (! function_exists('hooks')) {
    /**
     * Отримання менеджера хуків через фасад Hooks.
     */
    function hooks(): HookManagerInterface
    {
        // Завантажуємо інтерфейс
        if (!interface_exists('HookManagerInterface')) {
            require_once __DIR__ . '/../Contracts/HookManagerInterface.php';
        }

        // Hooks фасад має власні методи, тому використовуємо контейнер через App
        if (class_exists('App')) {
            try {
                $hookManager = App::make(HookManagerInterface::class);
                if ($hookManager instanceof \Flowaxy\Core\Contracts\HookManagerInterface) {
                    return $hookManager;
                }
            } catch (RuntimeException $e) {
                // Fallback для сумісності
            }
        }

        try {
            $hookManager = container()->make(HookManagerInterface::class);
            if ($hookManager instanceof \Flowaxy\Core\Contracts\HookManagerInterface) {
                return $hookManager;
            }
        } catch (RuntimeException $e) {
            // Fallback: створюємо екземпляр напряму
        }

        // Останній fallback: спробуємо завантажити через autoloader
        if (!class_exists(\Flowaxy\Core\System\HookManager::class)) {
            // Спробуємо викликати autoloader вручну
            if (function_exists('spl_autoload_call')) {
                spl_autoload_call(\Flowaxy\Core\System\HookManager::class);
            }
        }

        // Якщо все ще не завантажено, спробуємо напряму
        if (!class_exists(\Flowaxy\Core\System\HookManager::class)) {
            $hookManagerFile = __DIR__ . '/../core/system/HookManager.php';
            if (file_exists($hookManagerFile)) {
                // Зберігаємо поточний рівень помилок
                $prevErrorReporting = error_reporting(E_ALL);
                $prevDisplayErrors = ini_get('display_errors');
                ini_set('display_errors', '0');

                try {
                    // Спочатку перевіряємо синтаксис файлу
                    $syntaxCheck = @shell_exec('php -l "' . $hookManagerFile . '" 2>&1');
                    if ($syntaxCheck && !str_contains($syntaxCheck, 'No syntax errors')) {
                        if (function_exists('logError')) {
                            logError('HookManager syntax error', ['error' => $syntaxCheck, 'file' => $hookManagerFile]);
                        } else {
                            error_log('HookManager syntax error: ' . $syntaxCheck);
                        }
                        throw new RuntimeException('HookManager file has syntax errors: ' . $syntaxCheck);
                    }

                    // Встановлюємо обробник помилок для перехоплення фатальних помилок
                    $lastError = null;
                    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$lastError) {
                        $lastError = [
                            'errno' => $errno,
                            'errstr' => $errstr,
                            'errfile' => $errfile,
                            'errline' => $errline
                        ];
                        return false; // Продовжуємо обробку помилок
                    }, E_ALL);

                    ob_start();
                    try {
                        $result = require_once $hookManagerFile;
                    } catch (Throwable $e) {
                        ob_end_clean();
                        restore_error_handler();
                        error_reporting($prevErrorReporting);
                        ini_set('display_errors', $prevDisplayErrors);
                        if (function_exists('logError')) {
                            logError('HookManager: Exception during require_once', [
                                'error' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                                'exception' => $e,
                            ]);
                        } else {
                            error_log('HookManager: Exception during require_once: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                        }
                        throw new RuntimeException('Failed to load HookManager: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 0, $e);
                    }

                    $output = ob_get_clean();
                    restore_error_handler();

                    if ($lastError) {
                        if (function_exists('logError')) {
                            logError('HookManager: Error during require_once', [
                                'error' => $lastError['errstr'],
                                'file' => $lastError['errfile'],
                                'line' => $lastError['errline'],
                            ]);
                        } else {
                            error_log('HookManager: Error during require_once: ' . $lastError['errstr'] . ' in ' . $lastError['errfile'] . ':' . $lastError['errline']);
                        }
                    }

                    if (!empty($output)) {
                        if (function_exists('logWarning')) {
                            logWarning('HookManager require output', ['output' => $output]);
                        } else {
                            error_log('HookManager require output: ' . $output);
                        }
                    }

                    // Перевіряємо, чи файл дійсно завантажився
                    if ($result === false) {
                        if (function_exists('logWarning')) {
                            logWarning('HookManager: require_once returned false', ['file' => $hookManagerFile]);
                        } else {
                            error_log('HookManager: require_once returned false');
                        }
                    }

                    // Перевіряємо, чи клас тепер існує
                    if (class_exists(\Flowaxy\Core\System\HookManager::class)) {
                        if (function_exists('logDebug')) {
                            logDebug('HookManager: Class exists after require_once');
                        }
                    } else {
                        if (function_exists('logError')) {
                            logError('HookManager: Class still not exists after require_once', ['file' => $hookManagerFile]);
                        } else {
                            error_log('HookManager: Class still not exists after require_once');
                        }
                        // Перевіряємо всі завантажені класи
                        $allClasses = get_declared_classes();
                        $hookManagerClasses = array_filter($allClasses, fn($class) => str_contains($class, 'HookManager'));
                        if (!empty($hookManagerClasses)) {
                            if (function_exists('logDebug')) {
                                logDebug('HookManager: Found classes', ['classes' => $hookManagerClasses]);
                            }
                        }
                        // Перевіряємо, чи є помилки в use statements
                        $fileContent = file_get_contents($hookManagerFile);
                        if ($fileContent) {
                            preg_match_all('/use\s+([^;]+);/', $fileContent, $matches);
                            if (!empty($matches[1])) {
                                foreach ($matches[1] as $useStatement) {
                                    $className = trim($useStatement);
                                    if (!class_exists($className) && !interface_exists($className) && !enum_exists($className)) {
                                        if (function_exists('logWarning')) {
                                            logWarning('HookManager: Use statement class not found', ['class' => $className]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                } catch (Throwable $e) {
                    ob_end_clean();
                    error_reporting($prevErrorReporting);
                    ini_set('display_errors', $prevDisplayErrors);
                    throw new RuntimeException('Failed to load HookManager: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 0, $e);
                }

                error_reporting($prevErrorReporting);
                ini_set('display_errors', $prevDisplayErrors);
            } else {
                throw new RuntimeException('HookManager file not found: ' . $hookManagerFile);
            }
        }

        if (!class_exists(\Flowaxy\Core\System\HookManager::class)) {
            // Перевіряємо, чи є синтаксичні помилки
            $hookManagerFile = __DIR__ . '/../core/system/HookManager.php';
            $syntaxCheck = @shell_exec('php -l "' . $hookManagerFile . '" 2>&1');
            $errorMsg = 'HookManager class not found after require. File: ' . $hookManagerFile;

            // Перевіряємо, чи клас визначений в глобальному просторі імен
            $allClasses = get_declared_classes();
            $hookManagerClasses = array_filter($allClasses, fn($class) => str_contains($class, 'HookManager'));

            if (!empty($hookManagerClasses)) {
                $errorMsg .= '. Found HookManager classes: ' . implode(', ', $hookManagerClasses);
            }

            if ($syntaxCheck && !str_contains($syntaxCheck, 'No syntax errors')) {
                $errorMsg .= '. Syntax check: ' . $syntaxCheck;
            }

            // Перевіряємо, чи файл містить клас
            $fileContent = @file_get_contents($hookManagerFile);
            if ($fileContent) {
                if (!str_contains($fileContent, 'class HookManager') && !str_contains($fileContent, 'final class HookManager')) {
                    $errorMsg .= '. Class definition not found in file.';
                }
                if (!str_contains($fileContent, 'namespace Flowaxy\\Core\\System')) {
                    $errorMsg .= '. Namespace not found in file.';
                }
            }

            throw new RuntimeException($errorMsg);
        }

        try {
            $hookManager = new \Flowaxy\Core\System\HookManager();
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to instantiate HookManager: ' . $e->getMessage(), 0, $e);
        }

        if (!$hookManager instanceof \Flowaxy\Core\Contracts\HookManagerInterface) {
            throw new RuntimeException('HookManager не реалізує HookManagerInterface');
        }
        return $hookManager;
    }
}

if (! function_exists('hook_dispatch')) {
    /**
     * Виконання хука через фасад Hooks.
     */
    function hook_dispatch(string $hookName, mixed ...$args): void
    {
        if (class_exists('Hooks')) {
            try {
                Hooks::dispatch($hookName, ...$args);
                return;
            } catch (RuntimeException $e) {
                // Fallback для сумісності
            }
        }

        $pluginManager = pluginManager();
        if ($pluginManager) {
            $pluginManager->prepareHook($hookName, HookType::Action, $args);
        }
        hooks()->dispatch($hookName, ...$args);
    }
}

if (! function_exists('hook_apply')) {
    /**
     * Застосування фільтра через фасад Hooks.
     *
     * @param string $hookName
     * @param mixed $value
     * @param array<string, mixed> $context
     * @return mixed
     */
    function hook_apply(string $hookName, mixed $value = null, array $context = []): mixed
    {
        if (class_exists('Hooks')) {
            try {
                return Hooks::apply($hookName, $value, $context);
            } catch (RuntimeException $e) {
                // Fallback для сумісності
            }
        }

        $pluginManager = pluginManager();
        if ($pluginManager) {
            $value = $pluginManager->prepareHook($hookName, HookType::Filter, $value);
        }

        return hooks()->apply($hookName, $value, $context);
    }
}

// ============================================================================
// Configuration
// ============================================================================

if (! function_exists('config')) {
    /**
     * Отримання конфігурації через фасад App.
     *
     * @param string $key Ключ конфігурації
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    function config(string $key, mixed $default = null): mixed
    {
        if (class_exists('App')) {
            try {
                return App::config($key, $default);
            } catch (RuntimeException $e) {
                return $default;
            }
        }

        return $default;
    }
}

// ============================================================================
// Feature Flags
// ============================================================================

if (! function_exists('feature')) {
    /**
     * Перевірка, чи увімкнено feature flag через фасад FeatureFlag.
     *
     * @param string $flagName Назва прапорця
     * @param array<string, mixed> $context Контекст для перевірки
     * @return bool
     */
    function feature(string $flagName, array $context = []): bool
    {
        if (class_exists('FeatureFlag')) {
            try {
                return FeatureFlag::enabled($flagName, $context);
            } catch (RuntimeException | Exception $e) {
                return false;
            }
        }

        return false;
    }
}

if (! function_exists('featureFlag')) {
    /**
     * Отримання менеджера feature flags через фасад FeatureFlag.
     *
     * @return FeatureFlagManagerInterface|null
     */
    function featureFlag(): ?FeatureFlagManagerInterface
    {
        if (class_exists('App')) {
            try {
                return App::make(FeatureFlagManagerInterface::class);
            } catch (RuntimeException | Exception $e) {
                return null;
            }
        }

        return null;
    }
}

function loadDatabaseConfig(bool $reload = false): void
{
    $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
    $databaseIniFile = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.ini';

    if (! file_exists($databaseIniFile) || ! is_readable($databaseIniFile)) {
        if (! defined('DB_HOST')) {
            define('DB_HOST', '');
        }
        if (! defined('DB_NAME')) {
            define('DB_NAME', '');
        }
        if (! defined('DB_USER')) {
            define('DB_USER', '');
        }
        if (! defined('DB_PASS')) {
            define('DB_PASS', '');
        }
        if (! defined('DB_CHARSET')) {
            define('DB_CHARSET', 'utf8mb4');
        }

        return;
    }

    try {
        $dbConfig = null;
        if (class_exists('Ini')) {
            $ini = new Ini($databaseIniFile);
            $dbConfig = $ini->getSection('database', []);
        }
        if (empty($dbConfig)) {
            $parsed = @parse_ini_file($databaseIniFile, true);
            $dbConfig = $parsed['database'] ?? [];
        }

        if (! empty($dbConfig)) {
            $host = $dbConfig['host'] ?? '127.0.0.1';
            $port = (int)($dbConfig['port'] ?? 3306);

            if ($reload || ! defined('DB_HOST') || DB_HOST === '') {
                if (! defined('DB_HOST')) {
                    define('DB_HOST', $host . ':' . $port);
                }
                if (! defined('DB_NAME')) {
                    define('DB_NAME', $dbConfig['name'] ?? '');
                }
                if (! defined('DB_USER')) {
                    define('DB_USER', $dbConfig['user'] ?? 'root');
                }
                if (! defined('DB_PASS')) {
                    define('DB_PASS', $dbConfig['pass'] ?? '');
                }
                if (! defined('DB_CHARSET')) {
                    define('DB_CHARSET', $dbConfig['charset'] ?? 'utf8mb4');
                }
            }
        } else {
            if (! defined('DB_HOST')) {
                define('DB_HOST', '');
            }
            if (! defined('DB_NAME')) {
                define('DB_NAME', '');
            }
            if (! defined('DB_USER')) {
                define('DB_USER', '');
            }
            if (! defined('DB_PASS')) {
                define('DB_PASS', '');
            }
            if (! defined('DB_CHARSET')) {
                define('DB_CHARSET', 'utf8mb4');
            }
        }
    } catch (Exception $e) {
        logger()->logError('Error loading database.ini: ' . $e->getMessage(), ['exception' => $e, 'file' => $databaseIniFile]);
        if (! defined('DB_HOST')) {
            define('DB_HOST', '');
        }
        if (! defined('DB_NAME')) {
            define('DB_NAME', '');
        }
        if (! defined('DB_USER')) {
            define('DB_USER', '');
        }
        if (! defined('DB_PASS')) {
            define('DB_PASS', '');
        }
        if (! defined('DB_CHARSET')) {
            define('DB_CHARSET', 'utf8mb4');
        }
    }
}

/**
 * @param array<string, mixed> $errorDetails
 * @return void
 */
/**
 * @param array<string, mixed> $errorDetails
 */
function showDatabaseError(array $errorDetails = []): void
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
    $installedFlagFile = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.flag';
    $isInstalled = file_exists($installedFlagFile);

    if (str_starts_with($requestUri, '/install')) {
        return;
    }
    if (! $isInstalled && php_sapi_name() !== 'cli') {
        if (function_exists('showInstallationRequired')) {
            showInstallationRequired();
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

    if (! headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
    }

    if (isset($errorDetails['host']) && ! isset($errorDetails['port'])) {
        $host = $errorDetails['host'];
        if (str_contains($host, ':')) {
            [$host, $port] = explode(':', $host, 2);
            $errorDetails['host'] = $host;
            $errorDetails['port'] = (int)$port;
        } else {
            $errorDetails['port'] = 3306;
        }
    }

    $template = __DIR__ . '/../interface/errors/exceptions/database-connection-failure.php';
    if (file_exists($template)) {
        // Передаємо змінні в шаблон
        extract($errorDetails, EXTR_SKIP);
        include $template;
    } else {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Помилка БД</title></head><body><h1>Помилка підключення до бази даних</h1></body></html>';
    }
}

function initializeSystem(): void
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

    if (str_starts_with($requestUri, '/install')) {
        return;
    }

    $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
    $installedFlagFile = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.flag';
    $isInstalled = file_exists($installedFlagFile);

    if (! $isInstalled) {
        if (php_sapi_name() !== 'cli') {
            if (function_exists('showInstallationRequired')) {
                showInstallationRequired();
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

    if (! DatabaseHelper::isAvailable(false)) {
        showDatabaseError([
            'host' => DB_HOST,
            'database' => DB_NAME,
            'error' => 'Не вдалося підключитися до бази даних. Перевірте налаштування підключення.',
        ]);
        exit;
    }
}

function renderThemeFallback(): bool
{
    // Перевірка стану системи перед показом fallback
    // Якщо БД недоступна або таблиці відсутні - показуємо 500 помилку замість повідомлення про тему
    if (function_exists('checkSystemState')) {
        // Зберігаємо поточний REQUEST_URI для перевірки
        $originalUri = $_SERVER['REQUEST_URI'] ?? '/';

        // Викликаємо перевірку стану системи
        // Вона покаже 500 помилку, якщо БД недоступна або таблиці відсутні
        checkSystemState();

        // Якщо checkSystemState не завершила виконання (система в порядку),
        // продовжуємо показ повідомлення про відсутність теми
    }

    // Якщо система в порядку - показуємо повідомлення про відсутність теми
    http_response_code(200);
    $template = __DIR__ . '/../interface/errors/exceptions/theme-missing.php';
    if (file_exists($template)) {
        include $template;
    } else {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Встановіть тему</title></head><body><h1>Встановіть тему</h1><p><a href="/admin/themes">Перейти до тем</a></p></body></html>';
    }

    return true;
}

if (! function_exists('redirectTo')) {
    function redirectTo(string $url): void
    {
        Response::redirectStatic($url);
    }
}

if (! function_exists('formatBytes')) {
    function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes === 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = min(floor(($bytes ? log($bytes) : 0) / log(1024)), count($units) - 1);

        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }
}

if (! function_exists('pluginManager')) {
    function pluginManager(): ?PluginManager
    {
        if (! class_exists('PluginManager')) {
            return null;
        }

        $container = isset($GLOBALS['engineContainer']) && $GLOBALS['engineContainer'] instanceof ContainerInterface
            ? $GLOBALS['engineContainer']
            : null;

        if ($container && $container->has(PluginManager::class)) {
            return $container->make(PluginManager::class);
        }

        return PluginManager::getInstance();
    }
}

// ============================================================================
// Хелпер-функції для роботи з сховищами
// ============================================================================

if (! function_exists('cookieManager')) {
    /**
     * Отримання менеджера cookies через фасад
     *
     * @return CookieManager
     */
    function cookieManager(): CookieManager
    {
        if (class_exists('CookieFacade')) {
            return CookieFacade::manager();
        }

        return CookieManager::getInstance();
    }
}

if (! function_exists('sessionManager')) {
    /**
     * Отримання менеджера сесій через фасад
     *
     * @param string $prefix Префікс для ключів (опціонально)
     * @return SessionManager
     */
    function sessionManager(string $prefix = ''): SessionManager
    {
        if (class_exists('SessionFacade')) {
            return SessionFacade::manager($prefix);
        }

        $manager = SessionManager::getInstance();
        if ($prefix) {
            $manager->setPrefix($prefix);
        }

        return $manager;
    }
}

if (! function_exists('storageManager')) {
    /**
     * Отримання менеджера клієнтського сховища через фасад
     *
     * @param string $type Тип сховища (localStorage або sessionStorage)
     * @param string $prefix Префікс для ключів (опціонально)
     * @return StorageManager
     */
    function storageManager(string $type = 'localStorage', string $prefix = ''): StorageManager
    {
        if (class_exists('StorageFacade')) {
            return StorageFacade::manager($type, $prefix);
        }

        $manager = StorageManager::getInstance();
        $manager->setType($type);
        if ($prefix) {
            $manager->setPrefix($prefix);
        }

        return $manager;
    }
}

if (! function_exists('storageFactory')) {
    /**
     * Отримання менеджера сховища через фабрику
     *
     * @param string $type Тип сховища (cookie, session, localStorage, sessionStorage)
     * @param string $prefix Префікс для ключів (опціонально)
     * @return StorageInterface|null
     */
    function storageFactory(string $type = 'session', string $prefix = ''): ?StorageInterface
    {
        return StorageFactory::get($type, $prefix);
    }
}

if (! function_exists('timezoneManager')) {
    /**
     * Отримання менеджера часових поясів
     *
     * @return TimezoneManager
     */
    function timezoneManager(): TimezoneManager
    {
        return TimezoneManager::getInstance();
    }
}

if (! function_exists('getTimezone')) {
    /**
     * Отримання часового поясу за ідентифікатором
     *
     * @param string $identifier UTC ідентифікатор або значення часового поясу
     * @return Timezone|null
     */
    function getTimezone(string $identifier): ?Timezone
    {
        return timezoneManager()->find($identifier);
    }
}

if (! function_exists('getAllTimezones')) {
    /**
     * Отримання всіх часових поясів
     *
     * @return array Масив об'єктів Timezone
     */
    function getAllTimezones(): array
    {
        return timezoneManager()->getAll();
    }
}

if (! function_exists('getTimezoneOptions')) {
    /**
     * Отримання списку часових поясів для використання в select/dropdown
     *
     * @param string|null $selectedValue Вибране значення
     * @return array Масив ['value' => 'text'] для використання в HTML select
     */
    function getTimezoneOptions(?string $selectedValue = null): array
    {
        return timezoneManager()->getOptions($selectedValue);
    }
}

if (! function_exists('convertTimezone')) {
    /**
     * Конвертація дати/часу з одного часового поясу в інший
     *
     * @param string|\DateTime $dateTime Дата/час
     * @param string $fromTimezone З якого часового поясу
     * @param string $toTimezone В який часовий пояс
     * @param string|null $format Формат виводу (якщо null, повертається DateTime)
     * @return string|\DateTime
     */
    function convertTimezone($dateTime, string $fromTimezone, string $toTimezone, ?string $format = null)
    {
        return timezoneManager()->convert($dateTime, $fromTimezone, $toTimezone, $format);
    }
}

if (! function_exists('convertFromUtc')) {
    /**
     * Конвертація дати/часу з UTC в часовий пояс з БД
     *
     * @param string|\DateTime $dateTime Дата/час в UTC
     * @param string|null $format Формат виводу (якщо null, повертається DateTime)
     * @return string|\DateTime
     */
    function convertFromUtc($dateTime, ?string $format = null)
    {
        return timezoneManager()->convertFromUtc($dateTime, $format);
    }
}

if (! function_exists('convertToUtc')) {
    /**
     * Конвертація дати/часу з часового поясу з БД в UTC
     *
     * @param string|\DateTime $dateTime Дата/час в часовому поясі з БД
     * @param string|null $format Формат виводу (якщо null, повертається DateTime)
     * @return string|\DateTime
     */
    function convertToUtc($dateTime, ?string $format = null)
    {
        return timezoneManager()->convertToUtc($dateTime, $format);
    }
}

if (! function_exists('getTimezoneFromDatabase')) {
    /**
     * Отримання часового поясу з налаштувань БД через TimezoneManager
     *
     * @return string Часовий пояс (наприклад, "Europe/Kyiv")
     */
    function getTimezoneFromDatabase(): string
    {
        return timezoneManager()->getTimezoneFromDatabase();
    }
}

if (! function_exists('getTimezoneFromDatabaseAsObject')) {
    /**
     * Отримання об'єкта Timezone з налаштувань БД через TimezoneManager
     *
     * @return Timezone|null
     */
    function getTimezoneFromDatabaseAsObject(): ?Timezone
    {
        return timezoneManager()->getTimezoneFromDatabaseAsObject();
    }
}

// ============================================================================
// Helper функції для логування
// ============================================================================

if (! function_exists('logger')) {
    /**
     * Отримання екземпляра Logger
     *
     * @return \Flowaxy\Core\Contracts\LoggerInterface
     */
    function logger(): \Flowaxy\Core\Contracts\LoggerInterface
    {
        if (class_exists('Logger')) {
            return \Logger::getInstance();
        }

        // Fallback: створюємо простий логер якщо клас не знайдено
        throw new \RuntimeException('Logger class not found');
    }
}

if (! function_exists('logDebug')) {
    /**
     * Логування DEBUG рівня
     *
     * @param string $message Повідомлення
     * @param array $context Контекст
     * @return void
     */
    function logDebug(string $message, array $context = []): void
    {
        if (function_exists('logger')) {
            try {
                logger()->logDebug($message, $context);
            } catch (\Throwable $e) {
                // Fallback на error_log якщо logger недоступний
                error_log('DEBUG: ' . $message . ' | Context: ' . json_encode($context));
            }
        }
    }
}

if (! function_exists('logInfo')) {
    /**
     * Логування INFO рівня
     *
     * @param string $message Повідомлення
     * @param array $context Контекст
     * @return void
     */
    function logInfo(string $message, array $context = []): void
    {
        if (function_exists('logger')) {
            try {
                logger()->logInfo($message, $context);
            } catch (\Throwable $e) {
                error_log('INFO: ' . $message . ' | Context: ' . json_encode($context));
            }
        }
    }
}

if (! function_exists('logWarning')) {
    /**
     * Логування WARNING рівня
     *
     * @param string $message Повідомлення
     * @param array $context Контекст
     * @return void
     */
    function logWarning(string $message, array $context = []): void
    {
        if (function_exists('logger')) {
            try {
                logger()->logWarning($message, $context);
            } catch (\Throwable $e) {
                error_log('WARNING: ' . $message . ' | Context: ' . json_encode($context));
            }
        }
    }
}

if (! function_exists('logError')) {
    /**
     * Логування ERROR рівня
     *
     * @param string $message Повідомлення
     * @param array $context Контекст
     * @return void
     */
    function logError(string $message, array $context = []): void
    {
        if (function_exists('logger')) {
            try {
                logger()->logError($message, $context);
            } catch (\Throwable $e) {
                error_log('ERROR: ' . $message . ' | Context: ' . json_encode($context));
            }
        }
    }
}

if (! function_exists('logCritical')) {
    /**
     * Логування CRITICAL рівня
     *
     * @param string $message Повідомлення
     * @param array $context Контекст
     * @return void
     */
    function logCritical(string $message, array $context = []): void
    {
        if (function_exists('logger')) {
            try {
                logger()->logCritical($message, $context);
            } catch (\Throwable $e) {
                error_log('CRITICAL: ' . $message . ' | Context: ' . json_encode($context));
            }
        }
    }
}

if (! function_exists('logSql')) {
    /**
     * Логування SQL запитів
     *
     * @param string $query SQL запит
     * @param array $params Параметри запиту
     * @param float|null $executionTime Час виконання в секундах
     * @return void
     */
    function logSql(string $query, array $params = [], ?float $executionTime = null): void
    {
        if (function_exists('logger')) {
            try {
                $logger = logger();
                if (method_exists($logger, 'logSql')) {
                    $logger->logSql($query, $params, $executionTime);
                } else {
                    // Fallback: логуємо як DEBUG з контекстом
                    $context = [
                        'type' => 'sql',
                        'query' => $query,
                        'params' => $params,
                    ];
                    if ($executionTime !== null) {
                        $context['execution_time'] = $executionTime;
                    }
                    $logger->logDebug('SQL Query: ' . $query, $context);
                }
            } catch (\Throwable $e) {
                error_log('SQL: ' . $query . ' | Params: ' . json_encode($params) . ' | Time: ' . ($executionTime ?? 'N/A'));
            }
        }
    }
}

if (! function_exists('logDbError')) {
    /**
     * Логування помилок бази даних
     *
     * @param string $message Повідомлення про помилку
     * @param array $context Контекст (query, params, error_code, etc.)
     * @return void
     */
    function logDbError(string $message, array $context = []): void
    {
        if (function_exists('logger')) {
            try {
                $logger = logger();
                if (method_exists($logger, 'logDbError')) {
                    $logger->logDbError($message, $context);
                } else {
                    // Fallback: логуємо як ERROR з контекстом
                    $context['type'] = 'db_error';
                    $logger->logError('DB Error: ' . $message, $context);
                }
            } catch (\Throwable $e) {
                error_log('DB ERROR: ' . $message . ' | Context: ' . json_encode($context));
            }
        }
    }
}
