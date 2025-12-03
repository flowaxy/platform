<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/system/ServiceProvider.php';
require_once __DIR__ . '/../contracts/ServiceProviderInterface.php';

final class PluginModuleServiceProvider extends ServiceProvider implements ModuleServiceProviderInterface
{
    protected function registerBindings(): void
    {
        $this->container->singleton(PluginManager::class, static function () {
            $module = ModuleLoader::loadModule('PluginManager');

            return $module ?? PluginManager::getInstance();
        });
    }

    public function boot(ContainerInterface $container): void
    {
        $pluginManager = $container->make(PluginManager::class);
        if ($pluginManager instanceof PluginManager && method_exists($pluginManager, 'initializePlugins')) {
            $pluginManager->initializePlugins();
        }
    }
}
