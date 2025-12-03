<?php

/**
 * Flowaxy CMS - Bootstrap Application
 * Ініціалізація та налаштування системи
 *
 * @package Engine\Core\Bootstrap
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

// ============================================================================
// 0. ВИЗНАЧЕННЯ БАЗОВИХ КОНСТАНТ
// ============================================================================

if (!defined('FLOWAXY')) {
    define('FLOWAXY', true);
}
if (!defined('FLOWAXY_CMS')) {
    define('FLOWAXY_CMS', true);
}
if (!defined('ROOT_DIR')) {
    // app.php знаходиться в engine/core/bootstrap/app.php
    // dirname(__DIR__, 3) дає корінь проекту (на 3 рівні вгору)
    define('ROOT_DIR', dirname(__DIR__, 3));
}
if (!defined('ENGINE_DIR')) {
    define('ENGINE_DIR', ROOT_DIR . DIRECTORY_SEPARATOR . 'engine');
}

// ============================================================================
// 0.0. ЗАВАНТАЖЕННЯ ERROR HANDLER (для використання в перевірках)
// ============================================================================

// Завантаження error-handler для використання в перевірках
$errorHandlerFile = ROOT_DIR . DIRECTORY_SEPARATOR . 'engine' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'error-handler.php';
if (file_exists($errorHandlerFile) && is_readable($errorHandlerFile)) {
    require_once $errorHandlerFile;
}

// ============================================================================
// 0.1. ПЕРЕВІРКА БАЗИ ДАНИХ ПЕРЕД ВСІМ ІНШИМ
// ============================================================================

$isCli = php_sapi_name() === 'cli';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$isInstaller = is_string($path) && str_starts_with($path, '/install');
$rootDir = defined('ROOT_DIR') && is_string(constant('ROOT_DIR')) ? constant('ROOT_DIR') : dirname(__DIR__, 3);
$installedFlagFile = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.flag';
$isInstalled = file_exists($installedFlagFile);

// Перевірка наявності установки - показуємо сторінку з кнопкою установки
// Якщо файл-маркер installed.flag не існує - система не встановлена
// Ця перевірка має бути ПЕРШОЮ, перед перевіркою інсталятора
if (!$isCli && !$isInstaller && !$isInstalled) {
    // Завжди завантажуємо error-handler перед викликом функції
    if (!function_exists('showInstallationRequired')) {
        $errorHandlerFile = ROOT_DIR . DIRECTORY_SEPARATOR . 'engine' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'error-handler.php';
        if (file_exists($errorHandlerFile) && is_readable($errorHandlerFile)) {
            require_once $errorHandlerFile;
        }
    }
    
    // Викликаємо функцію, яка покаже сторінку з кнопкою установки
    // Функція сама викликає exit, тому код після неї не виконається
    if (function_exists('showInstallationRequired')) {
        showInstallationRequired();
        // Якщо функція не викликала exit (не повинно бути), виходимо тут
        exit;
    }
    
    // Fallback - якщо функція недоступна (має бути рідкістю)
    // Показуємо просту сторінку з повідомленням замість переадресації
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

// ============================================================================
// 0.2. ПЕРЕВІРКА ІНСТАЛЯТОРА (якщо база даних існує або це запит до /install)
// ============================================================================

if (!$isCli) {
    if (is_string($requestUri) && str_starts_with($requestUri, '/install')) {
        $installerEntry = ROOT_DIR . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'index.php';
        if (!file_exists($installerEntry)) {
            if (function_exists('showHttpError')) {
                showHttpError(404, 'Інсталятор не знайдено', 'Файл install/index.php відсутній. Можливо, система вже встановлена або каталог install було видалено.');
            } else {
                // Fallback якщо error handler недоступний
                http_response_code(404);
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Інсталятор не знайдено</title></head><body>';
                echo '<h1>Інсталятор не знайдено</h1>';
                echo '<p>Файл <code>install/index.php</code> відсутній. Можливо, система вже встановлена або каталог install було видалено.</p>';
                echo '</body></html>';
            }
            exit;
        }

        require_once $installerEntry;
        exit;
    }
}

// ============================================================================
// 1. ПЕРЕВІРКА ВЕРСІЇ PHP
// ============================================================================

if (version_compare(PHP_VERSION, '8.4.0', '<')) {
    $minVersion = '8.4.0';
    $currentVersion = PHP_VERSION;
    $errorMessage = "Ця CMS потребує PHP {$minVersion} або вище. Поточна версія: {$currentVersion}";
    
    error_log("Критична помилка версії PHP: {$errorMessage}");
    
    if (!$isCli) {
        // Використовуємо систему обробки помилок
        if (function_exists('showError500Page')) {
            try {
                showError500Page(null, [
                    'message' => $errorMessage,
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'code' => 0,
                    'type' => 'PHPVersionError',
                    'showDebug' => true,
                ]);
                exit;
            } catch (Throwable $e) {
                // Якщо не вдалося показати сторінку помилки, використовуємо fallback
            }
        }
        
        // Fallback - проста помилка, якщо error-handler недоступний
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Помилка версії PHP</title></head><body><h1>Потрібно PHP 8.4+</h1><p>Поточна версія: ' . htmlspecialchars($currentVersion, ENT_QUOTES, 'UTF-8') . '</p></body></html>');
    }
    
    die("{$errorMessage}" . PHP_EOL);
}

// ============================================================================
// 2. ВИЗНАЧЕННЯ БАЗОВИХ ШЛЯХІВ ТА ПАРАМЕТРІВ
// ============================================================================

$defaultTimezone = 'Europe/Kyiv';

// ============================================================================
// 3. ФУНКЦІЯ ВИЗНАЧЕННЯ ПРОТОКОЛУ
// ============================================================================

if (! function_exists('detectProtocol')) {
    /**
     * Визначення протоколу (HTTP/HTTPS)
     * Перевіряє налаштування з бази даних, якщо доступна, інакше визначає автоматично
     *
     * @return string Протокол (http:// або https://)
     */
    function detectProtocol(): string
    {
        // Спочатку перевіряємо глобальну змінну
        if (isset($GLOBALS['_SITE_PROTOCOL']) && is_string($GLOBALS['_SITE_PROTOCOL']) && ! empty($GLOBALS['_SITE_PROTOCOL'])) {
            return $GLOBALS['_SITE_PROTOCOL'];
        }

        // Потім перевіряємо налаштування з бази даних (якщо доступна)
        $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 3);
        $databaseIniFile = $rootDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.ini';
        
        if (file_exists($databaseIniFile) && is_readable($databaseIniFile) && class_exists('SettingsManager') && function_exists('settingsManager')) {
            try {
                $settingsManager = settingsManager();
                if ($settingsManager !== null && method_exists($settingsManager, 'get')) {
                    $protocolSetting = $settingsManager->get('site_protocol', 'auto');

                    // Якщо налаштування встановлено явно, використовуємо його
                    if ($protocolSetting === 'https') {
                        $GLOBALS['_SITE_PROTOCOL'] = 'https://';
                        return 'https://';
                    } elseif ($protocolSetting === 'http') {
                        $GLOBALS['_SITE_PROTOCOL'] = 'http://';
                        return 'http://';
                    }
                }
            } catch (Throwable $e) {
                // Якщо не вдалося завантажити налаштування, продовжуємо автоматичне визначення
                if (function_exists('logger')) {
                    try {
                        logger()->logError('detectProtocol: Не вдалося завантажити налаштування: ' . $e->getMessage(), ['exception' => $e]);
                    } catch (Throwable $logError) {
                        // Ігноруємо помилки логування
                    }
                }
            }
        }

        // Автоматичне визначення протоколу
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && is_string($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $protocol = 'https://';
            $GLOBALS['_SITE_PROTOCOL'] = $protocol;
            return $protocol;
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && is_string($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            $protocol = 'https://';
            $GLOBALS['_SITE_PROTOCOL'] = $protocol;
            return $protocol;
        }

        $isHttps = (
            (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
            (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        );

        $protocol = $isHttps ? 'https://' : 'http://';
        $GLOBALS['_SITE_PROTOCOL'] = $protocol;

        return $protocol;
    }
}

// Визначення протоколу та хоста
$protocol = detectProtocol();
$host = isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$GLOBALS['_SITE_PROTOCOL'] = $protocol;

// ============================================================================
// 4. ВИЗНАЧЕННЯ КОНСТАНТ СИСТЕМИ
// ============================================================================

if (!defined('SITE_URL')) {
    define('SITE_URL', $protocol . $host);
}
if (!defined('ADMIN_URL')) {
    define('ADMIN_URL', SITE_URL . '/admin');
}
if (!defined('UPLOADS_DIR')) {
    $uploadsDir = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    define('UPLOADS_DIR', $uploadsDir);
}
if (!defined('UPLOADS_URL')) {
    define('UPLOADS_URL', SITE_URL . '/uploads/');
}
if (!defined('CACHE_DIR')) {
    $cacheDir = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
    define('CACHE_DIR', $cacheDir);
}
if (!defined('LOGS_DIR')) {
    $logsDir = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;
    define('LOGS_DIR', $logsDir);
}
if (!defined('ADMIN_SESSION_NAME')) {
    define('ADMIN_SESSION_NAME', 'cms_admin_logged_in');
}
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'csrf_token');
}

