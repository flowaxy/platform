<?php

/**
 * Завантажувач системних модулів
 *
 * @package Engine\Classes
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class ModuleLoader
{
    private static array $loadedModules = [];
    private static array $moduleDirs = [];
    private static bool $initialized = false;

    /**
     * Ініціалізація завантажувача
     * Завантажує тільки критично важливі модулі, інші завантажуються за вимогою
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return; // Вже ініціалізовано
        }

        // Встановлюємо директорії модулів (старе та нове розташування)
        $engineRoot = dirname(__DIR__, 2);
        self::$moduleDirs = [
            $engineRoot . '/Support/Managers/',
        ];

        // Завантажуємо тільки критично важливі модулі, які потрібні для роботи системи
        $criticalModules = ['PluginManager', 'ThemeManager', 'SettingsManager']; // Модулі, які потрібно завантажити одразу

        foreach ($criticalModules as $moduleName) {
            self::loadModule($moduleName);
        }

        self::$initialized = true;
    }

    /**
     * Ліниве завантаження модуля за вимогою
     *
     * @param string $moduleName Ім'я модуля
     * @return BaseModule|null
     */
    public static function loadModule(string $moduleName): ?BaseModule
    {
        // Якщо модуль вже завантажено, повертаємо його
        if (isset(self::$loadedModules[$moduleName])) {
            return self::$loadedModules[$moduleName];
        }

        // Пропускаємо службові файли та класи, які не є модулями
        if ($moduleName === 'loader' || $moduleName === 'compatibility' || $moduleName === 'Config') {
            return null;
        }

        // Перевіряємо, що директорія модулів визначена
        if (empty(self::$moduleDirs)) {
            $engineRoot = dirname(__DIR__, 2);
            self::$moduleDirs = [
                $engineRoot . '/Support/Managers/',
            ];
        }

        $moduleFile = null;
        foreach (self::$moduleDirs as $dir) {
            $candidate = $dir . $moduleName . '.php';
            if (file_exists($candidate)) {
                $moduleFile = $candidate;

                break;
            }
        }

        // Перевіряємо існування файлу модуля
        if ($moduleFile === null) {
            $paths = array_map(fn ($dir) => $dir . $moduleName . '.php', self::$moduleDirs);
            if (function_exists('logWarning')) {
                logWarning('ModuleLoader::loadModule: Module file not found', [
                    'module' => $moduleName,
                    'paths' => $paths,
                ]);
            } else {
                error_log('Module file not found: ' . implode(' | ', $paths));
            }

            return null;
        }

        // Завантажуємо модуль
        return self::loadModuleFile($moduleFile, $moduleName);
    }

    /**
     * Завантаження всіх модулів (для сумісності та відлагодження)
     */
    private static function loadAllModules(): void
    {
        foreach (self::$moduleDirs as $dir) {
            $modules = glob($dir . '/*.php');

            if ($modules === false) {
                continue;
            }

            foreach ($modules as $moduleFile) {
                $moduleName = basename($moduleFile, '.php');

                // Пропускаємо службові файли та вже завантажені модулі
                if ($moduleName === 'loader' || $moduleName === 'compatibility' || isset(self::$loadedModules[$moduleName])) {
                    continue;
                }

                self::loadModuleFile($moduleFile, $moduleName);
            }
        }
    }

    /**
     * Завантаження файлу модуля
     */
    private static function loadModuleFile(string $moduleFile, string $moduleName): ?BaseModule
    {
        try {
            // Переконуємося, що BaseModule завантажено (автозавантажувач має завантажити)
            if (! class_exists('BaseModule')) {
                $engineRoot = dirname(__DIR__, 2);
                $baseModuleFile = $engineRoot . '/Support/Base/BaseModule.php';
                if (file_exists($baseModuleFile)) {
                    require_once $baseModuleFile;
                }
            }

            // Переконуємося, що Cache завантажено, щоб функція cache_remember() була доступна
            if (! class_exists('Cache')) {
                $engineRoot = dirname(__DIR__, 2);
                $cacheFile = $engineRoot . '/Cache/Cache.php';
                if (file_exists($cacheFile)) {
                    require_once $cacheFile;
                }
            }

            require_once $moduleFile;

            // Перевіряємо, що клас існує
            if (! class_exists($moduleName)) {
                if (function_exists('logError')) {
                    logError('ModuleLoader::loadModuleFile: Module class not found after loading file', [
                        'module' => $moduleName,
                        'file' => $moduleFile,
                    ]);
                } else {
                    error_log("Module class {$moduleName} not found after loading file: {$moduleFile}");
                }

                return null;
            }

            $module = $moduleName::getInstance();

            // Перевіряємо, що модуль наслідується від BaseModule
            if (! ($module instanceof BaseModule)) {
                if (function_exists('logWarning')) {
                    logWarning('ModuleLoader::loadModuleFile: Module does not extend BaseModule, skipping', [
                        'module' => $moduleName,
                        'file' => $moduleFile,
                    ]);
                } else {
                    error_log("Module {$moduleName} does not extend BaseModule, skipping");
                }

                return null;
            }

            // Реєструємо хуки модуля
            if (method_exists($module, 'registerHooks')) {
                $module->registerHooks();
            }

            self::$loadedModules[$moduleName] = $module;

            if (function_exists('logInfo')) {
                logInfo('ModuleLoader::loadModuleFile: Module loaded successfully', [
                    'module' => $moduleName,
                    'file' => $moduleFile,
                ]);
            }

            // Логуємо завантаження модуля через хук
            hook_dispatch('module_loaded', $moduleName);

            return $module;
        } catch (Exception | Error $e) {
            if (function_exists('logError')) {
                logError('ModuleLoader::loadModuleFile: Error loading module', [
                    'module' => $moduleName,
                    'file' => $moduleFile,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            } else {
                error_log("Error loading module {$moduleName}: " . $e->getMessage());
            }
            hook_dispatch('module_error', [
                'module' => $moduleName,
                'message' => $e->getMessage(),
                'file' => $moduleFile,
            ]);

            return null;
        }
    }

    /**
     * Отримання завантаженого модуля
     *
     * @param string $moduleName Ім'я модуля
     * @return BaseModule|null
     */
    public static function getModule(string $moduleName): ?BaseModule
    {
        return self::$loadedModules[$moduleName] ?? null;
    }

    /**
     * Отримання списку всіх завантажених модулів
     *
     * @param bool $loadAll Якщо true, завантажує всі модулі (для відлагодження)
     * @return array<string, BaseModule>
     */
    public static function getLoadedModules(bool $loadAll = false): array
    {
        // Якщо запрошено завантаження всіх модулів (для відлагодження/адмінки)
        if ($loadAll && self::$initialized) {
            self::loadAllModules();
        }

        return self::$loadedModules;
    }

    /**
     * Перевірка, чи завантажено модуль
     *
     * @param string $moduleName Ім'я модуля
     * @return bool
     */
    public static function isModuleLoaded(string $moduleName): bool
    {
        return isset(self::$loadedModules[$moduleName]);
    }
}
