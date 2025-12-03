<?php

/**
 * Фасад для роботи з налаштуваннями
 *
 * @package Engine\Core\Support\Facades
 */

declare(strict_types=1);

require_once __DIR__ . '/Facade.php';

final class SettingsFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsManager::class;
    }

    /**
     * Отримання менеджера налаштувань
     */
    public static function manager(): ?SettingsManager
    {
        try {
            return static::getFacadeRoot();
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /**
     * Отримання значення налаштування
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $manager = static::manager();
        if ($manager === null) {
            return $default;
        }

        return $manager->get($key, $default);
    }

    /**
     * Встановлення значення налаштування
     */
    public static function set(string $key, mixed $value): void
    {
        $manager = static::manager();
        if ($manager !== null) {
            $manager->set($key, $value);
        }
    }

    /**
     * Перевірка наявності налаштування
     */
    public static function has(string $key): bool
    {
        $manager = static::manager();
        if ($manager === null) {
            return false;
        }

        return $manager->has($key);
    }
}
