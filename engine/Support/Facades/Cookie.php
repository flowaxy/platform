<?php

/**
 * Фасад для роботи з cookies
 *
 * @package Engine\Core\Support\Facades
 */

declare(strict_types=1);

require_once __DIR__ . '/Facade.php';

final class CookieFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CookieManager::class;
    }

    /**
     * Отримання менеджера cookies
     */
    public static function manager(): CookieManager
    {
        return static::getFacadeRoot();
    }

    /**
     * Отримання значення cookie
     */
    public static function get(string $name, mixed $default = null): mixed
    {
        return static::manager()->get($name, $default);
    }

    /**
     * Встановлення cookie
     *
     * @param string $name
     * @param string $value
     * @param array<string, mixed> $options
     * @return void
     */
    public static function set(string $name, string $value, array $options = []): void
    {
        static::manager()->set($name, $value, $options);
    }

    /**
     * Видалення cookie
     */
    public static function delete(string $name): void
    {
        static::manager()->delete($name);
    }
}
