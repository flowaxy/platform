<?php

/**
 * CLI ядро системи Flowaxy CMS
 * Обробляє CLI команди
 *
 * @package Flowaxy\Core\System
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\System;

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
            'classmap' => [$this, 'runClassMap'],
            'cache:clear' => [$this, 'runCacheClear'],
            'plugin:list' => [$this, 'runPluginList'],
            'theme:list' => [$this, 'runThemeList'],
            'hooks:list' => [$this, 'runHooksList'],
            'make:model' => [$this, 'runMakeModel'],
            'make:controller' => [$this, 'runMakeController'],
            'make:plugin' => [$this, 'runMakePlugin'],
            'code:check' => [$this, 'runCodeCheck'],
            'code:analyze' => [$this, 'runCodeAnalyze'],
            'isolation:check' => [$this, 'runIsolationCheck'],
            'performance:test' => [$this, 'runPerformanceTest'],
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
        echo "  doctor       - Перевірка оточення та налаштувань\n";
        echo "  test         - Запуск тестів\n";
        echo "  classmap     - Генерація class map для оптимізації автозавантаження\n";
        echo "  cache:clear     - Очищення кешу\n";
        echo "  plugin:list     - Список плагінів\n";
        echo "  theme:list      - Список тем\n";
        echo "  hooks:list      - Список хуків\n";
        echo "  make:model        - Створити модель\n";
        echo "  make:controller   - Створити контролер\n";
        echo "  make:plugin       - Створити плагін\n";
        echo "  code:check        - Перевірка коду (синтаксис, стиль)\n";
        echo "  code:analyze      - Аналіз коду (статистика, складність)\n";
        echo "  isolation:check  - Перевірка ізоляції плагінів\n";
        echo "  performance:test - Тестування продуктивності\n";
        echo "  help         - Показати цю довідку\n\n";
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
        $parsedArgs = $this->parseArgs($args);
        $filter = $parsedArgs['filter'] ?? $parsedArgs[0] ?? null;
        $plugin = $parsedArgs['plugin'] ?? null;
        $list = isset($parsedArgs['list']) || isset($parsedArgs['l']);

        // Спробуємо використати TestService
        if (class_exists('TestService')) {
            $testService = new \TestService();

            if ($list) {
                $tests = $testService->listTests($filter);
                echo "Доступні тести:\n";
                foreach ($tests as $test) {
                    echo "  - {$test['class']}::{$test['method']}\n";
                }
                return;
            }

            if ($plugin !== null) {
                $results = $testService->runPluginTests($plugin);
            } else {
                $results = $testService->run($filter);
            }

            // Виводимо результати
            echo "Тестування\n";
            echo str_repeat("=", 80) . "\n\n";

            foreach ($results['tests'] as $test) {
                $status = $test['status'] === 'passed' ? '✓' : '✗';
                $time = round($test['time'], 4);
                echo "{$status} {$test['class']}::{$test['method']} ({$time}s)\n";

                if ($test['status'] !== 'passed' && isset($test['message'])) {
                    echo "    Помилка: {$test['message']}\n";
                }
            }

            echo "\n" . str_repeat("=", 80) . "\n";
            echo "Підсумок:\n";
            echo "  Всього: {$results['summary']['total']}\n";
            echo "  Пройдено: {$results['summary']['passed']}\n";
            echo "  Провалено: {$results['summary']['failed']}\n";
            echo "  Час: " . round($results['summary']['time'], 4) . "s\n";

            exit($results['success'] ? 0 : 1);
        }

        // Fallback до старого методу
        $testFile = $this->rootDir . '/application/testing/cli.php';
        if (file_exists($testFile)) {
            require $testFile;
        } else {
            echo "Файл тестів не знайдено: {$testFile}\n";
            exit(1);
        }
    }

    /**
     * Генерація class map
     */
    private function runClassMap(array $args): void
    {
        $classMapCommandFile = $this->rootDir . '/core/system/ClassMapCommand.php';
        if (!file_exists($classMapCommandFile)) {
            echo "Помилка: файл ClassMapCommand.php не знайдено\n";
            exit(1);
        }

        require_once $classMapCommandFile;

        if (!class_exists('ClassMapCommand')) {
            echo "Помилка: клас ClassMapCommand не знайдено\n";
            exit(1);
        }

        ClassMapCommand::run($args);
    }

    /**
     * Очищення кешу
     */
    private function runCacheClear(array $args): void
    {
        echo "Очищення кешу...\n";

        try {
            // Отримуємо екземпляр Cache через контейнер або напряму
            $cache = null;
            if ($this->container->has(\Flowaxy\Core\Infrastructure\Cache\Cache::class)) {
                $cache = $this->container->make(\Flowaxy\Core\Infrastructure\Cache\Cache::class);
            } elseif (class_exists(\Flowaxy\Core\Infrastructure\Cache\Cache::class)) {
                $cache = \Flowaxy\Core\Infrastructure\Cache\Cache::getInstance();
            } elseif (function_exists('cache')) {
                $cache = cache();
            }

            if ($cache === null) {
                echo "Помилка: не вдалося отримати екземпляр Cache\n";
                exit(1);
            }

            // Очищаємо кеш
            $result = $cache->clear();

            if ($result) {
                echo "✓ Кеш успішно очищено\n";
            } else {
                echo "✗ Помилка при очищенні кешу\n";
                exit(1);
            }
        } catch (\Exception $e) {
            echo "Помилка: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Список плагінів
     */
    private function runPluginList(array $args): void
    {
        echo "Список плагінів\n";
        echo "================\n\n";

        try {
            // Отримуємо PluginManager
            $pluginManager = null;
            if ($this->container->has('PluginManager')) {
                $pluginManager = $this->container->make('PluginManager');
            } elseif (class_exists('PluginManager')) {
                $pluginManager = new \PluginManager();
            }

            if ($pluginManager === null) {
                echo "Помилка: не вдалося отримати PluginManager\n";
                exit(1);
            }

            // Отримуємо всі плагіни
            $plugins = [];
            if (method_exists($pluginManager, 'getAllPlugins')) {
                $plugins = $pluginManager->getAllPlugins();
            } else {
                // Fallback: спробуємо отримати через рефлексію
                try {
                    $reflection = new \ReflectionClass($pluginManager);
                    if ($reflection->hasProperty('plugins')) {
                        $pluginsProperty = $reflection->getProperty('plugins');
                        $pluginsProperty->setAccessible(true);
                        $plugins = $pluginsProperty->getValue($pluginManager);
                    }
                } catch (\ReflectionException $e) {
                    // Ігноруємо помилки рефлексії
                }
            }

            if (empty($plugins)) {
                echo "Плагіни не знайдено\n";
                return;
            }

            // Виводимо список плагінів
            printf("%-30s %-15s %-10s %s\n", "Slug", "Версія", "Статус", "Назва");
            echo str_repeat("-", 80) . "\n";

            foreach ($plugins as $slug => $plugin) {
                $name = 'N/A';
                $version = 'N/A';
                $status = 'Неактивний';

                if (is_object($plugin)) {
                    if (method_exists($plugin, 'getName')) {
                        $name = $plugin->getName();
                    } elseif (method_exists($plugin, 'getInfo')) {
                        $info = $plugin->getInfo();
                        $name = $info['name'] ?? $info['title'] ?? 'N/A';
                    }

                    if (method_exists($plugin, 'getVersion')) {
                        $version = $plugin->getVersion();
                    } elseif (method_exists($plugin, 'getInfo')) {
                        $info = $plugin->getInfo();
                        $version = $info['version'] ?? 'N/A';
                    }

                    if (method_exists($plugin, 'isActive')) {
                        $status = $plugin->isActive() ? 'Активний' : 'Неактивний';
                    }
                } elseif (is_array($plugin)) {
                    $name = $plugin['name'] ?? $plugin['title'] ?? 'N/A';
                    $version = $plugin['version'] ?? 'N/A';
                    $isActive = $plugin['active'] ?? $plugin['is_active'] ?? false;
                    $status = $isActive ? 'Активний' : 'Неактивний';
                }

                printf("%-30s %-15s %-10s %s\n", $slug, $version, $status, $name);
            }
        } catch (\Exception $e) {
            echo "Помилка: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Список тем
     */
    private function runThemeList(array $args): void
    {
        echo "Список тем\n";
        echo "==========\n\n";

        try {
            // Отримуємо ThemeManager
            $themeManager = null;
            if ($this->container->has('ThemeManager')) {
                $themeManager = $this->container->make('ThemeManager');
            } elseif (class_exists('ThemeManager')) {
                $themeManager = new \ThemeManager();
            }

            if ($themeManager === null) {
                echo "Помилка: не вдалося отримати ThemeManager\n";
                exit(1);
            }

            // Отримуємо всі теми
            $themes = [];
            if (method_exists($themeManager, 'getAllThemes')) {
                $themes = $themeManager->getAllThemes();
            }

            if (empty($themes)) {
                echo "Теми не знайдено\n";
                return;
            }

            // Отримуємо активну тему
            $activeTheme = null;
            if (method_exists($themeManager, 'getActiveTheme')) {
                $activeThemeData = $themeManager->getActiveTheme();
                $activeTheme = $activeThemeData['slug'] ?? null;
            }

            // Виводимо список тем
            printf("%-30s %-15s %-10s %s\n", "Slug", "Версія", "Статус", "Назва");
            echo str_repeat("-", 80) . "\n";

            foreach ($themes as $slug => $theme) {
                $name = 'N/A';
                $version = 'N/A';
                $status = ($slug === $activeTheme) ? 'Активна' : 'Неактивна';

                if (is_array($theme)) {
                    $name = $theme['name'] ?? $theme['title'] ?? 'N/A';
                    $version = $theme['version'] ?? 'N/A';
                } elseif (is_object($theme)) {
                    if (method_exists($theme, 'getName')) {
                        $name = $theme->getName();
                    }
                    if (method_exists($theme, 'getVersion')) {
                        $version = $theme->getVersion();
                    }
                }

                printf("%-30s %-15s %-10s %s\n", $slug, $version, $status, $name);
            }
        } catch (\Exception $e) {
            echo "Помилка: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Список хуків
     */
    private function runHooksList(array $args): void
    {
        echo "Список хуків\n";
        echo "============\n\n";

        try {
            // Отримуємо HookManager
            $hookManager = null;
            if ($this->container->has(\Flowaxy\Core\Contracts\HookManagerInterface::class)) {
                $hookManager = $this->container->make(\Flowaxy\Core\Contracts\HookManagerInterface::class);
            } elseif (function_exists('hooks')) {
                $hookManager = hooks();
            } elseif (class_exists(\Flowaxy\Core\System\HookManager::class)) {
                $hookManager = new \Flowaxy\Core\System\HookManager();
            }

            if ($hookManager === null) {
                echo "Помилка: не вдалося отримати HookManager\n";
                exit(1);
            }

            // Отримуємо всі хуки
            $hooks = [];
            if (method_exists($hookManager, 'getAllHooks')) {
                $hooks = $hookManager->getAllHooks();
            }

            if (empty($hooks)) {
                echo "Хуки не знайдено\n";
                return;
            }

            // Отримуємо статистику
            $stats = [];
            if (method_exists($hookManager, 'getStats')) {
                $stats = $hookManager->getStats();
            }

            // Виводимо статистику
            if (!empty($stats)) {
                echo "Статистика:\n";
                echo "  Всього хуків: " . ($stats['total_hooks'] ?? count($hooks)) . "\n";
                if (isset($stats['hook_calls']) && is_array($stats['hook_calls'])) {
                    $totalCalls = array_sum($stats['hook_calls']);
                    echo "  Всього викликів: {$totalCalls}\n";
                }
                echo "\n";
            }

            // Виводимо список хуків
            printf("%-40s %-10s %-8s %s\n", "Назва хука", "Тип", "Пріоритет", "Слухачів");
            echo str_repeat("-", 80) . "\n";

            foreach ($hooks as $hookName => $listeners) {
                $type = 'N/A';
                $priority = 'N/A';
                $listenerCount = count($listeners);

                if (!empty($listeners)) {
                    $firstListener = $listeners[0];
                    if (is_array($firstListener)) {
                        $type = $firstListener['type'] ?? 'N/A';
                        $priority = $firstListener['priority'] ?? 'N/A';
                    }
                }

                printf("%-40s %-10s %-8s %d\n", $hookName, $type, $priority, $listenerCount);
            }
        } catch (\Exception $e) {
            echo "Помилка: " . $e->getMessage() . "\n";
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
            'BaseModule' => 'Support/Base/BaseModule.php',
            'BasePlugin' => 'Support/Base/BasePlugin.php',
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
            'Cache' => 'Cache/Cache.php',
            'Database' => 'Database/Database.php',
            'Logger' => 'infrastructure/logging/Logger.php',
            'Config' => 'infrastructure/config/Config.php',
            'SystemConfig' => 'infrastructure/config/SystemConfig.php',
            'EnvironmentLoader' => 'core/system/EnvironmentLoader.php',
            'UrlHelper' => 'Support/Helpers/UrlHelper.php',
            'DatabaseHelper' => 'Support/Helpers/DatabaseHelper.php',
            'SecurityHelper' => 'Support/Helpers/SecurityHelper.php',
            'ScssCompiler' => 'infrastructure/compilers/ScssCompiler.php',
            'Validator' => 'Support/Validation/Validator.php',
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
            'CookieManager' => 'Support/Managers/CookieManager.php',
            'SessionManager' => 'Support/Managers/SessionManager.php',
            'StorageManager' => 'Support/Managers/StorageManager.php',
            'StorageFactory' => 'Support/Managers/StorageFactory.php',
            'ThemeManager' => 'Support/Managers/ThemeManager.php',
            'RoleManager' => 'Support/Managers/RoleManager.php',
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
            'HookType' => 'Hooks/HookType.php',
            'HookDefinition' => 'Hooks/HookDefinition.php',
            'HookListener' => 'Hooks/HookListener.php',
            'ComponentRegistry' => 'core/system/ComponentRegistry.php',
            'PluginModuleServiceProvider' => 'core/providers/PluginModuleServiceProvider.php',
            'SettingsManager' => 'Support/Managers/SettingsManager.php',
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
            'ThemeRepository' => 'Database/ThemeRepository.php',
            'ThemeSettingsRepository' => 'Database/ThemeSettingsRepository.php',
            'AdminUserRepository' => 'Database/AdminUserRepository.php',
            'AdminRoleRepository' => 'Database/AdminRoleRepository.php',
            'PluginFilesystem' => 'infrastructure/filesystem/PluginFilesystem.php',
            'PluginCacheManager' => 'Cache/PluginCacheManager.php',
            'PluginRepository' => 'Database/PluginRepository.php',
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
            'Facade' => 'Support/Facades/Facade.php',
            'App' => 'Support/Facades/App.php',
            'Hooks' => 'Support/Facades/Hooks.php',
            'Plugin' => 'Support/Facades/Plugin.php',
            'Theme' => 'Support/Facades/Theme.php',
            'Role' => 'Support/Facades/Role.php',
            'Log' => 'Support/Facades/Log.php',
            'SessionFacade' => 'Support/Facades/Session.php',
            'CookieFacade' => 'Support/Facades/Cookie.php',
            'CacheFacade' => 'Support/Facades/Cache.php',
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
            $this->rootDir . '/Contracts/',
            $this->rootDir . '/core/system/',
            $this->rootDir . '/Support/',
            $this->rootDir . '/Support/Base/',
            $this->rootDir . '/Support/Helpers/',
            $this->rootDir . '/Support/Managers/',
            $this->rootDir . '/Support/Validation/',
            $this->rootDir . '/Support/Facades/',
            $this->rootDir . '/Support/Security/',
            $this->rootDir . '/domain/',
            $this->rootDir . '/domain/content/',
            $this->rootDir . '/domain/shared/',
            $this->rootDir . '/application/',
            $this->rootDir . '/application/content/',
            $this->rootDir . '/application/security/',
            $this->rootDir . '/application/testing/',
            $this->rootDir . '/infrastructure/',
            $this->rootDir . '/Database/',
            $this->rootDir . '/Cache/',
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

    /**
     * Генерація моделі
     */
    private function runMakeModel(array $args): void
    {
        require_once __DIR__ . '/commands/MakeCommand.php';
        require_once __DIR__ . '/commands/MakeModelCommand.php';

        $command = new \Flowaxy\Core\System\Commands\MakeModelCommand($this->rootDir);

        // Парсимо аргументи
        $parsedArgs = $this->parseArgs($args);
        $command->run($parsedArgs);
    }

    /**
     * Генерація контролера
     */
    private function runMakeController(array $args): void
    {
        require_once __DIR__ . '/commands/MakeCommand.php';
        require_once __DIR__ . '/commands/MakeControllerCommand.php';

        $command = new \Flowaxy\Core\System\Commands\MakeControllerCommand($this->rootDir);

        // Парсимо аргументи
        $parsedArgs = $this->parseArgs($args);
        $command->run($parsedArgs);
    }

    /**
     * Генерація плагіна
     */
    private function runMakePlugin(array $args): void
    {
        require_once __DIR__ . '/commands/MakeCommand.php';
        require_once __DIR__ . '/commands/MakePluginCommand.php';

        $command = new \Flowaxy\Core\System\Commands\MakePluginCommand($this->rootDir);

        // Парсимо аргументи
        $parsedArgs = $this->parseArgs($args);
        $command->run($parsedArgs);
    }

    /**
     * Парсинг аргументів командного рядка
     *
     * @param array $args
     * @return array
     */
    private function parseArgs(array $args): array
    {
        $parsed = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                // Опція --key=value або --key
                $arg = substr($arg, 2);
                if (str_contains($arg, '=')) {
                    [$key, $value] = explode('=', $arg, 2);
                    $parsed[$key] = $value;
                } else {
                    $parsed[$arg] = true;
                }
            } else {
                // Позиційний аргумент
                $parsed[] = $arg;
            }
        }

        return $parsed;
    }

    /**
     * Перевірка коду
     */
    private function runCodeCheck(array $args): void
    {
        require_once __DIR__ . '/commands/MakeCommand.php';
        require_once __DIR__ . '/commands/CodeCheckCommand.php';

        $command = new \Flowaxy\Core\System\Commands\CodeCheckCommand($this->rootDir);
        $parsedArgs = $this->parseArgs($args);
        $command->run($parsedArgs);
    }

    /**
     * Аналіз коду
     */
    private function runCodeAnalyze(array $args): void
    {
        require_once __DIR__ . '/commands/MakeCommand.php';
        require_once __DIR__ . '/commands/CodeAnalyzeCommand.php';

        $command = new \Flowaxy\Core\System\Commands\CodeAnalyzeCommand($this->rootDir);
        $parsedArgs = $this->parseArgs($args);
        $command->run($parsedArgs);
    }

    /**
     * Перевірка ізоляції
     */
    private function runIsolationCheck(array $args): void
    {
        require_once __DIR__ . '/commands/MakeCommand.php';
        require_once __DIR__ . '/commands/IsolationCheckCommand.php';

        $command = new \Flowaxy\Core\System\Commands\IsolationCheckCommand($this->rootDir);
        $parsedArgs = $this->parseArgs($args);
        $command->run($parsedArgs);
    }

    /**
     * Тестування продуктивності
     */
    private function runPerformanceTest(array $args): void
    {
        require_once __DIR__ . '/commands/MakeCommand.php';
        require_once __DIR__ . '/commands/PerformanceTestCommand.php';

        $command = new \Flowaxy\Core\System\Commands\PerformanceTestCommand($this->rootDir);
        $parsedArgs = $this->parseArgs($args);
        $command->run($parsedArgs);
    }
}