// PASSWORD_MIN_LENGTH завантажується з налаштувань через SystemConfig
if (!defined('PASSWORD_MIN_LENGTH')) {
    $minPasswordLength = 8;
    if (class_exists('SystemConfig')) {
        try {
            $systemConfig = SystemConfig::getInstance();
            if ($systemConfig !== null && method_exists($systemConfig, 'getPasswordMinLength')) {
                $minPasswordLength = $systemConfig->getPasswordMinLength();
            }
        } catch (Throwable $e) {
            // Використовуємо значення за замовчуванням
        }
    }
    define('PASSWORD_MIN_LENGTH', max(4, (int)$minPasswordLength));
}

// ============================================================================
// 5. ІНІЦІАЛІЗАЦІЯ БУФЕРИЗАЦІЇ ВИВОДУ
// ============================================================================

if (!ob_get_level()) {
    ob_start();
}

// ============================================================================
// 6. ЗАВАНТАЖЕННЯ CLASS AUTOLOADER
// ============================================================================

$engineDir = defined('ENGINE_DIR') ? ENGINE_DIR : (defined('ROOT_DIR') ? ROOT_DIR . DIRECTORY_SEPARATOR . 'engine' : dirname(__DIR__, 2));
$autoloaderFile = $engineDir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'ClassAutoloader.php';
if (!file_exists($autoloaderFile) || !is_readable($autoloaderFile)) {
    throw new RuntimeException('ClassAutoloader file not found or not readable: ' . $autoloaderFile);
}
require_once $autoloaderFile;

