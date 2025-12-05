<?php

/**
 * Фасад для роботи з темами
 *
 * @package Engine\Core\Support\Facades
 */

declare(strict_types=1);

require_once __DIR__ . '/Facade.php';

final class Theme extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ThemeManager::class;
    }

    /**
     * Отримання менеджера тем
     */
    public static function manager(): ThemeManager
    {
        $container = static::getContainer();
        if ($container->has(ThemeManager::class)) {
            return $container->make(ThemeManager::class);
        }

        // Fallback на getInstance
        return ThemeManager::getInstance();
    }
}
