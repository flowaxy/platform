<?php

/**
 * Фасад для роботи з логуванням
 *
 * @package Engine\Core\Support\Facades
 */

declare(strict_types=1);

require_once __DIR__ . '/Facade.php';
require_once __DIR__ . '/../../Contracts/LoggerInterface.php';

final class Log extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LoggerInterface::class;
    }

    /**
     * Отримання екземпляра Logger
     */
    public static function instance(): Logger
    {
        $container = static::getContainer();
        if ($container->has(LoggerInterface::class)) {
            return $container->make(LoggerInterface::class);
        }

        // Fallback на getInstance
        return Logger::getInstance();
    }

    /**
     * Логування повідомлення
     *
     * @param int $level
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    public static function log(int $level, string $message, array $context = []): void
    {
        static::instance()->log($level, $message, $context);
    }

    /**
     * Логування помилки
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        static::instance()->logError($message, $context);
    }

    /**
     * Логування попередження
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        static::instance()->logWarning($message, $context);
    }

    /**
     * Логування інформації
     */
    public static function info(string $message, array $context = []): void
    {
        static::instance()->logInfo($message, $context);
    }

    /**
     * Логування винятку
     */
    public static function exception(\Throwable $exception): void
    {
        static::instance()->logException($exception);
    }
}
