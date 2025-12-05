<?php

/**
 * Prefetching для кешування
 *
 * Попереднє завантаження даних у кеш на основі патернів доступу
 * або прогнозованих потреб.
 *
 * @package Flowaxy\Core\Infrastructure\Cache
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Infrastructure\Cache;

final class CachePrefetcher
{
    /**
     * @var array<string, int> Статистика доступу до ключів (ключ => кількість доступів)
     */
    private array $accessStats = [];

    /**
     * @var array<string, array<string>> Патерни доступу (ключ => масив пов'язаних ключів)
     */
    private array $accessPatterns = [];

    /**
     * @var int Максимальна кількість ключів для prefetch
     */
    private int $maxPrefetchKeys = 50;

    public function __construct(
        private Cache $cache
    ) {
    }

    /**
     * Реєстрація доступу до ключа
     *
     * @param string $key Ключ
     * @return void
     */
    public function recordAccess(string $key): void
    {
        $this->accessStats[$key] = ($this->accessStats[$key] ?? 0) + 1;
    }

    /**
     * Реєстрація патерну доступу (послідовність ключів)
     *
     * @param array<string> $keys Послідовність ключів
     * @return void
     */
    public function recordPattern(array $keys): void
    {
        if (count($keys) < 2) {
            return;
        }

        $firstKey = $keys[0];
        $relatedKeys = array_slice($keys, 1);

        if (!isset($this->accessPatterns[$firstKey])) {
            $this->accessPatterns[$firstKey] = [];
        }

        // Додаємо пов'язані ключі
        foreach ($relatedKeys as $relatedKey) {
            if (!in_array($relatedKey, $this->accessPatterns[$firstKey], true)) {
                $this->accessPatterns[$firstKey][] = $relatedKey;
            }
        }
    }

    /**
     * Prefetch пов'язаних ключів на основі патернів
     *
     * @param string $key Поточний ключ
     * @param callable $loader Функція для завантаження даних
     * @return int Кількість prefetched ключів
     */
    public function prefetchRelated(string $key, callable $loader): int
    {
        if (!isset($this->accessPatterns[$key])) {
            return 0;
        }

        $relatedKeys = array_slice($this->accessPatterns[$key], 0, $this->maxPrefetchKeys);
        $prefetched = 0;

        foreach ($relatedKeys as $relatedKey) {
            // Перевіряємо, чи немає вже в кеші
            if (!$this->cache->has($relatedKey)) {
                try {
                    $value = $loader($relatedKey);
                    if ($value !== null) {
                        $this->cache->set($relatedKey, $value);
                        $prefetched++;
                    }
                } catch (\Throwable $e) {
                    // Ігноруємо помилки prefetch
                }
            }
        }

        return $prefetched;
    }

    /**
     * Prefetch найпопулярніших ключів
     *
     * @param callable $loader Функція для завантаження даних
     * @param int $limit Кількість ключів для prefetch
     * @return int Кількість prefetched ключів
     */
    public function prefetchPopular(callable $loader, int $limit = 20): int
    {
        // Сортуємо за популярністю
        arsort($this->accessStats);
        $popularKeys = array_slice(array_keys($this->accessStats), 0, $limit);

        $prefetched = 0;

        foreach ($popularKeys as $key) {
            // Перевіряємо, чи немає вже в кеші
            if (!$this->cache->has($key)) {
                try {
                    $value = $loader($key);
                    if ($value !== null) {
                        $this->cache->set($key, $value);
                        $prefetched++;
                    }
                } catch (\Throwable $e) {
                    // Ігноруємо помилки prefetch
                }
            }
        }

        return $prefetched;
    }

    /**
     * Очищення статистики
     *
     * @return void
     */
    public function clearStats(): void
    {
        $this->accessStats = [];
        $this->accessPatterns = [];
    }

    /**
     * Отримання статистики доступу
     *
     * @return array<string, int>
     */
    public function getAccessStats(): array
    {
        return $this->accessStats;
    }

    /**
     * Отримання патернів доступу
     *
     * @return array<string, array<string>>
     */
    public function getAccessPatterns(): array
    {
        return $this->accessPatterns;
    }

    /**
     * Встановлення максимальної кількості ключів для prefetch
     *
     * @param int $max
     * @return void
     */
    public function setMaxPrefetchKeys(int $max): void
    {
        $this->maxPrefetchKeys = max(1, $max);
    }
}