if (!class_exists('ClassAutoloader')) {
    throw new RuntimeException('ClassAutoloader class not found after require');
}

// Константи для базових шляхів
if (!defined('ENGINE_PATH_CORE')) {
    define('ENGINE_PATH_CORE', 'core/');
}
if (!defined('ENGINE_PATH_INFRA')) {
    define('ENGINE_PATH_INFRA', 'infrastructure/');
}
if (!defined('ENGINE_PATH_DOMAIN')) {
    define('ENGINE_PATH_DOMAIN', 'domain/');
}
if (!defined('ENGINE_PATH_APP')) {
    define('ENGINE_PATH_APP', 'application/');
}
if (!defined('ENGINE_PATH_INTERFACE')) {
    define('ENGINE_PATH_INTERFACE', 'interface/');
}

// Функція для генерування class map
if (!function_exists('buildClassMap')) {
    function buildClassMap(): array
    {
        $C = ENGINE_PATH_CORE;
        $I = ENGINE_PATH_INFRA;
        $D = ENGINE_PATH_DOMAIN;
        $A = ENGINE_PATH_APP;
        $IF = ENGINE_PATH_INTERFACE;

        return array_merge(
            // Core - Base & Support
            [
                'BaseModule' => "{$C}support/base/BaseModule.php",
                'BasePlugin' => "{$C}support/base/BasePlugin.php",
                'UrlHelper' => "{$C}support/helpers/UrlHelper.php",
                'DatabaseHelper' => "{$C}support/helpers/DatabaseHelper.php",
                'SecurityHelper' => "{$C}support/helpers/SecurityHelper.php",
                'Validator' => "{$C}support/validation/Validator.php",
                'Facade' => "{$C}support/facades/Facade.php",
                'App' => "{$C}support/facades/App.php",
                'Hooks' => "{$C}support/facades/Hooks.php",
                'Plugin' => "{$C}support/facades/Plugin.php",
                'Theme' => "{$C}support/facades/Theme.php",
                'Role' => "{$C}support/facades/Role.php",
                'Log' => "{$C}support/facades/Log.php",
                'CacheFacade' => "{$C}support/facades/Cache.php",
                'SessionFacade' => "{$C}support/facades/Session.php",
                'CookieFacade' => "{$C}support/facades/Cookie.php",
                'StorageFacade' => "{$C}support/facades/Storage.php",
                'SettingsFacade' => "{$C}support/facades/Settings.php",
                'FeatureFlag' => "{$C}support/facades/FeatureFlag.php",
            ],
            // Core - Managers
            [
                'CookieManager' => "{$C}support/managers/CookieManager.php",
                'SessionManager' => "{$C}support/managers/SessionManager.php",
                'StorageManager' => "{$C}support/managers/StorageManager.php",
                'StorageFactory' => "{$C}support/managers/StorageFactory.php",
                'ThemeManager' => "{$C}support/managers/ThemeManager.php",
                'ThemeEditorManager' => "{$C}support/managers/ThemeEditorManager.php",
                'RoleManager' => "{$C}support/managers/RoleManager.php",
                'ThemeCustomizer' => "{$C}support/managers/ThemeCustomizer.php",
                'SettingsManager' => "{$C}support/managers/SettingsManager.php",
            ],
            // Core - System
            [
                'ModuleLoader' => "{$C}system/ModuleLoader.php",
                'HookManager' => "{$C}system/HookManager.php",
                'Container' => "{$C}system/Container.php",
                'ServiceProvider' => "{$C}system/ServiceProvider.php",
                'ServiceConfig' => "{$C}system/ServiceConfig.php",
                'ModuleManager' => "{$C}system/ModuleManager.php",
                'MigrationRunner' => "{$C}system/MigrationRunner.php",
                'HookType' => "{$C}system/hooks/HookType.php",
                'HookDefinition' => "{$C}system/hooks/HookDefinition.php",
                'HookListener' => "{$C}system/hooks/HookListener.php",
                'ComponentRegistry' => "{$C}system/ComponentRegistry.php",
                'TestService' => "{$C}system/TestService.php",
                'KernelInterface' => "{$C}contracts/KernelInterface.php",
                'Kernel' => "{$C}system/Kernel.php",
                'HttpKernel' => "{$C}system/HttpKernel.php",
                'CliKernel' => "{$C}system/CliKernel.php",
            ],
            // Core - Providers
            [
                'CoreServiceProvider' => "{$C}providers/CoreServiceProvider.php",
                'ThemeServiceProvider' => "{$C}providers/ThemeServiceProvider.php",
                'AuthServiceProvider' => "{$C}providers/AuthServiceProvider.php",
                'PluginModuleServiceProvider' => "{$C}providers/PluginModuleServiceProvider.php",
            ],
            // Infrastructure - Filesystem
            [
                'Ini' => "{$I}filesystem/Ini.php",
                'Json' => "{$I}filesystem/Json.php",
                'Zip' => "{$I}filesystem/Zip.php",
                'File' => "{$I}filesystem/File.php",
                'Xml' => "{$I}filesystem/Xml.php",
                'Csv' => "{$I}filesystem/Csv.php",
                'Yaml' => "{$I}filesystem/Yaml.php",
                'Image' => "{$I}filesystem/Image.php",
                'Upload' => "{$I}filesystem/Upload.php",
                'MimeType' => "{$I}filesystem/MimeType.php",
                'PluginFilesystem' => "{$I}filesystem/PluginFilesystem.php",
            ],
            // Infrastructure - Other
            [
                'Cache' => "{$I}cache/Cache.php",
                'Database' => "{$I}persistence/Database.php",
                'Logger' => "{$I}logging/Logger.php",
                'Config' => "{$I}config/Config.php",
                'SystemConfig' => "{$I}config/SystemConfig.php",
                'ScssCompiler' => "{$I}compilers/ScssCompiler.php",
                'Security' => "{$I}security/Security.php",
                'Hash' => "{$I}security/Hash.php",
                'Encryption' => "{$I}security/Encryption.php",
                'Session' => "{$I}security/Session.php",
                'Mail' => "{$I}mail/Mail.php",
                'PluginCacheManager' => "{$I}cache/PluginCacheManager.php",
            ],
            // Domain
            [
                'Theme' => "{$D}content/Theme.php",
                'ThemeRepositoryInterface' => "{$D}content/ThemeRepositoryInterface.php",
                'ThemeSettingsRepositoryInterface' => "{$D}content/ThemeSettingsRepositoryInterface.php",
                'AdminUser' => "{$D}content/AdminUser.php",
                'AdminUserRepositoryInterface' => "{$D}content/AdminUserRepositoryInterface.php",
                'AdminRole' => "{$D}content/AdminRole.php",
                'AdminRoleRepositoryInterface' => "{$D}content/AdminRoleRepositoryInterface.php",
                'Plugin' => "{$D}content/Plugin.php",
                'PluginRepositoryInterface' => "{$D}content/PluginRepositoryInterface.php",
                'PluginLifecycleInterface' => "{$D}content/PluginLifecycleInterface.php",
                'PluginFilesystemInterface' => "{$D}content/PluginFilesystemInterface.php",
                'PluginCacheInterface' => "{$D}content/PluginCacheInterface.php",
            ],
            // Application - Services
            [
                'ActivateThemeService' => "{$A}content/ActivateThemeService.php",
                'UpdateThemeSettingsService' => "{$A}content/UpdateThemeSettingsService.php",
                'InstallPluginService' => "{$A}content/InstallPluginService.php",
                'PluginLifecycleService' => "{$A}content/PluginLifecycleService.php",
                'AuthenticateAdminUserService' => "{$A}security/AuthenticateAdminUserService.php",
                'AuthenticationResult' => "{$A}security/AuthenticationResult.php",
                'AdminAuthorizationService' => "{$A}security/AdminAuthorizationService.php",
                'LogoutAdminUserService' => "{$A}security/LogoutAdminUserService.php",
                'ActivatePluginService' => "{$A}content/ActivatePluginService.php",
                'DeactivatePluginService' => "{$A}content/DeactivatePluginService.php",
                'TogglePluginService' => "{$A}content/TogglePluginService.php",
                'UninstallPluginService' => "{$A}content/UninstallPluginService.php",
            ],
            // Infrastructure - Repositories
            [
                'ThemeRepository' => "{$I}persistence/ThemeRepository.php",
                'ThemeSettingsRepository' => "{$I}persistence/ThemeSettingsRepository.php",
                'AdminUserRepository' => "{$I}persistence/AdminUserRepository.php",
                'AdminRoleRepository' => "{$I}persistence/AdminRoleRepository.php",
                'PluginRepository' => "{$I}persistence/PluginRepository.php",
            ],
            // Interface - HTTP
            [
                'Cookie' => "{$IF}http/controllers/Cookie.php",
                'Response' => "{$IF}http/controllers/Response.php",
                'Request' => "{$IF}http/controllers/Request.php",
                'Router' => "{$IF}http/router/Router.php",
                'AjaxHandler' => "{$IF}http/controllers/AjaxHandler.php",
                'ApiHandler' => "{$IF}http/controllers/AjaxHandler.php",
                'ApiController' => "{$IF}http/controllers/ApiController.php",
                'RouterManager' => "{$IF}http/router/RouterManager.php",
            ],
            // Interface - UI
            [
                'View' => "{$IF}ui/View.php",
                'ModalHandler' => "{$IF}ui/ModalHandler.php",
            ],
            // Interface - Admin Pages
            [
                'LoginPage' => "{$IF}admin-ui/pages/LoginPage.php",
                'LogoutPage' => "{$IF}admin-ui/pages/LogoutPage.php",
                'DashboardPage' => "{$IF}admin-ui/pages/DashboardPage.php",
                'SettingsPage' => "{$IF}admin-ui/pages/SettingsPage.php",
                'SiteSettingsPage' => "{$IF}admin-ui/pages/SiteSettingsPage.php",
                'ProfilePage' => "{$IF}admin-ui/pages/ProfilePage.php",
                'PluginsPage' => "{$IF}admin-ui/pages/PluginsPage.php",
                'ThemesPage' => "{$IF}admin-ui/pages/ThemesPage.php",
                'RolesPage' => "{$IF}admin-ui/pages/RolesPage.php",
                'UsersPage' => "{$IF}admin-ui/pages/UsersPage.php",
                'CacheClearPage' => "{$IF}admin-ui/pages/CacheClearPage.php",
            ],
            // Engine namespace classes
            [
                'Engine\\Core\\System\\HttpKernel' => "{$C}system/HttpKernel.php",
                'Engine\\Core\\System\\Kernel' => "{$C}system/Kernel.php",
                'Engine\\Core\\System\\CliKernel' => "{$C}system/CliKernel.php",
                'Engine\\Core\\Contracts\\KernelInterface' => "{$C}contracts/KernelInterface.php",
            ]
        );
    }
}

