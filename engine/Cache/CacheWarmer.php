<?php

/**
 * Система попереднього нагрівання кешу
 * 
 * Реєструє та виконує warmers для попереднього наповнення кешу
 * 
 * @package Engine\Infrastructure\Cache
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/CacheWarmerInterface.php';
require_once __DIR__ . '/Cache.php';

final class CacheWarmer
{
    /**
     * @var array<int, CacheWarmerInterface>
     */
    private array $warmers = [];

    private Cache $cache;

    public function __construct(?Cache $cache = null)
    {
        $this->cache = $cache ?? Cache::getInstance();
    }

    /**
     * Реєстрація warmer
     * 
     * @param CacheWarmerInterface $warmer Warmer для реєстрації
     * @return void
     */
    public function add(CacheWarmerInterface $warmer): void
    {
        $this->warmers[] = $warmer;
    }

    /**
     * Виконання всіх warmers
     * 
     * @return void
     */
    public function warm(): void
    {
        foreach ($this->warmers as $warmer) {
            try {
                $warmer->warm();
            } catch (Throwable $e) {
                // Логуємо помилку, але продовжуємо з іншими warmers
                if (function_exists('logger')) {
                    logger()->logError('CacheWarmer помилка: ' . $e->getMessage(), [
                        'exception' => $e,
                        'warmer' => get_class($warmer),
                    ]);
                }
            }
        }
    }

    /**
     * Отримання кількості зареєстрованих warmers
     * 
     * @return int
     */
    public function count(): int
    {
        return count($this->warmers);
    }

    /**
     * Очищення всіх warmers
     * 
     * @return void
     */
    public function clear(): void
    {
        $this->warmers = [];
    }
}

