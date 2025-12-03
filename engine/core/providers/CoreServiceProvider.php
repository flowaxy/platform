<?php

/**
 * Реєструє базові сервіси ядра у контейнері.
 *
 * @package Engine\System\Providers
 */

declare(strict_types=1);

require_once __DIR__ . '/../system/ServiceProvider.php';
require_once __DIR__ . '/../contracts/HookManagerInterface.php';
require_once __DIR__ . '/../contracts/HookRegistryInterface.php';
require_once __DIR__ . '/../contracts/ComponentRegistryInterface.php';
require_once __DIR__ . '/../system/ComponentRegistry.php';

final class CoreServiceProvider extends ServiceProvider
{
    protected function registerBindings(): void
    {
        // Контейнер доступний також за своїм інтерфейсом
        $this->container->instance(ContainerInterface::class, $this->container);

        $this->registerDatabase();
        $this->registerCache();
        $this->registerLogger();
        $this->registerConfig();
        $this->registerManagers();
        $this->registerHttpComponents();
        $this->registerHookManager();
        $this->registerComponentRegistry();
        $this->registerModuleManager();
        $this->registerTestService();
        $this->registerFeatureFlags();
    }

    private function registerDatabase(): void
    {
        $this->container->singleton(Database::class, static fn () => Database::getInstance());
        $this->container->singleton(DatabaseInterface::class, fn () => $this->container->make(Database::class));
    }

    private function registerCache(): void
    {
        $this->container->singleton(Cache::class, static fn () => Cache::getInstance());
    }

    private function registerLogger(): void
    {
        $this->container->singleton(Logger::class, static fn () => Logger::getInstance());
        $this->container->singleton(LoggerInterface::class, fn () => $this->container->make(Logger::class));
    }

    private function registerHookManager(): void
    {
        $this->container->singleton(HookManagerInterface::class, static fn () => new HookManager());
        $this->container->singleton(HookRegistryInterface::class, fn () => $this->container->make(HookManagerInterface::class));
        $this->container->singleton(HookManager::class, fn () => $this->container->make(HookManagerInterface::class));
    }

    private function registerComponentRegistry(): void
    {
        $this->container->singleton(ComponentRegistryInterface::class, static fn () => new ComponentRegistry());
    }

    private function registerModuleManager(): void
    {
        $this->container->singleton(ModuleManager::class, function () {
            return new ModuleManager(
                $this->container,
                $this->container->make(ComponentRegistryInterface::class)
            );
        });
    }

    private function registerTestService(): void
    {
        $this->container->singleton(TestService::class, static fn () => new TestService());
    }

    private function registerConfig(): void
    {
        $this->container->singleton(SystemConfig::class, static fn () => SystemConfig::getInstance());
        $this->container->singleton(Config::class, static fn () => Config::getInstance());
    }

    private function registerManagers(): void
    {
        // Менеджери сховищ та сесій
        $this->container->singleton(CookieManager::class, static fn () => CookieManager::getInstance());
        $this->container->singleton(SessionManager::class, static fn () => SessionManager::getInstance());
        $this->container->singleton(StorageManager::class, static fn () => StorageManager::getInstance());

        // Менеджери системи
        $this->container->singleton(SettingsManager::class, static fn () => SettingsManager::getInstance());
        $this->container->singleton(RoleManager::class, static fn () => RoleManager::getInstance());

        // ThemeManager наслідується від BaseModule, тому має getInstance() звідти
        $this->container->singleton(ThemeManager::class, static fn () => ThemeManager::getInstance());
    }

    private function registerHttpComponents(): void
    {
        $this->container->singleton(Request::class, static fn () => Request::getInstance());
        $this->container->singleton(ModalHandler::class, static fn () => ModalHandler::getInstance());
        $this->container->singleton(RouterManager::class, static fn () => RouterManager::getInstance());
    }

    private function registerFeatureFlags(): void
    {
        require_once __DIR__ . '/../contracts/FeatureFlagManagerInterface.php';
        require_once __DIR__ . '/../system/FeatureFlagManager.php';

        $this->container->singleton(FeatureFlagManagerInterface::class, function () {
            $logger = $this->container->has(LoggerInterface::class)
                ? $this->container->make(LoggerInterface::class)
                : null;

            return new FeatureFlagManager($logger);
        });

        $this->container->singleton(FeatureFlagManager::class, fn () => $this->container->make(FeatureFlagManagerInterface::class));
    }
}
