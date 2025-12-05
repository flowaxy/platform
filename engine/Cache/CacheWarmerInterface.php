<?php

/**
 * Інтерфейс для Cache Warmer
 * 
 * @package Engine\Infrastructure\Cache
 * @version 1.0.0
 */

declare(strict_types=1);

interface CacheWarmerInterface
{
    /**
     * Нагрівання кешу
     * 
     * @return void
     */
    public function warm(): void;

    /**
     * Перевірка, чи підтримується нагрівання
     * 
     * @return bool
     */
    public function isOptional(): bool;
}

