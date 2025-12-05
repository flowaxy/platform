<?php

/**
 * Стратегії обмеження швидкості запитів
 * 
 * @package Engine\Infrastructure\Security
 * @version 1.0.0
 */

declare(strict_types=1);

enum RateLimitStrategy: string
{
    case IP = 'ip';
    case User = 'user';
    case Route = 'route';
    case IPAndRoute = 'ip_route';
}

