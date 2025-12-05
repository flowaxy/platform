<?php

/**
 * Middleware для обмеження швидкості API запитів
 * 
 * @package Engine\Interface\API\Middleware
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../infrastructure/security/RateLimiter.php';
require_once __DIR__ . '/../../infrastructure/security/RateLimitStrategy.php';

final class RateLimitMiddleware
{
    /**
     * Створення middleware з обмеженням
     * 
     * @param int $maxRequests Максимальна кількість запитів
     * @param int $windowSeconds Вікно часу
     * @return callable
     */
    public static function create(int $maxRequests = 60, int $windowSeconds = 60): callable
    {
        return function () use ($maxRequests, $windowSeconds) {
            $limiter = new RateLimiter(
                RateLimitStrategy::IPAndRoute,
                $maxRequests,
                $windowSeconds
            );

            $route = $_SERVER['REQUEST_URI'] ?? '/';

            if ($limiter->isLimited($route)) {
                $remaining = $limiter->getRemainingTime($route);
                
                ApiResponse::error('Too many requests', 429)
                    ->addHeader('Retry-After', (string)$remaining)
                    ->addHeader('X-RateLimit-Limit', (string)$maxRequests)
                    ->addHeader('X-RateLimit-Remaining', (string)max(0, $maxRequests - $limiter->getCurrentCount($route)))
                    ->send();
                return;
            }

            return null;
        };
    }
}

