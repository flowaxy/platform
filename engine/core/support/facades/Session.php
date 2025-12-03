<?php

/**
 * Фасад для роботи з сесіями
 *
 * @package Engine\Core\Support\Facades
 */

declare(strict_types=1);

require_once __DIR__ . '/Facade.php';

final class SessionFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SessionManager::class;
    }

    /**
     * Отримання менеджера сесій
     */
    public static function manager(string $prefix = ''): SessionManager
    {
        $manager = static::getFacadeRoot();
        if ($manager instanceof SessionManager) {
            if ($prefix) {
                $manager->setPrefix($prefix);
            }

            return $manager;
        }

        throw new \RuntimeException('SessionManager not found in container');
    }

    /**
     * Отримання значення з сесії
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::manager()->get($key, $default);
    }

    /**
     * Збереження значення в сесію
     */
    public static function set(string $key, mixed $value): void
    {
        static::manager()->set($key, $value);
    }

    /**
     * Видалення значення з сесії
     */
    public static function delete(string $key): void
    {
        static::manager()->remove($key);
    }

    /**
     * Очищення сесії
     */
    public static function clear(): void
    {
        static::manager()->clear();
    }
}
