<?php

/**
 * Фасад для роботи з Feature Flags
 *
 * @package Engine\Core\Support\Facades
 */

declare(strict_types=1);

require_once __DIR__ . '/Facade.php';
require_once __DIR__ . '/../../contracts/FeatureFlagManagerInterface.php';

final class FeatureFlag extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FeatureFlagManagerInterface::class;
    }

    /**
     * Перевірка, чи увімкнено feature flag
     */
    public static function enabled(string $flagName, array $context = []): bool
    {
        try {
            $manager = static::getFacadeRoot();
            if ($manager instanceof FeatureFlagManagerInterface) {
                return $manager->isEnabled($flagName, $context);
            }
        } catch (RuntimeException | Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Перевірка, чи вимкнено feature flag
     */
    public static function disabled(string $flagName, array $context = []): bool
    {
        return ! static::enabled($flagName, $context);
    }

    /**
     * Отримання значення feature flag
     */
    public static function get(string $flagName, mixed $default = false, array $context = []): mixed
    {
        try {
            $manager = static::getFacadeRoot();
            if ($manager instanceof FeatureFlagManagerInterface) {
                return $manager->get($flagName, $default, $context);
            }
        } catch (RuntimeException | Exception $e) {
            return $default;
        }

        return $default;
    }

    /**
     * Встановлення значення feature flag
     */
    public static function set(string $flagName, mixed $value): void
    {
        try {
            $manager = static::getFacadeRoot();
            if ($manager instanceof FeatureFlagManagerInterface) {
                $manager->set($flagName, $value);
            }
        } catch (RuntimeException | Exception $e) {
            // Ігноруємо помилки
        }
    }
}