// Ініціалізація автозавантажувача класів
try {
    $autoloader = new ClassAutoloader($engineDir);
    $autoloader->enableMissingClassLogging(true);
    
    // Додавання class map
    $classMap = buildClassMap();
    if (is_array($classMap) && !empty($classMap)) {
        $autoloader->addClassMap($classMap);
    }
    
    // Директорії для автозавантаження
    $directoryPaths = [
        'core/', 'core/bootstrap/', 'core/providers/', 'core/contracts/', 'core/system/', 'core/support/',
        'core/support/base/', 'core/support/helpers/', 'core/support/managers/', 'core/support/validation/',
        'domain/', 'domain/content/', 'domain/shared/',
        'application/', 'application/content/', 'application/security/', 'application/testing/',
        'infrastructure/', 'infrastructure/persistence/', 'infrastructure/cache/', 'infrastructure/config/',
        'infrastructure/logging/', 'infrastructure/filesystem/', 'infrastructure/filesystem/contracts/',
        'infrastructure/security/', 'infrastructure/mail/', 'infrastructure/compilers/',
        'interface/', 'interface/http/', 'interface/http/contracts/', 'interface/http/controllers/',
        'interface/http/middleware/', 'interface/http/router/', 'interface/admin-ui/',
        'interface/admin-ui/pages/', 'interface/admin-ui/includes/', 'interface/admin-ui/components/',
        'interface/admin-ui/layouts/', 'interface/admin-ui/assets/', 'interface/ui/', 'interface/templates/',
    ];
    
    // Фільтрація та додавання тільки існуючих директорій
    $classDirectories = [];
    foreach ($directoryPaths as $dir) {
        $fullPath = $engineDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
        if (is_dir($fullPath) && is_readable($fullPath)) {
            $classDirectories[] = $fullPath;
        }
    }
    
    if (!empty($classDirectories)) {
        $autoloader->addDirectories($classDirectories);
    }
    
    $autoloader->register();
    $GLOBALS['engineAutoloader'] = $autoloader;
} catch (Throwable $e) {
    throw new RuntimeException('Failed to initialize ClassAutoloader: ' . $e->getMessage(), 0, $e);
}

