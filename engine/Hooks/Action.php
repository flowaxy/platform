<?php

/**
 * Клас для роботи з actions (подіями) у стилі WordPress
 *
 * @package Flowaxy\Core\System\Hooks
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Hooks;

require_once __DIR__ . '/HookManager.php';
require_once __DIR__ . '/HookType.php';

// HookManager не має namespace, використовуємо глобальний клас

/**
 * Статичний клас для роботи з actions
 *
 * Приклад використання:
 * Action::add('init', function() { echo 'Init hook'; });
 * Action::do('init');
 */
final class Action
{
    /**
     * Отримання екземпляра HookManager
     *
     * @return \HookManager
     */
    private static function getHookManager()
    {
        static $hookManager = null;

        if ($hookManager === null) {
            // Спробуємо отримати з контейнера
            if (function_exists('container')) {
                try {
                    $container = container();
                    if ($container->has(\HookManagerInterface::class)) {
                        $hookManager = $container->make(\HookManagerInterface::class);
                        if ($hookManager instanceof \HookManagerInterface) {
                            return $hookManager;
                        }
                    }
                } catch (\Exception $e) {
                    // Fallback нижче
                }
            }

            // Спробуємо отримати через hooks() функцію
            if (function_exists('hooks')) {
                try {
                    $hookManager = hooks();
                    if ($hookManager instanceof \HookManagerInterface) {
                        return $hookManager;
                    }
                } catch (\Exception $e) {
                    // Fallback нижче
                }
            }

            // Fallback: створюємо новий екземпляр
            if (!class_exists('HookManager')) {
                require_once __DIR__ . '/HookManager.php';
            }
            $hookManager = new \HookManager();
        }

        return $hookManager;
    }

    /**
     * Додавання action (події)
     *
     * @param string $hookName Назва хука
     * @param callable $callback Callback функція
     * @param int $priority Пріоритет (за замовчуванням 10)
     * @param bool $once Виконати тільки один раз
     * @return void
     */
    public static function add(string $hookName, callable $callback, int $priority = 10, bool $once = false): void
    {
        self::getHookManager()->on($hookName, $callback, $priority, $once);
    }

    /**
     * Виконання action (події)
     *
     * @param string $hookName Назва хука
     * @param mixed ...$args Аргументи для передачі в callback
     * @return void
     */
    public static function do(string $hookName, mixed ...$args): void
    {
        self::getHookManager()->dispatch($hookName, ...$args);
    }

    /**
     * Видалення action
     *
     * @param string $hookName Назва хука
     * @param callable|null $callback Callback для видалення (null = видалити всі)
     * @return void
     */
    public static function remove(string $hookName, ?callable $callback = null): void
    {
        self::getHookManager()->remove($hookName, $callback);
    }

    /**
     * Перевірка наявності action
     *
     * @param string $hookName Назва хука
     * @return bool
     */
    public static function has(string $hookName): bool
    {
        return self::getHookManager()->has($hookName);
    }
}
