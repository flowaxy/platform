<?php

/**
 * Фасад для роботи з клієнтським сховищем (localStorage/sessionStorage)
 *
 * @package Engine\Core\Support\Facades
 */

declare(strict_types=1);

require_once __DIR__ . '/Facade.php';

final class StorageFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StorageManager::class;
    }

    /**
     * Отримання менеджера сховища
     */
    public static function manager(string $type = 'localStorage', string $prefix = ''): StorageManager
    {
        $manager = static::getFacadeRoot();
        if ($manager instanceof StorageManager) {
            $manager->setType($type);
            if ($prefix) {
                $manager->setPrefix($prefix);
            }

            return $manager;
        }

        throw new \RuntimeException('StorageManager not found in container');
    }

    /**
     * Отримання значення зі сховища
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::manager()->get($key, $default);
    }

    /**
     * Збереження значення в сховище
     */
    public static function set(string $key, mixed $value): void
    {
        static::manager()->set($key, $value);
    }

    /**
     * Видалення значення зі сховища
     */
    public static function delete(string $key): void
    {
        static::manager()->remove($key);
    }

    /**
     * Очищення сховища
     */
    public static function clear(): void
    {
        static::manager()->clear();
    }
}
