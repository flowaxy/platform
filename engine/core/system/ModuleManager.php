<?php

declare(strict_types=1);

require_once __DIR__ . '/../contracts/ComponentRegistryInterface.php';
require_once __DIR__ . '/../contracts/ContainerInterface.php';
require_once __DIR__ . '/../contracts/ServiceProviderInterface.php';

final class ModuleManager
{
    /**
     * @var array<string,string>
     */
    private array $providers = [];

    /**
     * @var ModuleServiceProviderInterface[]
     */
    private array $instances = [];

    private bool $booted = false;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ComponentRegistryInterface $registry
    ) {
        $this->loadConfiguredModules();
    }

    public function register(string $moduleName, string $providerClass): void
    {
        $this->providers[$moduleName] = $providerClass;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $moduleName => $providerClass) {
            ModuleLoader::loadModule($moduleName);

            /** @var ModuleServiceProviderInterface $provider */
            $provider = $this->container->make($providerClass);
            $provider->register($this->container);
            $this->instances[] = $provider;
        }

        foreach ($this->instances as $provider) {
            $provider->boot($this->container);
        }

        $this->booted = true;
    }

    /**
     * @return array<string,string>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    private function loadConfiguredModules(): void
    {
        $configPath = dirname(__DIR__) . '/config/modules.php';
        if (! file_exists($configPath)) {
            return;
        }

        $modules = require $configPath;
        if (! is_array($modules)) {
            return;
        }

        foreach ($modules as $moduleName => $providerClass) {
            if (is_string($moduleName) && is_string($providerClass)) {
                $this->register($moduleName, $providerClass);
            }
        }
    }
}