// ============================================================================
// 7. ЗАВАНТАЖЕННЯ LOGGER ТА ДОПОМІЖНИХ ФАЙЛІВ
// ============================================================================

// Завантаження Logger рано для доступності функції logger()
$loggerFile = $engineDir . DIRECTORY_SEPARATOR . 'infrastructure' . DIRECTORY_SEPARATOR . 'logging' . DIRECTORY_SEPARATOR . 'Logger.php';
if (file_exists($loggerFile) && is_readable($loggerFile)) {
    require_once $loggerFile;
}

// Підключення допоміжних файлів
$functionsFile = $engineDir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'functions.php';
if (file_exists($functionsFile) && is_readable($functionsFile)) {
    require_once $functionsFile;
}

// Error-handler вже завантажений на початку файлу, не потрібно завантажувати повторно

// Ініціалізація модулів
if ($isInstalled && class_exists('ModuleLoader') && method_exists('ModuleLoader', 'init')) {
    try {
        ModuleLoader::init();
    } catch (Throwable $e) {
        // Продовжуємо роботу навіть якщо ModuleLoader не вдався
    }
}

// ============================================================================
// 8. ВСТАНОВЛЕННЯ ЧАСОВОГО ПОЯСУ
// ============================================================================

$timezone = $defaultTimezone;
if ($isInstalled && function_exists('getTimezoneFromDatabase')) {
    try {
        $tz = getTimezoneFromDatabase();
        
        if (is_string($tz) && !empty($tz)) {
            // Автоматичне оновлення старого часового поясу
            if ($tz === 'Europe/Kiev') {
                $tz = 'Europe/Kyiv';
                if (class_exists('SettingsManager') && function_exists('settingsManager')) {
                    try {
                        $settingsManager = settingsManager();
                        if ($settingsManager !== null && method_exists($settingsManager, 'set')) {
                            $settingsManager->set('timezone', 'Europe/Kyiv');
                        }
                    } catch (Throwable $e) {
                        if (class_exists('Logger')) {
                            try {
                                Logger::getInstance()->logWarning('Не вдалося оновити налаштування часового поясу', ['error' => $e->getMessage()]);
                            } catch (Throwable $logError) {
                                // Ігноруємо помилки логування
                            }
                        }
                    }
                }
            }
            $timezone = $tz;
        }
    } catch (Throwable $e) {
        if (class_exists('Logger')) {
            try {
                Logger::getInstance()->logWarning('Помилка завантаження часового поясу', ['error' => $e->getMessage()]);
            } catch (Throwable $logError) {
                // Ігноруємо помилки логування
            }
        }
    }
}

