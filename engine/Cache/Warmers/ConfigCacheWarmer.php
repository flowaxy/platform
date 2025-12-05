<?php

/**
 * Warmer для кешування конфігурацій
 * 
 * @package Engine\Infrastructure\Cache\Warmers
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/CacheWarmerInterface.php';
require_once dirname(__DIR__) . '/Cache.php';

final class ConfigCacheWarmer implements CacheWarmerInterface
{
    private Cache $cache;

    public function __construct(?Cache $cache = null)
    {
        $this->cache = $cache ?? Cache::getInstance();
    }

    /**
     * Нагрівання кешу конфігурацій
     */
    public function warm(): void
    {
        // Кешуємо системні конфігурації
        if (class_exists('SystemConfig')) {
            $config = SystemConfig::getInstance();
            $this->cache->set('config.system', $config->getAll(), 3600);
        }

        // Кешуємо налаштування модулів
        if (file_exists(dirname(__DIR__, 3) . '/core/config/modules.php')) {
            $modules = require dirname(__DIR__, 3) . '/core/config/modules.php';
            $this->cache->set('config.modules', $modules, 3600);
        }
    }

    /**
     * Перевірка, чи підтримується нагрівання
     */
    public function isOptional(): bool
    {
        return true;
    }
}

