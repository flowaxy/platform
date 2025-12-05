<?php

declare(strict_types=1);

/**
 * Конфігурація модулів
 * 
 * Підтримуються формати: PHP, JSON, YAML
 * 
 * Формат конфігурації:
 * [
 *     'ModuleName' => [
 *         'provider' => 'ProviderClass',
 *         'dependencies' => ['OtherModule'],
 *         'enabled' => true,
 *         'priority' => 10,
 *     ]
 * ]
 * 
 * Або простий формат (зворотна сумісність):
 * [
 *     'ModuleName' => 'ProviderClass'
 * ]
 */

return [
    'PluginManager' => [
        'provider' => 'PluginModuleServiceProvider',
        'dependencies' => [],
        'enabled' => true,
        'priority' => 10,
    ],
];