// Встановлення часового поясу з перевіркою валідності
if (is_string($timezone) && !empty($timezone)) {
    try {
        date_default_timezone_set($timezone);
    } catch (Throwable $e) {
        // Якщо часовий пояс невірний, використовуємо за замовчуванням
        date_default_timezone_set($defaultTimezone);
    }
} else {
    date_default_timezone_set($defaultTimezone);
}

// ============================================================================
// 9. НАЛАШТУВАННЯ ОБРОБНИКІВ ПОМИЛОК
// ============================================================================

if ($isInstalled && class_exists('Logger') && method_exists('Logger', 'getInstance')) {
    try {
        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
            if (class_exists('Logger') && method_exists('Logger', 'getInstance')) {
                try {
                    Logger::getInstance()->log(match($errno) {
                        E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR => Logger::LEVEL_ERROR,
                        E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => Logger::LEVEL_WARNING,
                        default => Logger::LEVEL_INFO
                    }, $errstr, ['file' => $errfile, 'line' => $errline, 'errno' => $errno]);
                } catch (Throwable $e) {
                    // Ігноруємо помилки логування
                }
            }
            return false;
        });
    } catch (Throwable $e) {
        // Продовжуємо роботу навіть якщо error handler не вдався
    }

    try {
        set_exception_handler(function (\Throwable $e): void {
            if (class_exists('Logger') && method_exists('Logger', 'getInstance')) {
                try {
                    Logger::getInstance()->logException($e);
                } catch (Throwable $logError) {
                    // Ігноруємо помилки логування
                }
            }
            
            if (function_exists('showError500Page')) {
                try {
                    showError500Page($e);
                    return;
                } catch (Throwable $pageError) {
                    // Якщо не вдалося показати сторінку помилки, використовуємо мінімальний fallback
                    if (!headers_sent()) {
                        http_response_code(500);
                        header('Content-Type: text/html; charset=UTF-8');
                    }
                    echo '<h1>Внутрішня помилка сервера</h1>';
                }
            } else {
                // Fallback якщо error handler недоступний
                if (!headers_sent()) {
                    http_response_code(500);
                    header('Content-Type: text/html; charset=UTF-8');
                }
                echo '<h1>Внутрішня помилка сервера</h1>';
            }
        });
    } catch (Throwable $e) {
        // Продовжуємо роботу навіть якщо exception handler не вдався
    }

    try {
        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error !== null && is_array($error) && isset($error['type']) && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
                if (class_exists('Logger') && method_exists('Logger', 'getInstance')) {
                    try {
                        Logger::getInstance()->logCritical('Критична помилка: ' . ($error['message'] ?? 'Unknown error'), [
                            'file' => $error['file'] ?? 'unknown',
                            'line' => $error['line'] ?? 0
                        ]);
                    } catch (Throwable $logError) {
                        // Ігноруємо помилки логування
                    }
                }
                
                if (function_exists('showError500Page')) {
                    try {
                        showError500Page(null, [
                            'message' => $error['message'] ?? 'Unknown error',
                            'file' => $error['file'] ?? 'unknown',
                            'line' => $error['line'] ?? 0,
                            'code' => $error['type'] ?? 0,
                        ]);
                    } catch (Throwable $pageError) {
                        // Ігноруємо помилки відображення сторінки
                    }
                }
            }
        });
    } catch (Throwable $e) {
        // Продовжуємо роботу навіть якщо shutdown handler не вдався
    }
}

// ============================================================================
// 10. ВИЗНАЧЕННЯ ПРОТОКОЛУ ТА SECURE-ПРАПОРЦЯ
// ============================================================================

$isSecure = false;
$protocolFromSettings = null;

