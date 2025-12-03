<?php

/**
 * Конфігурація Feature Flags для ядра Flowaxy CMS
 *
 * Цей файл визначає дефолтні значення feature flags.
 * Для зміни значень використовуйте SettingsManager або адмін-панель.
 *
 * @package Engine\Core\Config
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

return [
    // Основні функції системи
    'api_enabled' => true,
    'plugin_system' => true,
    'theme_system' => true,

    // Режими роботи
    'maintenance_mode' => false,
    'debug_mode' => false,

    // Оптимізація та продуктивність
    'cache_enabled' => true,
    'logging_enabled' => true,
    'query_optimization_enabled' => true,

    // Безпека
    'rate_limiting_enabled' => false,
    'two_factor_auth_enabled' => false,
    'session_regeneration_enabled' => true,

    // API та інтеграції
    'rest_api_enabled' => true,
    'graphql_enabled' => false,
    'webhook_system_enabled' => false,

    // UI/UX
    'admin_ui_v2' => false,
    'dark_mode_enabled' => true,
    'responsive_admin_enabled' => true,

    // Експериментальні функції
    'experimental_features' => false,
    'beta_features' => false,
];
