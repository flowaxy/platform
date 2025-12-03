<?php

/**
 * Менеджер плагінів системи
 *
 * @package Engine\Managers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

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
                logger()->logError("Plugin init error for {$slug}: " . $e->getMessage(), ['exception' => $e]);
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
                logger()->logDebug('Плагіни завантажено', [
                    'count' => count($pluginData),
                    'plugins' => $pluginData,
                ]);
            }
        } catch (Exception $e) {
            logger()->logError('Error loading plugins: ' . $e->getMessage(), ['exception' => $e]);
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
            logger()->logError("Error loading plugin full data for {$slug}: " . $e->getMessage(), ['exception' => $e]);
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
            logger()->logError("Error loading plugin {$slug}: " . $e->getMessage(), ['exception' => $e]);
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
            logger()->logError("Error loading plugin instance {$pluginSlug}: " . $e->getMessage(), ['exception' => $e]);
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
                logger()->logError("Hook execution error for '{$hookName}': " . $e->getMessage(), ['exception' => $e]);
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
                        logger()->logError("Error registering hooks for module {$moduleName}: " . $e->getMessage(), ['exception' => $e]);
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
                        logger()->logWarning("Invalid JSON in plugin.json for plugin: {$pluginSlug}");
                    }
                } catch (Exception $e) {
                    logger()->logError("Cannot read plugin.json for plugin {$pluginSlug}: " . $e->getMessage(), ['exception' => $e]);
                }
            }
        }

        return $allPlugins;
    }

    public function autoDiscoverPlugins(): int
    {
        $allPlugins = $this->getAllPlugins();
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

        foreach ($allPlugins as $slug => $config) {
            try {
                $stmt = $db->prepare('SELECT id FROM plugins WHERE slug = ?');
                $stmt->execute([$slug]);

                if (! $stmt->fetch()) {
                    if ($this->installPlugin($slug)) {
                        $installedCount++;
                    }
                }
            } catch (Exception $e) {
                logger()->logError("Error checking plugin {$slug}: " . $e->getMessage(), ['exception' => $e]);
            }
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
            logger()->logError('Error getting active plugins', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function installPlugin(string $pluginSlug): bool
    {
        try {
            return $this->getLifecycle()->install($pluginSlug);
        } catch (Exception $e) {
            logger()->logError('Plugin installation error: ' . $e->getMessage(), ['exception' => $e]);
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
            logger()->logError('Plugin uninstallation error: ' . $e->getMessage(), ['exception' => $e]);
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

    public function activatePlugin(string $pluginSlug): bool
    {
        try {
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
                        logger()->logError("Plugin init error for {$pluginSlug} after activation: " . $e->getMessage(), ['exception' => $e]);
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            logger()->logError('Plugin activation error: ' . $e->getMessage(), ['exception' => $e]);

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
            logger()->logError('Plugin deactivation error: ' . $e->getMessage(), ['exception' => $e]);

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
            logger()->logError('Error setting plugin setting: ' . $e->getMessage(), ['exception' => $e]);

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
