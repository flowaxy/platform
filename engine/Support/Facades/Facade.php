<?php

/**
 * Базовий клас для фасадів
 *
 * Фасади надають статичний доступ до сервісів, зареєстрованих в контейнері
 *
 * @package Engine\Core\Support\Facades
 */

declare(strict_types=1);

require_once __DIR__ . '/../../Contracts/ContainerInterface.php';

use Flowaxy\Core\Contracts\ContainerInterface;

abstract class Facade
{
    /**
     * Отримання імені сервісу в контейнері
     *
     * @return string
     */
    abstract protected static function getFacadeAccessor(): string;

    /**
     * Отримання контейнера залежностей
     *
     * @return ContainerInterface
     * @throws \RuntimeException
     */
    protected static function getContainer(): ContainerInterface
    {
        if (! isset($GLOBALS['engineContainer']) || ! $GLOBALS['engineContainer'] instanceof ContainerInterface) {
            // Спробуємо ініціалізувати Container, якщо він ще не ініціалізований
            if (class_exists('Flowaxy\Core\System\Container')) {
                try {
                    $container = new \Flowaxy\Core\System\Container();
                    $GLOBALS['engineContainer'] = $container;
                    return $container;
                } catch (Throwable $e) {
                    // Якщо не вдалося, пробуємо fallback
                }
            }
            // Fallback для зворотної сумісності
            if (class_exists('Container')) {
                try {
                    $container = new \Container();
                    $GLOBALS['engineContainer'] = $container;
                    return $container;
                } catch (Throwable $e) {
                    // Якщо і це не спрацювало, викидаємо помилку
                }
            }
            throw new \RuntimeException('Container is not initialized. Make sure Kernel is booted.');
        }

        return $GLOBALS['engineContainer'];
    }

    /**
     * Отримання екземпляра сервісу
     *
     * @return object
     */
    protected static function getFacadeRoot(): object
    {
        $container = static::getContainer();
        $accessor = static::getFacadeAccessor();

        if ($container->has($accessor)) {
            $result = $container->make($accessor);
            // Якщо контейнер повернув Closure, викликаємо його
            if ($result instanceof \Closure) {
                $result = $result();
            }
            // Перевіряємо, що отримали об'єкт
            if (is_object($result)) {
                return $result;
            }
        }

        // Fallback: якщо сервіс не в контейнері, намагаємося отримати через getInstance()
        if (class_exists($accessor)) {
            if (method_exists($accessor, 'getInstance')) {
                return $accessor::getInstance();
            }
        }

        throw new \RuntimeException("Facade accessor '{$accessor}' not found in container and no getInstance() method available.");
    }

    /**
     * Статичний виклик методів через фасад
     *
     * @param string $method
     * @param array<int, mixed> $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::getFacadeRoot();

        return $instance->$method(...$args);
    }
}
