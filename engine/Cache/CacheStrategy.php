<?php

/**
 * Стратегії кешування
 *
 * Визначає різні стратегії запису та читання з кешу:
 * - Write-Through: запис у кеш та джерело даних одночасно
 * - Write-Back: запис спочатку у кеш, потім у джерело даних (lazy)
 * - Cache-Aside: додаток самостійно керує кешем
 *
 * @package Flowaxy\Core\Infrastructure\Cache
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Infrastructure\Cache;

/**
 * Інтерфейс стратегії кешування
 */
interface CacheStrategyInterface
{
    /**
     * Читання з кешу
     *
     * @param string $key Ключ
     * @param callable $loader Функція для завантаження даних, якщо їх немає в кеші
     * @param int|null $ttl TTL для збереження
     * @return mixed
     */
    public function get(string $key, callable $loader, ?int $ttl = null): mixed;

    /**
     * Запис у кеш
     *
     * @param string $key Ключ
     * @param mixed $value Значення
     * @param int|null $ttl TTL
     * @param callable|null $persister Функція для збереження у джерело даних
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $ttl = null, ?callable $persister = null): bool;

    /**
     * Видалення з кешу
     *
     * @param string $key Ключ
     * @param callable|null $deleter Функція для видалення з джерела даних
     * @return bool
     */
    public function delete(string $key, ?callable $deleter = null): bool;
}

/**
 * Стратегія Cache-Aside (найпоширеніша)
 *
 * Додаток самостійно керує кешем:
 * 1. Перевіряє кеш
 * 2. Якщо немає - завантажує з джерела та зберігає в кеш
 * 3. При записі - оновлює джерело та кеш окремо
 */
final class CacheAsideStrategy implements CacheStrategyInterface
{
    public function __construct(
        private Cache $cache
    ) {
    }

    public function get(string $key, callable $loader, ?int $ttl = null): mixed
    {
        $value = $this->cache->get($key);

        if ($value === null) {
            $value = $loader();
            if ($value !== null) {
                $this->cache->set($key, $value, $ttl);
            }
        }

        return $value;
    }

    public function set(string $key, mixed $value, ?int $ttl = null, ?callable $persister = null): bool
    {
        $result = $this->cache->set($key, $value, $ttl);

        if ($persister !== null) {
            $persister($value);
        }

        return $result;
    }

    public function delete(string $key, ?callable $deleter = null): bool
    {
        $result = $this->cache->delete($key);

        if ($deleter !== null) {
            $deleter();
        }

        return $result;
    }
}

/**
 * Стратегія Write-Through
 *
 * Запис у кеш та джерело даних одночасно:
 * 1. Записує у джерело даних
 * 2. Записує у кеш
 */
final class WriteThroughStrategy implements CacheStrategyInterface
{
    public function __construct(
        private Cache $cache
    ) {
    }

    public function get(string $key, callable $loader, ?int $ttl = null): mixed
    {
        $value = $this->cache->get($key);

        if ($value === null) {
            $value = $loader();
            if ($value !== null && $ttl !== null) {
                $this->cache->set($key, $value, $ttl);
            }
        }

        return $value;
    }

    public function set(string $key, mixed $value, ?int $ttl = null, ?callable $persister = null): bool
    {
        // Спочатку зберігаємо у джерело даних
        if ($persister !== null) {
            $persister($value);
        }

        // Потім у кеш
        return $this->cache->set($key, $value, $ttl);
    }

    public function delete(string $key, ?callable $deleter = null): bool
    {
        // Спочатку видаляємо з джерела даних
        if ($deleter !== null) {
            $deleter();
        }

        // Потім з кешу
        return $this->cache->delete($key);
    }
}

/**
 * Стратегія Write-Back (Write-Behind)
 *
 * Запис спочатку у кеш, потім у джерело даних (lazy):
 * 1. Записує у кеш
 * 2. Відкладає запис у джерело даних (через чергу або таймер)
 */
final class WriteBackStrategy implements CacheStrategyInterface
{
    /**
     * @var array<string, array{value: mixed, ttl: ?int, persister: ?callable}> Черга записів
     */
    private array $writeQueue = [];

    public function __construct(
        private Cache $cache,
        private int $flushInterval = 60 // Інтервал автоматичного flush в секундах
    ) {
    }

    public function get(string $key, callable $loader, ?int $ttl = null): mixed
    {
        $value = $this->cache->get($key);

        if ($value === null) {
            $value = $loader();
            if ($value !== null && $ttl !== null) {
                $this->cache->set($key, $value, $ttl);
            }
        }

        return $value;
    }

    public function set(string $key, mixed $value, ?int $ttl = null, ?callable $persister = null): bool
    {
        // Записуємо у кеш одразу
        $result = $this->cache->set($key, $value, $ttl);

        // Додаємо до черги для відкладеного запису
        if ($persister !== null) {
            $this->writeQueue[$key] = [
                'value' => $value,
                'ttl' => $ttl,
                'persister' => $persister,
            ];
        }

        return $result;
    }

    public function delete(string $key, ?callable $deleter = null): bool
    {
        // Видаляємо з кешу
        $result = $this->cache->delete($key);

        // Видаляємо з черги, якщо є
        unset($this->writeQueue[$key]);

        // Виконуємо видалення з джерела даних одразу
        if ($deleter !== null) {
            $deleter();
        }

        return $result;
    }

    /**
     * Виконання відкладених записів
     *
     * @return int Кількість виконаних записів
     */
    public function flush(): int
    {
        $count = 0;

        foreach ($this->writeQueue as $key => $item) {
            try {
                if ($item['persister'] !== null) {
                    ($item['persister'])($item['value']);
                    $count++;
                }
            } catch (\Throwable $e) {
                // Логуємо помилку, але продовжуємо
                if (function_exists('logger')) {
                    logger()->logError("Write-Back стратегія помилка для ключа '{$key}': " . $e->getMessage());
                }
            }
        }

        $this->writeQueue = [];
        return $count;
    }

    /**
     * Отримання розміру черги
     *
     * @return int
     */
    public function getQueueSize(): int
    {
        return count($this->writeQueue);
    }
}
