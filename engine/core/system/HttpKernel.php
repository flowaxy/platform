<?php

/**
 * HTTP ядро системи Flowaxy CMS
 * Обробляє HTTP запити через роутер
 *
 * @package Engine\Core\System
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/Kernel.php';

final class HttpKernel extends Kernel
{
    /**
     * Отримання списку сервіс-провайдерів
     */
    protected function getServiceProviders(): array
    {
        return [
            CoreServiceProvider::class,
            ThemeServiceProvider::class,
            AuthServiceProvider::class,
        ];
    }

    /**
     * Обробка HTTP запиту
     */
    public function serve(): void
    {
        if (! $this->isBooted()) {
            $this->boot();
            $this->configure();
            $this->registerProviders();
            $this->bootProviders();
        }

        // Завантажуємо конфігурацію БД (потрібно перед ініціалізацією)
        if (function_exists('loadDatabaseConfig')) {
            loadDatabaseConfig();
        }

        // Ініціалізація системи (timezone, error handlers, session, migrations, roles)
        $this->initializeApplication();

        // Перевіряємо підключення до БД
        if (function_exists('initializeSystem')) {
            initializeSystem();
        }

        // Обробляємо запит через роутер
        $this->dispatchRequest();
    }

    /**
     * Ініціалізація додатка (timezone, error handlers, session, migrations)
     */
    private function initializeApplication(): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $databaseIniFile = dirname($this->rootDir) . '/storage/config/database.ini';
        $defaultTimezone = 'Europe/Kyiv';

        // Встановлення часового поясу з БД через TimezoneManager
        $timezone = $defaultTimezone;
        if (file_exists($databaseIniFile)) {
            if (class_exists('ModuleLoader')) {
                ModuleLoader::init();
            }

            // Завантажуємо TimezoneManager та отримуємо timezone з БД
            // Підключаємо functions.php, який завантажує TimezoneManager
            $functionsFile = $this->rootDir . '/engine/core/support/functions.php';
            if (file_exists($functionsFile)) {
                require_once $functionsFile;
            }

            // Використовуємо TimezoneManager для отримання timezone з БД
            if (function_exists('getTimezoneFromDatabase')) {
                try {
                    $tz = getTimezoneFromDatabase();
                    
                    // Автоматичне оновлення старого часового поясу на новий
                    if ($tz === 'Europe/Kiev') {
                        $tz = 'Europe/Kyiv';
                        
                        // Оновлюємо в налаштуваннях
                        if (class_exists('SettingsManager') && function_exists('settingsManager')) {
                            try {
                                settingsManager()->set('timezone', 'Europe/Kyiv');
                            } catch (Exception $e) {
                                if (class_exists('Logger')) {
                                    Logger::getInstance()->logWarning('Не вдалося оновити налаштування часового поясу', ['error' => $e->getMessage()]);
                                }
                            }
                        }
                    }
                    
                    $timezone = $tz;
                } catch (Exception $e) {
                    if (class_exists('Logger')) {
                        Logger::getInstance()->logWarning('Помилка завантаження часового поясу', ['error' => $e->getMessage()]);
                    }
                }
            }
        }
        date_default_timezone_set($timezone);

        // Налаштування обробників помилок
        if (file_exists($databaseIniFile) && class_exists('Logger')) {
            set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
                Logger::getInstance()->log(match($errno) {
                    E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR => Logger::LEVEL_ERROR,
                    E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => Logger::LEVEL_WARNING,
                    default => Logger::LEVEL_INFO
                }, $errstr, ['file' => $errfile, 'line' => $errline, 'errno' => $errno]);

                return false;
            });

            set_exception_handler(function (\Throwable $e): void {
                Logger::getInstance()->logException($e);
                
                // Використовуємо функцію для відображення сторінки помилки
                if (function_exists('showError500Page')) {
                    showError500Page($e);
                } else {
                    // Fallback якщо функція недоступна
                    if (! headers_sent()) {
                        http_response_code(500);
                    }
                    if (defined('DEBUG_MODE') && constant('DEBUG_MODE')) {
                        echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "\n" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
                    } else {
                        echo '<h1>Внутрішня помилка сервера</h1>';
                    }
                }
            });

            register_shutdown_function(function (): void {
                $error = error_get_last();
                if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
                    Logger::getInstance()->logCritical('Критична помилка: ' . $error['message'], ['file' => $error['file'], 'line' => $error['line']]);
                    
                    // Відображаємо сторінку помилки, якщо функція доступна
                    if (function_exists('showError500Page')) {
                        showError500Page(null, [
                            'message' => $error['message'],
                            'file' => $error['file'],
                            'line' => $error['line'],
                            'code' => $error['type'],
                        ]);
                    }
                }
            });
        }

        // Визначаємо secure для сесії
        $isSecure = false;
        if (function_exists('detectProtocol')) {
            $protocol = detectProtocol();
            $isSecure = ($protocol === 'https://');
        }

        // Запускаємо сесію
        if (class_exists('Session')) {
            Session::start([
                'domain' => '',
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        // Встановлення security headers
        if (! headers_sent() && class_exists('Response')) {
            Response::setSecurityHeaders();
        }

        // Виконання міграцій через систему міграцій
        if (file_exists($databaseIniFile) && function_exists('DatabaseHelper') && class_exists('DatabaseHelper')) {
            try {
                $db = DatabaseHelper::getConnection();
                if ($db && class_exists('MigrationRunner')) {
                    $migrationsDir = $this->rootDir . '/core/system/migrations';
                    $runner = new MigrationRunner($migrationsDir, $db);
                    $runner->run();
                }
            } catch (Exception $e) {
                if (class_exists('Logger')) {
                    Logger::getInstance()->logError('Не вдалося виконати міграції', ['error' => $e->getMessage()]);
                }
            }
        }

        // Ініціалізація системи ролей
        $rolesInitFile = $this->rootDir . '/core/bootstrap/roles-init.php';
        if (file_exists($rolesInitFile)) {
            require_once $rolesInitFile;
            if (function_exists('initializeRolesSystem')) {
                initializeRolesSystem();
            }
        }
    }

    /**
     * Обробка запиту через роутер
     */
    private function dispatchRequest(): void
    {
        // Перевірка ранніх хуків
        if (function_exists('hook_apply')) {
            $handled = hook_apply('handle_early_request', false);
            if ($handled === true) {
                exit;
            }
        }

        // Обробляємо запит через роутер
        require_once $this->rootDir . '/core/bootstrap/router.php';
    }

    /**
     * Отримання class map для автозавантажувача
     *
     * @return array<string, string>
     */
    protected function getClassMap(): array
    {
        return [
            'BaseModule' => 'core/support/base/BaseModule.php',
            'BasePlugin' => 'core/support/base/BasePlugin.php',
            'Ini' => 'infrastructure/filesystem/Ini.php',
            'Json' => 'infrastructure/filesystem/Json.php',
            'Zip' => 'infrastructure/filesystem/Zip.php',
            'File' => 'infrastructure/filesystem/File.php',
            'Xml' => 'infrastructure/filesystem/Xml.php',
            'Csv' => 'infrastructure/filesystem/Csv.php',
            'Yaml' => 'infrastructure/filesystem/Yaml.php',
            'Image' => 'infrastructure/filesystem/Image.php',
            'Upload' => 'infrastructure/filesystem/Upload.php',
            'MimeType' => 'infrastructure/filesystem/MimeType.php',
            'Cache' => 'infrastructure/cache/Cache.php',
            'Database' => 'infrastructure/persistence/Database.php',
            'Logger' => 'infrastructure/logging/Logger.php',
            'Config' => 'infrastructure/config/Config.php',
            'SystemConfig' => 'infrastructure/config/SystemConfig.php',
            'EnvironmentLoader' => 'core/system/EnvironmentLoader.php',
            'UrlHelper' => 'core/support/helpers/UrlHelper.php',
            'DatabaseHelper' => 'core/support/helpers/DatabaseHelper.php',
            'SecurityHelper' => 'core/support/helpers/SecurityHelper.php',
            'ScssCompiler' => 'infrastructure/compilers/ScssCompiler.php',
            'Validator' => 'core/support/validation/Validator.php',
            'Security' => 'infrastructure/security/Security.php',
            'Hash' => 'infrastructure/security/Hash.php',
            'Encryption' => 'infrastructure/security/Encryption.php',
            'Session' => 'infrastructure/security/Session.php',
            'Cookie' => 'interface/http/controllers/Cookie.php',
            'Response' => 'interface/http/controllers/Response.php',
            'Request' => 'interface/http/controllers/Request.php',
            'Router' => 'interface/http/router/Router.php',
            'AjaxHandler' => 'interface/http/controllers/AjaxHandler.php',
            'ApiHandler' => 'interface/http/controllers/ApiHandler.php',
            'ApiController' => 'interface/http/controllers/ApiController.php',
            'RouterManager' => 'interface/http/router/RouterManager.php',
            'CookieManager' => 'core/support/managers/CookieManager.php',
            'SessionManager' => 'core/support/managers/SessionManager.php',
            'StorageManager' => 'core/support/managers/StorageManager.php',
            'StorageFactory' => 'core/support/managers/StorageFactory.php',
            'ThemeManager' => 'core/support/managers/ThemeManager.php',
            'ThemeEditorManager' => 'core/support/managers/ThemeEditorManager.php',
            'RoleManager' => 'core/support/managers/RoleManager.php',
            'ThemeCustomizer' => 'core/support/managers/ThemeCustomizer.php',
            'View' => 'interface/ui/View.php',
            'Mail' => 'infrastructure/mail/Mail.php',
            'ModalHandler' => 'interface/ui/ModalHandler.php',
            'ModuleLoader' => 'core/system/ModuleLoader.php',
            'HookManager' => 'core/system/HookManager.php',
            'Container' => 'core/system/Container.php',
            'ServiceProvider' => 'core/system/ServiceProvider.php',
            'ServiceConfig' => 'core/system/ServiceConfig.php',
            'ModuleManager' => 'core/system/ModuleManager.php',
            'CoreServiceProvider' => 'core/providers/CoreServiceProvider.php',
            'ThemeServiceProvider' => 'core/providers/ThemeServiceProvider.php',
            'AuthServiceProvider' => 'core/providers/AuthServiceProvider.php',
            'HookType' => 'core/system/hooks/HookType.php',
            'HookDefinition' => 'core/system/hooks/HookDefinition.php',
            'HookListener' => 'core/system/hooks/HookListener.php',
            'ComponentRegistry' => 'core/system/ComponentRegistry.php',
            'PluginModuleServiceProvider' => 'core/providers/PluginModuleServiceProvider.php',
            'SettingsManager' => 'core/support/managers/SettingsManager.php',
            'Theme' => 'domain/content/Theme.php',
            'ThemeRepositoryInterface' => 'domain/content/ThemeRepositoryInterface.php',
            'ThemeSettingsRepositoryInterface' => 'domain/content/ThemeSettingsRepositoryInterface.php',
            'AdminUser' => 'domain/content/AdminUser.php',
            'AdminUserRepositoryInterface' => 'domain/content/AdminUserRepositoryInterface.php',
            'AdminRole' => 'domain/content/AdminRole.php',
            'AdminRoleRepositoryInterface' => 'domain/content/AdminRoleRepositoryInterface.php',
            'Plugin' => 'domain/content/Plugin.php',
            'PluginRepositoryInterface' => 'domain/content/PluginRepositoryInterface.php',
            'PluginLifecycleInterface' => 'domain/content/PluginLifecycleInterface.php',
            'PluginFilesystemInterface' => 'domain/content/PluginFilesystemInterface.php',
            'PluginCacheInterface' => 'domain/content/PluginCacheInterface.php',
            'ActivateThemeService' => 'application/content/ActivateThemeService.php',
            'UpdateThemeSettingsService' => 'application/content/UpdateThemeSettingsService.php',
            'InstallPluginService' => 'application/content/InstallPluginService.php',
            'PluginLifecycleService' => 'application/content/PluginLifecycleService.php',
            'AuthenticateAdminUserService' => 'application/security/AuthenticateAdminUserService.php',
            'AuthenticationResult' => 'application/security/AuthenticationResult.php',
            'AdminAuthorizationService' => 'application/security/AdminAuthorizationService.php',
            'LogoutAdminUserService' => 'application/security/LogoutAdminUserService.php',
            'ActivatePluginService' => 'application/content/ActivatePluginService.php',
            'DeactivatePluginService' => 'application/content/DeactivatePluginService.php',
            'TogglePluginService' => 'application/content/TogglePluginService.php',
            'UninstallPluginService' => 'application/content/UninstallPluginService.php',
            'ThemeRepository' => 'infrastructure/persistence/ThemeRepository.php',
            'ThemeSettingsRepository' => 'infrastructure/persistence/ThemeSettingsRepository.php',
            'AdminUserRepository' => 'infrastructure/persistence/AdminUserRepository.php',
            'AdminRoleRepository' => 'infrastructure/persistence/AdminRoleRepository.php',
            'PluginFilesystem' => 'infrastructure/filesystem/PluginFilesystem.php',
            'PluginCacheManager' => 'infrastructure/cache/PluginCacheManager.php',
            'PluginRepository' => 'infrastructure/persistence/PluginRepository.php',
            'LoginPage' => 'interface/admin-ui/pages/LoginPage.php',
            'LogoutPage' => 'interface/admin-ui/pages/LogoutPage.php',
            'DashboardPage' => 'interface/admin-ui/pages/DashboardPage.php',
            'SettingsPage' => 'interface/admin-ui/pages/SettingsPage.php',
            'SiteSettingsPage' => 'interface/admin-ui/pages/SiteSettingsPage.php',
            'ProfilePage' => 'interface/admin-ui/pages/ProfilePage.php',
            'PluginsPage' => 'interface/admin-ui/pages/PluginsPage.php',
            'ThemesPage' => 'interface/admin-ui/pages/ThemesPage.php',
            'RolesPage' => 'interface/admin-ui/pages/RolesPage.php',
            'UsersPage' => 'interface/admin-ui/pages/UsersPage.php',
            'CacheClearPage' => 'interface/admin-ui/pages/CacheClearPage.php',
            // Removed: CacheViewPage - now in plugins/cache-view
            // Removed: LogsViewPage - now in plugins/logs-view
            // Removed: CustomizerPage - now in plugins/theme-customizer
            // Removed: StorageManagementPage - now in plugins/storage-management
            // Removed: DevelopmentPage - deleted
            'TestService' => 'core/system/TestService.php',
            'MigrationRunner' => 'core/system/MigrationRunner.php',
            'Facade' => 'core/support/facades/Facade.php',
            'App' => 'core/support/facades/App.php',
            'Hooks' => 'core/support/facades/Hooks.php',
            'Plugin' => 'core/support/facades/Plugin.php',
            'Theme' => 'core/support/facades/Theme.php',
            'Role' => 'core/support/facades/Role.php',
            'Log' => 'core/support/facades/Log.php',
            'SessionFacade' => 'core/support/facades/Session.php',
            'CookieFacade' => 'core/support/facades/Cookie.php',
            'CacheFacade' => 'core/support/facades/Cache.php',
        ];
    }

    /**
     * Отримання списку директорій для автозавантажувача
     *
     * @return array<string>
     */
    protected function getDirectories(): array
    {
        return [
            $this->rootDir . '/core/',
            $this->rootDir . '/core/bootstrap/',
            $this->rootDir . '/core/providers/',
            $this->rootDir . '/core/contracts/',
            $this->rootDir . '/core/system/',
            $this->rootDir . '/core/support/',
            $this->rootDir . '/core/support/base/',
            $this->rootDir . '/core/support/helpers/',
            $this->rootDir . '/core/support/managers/',
            $this->rootDir . '/core/support/validation/',
            $this->rootDir . '/core/support/facades/',
            $this->rootDir . '/core/support/security/',
            $this->rootDir . '/domain/',
            $this->rootDir . '/domain/content/',
            $this->rootDir . '/domain/shared/',
            $this->rootDir . '/application/',
            $this->rootDir . '/application/content/',
            $this->rootDir . '/application/security/',
            $this->rootDir . '/application/testing/',
            $this->rootDir . '/infrastructure/',
            $this->rootDir . '/infrastructure/persistence/',
            $this->rootDir . '/infrastructure/cache/',
            $this->rootDir . '/infrastructure/config/',
            $this->rootDir . '/infrastructure/logging/',
            $this->rootDir . '/infrastructure/filesystem/',
            $this->rootDir . '/infrastructure/filesystem/contracts/',
            $this->rootDir . '/infrastructure/security/',
            $this->rootDir . '/infrastructure/mail/',
            $this->rootDir . '/infrastructure/compilers/',
            $this->rootDir . '/interface/',
            $this->rootDir . '/interface/http/',
            $this->rootDir . '/interface/http/contracts/',
            $this->rootDir . '/interface/http/controllers/',
            $this->rootDir . '/interface/http/middleware/',
            $this->rootDir . '/interface/http/router/',
            $this->rootDir . '/interface/admin-ui/',
            $this->rootDir . '/interface/admin-ui/pages/',
            $this->rootDir . '/interface/admin-ui/includes/',
            $this->rootDir . '/interface/admin-ui/components/',
            $this->rootDir . '/interface/admin-ui/layouts/',
            $this->rootDir . '/interface/admin-ui/assets/',
            $this->rootDir . '/interface/ui/',
            $this->rootDir . '/interface/templates/',
        ];
    }
}
