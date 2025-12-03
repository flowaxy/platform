<?php

declare(strict_types=1);

return [
    'singletons' => [
        // Theme services
        'ThemeRepositoryInterface' => 'ThemeRepository',
        'ThemeSettingsRepositoryInterface' => 'ThemeSettingsRepository',
        'ActivateThemeService' => 'ActivateThemeService',
        'UpdateThemeSettingsService' => 'UpdateThemeSettingsService',

        // Plugin services
        'PluginRepositoryInterface' => 'PluginRepository',
        'PluginFilesystemInterface' => 'PluginFilesystem',
        'PluginCacheInterface' => 'PluginCacheManager',
        'InstallPluginService' => 'InstallPluginService',
        'ActivatePluginService' => 'ActivatePluginService',
        'DeactivatePluginService' => 'DeactivatePluginService',
        'TogglePluginService' => 'TogglePluginService',
        'UninstallPluginService' => 'UninstallPluginService',
        'PluginLifecycleInterface' => 'PluginLifecycleService',

        // Auth services
        'AdminUserRepositoryInterface' => 'AdminUserRepository',
        'AdminRoleRepositoryInterface' => 'AdminRoleRepository',
        'AuthenticateAdminUserService' => 'AuthenticateAdminUserService',
        'AdminAuthorizationService' => 'AdminAuthorizationService',
        'LogoutAdminUserService' => 'LogoutAdminUserService',
    ],
    'bindings' => [
        // 'HookManagerInterface' => 'Custom\\HookManager',
    ],
];

