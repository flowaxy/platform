<?php

/**
 * Фасад для доступу до контейнера та конфігурації
 *
 * @package Engine\Core\Support\Facades
 */

declare(strict_types=1);

require_once __DIR__ . '/Facade.php';
require_once __DIR__ . '/../../contracts/ContainerInterface.php';

final class App extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ContainerInterface::class;
    }

    /**
     * Отримання контейнера
     */
    public static function container(): ContainerInterface
    {
        return static::getContainer();
    }

    /**
     * Отримання сервісу з контейнера
     */
    public static function make(string $abstract): object
    {
        return static::getContainer()->make($abstract);
    }

    /**
     * Перевірка наявності сервісу в контейнері
     */
    public static function has(string $abstract): bool
    {
        return static::getContainer()->has($abstract);
    }

    /**
     * Отримання конфігурації
     */
    public static function config(string $key, mixed $default = null): mixed
    {
        if (! class_exists('SystemConfig')) {
            return $default;
        }

        try {
            $container = static::getContainer();
            if ($container->has(SystemConfig::class)) {
                $systemConfig = $container->make(SystemConfig::class);
                return $systemConfig->get($key, $default);
            }
        } catch (Exception $e) {
            // Fallback на getInstance() для обратной совместимости
        }

        return SystemConfig::getInstance()->get($key, $default);
    }
}
