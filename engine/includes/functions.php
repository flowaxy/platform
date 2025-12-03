<?php
/**
 * Helper functions for plugins compatibility
 * 
 * @package Engine\Includes
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

// Функції addHook, addFilter, hooks() вже визначені в PluginManager.php
// Цей файл існує для зворотної сумісності з плагінами, які його підключають

// Якщо функції ще не визначені, визначаємо їх тут
if (!function_exists('addHook')) {
    /**
     * Додавання хука
     */
    function addHook(string $hookName, callable $callback, int $priority = 10): void
    {
        $manager = function_exists('pluginManager') ? pluginManager() : null;
        if ($manager && method_exists($manager, 'addHook')) {
            $manager->addHook($hookName, $callback, $priority);
        }
    }
}

if (!function_exists('addFilter')) {
    /**
     * Додавання фільтра
     */
    function addFilter(string $hookName, callable $callback, int $priority = 10, ?callable $condition = null): void
    {
        if (function_exists('hooks')) {
            hooks()->filter($hookName, $callback, $priority);
        }
    }
}

if (!function_exists('hooks')) {
    /**
     * Отримання менеджера хуків
     */
    function hooks(): ?object
    {
        if (class_exists('HookManager')) {
            return HookManager::getInstance();
        }
        return null;
    }
}

