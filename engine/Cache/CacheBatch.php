<?php

/**
 * Batch операції для кешування
 *
 * Дозволяє виконувати множинні операції з кешем за один раз,
 * що значно покращує продуктивність при роботі з великою кількістю ключів.
 *
 * @package Flowaxy\Core\Infrastructure\Cache
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Infrastructure\Cache;

final class CacheBatch
{
    public function __construct(
        private Cache $cache
    ) {
    }

    /**
     * Множинне отримання значень
     *
     * @param array<string> $keys Масив ключів
     * @param mixed $default Значення за замовчуванням для відсутніх ключів
     * @return array<string, mixed> Асоціативний масив ключ => значення
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->cache->get($key, $default);
        }

        return $results;
    }

    /**
     * Множинне збереження значень
     *
     * @param array<string, mixed> $values Асоціативний масив ключ => значення
     * @param int|null $ttl TTL для всіх значень
     * @return array<string, bool> Результати операцій (ключ => успіх)
     */
    public function setMultiple(array $values, ?int $ttl = null): array
    {
        $results = [];

        foreach ($values as $key => $value) {
            $results[$key] = $this->cache->set($key, $value, $ttl);
        }

        return $results;
    }

    /**
     * Множинне видалення ключів
     *
     * @param array<string> $keys Масив ключів
     * @return array<string, bool> Результати операцій (ключ => успіх)
     */
    public function deleteMultiple(array $keys): array
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->cache->delete($key);
        }

        return $results;
    }

    /**
     * Перевірка наявності множини ключів
     *
     * @param array<string> $keys Масив ключів
     * @return array<string, bool> Результати перевірок (ключ => існує)
     */
    public function hasMultiple(array $keys): array
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->cache->has($key);
        }

        return $results;
    }
}
