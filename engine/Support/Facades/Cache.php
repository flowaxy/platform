<?php

/**
 * Фасад для роботи з кешем
 *
 * @package Engine\Core\Support\Facades
 */

declare(strict_types=1);

require_once __DIR__ . '/Facade.php';

final class CacheFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'Cache';
    }

    /**
     * Отримання екземпляра Cache
     */
    public static function instance(): Cache
    {
        $container = static::getContainer();
        if ($container->has('Cache')) {
            return $container->make('Cache');
        }

        // Fallback на getInstance
        return Cache::getInstance();
    }

    /**
     * Отримання значення з кешу
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::instance()->get($key, $default);
    }

    /**
     * Збереження значення в кеш
     */
    public static function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return static::instance()->set($key, $value, $ttl);
    }

    /**
     * Видалення значення з кешу
     */
    public static function delete(string $key): bool
    {
        return static::instance()->delete($key);
    }

    /**
     * Очищення кешу
     */
    public static function clear(): bool
    {
        return static::instance()->clear();
    }
}
