<?php

/**
 * Фасад для роботи з плагінами
 *
 * @package Engine\Core\Support\Facades
 */

declare(strict_types=1);

require_once __DIR__ . '/Facade.php';

final class Plugin extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PluginManager::class;
    }

    /**
     * Отримання менеджера плагінів
     */
    public static function manager(): ?PluginManager
    {
        try {
            $manager = static::getFacadeRoot();
            if ($manager instanceof PluginManager) {
                return $manager;
            }

            return null;
        } catch (\RuntimeException $e) {
            return null;
        }
    }
}
