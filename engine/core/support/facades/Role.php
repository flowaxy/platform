<?php

/**
 * Фасад для роботи з ролями та правами
 *
 * @package Engine\Core\Support\Facades
 */

declare(strict_types=1);

require_once __DIR__ . '/Facade.php';

final class Role extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RoleManager::class;
    }

    /**
     * Отримання менеджера ролей
     */
    public static function manager(): RoleManager
    {
        $container = static::getContainer();
        if ($container->has(RoleManager::class)) {
            return $container->make(RoleManager::class);
        }

        // Fallback на getInstance
        return RoleManager::getInstance();
    }

    /**
     * Перевірка дозволу у користувача
     */
    public static function userCan(int $userId, string $permission): bool
    {
        if (function_exists('user_can')) {
            return user_can($userId, $permission);
        }

        return static::manager()->hasPermission($userId, $permission);
    }

    /**
     * Перевірка ролі у користувача
     */
    public static function userHasRole(int $userId, string $roleSlug): bool
    {
        if (function_exists('user_has_role')) {
            return user_has_role($userId, $roleSlug);
        }

        return static::manager()->hasRole($userId, $roleSlug);
    }
}
