<?php

declare(strict_types=1);

require_once __DIR__ . '/../system/ServiceProvider.php';
require_once __DIR__ . '/../../domain/content/ThemeRepositoryInterface.php';
require_once __DIR__ . '/../../domain/content/ThemeSettingsRepositoryInterface.php';
require_once __DIR__ . '/../../Database/ThemeRepository.php';
require_once __DIR__ . '/../../Database/ThemeSettingsRepository.php';
require_once __DIR__ . '/../../application/content/ActivateThemeService.php';
require_once __DIR__ . '/../../application/content/UpdateThemeSettingsService.php';
require_once __DIR__ . '/../../domain/content/PluginRepositoryInterface.php';
require_once __DIR__ . '/../../domain/content/PluginLifecycleInterface.php';
require_once __DIR__ . '/../../domain/content/PluginFilesystemInterface.php';
require_once __DIR__ . '/../../domain/content/PluginCacheInterface.php';
require_once __DIR__ . '/../../Database/PluginRepository.php';
require_once __DIR__ . '/../../infrastructure/filesystem/PluginFilesystem.php';
require_once __DIR__ . '/../../Cache/PluginCacheManager.php';
require_once __DIR__ . '/../../application/content/InstallPluginService.php';
require_once __DIR__ . '/../../application/content/ActivatePluginService.php';
require_once __DIR__ . '/../../application/content/DeactivatePluginService.php';
require_once __DIR__ . '/../../application/content/TogglePluginService.php';
require_once __DIR__ . '/../../application/content/UninstallPluginService.php';
require_once __DIR__ . '/../../application/content/PluginLifecycleService.php';

final class ThemeServiceProvider extends ServiceProvider
{
    protected function registerBindings(): void
    {
        if (! $this->container->has(ThemeRepositoryInterface::class)) {
            $this->container->singleton(ThemeRepositoryInterface::class, static fn () => new ThemeRepository());
        }

        if (! $this->container->has(PluginRepositoryInterface::class)) {
            $this->container->singleton(PluginRepositoryInterface::class, static fn () => new PluginRepository());
        }

        if (! $this->container->has(ThemeSettingsRepositoryInterface::class)) {
            $this->container->singleton(ThemeSettingsRepositoryInterface::class, static fn () => new ThemeSettingsRepository());
        }

        if (! $this->container->has(PluginFilesystemInterface::class)) {
            $this->container->singleton(PluginFilesystemInterface::class, static fn () => new PluginFilesystem());
        }

        if (! $this->container->has(PluginCacheInterface::class)) {
            $this->container->singleton(PluginCacheInterface::class, static fn () => new PluginCacheManager());
        }

        if (! $this->container->has(ActivateThemeService::class)) {
            $this->container->singleton(ActivateThemeService::class, fn () => new ActivateThemeService(
                $this->container->make(ThemeRepositoryInterface::class)
            ));
        }

        if (! $this->container->has(UpdateThemeSettingsService::class)) {
            $this->container->singleton(UpdateThemeSettingsService::class, fn () => new UpdateThemeSettingsService(
                $this->container->make(ThemeSettingsRepositoryInterface::class),
                $this->container->make(ThemeRepositoryInterface::class)
            ));
        }

        if (! $this->container->has(InstallPluginService::class)) {
            $this->container->singleton(InstallPluginService::class, fn () => new InstallPluginService(
                $this->container->make(PluginRepositoryInterface::class)
            ));
        }

        if (! $this->container->has(ActivatePluginService::class)) {
            $this->container->singleton(ActivatePluginService::class, fn () => new ActivatePluginService(
                $this->container->make(PluginRepositoryInterface::class)
            ));
        }

        if (! $this->container->has(DeactivatePluginService::class)) {
            $this->container->singleton(DeactivatePluginService::class, fn () => new DeactivatePluginService(
                $this->container->make(PluginRepositoryInterface::class)
            ));
        }

        if (! $this->container->has(TogglePluginService::class)) {
            $this->container->singleton(TogglePluginService::class, fn () => new TogglePluginService(
                $this->container->make(ActivatePluginService::class),
                $this->container->make(DeactivatePluginService::class)
            ));
        }

        if (! $this->container->has(UninstallPluginService::class)) {
            $this->container->singleton(UninstallPluginService::class, fn () => new UninstallPluginService(
                $this->container->make(PluginRepositoryInterface::class)
            ));
        }

        if (! $this->container->has(PluginLifecycleInterface::class)) {
            $this->container->singleton(PluginLifecycleInterface::class, fn () => new PluginLifecycleService(
                $this->container->make(PluginFilesystemInterface::class),
                $this->container->make(PluginCacheInterface::class),
                $this->container->make(InstallPluginService::class),
                $this->container->make(ActivatePluginService::class),
                $this->container->make(DeactivatePluginService::class),
                $this->container->make(UninstallPluginService::class)
            ));
        }
    }
}
