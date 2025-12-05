<?php

/**
 * Warmer для кешування маршрутів
 * 
 * @package Engine\Infrastructure\Cache\Warmers
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/CacheWarmerInterface.php';
require_once dirname(__DIR__) . '/Cache.php';

final class RoutesCacheWarmer implements CacheWarmerInterface
{
    private Cache $cache;

    public function __construct(?Cache $cache = null)
    {
        $this->cache = $cache ?? Cache::getInstance();
    }

    /**
     * Нагрівання кешу маршрутів
     */
    public function warm(): void
    {
        // Кешуємо маршрути API
        $apiRoutesFile = dirname(__DIR__, 3) . '/interface/api/routes.php';
        if (file_exists($apiRoutesFile)) {
            $routes = require $apiRoutesFile;
            $this->cache->set('routes.api', $routes, 3600);
        }

        // Кешуємо адмін маршрути
        $adminRoutesFile = dirname(__DIR__, 3) . '/interface/admin-ui/includes/admin-routes.php';
        if (file_exists($adminRoutesFile)) {
            $routes = require $adminRoutesFile;
            $this->cache->set('routes.admin', $routes, 3600);
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

