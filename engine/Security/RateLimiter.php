<?php

/**
 * Обмеження швидкості запитів
 * 
 * Підтримка різних стратегій обмеження (IP, User, Route)
 * 
 * @package Engine\Infrastructure\Security
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/RateLimitStrategy.php';

final class RateLimiter
{
    private RateLimitStrategy $strategy;
    private int $maxRequests;
    private int $windowSeconds;
    private ?object $cache = null;

    /**
     * Конструктор
     * 
     * @param RateLimitStrategy $strategy Стратегія обмеження
     * @param int $maxRequests Максимальна кількість запитів
     * @param int $windowSeconds Вікно часу в секундах
     */
    public function __construct(
        RateLimitStrategy $strategy = RateLimitStrategy::IP,
        int $maxRequests = 60,
        int $windowSeconds = 60
    ) {
        $this->strategy = $strategy;
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    /**
     * Перевірка обмеження швидкості
     * 
     * @param string|null $identifier Ідентифікатор (IP, User ID, Route)
     * @return bool True якщо ліміт перевищено
     */
    public function isLimited(?string $identifier = null): bool
    {
        $key = $this->getKey($identifier);
        $cache = $this->getCache();

        if (!$cache) {
            return false; // Якщо кеш недоступний, не обмежуємо
        }

        $current = $cache->get($key, 0);
        
        if ($current >= $this->maxRequests) {
            return true;
        }

        // Збільшуємо лічильник
        $cache->set($key, $current + 1, $this->windowSeconds);
        
        return false;
    }

    /**
     * Отримання поточного лічильника
     * 
     * @param string|null $identifier Ідентифікатор
     * @return int
     */
    public function getCurrentCount(?string $identifier = null): int
    {
        $key = $this->getKey($identifier);
        $cache = $this->getCache();

        if (!$cache) {
            return 0;
        }

        return (int)$cache->get($key, 0);
    }

    /**
     * Отримання залишкового часу до скидання
     * 
     * @param string|null $identifier Ідентифікатор
     * @return int Секунди до скидання
     */
    public function getRemainingTime(?string $identifier = null): int
    {
        $key = $this->getKey($identifier);
        $cache = $this->getCache();

        if (!$cache) {
            return 0;
        }

        // Спробуємо отримати TTL з кешу
        // Це спрощена реалізація - в реальності потрібно зберігати час створення
        return $this->windowSeconds;
    }

    /**
     * Скидання ліміту
     * 
     * @param string|null $identifier Ідентифікатор
     * @return void
     */
    public function reset(?string $identifier = null): void
    {
        $key = $this->getKey($identifier);
        $cache = $this->getCache();

        if ($cache) {
            $cache->delete($key);
        }
    }

    /**
     * Генерація ключа для кешу
     * 
     * @param string|null $identifier Ідентифікатор
     * @return string
     */
    private function getKey(?string $identifier): string
    {
        $parts = ['rate_limit', $this->strategy->value];

        switch ($this->strategy) {
            case RateLimitStrategy::IP:
                $parts[] = $identifier ?? $this->getClientIp();
                break;

            case RateLimitStrategy::User:
                $userId = $identifier ?? $this->getUserId();
                if ($userId) {
                    $parts[] = $userId;
                } else {
                    $parts[] = $this->getClientIp(); // Fallback на IP
                }
                break;

            case RateLimitStrategy::Route:
                $parts[] = $identifier ?? $this->getCurrentRoute();
                break;

            case RateLimitStrategy::IPAndRoute:
                $parts[] = $this->getClientIp();
                $parts[] = $identifier ?? $this->getCurrentRoute();
                break;
        }

        return implode(':', $parts);
    }

    /**
     * Отримання IP адреси клієнта
     */
    private function getClientIp(): string
    {
        if (class_exists('Security')) {
            return Security::getClientIp();
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Отримання ID користувача
     */
    private function getUserId(): ?string
    {
        if (function_exists('sessionManager')) {
            $session = sessionManager();
            return $session->get('user_id');
        }

        return null;
    }

    /**
     * Отримання поточного маршруту
     */
    private function getCurrentRoute(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return $path ?? '/';
    }

    /**
     * Отримання кешу
     */
    private function getCache(): ?object
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (function_exists('cache')) {
            $this->cache = cache();
            return $this->cache;
        }

        return null;
    }
}

