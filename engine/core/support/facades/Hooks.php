<?php

/**
 * Фасад для роботи з хуками
 *
 * @package Engine\Core\Support\Facades
 */

declare(strict_types=1);

require_once __DIR__ . '/Facade.php';
require_once __DIR__ . '/../../contracts/HookManagerInterface.php';
require_once __DIR__ . '/../../system/hooks/HookType.php';

final class Hooks extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HookManagerInterface::class;
    }

    /**
     * Виконання події (action)
     */
    public static function dispatch(string $hookName, mixed ...$args): void
    {
        $pluginManager = function_exists('pluginManager') ? pluginManager() : null;
        if ($pluginManager instanceof \PluginManager && method_exists($pluginManager, 'prepareHook')) {
            $pluginManager->prepareHook($hookName, HookType::Action, $args);
        }

        $instance = static::getFacadeRoot();
        if ($instance instanceof HookManagerInterface) {
            $instance->dispatch($hookName, ...$args);
        }
    }

    /**
     * Застосування фільтра (filter)
     *
     * @param string $hookName
     * @param mixed $value
     * @param array<string, mixed> $context
     * @return mixed
     */
    public static function apply(string $hookName, mixed $value = null, array $context = []): mixed
    {
        $pluginManager = function_exists('pluginManager') ? pluginManager() : null;
        if ($pluginManager instanceof \PluginManager && method_exists($pluginManager, 'prepareHook')) {
            $value = $pluginManager->prepareHook($hookName, HookType::Filter, $value);
        }

        $instance = static::getFacadeRoot();
        if ($instance instanceof HookManagerInterface) {
            return $instance->apply($hookName, $value, $context);
        }

        return $value;
    }
}
