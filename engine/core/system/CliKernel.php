<?php

/**
 * CLI ядро системи Flowaxy CMS
 * Обробляє CLI команди
 *
 * @package Engine\Core\System
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/Kernel.php';

final class CliKernel extends Kernel
{
    private array $argv = [];
    private array $commands = [];

    /**
     * @param string $rootDir Корінь директорії engine/
     * @param array $argv Аргументи командного рядка
     */
    public function __construct(string $rootDir, array $argv = [])
    {
        parent::__construct($rootDir);
        $this->argv = $argv;
        $this->registerCommands();
    }

    /**
     * Отримання списку сервіс-провайдерів
     */
    protected function getServiceProviders(): array
    {
        return [
            CoreServiceProvider::class,
            // Для CLI можна додати інші провайдери за потреби
        ];
    }

    /**
     * Обробка CLI запиту
     */
    public function serve(): void
    {
        if (! $this->isBooted()) {
            $this->boot();
            $this->configure();
            $this->registerProviders();
            $this->bootProviders();
        }

        $command = $this->argv[1] ?? 'help';
        $args = array_slice($this->argv, 2);

        if (! isset($this->commands[$command])) {
            echo "Невідома команда: {$command}\n\n";
            $this->showHelp();
            exit(1);
        }

        $handler = $this->commands[$command];
        if (is_callable($handler)) {
            $handler($args);
        } else {
            echo "Помилка: обробник для команди '{$command}' не знайдено\n";
            exit(1);
        }
    }

    /**
     * Реєстрація доступних команд
     */
    private function registerCommands(): void
    {
        $this->commands = [
            'help' => [$this, 'showHelp'],
            'doctor' => [$this, 'runDoctor'],
            'test' => [$this, 'runTests'],
        ];
    }

    /**
     * Показати довідку
     */
    private function showHelp(): void
    {
        echo "Flowaxy CMS CLI\n";
        echo "===============\n\n";
        echo "Доступні команди:\n";
        echo "  doctor     - Перевірка оточення та налаштувань\n";
        echo "  test       - Запуск тестів\n";
        echo "  help       - Показати цю довідку\n\n";
    }

    /**
     * Команда doctor - перевірка оточення
     */
    private function runDoctor(array $args): void
    {
        echo "Flowaxy CMS - Doctor\n";
        echo "====================\n\n";

        $checks = [
            'PHP Version' => $this->checkPhpVersion(),
            'Required Extensions' => $this->checkExtensions(),
            'Directories' => $this->checkDirectories(),
            'Database Config' => $this->checkDatabaseConfig(),
        ];

        foreach ($checks as $name => $result) {
            echo "{$name}: " . ($result ? '✓ OK' : '✗ FAIL') . "\n";
        }

        echo "\n";
    }

    /**
     * Перевірка версії PHP
     */
    private function checkPhpVersion(): bool
    {
        $required = '8.4.0';
        $current = PHP_VERSION;
        $isOk = version_compare($current, $required, '>=');

        echo "  PHP {$current} (потрібно: {$required})\n";

        return $isOk;
    }

    /**
     * Перевірка необхідних розширень
     */
    private function checkExtensions(): bool
    {
        $required = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'xml', 'curl'];
        $allOk = true;

        foreach ($required as $ext) {
            $loaded = extension_loaded($ext);
            echo "  {$ext}: " . ($loaded ? '✓' : '✗') . "\n";
            if (! $loaded) {
                $allOk = false;
            }
        }

        return $allOk;
    }

    /**
     * Перевірка директорій
     */
    private function checkDirectories(): bool
    {
        $dirs = [
            'storage/cache' => dirname($this->rootDir) . '/storage/cache',
            'storage/logs' => dirname($this->rootDir) . '/storage/logs',
            'storage/config' => dirname($this->rootDir) . '/storage/config',
            'uploads' => dirname($this->rootDir) . '/uploads',
        ];

        $allOk = true;
        foreach ($dirs as $name => $path) {
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            echo "  {$name}: " . ($exists ? '✓' : '✗') . ' ' . ($writable ? '(writable)' : '(not writable)') . "\n";
            if (! $exists || ! $writable) {
                $allOk = false;
            }
        }

        return $allOk;
    }

    /**
     * Перевірка конфігурації БД
     */
    private function checkDatabaseConfig(): bool
    {
        $configFile = dirname($this->rootDir) . '/storage/config/database.ini';
        $exists = file_exists($configFile);
        echo '  database.ini: ' . ($exists ? '✓' : '✗') . "\n";

        return $exists;
    }

    /**
     * Запуск тестів
     */
    private function runTests(array $args): void
    {
        $testFile = $this->rootDir . '/application/testing/cli.php';
        if (file_exists($testFile)) {
            require $testFile;
        } else {
            echo "Файл тестів не знайдено: {$testFile}\n";
            exit(1);
        }
    }

    /**
     * Отримання class map
     * Використовуємо той самий class map, що й HttpKernel
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
            'RoleManager' => 'core/support/managers/RoleManager.php',
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
     * Отримання директорій (такі ж, як у HttpKernel)
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
