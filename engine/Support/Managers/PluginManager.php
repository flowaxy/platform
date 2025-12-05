<?php

/**
 * Менеджер плагінів системи
 *
 * @package Engine\Managers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../../Hooks/HookType.php';

use Flowaxy\Core\System\Hooks\HookType;
use Flowaxy\Core\Contracts\HookManagerInterface;

class PluginManager extends BaseModule
{
    /**
     * @var array<string, Plugin>
     */
    private array $plugins = [];

    /**
     * @var array<string, array<int, mixed>>
     */
    private array $hooks = [];
    private HookManagerInterface $hookManager;
    private string $pluginsDir = '';
    private ?PluginLifecycleInterface $lifecycle = null;

    protected function init(): void
    {
        $rootDir = $this->getProjectRoot();
        $this->pluginsDir = $rootDir . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR;
        $this->hookManager = hooks();
    }

    public function registerHooks(): void
    {
    }

    /**
     * Отримання інформації про модуль
     *
     * @return array<string, string>
     */
    public function getInfo(): array
    {
        return [
            'name' => 'PluginManager',
            'title' => 'Менеджер плагінів',
            'description' => 'Управління плагінами системи',
            'version' => '1.0.0 Alpha prerelease',
            'author' => 'Flowaxy CMS',
        ];
    }

    /**
     * Отримання API методів модуля
     *
     * @return array<string, string>
     */
    public function getApiMethods(): array
    {
        return [
            'getAllPlugins' => 'Отримання всіх плагінів',
            'getActivePlugins' => 'Отримання активних плагінів',
            'getPlugin' => 'Отримання плагіна за slug',
            'installPlugin' => 'Встановлення плагіна',
            'activatePlugin' => 'Активація плагіна',
            'deactivatePlugin' => 'Деактивація плагіна',
            'uninstallPlugin' => 'Видалення плагіна',
            'addHook' => 'Додавання хука',
            'prepareHook' => 'Підготовка даних перед викликом хука',
            'hasHook' => 'Перевірка існування хука',
            'autoDiscoverPlugins' => 'Автоматичне виявлення плагінів',
        ];
    }

    public function initializePlugins(): void
    {
        static $initialized = false;

        if ($initialized) {
            return;
        }

        $this->loadPlugins('handle_early_request');

        foreach ($this->plugins as $slug => $plugin) {
            static $initializedPlugins = [];
            if (isset($initializedPlugins[$slug])) {
                continue;
            }

            // Plugin завжди має метод init, але перевіряємо для безпеки
            try {
                if ($plugin instanceof BasePlugin) {
                    $plugin->init();
                    $initializedPlugins[$slug] = true;
                }
            } catch (Exception $e) {
                if (function_exists('logError')) {
                    logError("Plugin init error", ['slug' => $slug, 'error' => $e->getMessage(), 'exception' => $e]);
                } else {
                    logger()->logError("Plugin init error for {$slug}: " . $e->getMessage(), ['exception' => $e]);
                }
            }
        }

        $initialized = true;
    }

    private function loadPlugins(?string $forHook = null): void
    {
        static $pluginsLoaded = false;
        static $hooksChecked = [];

        if ($pluginsLoaded) {
            return;
        }

        if ($forHook && ! in_array($forHook, ['admin_menu', 'admin_register_routes', 'handle_early_request'])) {
            return;
        }

        if ($forHook && isset($hooksChecked[$forHook])) {
            return;
        }

        $db = $this->getDB();
        if (! $db) {
            return;
        }

        try {
            $cacheKey = 'active_plugins_list';

            if (function_exists('cache_remember')) {
                $pluginData = cache_remember($cacheKey, function () use ($db) {
                    $stmt = $db->query('SELECT slug FROM plugins WHERE is_active = 1');
                    if ($stmt === false) {
                        return [];
                    }

                    return $stmt->fetchAll(PDO::FETCH_COLUMN);
                }, 300);
            } else {
                $stmt = $db->query('SELECT slug FROM plugins WHERE is_active = 1');
                if ($stmt === false) {
                    $pluginData = [];
                } else {
                    $pluginData = $stmt->fetchAll(PDO::FETCH_COLUMN);
                }
            }

            foreach ($pluginData as $slug) {
                if (! isset($this->plugins[$slug])) {
                    $this->loadPluginFull($slug);
                }
            }

            $pluginsLoaded = true;
            if ($forHook) {
                $hooksChecked[$forHook] = true;
            }

            // DEBUG: логуємо завантажені плагіни
            if (!empty($pluginData)) {
                if (function_exists('logDebug')) {
                    logDebug('PluginManager: Plugins loaded', [
                        'count' => count($pluginData),
                        'plugins' => $pluginData,
                    ]);
                } else {
                    logger()->logDebug('Плагіни завантажено', [
                        'count' => count($pluginData),
                        'plugins' => $pluginData,
                    ]);
                }
            }
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('PluginManager: Error loading plugins', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('Error loading plugins: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    private function loadPluginFull(string $slug): void
    {
        if (isset($this->plugins[$slug])) {
            return;
        }

        $db = $this->getDB();
        if (! $db) {
            return;
        }

        try {
            $cacheKey = 'plugin_data_' . $slug;

            if (function_exists('cache_remember')) {
                $pluginData = cache_remember($cacheKey, function () use ($db, $slug) {
                    $stmt = $db->prepare('SELECT * FROM plugins WHERE slug = ? AND is_active = 1');
                    $stmt->execute([$slug]);

                    return $stmt->fetch(PDO::FETCH_ASSOC);
                }, 300);
            } else {
                $stmt = $db->prepare('SELECT * FROM plugins WHERE slug = ? AND is_active = 1');
                $stmt->execute([$slug]);
                $pluginData = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($pluginData) {
                $this->loadPlugin($pluginData);
            }
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError("PluginManager: Error loading plugin full data", ['slug' => $slug, 'error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError("Error loading plugin full data for {$slug}: " . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    /**
     * Отримання імені класу плагіна з slug
     */
    private function getPluginClassName(string $pluginSlug): string
    {
        $parts = explode('-', $pluginSlug);
        $className = '';
        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }

        return $className . 'Plugin';
    }

    private function loadPlugin(array $pluginData): void
    {
        $slug = $pluginData['slug'] ?? '';
        if (empty($slug) || isset($this->plugins[$slug])) {
            return;
        }

        // Використовуємо оптимізований PluginLoader, якщо доступний
        if (class_exists('Flowaxy\Core\Support\Managers\PluginLoader')) {
            try {
                // Ініціалізуємо PluginLoader, якщо ще не ініціалізований
                \Flowaxy\Core\Support\Managers\PluginLoader::initialize($this->pluginsDir);

                $plugin = \Flowaxy\Core\Support\Managers\PluginLoader::load($slug, $pluginData);
                if ($plugin !== null) {
                    $this->plugins[$slug] = $plugin;
                    return;
                }
            } catch (\Exception $e) {
                // Fallback до старої реалізації
                if (function_exists('logger')) {
                    if (function_exists('logError')) {
                        logError("PluginManager: Error loading plugin with PluginLoader", ['slug' => $slug, 'error' => $e->getMessage()]);
                    } else {
                        logger()->logError("Error loading plugin with PluginLoader {$slug}: " . $e->getMessage());
                    }
                }
            }
        }

        // Fallback: стара реалізація для зворотної сумісності
        $pluginPath = $this->getPluginPath($slug);
        if (! file_exists($pluginPath) || ! is_readable($pluginPath)) {
            return;
        }

        try {
            require_once $pluginPath;

            $className = $this->getPluginClassName($slug);
            if (class_exists($className)) {
                /**
                 * @var Plugin $plugin
                 */
                $plugin = new $className();
                $this->plugins[$slug] = $plugin;
            }
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError("PluginManager: Error loading plugin", ['slug' => $slug, 'error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError("Error loading plugin {$slug}: " . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    /**
     * Отримання шляху до файлу плагіна
     * Спочатку пробуємо init.php, потім старий формат {ClassName}.php для зворотної сумісності
     */
    private function getPluginPath(string $pluginSlug): string
    {
        $initPath = $this->pluginsDir . $pluginSlug . '/init.php';
        if (file_exists($initPath)) {
            return $initPath;
        }

        // Зворотна сумісність: старий формат {ClassName}.php
        $className = $this->getPluginClassName($pluginSlug);

        return $this->pluginsDir . $pluginSlug . '/' . $className . '.php';
    }

    /**
     * @return BasePlugin|object|null
     */
    private function getPluginInstance(string $pluginSlug)
    {
        if (isset($this->plugins[$pluginSlug])) {
            return $this->plugins[$pluginSlug];
        }

        $db = $this->getDB();
        if (! $db) {
            return null;
        }

        try {
            $stmt = $db->prepare('SELECT * FROM plugins WHERE slug = ?');
            $stmt->execute([$pluginSlug]);
            $pluginData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($pluginData) {
                if (function_exists('cache_forget')) {
                    cache_forget('plugin_data_' . $pluginSlug);
                }

                $this->loadPlugin($pluginData);

                return $this->plugins[$pluginSlug] ?? null;
            }
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError("PluginManager: Error loading plugin instance", ['plugin_slug' => $pluginSlug, 'error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError("Error loading plugin instance {$pluginSlug}: " . $e->getMessage(), ['exception' => $e]);
            }
        }

        return null;
    }

    public function addHook(string $hookName, callable $callback, int $priority = 10): void
    {
        $this->hookManager->filter($hookName, $callback, $priority);

        if (! isset($this->hooks[$hookName])) {
            $this->hooks[$hookName] = [];
        }
        $this->hooks[$hookName][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];
        usort($this->hooks[$hookName], fn ($a, $b) => $a['priority'] - $b['priority']);
    }

    public function prepareHook(string $hookName, HookType $type, mixed $payload = null): mixed
    {
        if (empty($hookName)) {
            return $payload;
        }

        if (($hookName === 'admin_menu' || $hookName === 'admin_register_routes') && class_exists('ModuleLoader')) {
            static $adminModulesChecked = false;
            if (! $adminModulesChecked) {
                $this->loadAdminModules(false);
                $adminModulesChecked = true;
            }
        }

        if (! $this->hookManager->has($hookName) && ! isset($this->hooks[$hookName])) {
            if ($hookName === 'admin_menu' || $hookName === 'admin_register_routes' || $hookName === 'handle_early_request') {
                $this->loadPlugins($hookName);
                $this->initializePlugins();
            } else {
                return $payload;
            }
        }

        if (! isset($this->hooks[$hookName])) {
            return $payload;
        }

        foreach ($this->hooks[$hookName] as $hook) {
            if (! is_callable($hook['callback'])) {
                continue;
            }

            try {
                if ($type === HookType::Action) {
                    $args = is_array($payload) ? $payload : [$payload];
                    call_user_func_array($hook['callback'], $args);
                } else {
                    $result = call_user_func($hook['callback'], $payload);
                    if ($result !== null) {
                        $payload = $result;
                    }
                }
            } catch (Exception $e) {
                if (function_exists('logError')) {
                    logError("PluginManager: Hook execution error", ['hook' => $hookName, 'error' => $e->getMessage(), 'exception' => $e]);
                } else {
                    logger()->logError("Hook execution error for '{$hookName}': " . $e->getMessage(), ['exception' => $e]);
                }
            }
        }

        return $payload;
    }

    private function loadAdminModules(bool $forceLoadAll = false): void
    {
        static $adminModulesLoaded = false;
        static $allModulesLoaded = false;

        if ($forceLoadAll) {
            if ($allModulesLoaded) {
                $this->ensureModulesHooksRegistered();

                return;
            }
        } else {
            if ($adminModulesLoaded) {
                return;
            }

            $loadedModules = ModuleLoader::getLoadedModules();
            if (count($loadedModules) > 1) {
                $adminModulesLoaded = true;

                return;
            }
        }

        static $modulesList = null;

        if ($modulesList === null) {
            $managersDir = dirname(__DIR__) . '/managers';
            $modules = glob($managersDir . '/*.php');
            $modulesList = [];

            if ($modules !== false) {
                foreach ($modules as $moduleFile) {
                    $moduleName = basename($moduleFile, '.php');

                    if ($moduleName === 'loader' ||
                        $moduleName === 'compatibility' ||
                        $moduleName === 'PluginManager') {
                        continue;
                    }

                    $modulesList[] = $moduleName;
                }
            }
        }

        foreach ($modulesList as $moduleName) {
            if (! ModuleLoader::isModuleLoaded($moduleName)) {
                ModuleLoader::loadModule($moduleName);
            }
        }

        $this->ensureModulesHooksRegistered();

        if ($forceLoadAll) {
            $allModulesLoaded = true;
        } else {
            $adminModulesLoaded = true;
        }
    }

    private function ensureModulesHooksRegistered(): void
    {
        if (! class_exists('ModuleLoader')) {
            return;
        }

        $loadedModules = ModuleLoader::getLoadedModules();
        foreach ($loadedModules as $moduleName => $module) {
            if (is_object($module) && method_exists($module, 'registerHooks')) {
                $needsRegistration = false;

                if (! isset($this->hooks['admin_menu']) || ! $this->hasModuleHook($moduleName, 'admin_menu')) {
                    $needsRegistration = true;
                }

                if (! isset($this->hooks['admin_register_routes']) || ! $this->hasModuleHook($moduleName, 'admin_register_routes')) {
                    $needsRegistration = true;
                }

                if ($needsRegistration) {
                    try {
                        $module->registerHooks();
                    } catch (Exception $e) {
                        if (function_exists('logError')) {
                            logError("PluginManager: Error registering hooks for module", ['module' => $moduleName, 'error' => $e->getMessage(), 'exception' => $e]);
                        } else {
                            logger()->logError("Error registering hooks for module {$moduleName}: " . $e->getMessage(), ['exception' => $e]);
                        }
                    }
                }
            }
        }
    }

    private function hasModuleHook(string $moduleName, string $hookName): bool
    {
        if (! isset($this->hooks[$hookName])) {
            return false;
        }

        foreach ($this->hooks[$hookName] as $hook) {
            if (is_array($hook['callback']) &&
                isset($hook['callback'][0]) &&
                is_object($hook['callback'][0])) {
                $objectClass = get_class($hook['callback'][0]);
                if ($objectClass === $moduleName || str_contains($objectClass, $moduleName)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hasHook(string $hookName): bool
    {
        return $this->hookManager->has($hookName) ||
               (! empty($hookName) && isset($this->hooks[$hookName]) && ! empty($this->hooks[$hookName]));
    }

    public function getHookManager(): HookManagerInterface
    {
        return $this->hookManager;
    }

    public function getAllPlugins(): array
    {
        $allPlugins = [];

        if (! is_dir($this->pluginsDir)) {
            return $allPlugins;
        }

        $directories = glob($this->pluginsDir . '*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $pluginSlug = basename($dir);
            $configFile = $dir . '/plugin.json';
            $json = new Json($configFile);
            if ($json->getFilePath() && file_exists($json->getFilePath())) {
                try {
                    $json->load(true);
                    $config = $json->getAll([]);

                    if (is_array($config) && ! empty($config)) {
                        if (empty($config['slug'])) {
                            $config['slug'] = $pluginSlug;
                        }

                        $pluginFile = new File($this->getPluginPath($pluginSlug));
                        $config['has_plugin_file'] = $pluginFile->exists();
                        $allPlugins[$pluginSlug] = $config;
                    } else {
                        if (function_exists('logWarning')) {
                            logWarning("PluginManager: Invalid JSON in plugin.json", ['plugin_slug' => $pluginSlug]);
                        } else {
                            logger()->logWarning("Invalid JSON in plugin.json for plugin: {$pluginSlug}");
                        }
                    }
                } catch (Exception $e) {
                    if (function_exists('logError')) {
                        logError("PluginManager: Cannot read plugin.json", ['plugin_slug' => $pluginSlug, 'error' => $e->getMessage(), 'exception' => $e]);
                    } else {
                        logger()->logError("Cannot read plugin.json for plugin {$pluginSlug}: " . $e->getMessage(), ['exception' => $e]);
                    }
                }
            }
        }

        return $allPlugins;
    }

    public function autoDiscoverPlugins(): int
    {
        // Кешуємо результат пошуку плагінів
        $cacheKey = 'discovered_plugins_list';

        if (function_exists('cache_remember')) {
            $allPlugins = cache_remember($cacheKey, function () {
                return $this->getAllPlugins();
            }, 600); // Кешуємо на 10 хвилин
        } else {
            $allPlugins = $this->getAllPlugins();
        }

        $installedCount = 0;

        $db = $this->getDB();
        if (! $db) {
            return 0;
        }

        try {
            $checkStmt = $db->query("SHOW COLUMNS FROM plugins LIKE 'is_deleted'");
            if ($checkStmt && $checkStmt->rowCount() > 0) {
                $db->exec('ALTER TABLE plugins DROP COLUMN is_deleted');
            }
        } catch (Exception $e) {
        }

        // Оптимізація: використовуємо пакетну перевірку
        $slugs = array_keys($allPlugins);
        if (empty($slugs)) {
            return 0;
        }

        // Отримуємо всі встановлені плагіни одним запитом
        $placeholders = implode(',', array_fill(0, count($slugs), '?'));
        $stmt = $db->prepare("SELECT slug FROM plugins WHERE slug IN ({$placeholders})");
        $stmt->execute($slugs);
        $installedSlugs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Встановлюємо тільки нові плагіни
        foreach ($allPlugins as $slug => $config) {
            if (!in_array($slug, $installedSlugs, true)) {
                try {
                    if ($this->installPlugin($slug)) {
                        $installedCount++;
                    }
                } catch (Exception $e) {
                    if (function_exists('logError')) {
                        logError("PluginManager: Error installing plugin", ['slug' => $slug, 'error' => $e->getMessage(), 'exception' => $e]);
                    } else {
                        logger()->logError("Error installing plugin {$slug}: " . $e->getMessage(), ['exception' => $e]);
                    }
                }
            }
        }

        // Очищаємо кеш після встановлення
        if ($installedCount > 0 && function_exists('cache_forget')) {
            cache_forget($cacheKey);
        }

        return $installedCount;
    }

    public function getActivePlugins(): array
    {
        $db = $this->getDB();
        if (! $db) {
            return [];
        }

        try {
            $stmt = $db->query('SELECT slug FROM plugins WHERE is_active = 1');
            if ($stmt === false) {
                return [];
            }
            $activeSlugs = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $activePlugins = [];
            foreach ($activeSlugs as $slug) {
                if (isset($this->plugins[$slug])) {
                    $activePlugins[$slug] = $this->plugins[$slug];
                }
            }

            return $activePlugins;
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('PluginManager: Error getting active plugins', ['error' => $e->getMessage()]);
            } else {
                logger()->logError('Error getting active plugins', ['error' => $e->getMessage()]);
            }
            return [];
        }
    }

    public function installPlugin(string $pluginSlug): bool
    {
        try {
            return $this->getLifecycle()->install($pluginSlug);
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('PluginManager: Plugin installation error', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('Plugin installation error: ' . $e->getMessage(), ['exception' => $e]);
            }
            return false;
        }
    }

    public function uninstallPlugin(string $pluginSlug): bool
    {
        try {
            $plugin = $this->getPlugin($pluginSlug);
            if ($plugin instanceof BasePlugin && method_exists($plugin, 'uninstall')) {
                $plugin->uninstall();
            }

            $result = $this->getLifecycle()->uninstall($pluginSlug);

            if ($result) {
                $this->removePluginHooks($pluginSlug);
                unset($this->plugins[$pluginSlug]);

                return true;
            }
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('PluginManager: Plugin uninstallation error', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('Plugin uninstallation error: ' . $e->getMessage(), ['exception' => $e]);
            }
        }

        return false;
    }

    private function deletePluginDirectory(string $dir): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deletePluginDirectory($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }

    /**
     * Валідація залежностей плагіна перед активацією
     *
     * @param string $pluginSlug Слаг плагіна
     * @return array<string, string> Масив помилок (порожній якщо все ОК)
     */
    public function validatePluginDependencies(string $pluginSlug): array
    {
        $plugin = $this->getPluginInstance($pluginSlug);

        if (!$plugin instanceof BasePlugin) {
            return ['general' => 'Плагін не знайдено'];
        }

        return $plugin->getDependencyErrors();
    }

    /**
     * Перевірка сумісності плагіна з ядром
     *
     * @param string $pluginSlug Слаг плагіна
     * @return array<string, mixed> Результат перевірки сумісності
     */
    public function checkPluginCompatibility(string $pluginSlug): array
    {
        // Перевіряємо, чи доступний PluginCompatibilityChecker
        if (!class_exists('Flowaxy\Core\Support\Validators\PluginCompatibilityChecker')) {
            return [
                'compatible' => true,
                'errors' => [],
                'warnings' => ['Система перевірки сумісності недоступна'],
            ];
        }

        try {
            // Завантажуємо конфігурацію плагіна
            $pluginConfig = $this->getPluginConfig($pluginSlug);

            if (empty($pluginConfig)) {
                return [
                    'compatible' => false,
                    'errors' => ['Конфігурація плагіна не знайдена'],
                ];
            }

            // Виконуємо перевірку сумісності
            return \Flowaxy\Core\Support\Validators\PluginCompatibilityChecker::getCompatibilityReport($pluginConfig);
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                if (function_exists('logError')) {
                    logError("PluginManager: Error checking plugin compatibility", ['plugin_slug' => $pluginSlug, 'error' => $e->getMessage()]);
                } else {
                    logger()->logError("Error checking plugin compatibility for {$pluginSlug}: " . $e->getMessage());
                }
            }

            return [
                'compatible' => false,
                'errors' => ['Помилка перевірки сумісності: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Отримання конфігурації плагіна
     *
     * @param string $pluginSlug Слаг плагіна
     * @return array<string, mixed>
     */
    private function getPluginConfig(string $pluginSlug): array
    {
        $configFile = $this->pluginsDir . $pluginSlug . DIRECTORY_SEPARATOR . 'plugin.json';

        if (!file_exists($configFile)) {
            return [];
        }

        $configContent = file_get_contents($configFile);
        if ($configContent === false) {
            return [];
        }

        $config = json_decode($configContent, true);
        if (!is_array($config)) {
            return [];
        }

        return $config;
    }

    /**
     * Автоматичне встановлення залежностей плагіна
     *
     * @param string $pluginSlug Слаг плагіна
     * @return array<string, bool> Результати встановлення (ключ - slug залежності, значення - успіх)
     */
    public function installDependencies(string $pluginSlug): array
    {
        $plugin = $this->getPluginInstance($pluginSlug);

        if (!$plugin instanceof BasePlugin) {
            return [];
        }

        $dependencies = $plugin->getDependencies();
        $results = [];

        foreach ($dependencies as $dependency) {
            $depSlug = is_array($dependency) ? ($dependency['slug'] ?? '') : $dependency;

            if (empty($depSlug)) {
                continue;
            }

            // Перевіряємо, чи вже встановлений
            if ($this->isPluginInstalled($depSlug)) {
                $results[$depSlug] = true;
                continue;
            }

            // Спробуємо встановити
            try {
                $results[$depSlug] = $this->installPlugin($depSlug);

                // Якщо встановлено, активуємо
                if ($results[$depSlug]) {
                    $this->activatePlugin($depSlug);
                }
            } catch (Exception $e) {
                $results[$depSlug] = false;
                if (function_exists('logError')) {
                    logError("PluginManager: Failed to install dependency", ['plugin_slug' => $pluginSlug, 'dependency' => $depSlug, 'error' => $e->getMessage()]);
                } else {
                    logger()->logError("Failed to install dependency {$depSlug} for plugin {$pluginSlug}: " . $e->getMessage());
                }
            }
        }

        return $results;
    }

    public function activatePlugin(string $pluginSlug, bool $autoInstallDependencies = false): bool
    {
        try {
            // Перевірка сумісності з ядром перед активацією
            $compatibility = $this->checkPluginCompatibility($pluginSlug);
            if (!$compatibility['compatible']) {
                if (function_exists('logWarning')) {
                    logWarning("PluginManager: Cannot activate plugin - compatibility check failed", [
                        'plugin_slug' => $pluginSlug,
                        'errors' => $compatibility['errors'] ?? [],
                        'warnings' => $compatibility['warnings'] ?? [],
                    ]);
                } else {
                    logger()->logWarning("Cannot activate plugin {$pluginSlug}: compatibility check failed", [
                        'errors' => $compatibility['errors'] ?? [],
                        'warnings' => $compatibility['warnings'] ?? [],
                    ]);
                }
                return false;
            }

            // Валідація залежностей перед активацією
            $dependencyErrors = $this->validatePluginDependencies($pluginSlug);

            if (!empty($dependencyErrors)) {
                // Якщо дозволено автоматичне встановлення залежностей
                if ($autoInstallDependencies) {
                    $installResults = $this->installDependencies($pluginSlug);

                    // Перевіряємо, чи всі залежності встановлені
                    $dependencyErrors = $this->validatePluginDependencies($pluginSlug);
                }

                // Якщо все ще є помилки, не активуємо плагін
                if (!empty($dependencyErrors)) {
                    if (function_exists('logWarning')) {
                        logWarning("PluginManager: Cannot activate plugin - missing dependencies", [
                            'plugin_slug' => $pluginSlug,
                            'errors' => $dependencyErrors,
                        ]);
                    } else {
                        logger()->logWarning("Cannot activate plugin {$pluginSlug}: missing dependencies", [
                            'errors' => $dependencyErrors,
                        ]);
                    }
                    return false;
                }
            }

            $result = $this->getLifecycle()->activate($pluginSlug);
            if (! $result) {
                return false;
            }

            $plugin = $this->getPluginInstance($pluginSlug);

            if ($plugin instanceof BasePlugin) {
                if (method_exists($plugin, 'activate')) {
                    $plugin->activate();
                }

                if (method_exists($plugin, 'init')) {
                    try {
                        $plugin->init();
                    } catch (Exception $e) {
                        if (function_exists('logError')) {
                            logError("PluginManager: Plugin init error after activation", ['plugin_slug' => $pluginSlug, 'error' => $e->getMessage(), 'exception' => $e]);
                        } else {
                            logger()->logError("Plugin init error for {$pluginSlug} after activation: " . $e->getMessage(), ['exception' => $e]);
                        }
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('PluginManager: Plugin activation error', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('Plugin activation error: ' . $e->getMessage(), ['exception' => $e]);
            }

            return false;
        }
    }

    public function deactivatePlugin(string $pluginSlug): bool
    {
        try {
            $plugin = $this->getPluginInstance($pluginSlug);
            if ($plugin instanceof BasePlugin && method_exists($plugin, 'deactivate')) {
                $plugin->deactivate();
            }

            $result = $this->getLifecycle()->deactivate($pluginSlug);
            if (! $result) {
                return false;
            }

            $this->removePluginHooks($pluginSlug);

            if (isset($this->plugins[$pluginSlug])) {
                unset($this->plugins[$pluginSlug]);
            }

            return true;
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('PluginManager: Plugin deactivation error', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('Plugin deactivation error: ' . $e->getMessage(), ['exception' => $e]);
            }

            return false;
        }
    }

    /**
     * Перевірка активності плагіна
     */
    public function isPluginActive(string $pluginSlug): bool
    {
        return ! empty($pluginSlug) && isset($this->plugins[$pluginSlug]);
    }

    /**
     * Отримання конкретного плагіна
     *
     * @return BasePlugin|object|null
     */
    public function getPlugin(string $pluginSlug)
    {
        return $this->plugins[$pluginSlug] ?? $this->getPluginInstance($pluginSlug);
    }

    public function getPluginSetting(string $pluginSlug, string $settingKey, $default = null)
    {
        $db = $this->getDB();
        if (! $db) {
            return $default;
        }

        try {
            $stmt = $db->prepare('SELECT setting_value FROM plugin_settings WHERE plugin_slug = ? AND setting_key = ?');
            $stmt->execute([$pluginSlug, $settingKey]);
            $result = $stmt->fetch();

            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    public function setPluginSetting(string $pluginSlug, string $settingKey, $value): bool
    {
        $db = $this->getDB();
        if (! $db) {
            return false;
        }

        try {
            $stmt = $db->prepare('
                INSERT INTO plugin_settings (plugin_slug, setting_key, setting_value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ');

            return $stmt->execute([$pluginSlug, $settingKey, $value]);
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('PluginManager: Error setting plugin setting', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('Error setting plugin setting: ' . $e->getMessage(), ['exception' => $e]);
            }

            return false;
        }
    }

    private function removePluginHooks(string $pluginSlug): void
    {
        if (empty($pluginSlug)) {
            return;
        }

        $className = $this->getPluginClassName($pluginSlug);
        $allHooks = $this->hookManager->getAllHooks();

        foreach ($allHooks as $hookName => $hooks) {
            foreach ($hooks as $hook) {
                $callback = $hook['callback'] ?? null;
                if ($callback === null) {
                    continue;
                }

                if (is_array($callback)) {
                    if (isset($callback[0]) && is_object($callback[0])) {
                        $objectClass = get_class($callback[0]);
                        if ($objectClass === $className || str_contains($objectClass, $pluginSlug)) {
                            $this->hookManager->remove($hookName, $callback);
                        }
                    }
                } elseif (is_string($callback) && (str_contains($callback, $className) || str_contains($callback, $pluginSlug))) {
                    $this->hookManager->remove($hookName, $callback);
                }
            }
        }

        foreach ($this->hooks as $hookName => $hooks) {
            $filteredHooks = array_filter($hooks, function ($hook) use ($className, $pluginSlug) {
                if (is_array($hook['callback'])) {
                    if (isset($hook['callback'][0])) {
                        $object = $hook['callback'][0];
                        if (is_object($object)) {
                            $objectClass = get_class($object);
                            if ($objectClass === $className || str_contains($objectClass, $pluginSlug)) {
                                return false;
                            }
                        }
                    }
                }

                return true;
            });

            $this->hooks[$hookName] = array_values($filteredHooks);

            if (empty($this->hooks[$hookName])) {
                unset($this->hooks[$hookName]);
            }
        }
    }

    private function getEngineDir(): string
    {
        static $engineDir = null;
        if ($engineDir === null) {
            $engineDir = dirname(__DIR__, 3);
        }

        return $engineDir;
    }

    private function getProjectRoot(): string
    {
        static $rootDir = null;
        if ($rootDir === null) {
            $rootDir = dirname($this->getEngineDir());
        }

        return $rootDir;
    }

    private function getLifecycle(): PluginLifecycleInterface
    {
        if ($this->lifecycle instanceof PluginLifecycleInterface) {
            return $this->lifecycle;
        }

        if (function_exists('container')) {
            try {
                $app = container();
                if ($app->has(PluginLifecycleInterface::class)) {
                    $this->lifecycle = $app->make(PluginLifecycleInterface::class);

                    return $this->lifecycle;
                }
            } catch (Exception $e) {
                // fallback below
            }
        }

        $repository = new PluginRepository();
        $filesystem = new PluginFilesystem();
        $cache = new PluginCacheManager();

        $installer = new InstallPluginService($repository);
        $activator = new ActivatePluginService($repository);
        $deactivator = new DeactivatePluginService($repository);
        $uninstaller = new UninstallPluginService($repository);

        $this->lifecycle = new PluginLifecycleService(
            $filesystem,
            $cache,
            $installer,
            $activator,
            $deactivator,
            $uninstaller
        );

        return $this->lifecycle;
    }
}

function addHook(string $hookName, callable $callback, int $priority = 10): void
{
    $manager = pluginManager();
    if ($manager) {
        $manager->addHook($hookName, $callback, $priority);
    } else {
        hooks()->filter($hookName, $callback, $priority);
    }
}

function hasHook(string $hookName): bool
{
    $manager = pluginManager();

    return $manager ? $manager->hasHook($hookName) : hooks()->has($hookName);
}

function addFilter(string $hookName, callable $callback, int $priority = 10, ?callable $condition = null): void
{
    hooks()->filter($hookName, $callback, $priority);
}

/**
 * @param mixed $data
 * @param mixed ...$args
 * @return mixed
 */
function applyFilter(string $hookName, $data = null, ...$args)
{
    $context = [];
    if (! empty($args)) {
        $context = is_array($args[0]) ? $args[0] : $args;
    }

    return hook_apply($hookName, $data, $context);
}

function addAction(string $hookName, callable $callback, int $priority = 10, ?callable $condition = null): void
{
    hooks()->on($hookName, $callback, $priority);
}

function doAction(string $hookName, ...$args): void
{
    hook_dispatch($hookName, ...$args);
}

function removeHook(string $hookName, ?callable $callback = null): bool
{
    hooks()->remove($hookName, $callback);

    return true;
}

function hookManager(): HookManagerInterface
{
    return hooks();
}