if ($isInstalled && class_exists('SettingsManager') && function_exists('settingsManager')) {
    try {
        $settingsManager = settingsManager();
        if ($settingsManager !== null && method_exists($settingsManager, 'get')) {
            $protocolSetting = $settingsManager->get('site_protocol', 'auto');
            
            if (is_string($protocolSetting)) {
                if ($protocolSetting === 'https') {
                    $protocolFromSettings = 'https://';
                    $isSecure = true;
                    $GLOBALS['_SITE_PROTOCOL'] = 'https://';
                } elseif ($protocolSetting === 'http') {
                    $protocolFromSettings = 'http://';
                    $isSecure = false;
                    $GLOBALS['_SITE_PROTOCOL'] = 'http://';
                }
            }
        }
    } catch (Throwable $e) {
        // Ігноруємо помилки при завантаженні налаштувань
    }
}

// Автоматичне визначення протоколу, якщо не встановлено в налаштуваннях
if ($protocolFromSettings === null) {
    $realHttps = (!empty($_SERVER['HTTPS']) && is_string($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                 (isset($_SERVER['REQUEST_SCHEME']) && is_string($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
                 (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
                 (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && is_string($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    if ($realHttps) {
        $isSecure = true;
    } elseif (class_exists('UrlHelper') && method_exists('UrlHelper', 'isHttps')) {
        try {
            $isSecure = UrlHelper::isHttps();
        } catch (Throwable $e) {
            // Якщо не вдалося визначити через UrlHelper, пробуємо detectProtocol
            if (function_exists('detectProtocol')) {
                try {
                    $isSecure = (detectProtocol() === 'https://');
                } catch (Throwable $e2) {
                    // Використовуємо значення за замовчуванням
                }
            }
        }
    } elseif (function_exists('detectProtocol')) {
        try {
            $isSecure = (detectProtocol() === 'https://');
        } catch (Throwable $e) {
            // Використовуємо значення за замовчуванням
        }
    }
}

// ============================================================================
// 11. ІНІЦІАЛІЗАЦІЯ КОНТЕЙНЕРА ЗАЛЕЖНОСТЕЙ
// ============================================================================

if (!class_exists('Container')) {
    throw new RuntimeException('Container class not found');
}

try {
    $container = new Container();
    $GLOBALS['engineContainer'] = $container;
} catch (Throwable $e) {
    throw new RuntimeException('Failed to create Container: ' . $e->getMessage(), 0, $e);
}

// Завантаження конфігурації сервісів
$servicesConfigFile = $engineDir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'services.php';
if (file_exists($servicesConfigFile) && is_readable($servicesConfigFile) && class_exists('ServiceConfig')) {
    try {
        $servicesConfig = ServiceConfig::load($servicesConfigFile, null);
        if ($servicesConfig !== null) {
            ServiceConfig::register($container, $servicesConfig);
        }
    } catch (Throwable $e) {
        // Продовжуємо роботу навіть якщо конфігурацію не вдалося завантажити
    }
}

// Реєстрація сервіс-провайдерів
$serviceProviders = [
    CoreServiceProvider::class,
    ThemeServiceProvider::class,
    AuthServiceProvider::class,
];

$providerInstances = [];
foreach ($serviceProviders as $providerClass) {
    if (!class_exists($providerClass)) {
        continue;
    }
    
    try {
        $provider = new $providerClass();
        if ($provider instanceof ServiceProvider && method_exists($provider, 'register')) {
            $provider->register($container);
            $providerInstances[] = $provider;
        }
    } catch (Throwable $e) {
        // Пропускаємо провайдер, який не вдалося ініціалізувати
        continue;
    }
}

// Завантаження конфігурації бази даних
if (function_exists('loadDatabaseConfig')) {
    try {
        loadDatabaseConfig();
    } catch (Throwable $e) {
        // Продовжуємо роботу навіть якщо конфігурацію БД не вдалося завантажити
    }
}

// Запуск сервіс-провайдерів
foreach ($providerInstances as $provider) {
    try {
        if (method_exists($provider, 'boot')) {
            $provider->boot($container);
        }
    } catch (Throwable $e) {
        // Продовжуємо роботу навіть якщо boot не вдався
    }
}

// Ініціалізація ModuleManager
if (class_exists('ModuleManager')) {
    try {
        $moduleManager = $container->make(ModuleManager::class);
        if ($moduleManager !== null && method_exists($moduleManager, 'boot')) {
            $moduleManager->boot();
        }
    } catch (Throwable $e) {
        // Продовжуємо роботу навіть якщо ModuleManager не вдалося ініціалізувати
    }
}

// Налаштування Logger для автозавантажувача
if (isset($autoloader) && $container->has(LoggerInterface::class)) {
    try {
        $loggerInstance = $container->make(LoggerInterface::class);
        if ($loggerInstance !== null && method_exists($autoloader, 'setLogger')) {
            $autoloader->setLogger($loggerInstance);
        }
    } catch (Throwable $e) {
        $autoloader->enableMissingClassLogging(false);
    }
}

// Завантаження Cache, якщо не завантажений через autoloader
if (!class_exists('Cache')) {
    $cacheFile = $engineDir . DIRECTORY_SEPARATOR . 'infrastructure' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'Cache.php';
    if (file_exists($cacheFile) && is_readable($cacheFile)) {
        require_once $cacheFile;
    }
}

// ============================================================================
// 12. ІНІЦІАЛІЗАЦІЯ СЕСІЇ
// ============================================================================

if (class_exists('Session') && method_exists('Session', 'start')) {
    try {
        Session::start([
            'domain' => '',
            'path' => '/',
            'secure' => (bool)$isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } catch (Throwable $e) {
        // Продовжуємо роботу навіть якщо сесію не вдалося ініціалізувати
    }
}

// Встановлення security headers
if (!headers_sent() && class_exists('Response') && method_exists('Response', 'setSecurityHeaders')) {
    try {
        Response::setSecurityHeaders();
    } catch (Throwable $e) {
        // Продовжуємо роботу навіть якщо security headers не вдалося встановити
    }
}

// Ініціалізація системи
if (function_exists('initializeSystem')) {
    try {
        initializeSystem();
    } catch (Throwable $e) {
        // Продовжуємо роботу навіть якщо ініціалізація системи не вдалася
    }
}

// ============================================================================
// 13. ВИКОНАННЯ МІГРАЦІЙ
// ============================================================================

if ($isInstalled && class_exists('DatabaseHelper') && method_exists('DatabaseHelper', 'getConnection')) {
    try {
        $db = DatabaseHelper::getConnection();
        if ($db !== null && class_exists('MigrationRunner')) {
            $migrationsDir = $engineDir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'migrations';
            if (is_dir($migrationsDir) && is_readable($migrationsDir)) {
                try {
                    $runner = new MigrationRunner($migrationsDir, $db);
                    if ($runner !== null && method_exists($runner, 'run')) {
                        $runner->run();
                    }
                } catch (Throwable $migrationError) {
                    if (class_exists('Logger') && method_exists('Logger', 'getInstance')) {
                        try {
                            Logger::getInstance()->logError('Не вдалося виконати міграції', ['error' => $migrationError->getMessage()]);
                        } catch (Throwable $logError) {
                            // Ігноруємо помилки логування
                        }
                    }
                }
            }
        }
    } catch (Throwable $e) {
        if (class_exists('Logger') && method_exists('Logger', 'getInstance')) {
            try {
                Logger::getInstance()->logError('Не вдалося отримати підключення до БД для міграцій', ['error' => $e->getMessage()]);
            } catch (Throwable $logError) {
                // Ігноруємо помилки логування
            }
        }
    }
    
    // Ініціалізація системи ролей
    $rolesInitFile = $engineDir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'roles-init.php';
    if (file_exists($rolesInitFile) && is_readable($rolesInitFile)) {
        try {
            require_once $rolesInitFile;
            if (function_exists('initializeRolesSystem')) {
                initializeRolesSystem();
            }
        } catch (Throwable $e) {
            // Продовжуємо роботу навіть якщо ініціалізація ролей не вдалася
        }
    }
}

// ============================================================================
// 14. ПЕРЕВІРКА РАННІХ ХУКІВ
// ============================================================================

if (function_exists('hook_apply')) {
    try {
        $handled = hook_apply('handle_early_request', false);
        if ($handled === true) {
            exit;
        }
    } catch (Throwable $e) {
        // Продовжуємо роботу навіть якщо хук викликав помилку
    }
}

// ============================================================================
// 15. ЗАПУСК СИСТЕМИ (CLI або HTTP)
// ============================================================================

if ($isCli) {
    // CLI режим
    $cliKernelFile = $engineDir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'CliKernel.php';
    if (!file_exists($cliKernelFile)) {
        error_log('Критична помилка: файл engine/core/system/CliKernel.php відсутній.');
        die("Помилка: файл engine/core/system/CliKernel.php відсутній.\n");
    }
    require_once $cliKernelFile;

    $cliKernelClass = 'Engine\Core\System\CliKernel';
    if (!class_exists($cliKernelClass)) {
        error_log("Критична помилка: клас {$cliKernelClass} не знайдено. Перевірте автозавантаження класів.");
        die("Помилка: клас {$cliKernelClass} не знайдено. Перевірте автозавантаження класів.\n");
    }

    // Створюємо та запускаємо CLI ядро
    $kernel = new $cliKernelClass($engineDir, $argv ?? []);
    $kernel->boot();
    $kernel->configure();
    $kernel->registerProviders();
    $kernel->bootProviders();
    $kernel->serve();
} else {
    // HTTP режим - завантаження роутера
    // Повторна перевірка установки перед завантаженням роутера
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
    $isInstallerRoute = is_string($path) && str_starts_with($path, '/install');
    $installedFlagFile = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.flag';
    $isInstalled = file_exists($installedFlagFile);
    
    if (!$isInstallerRoute && !$isInstalled) {
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
    
    $routerFile = $engineDir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'router.php';
    if (file_exists($routerFile) && is_readable($routerFile)) {
        require_once $routerFile;
    } else {
        throw new RuntimeException('Router file not found or not readable: ' . $routerFile);
    }
}

