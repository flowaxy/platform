<?php

/**
 * Інтерфейс драйвера кешу
 * 
 * @package Flowaxy\Core\Infrastructure\Cache
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Flowaxy\Core\Infrastructure\Cache;

interface CacheDriverInterface
{
    /**
     * Отримання значення з кешу
     * 
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Збереження значення в кеш
     * 
     * @param string $key Ключ
     * @param mixed $value Значення
     * @param int|null $ttl Час життя в секундах
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Видалення значення з кешу
     * 
     * @param string $key Ключ
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * Перевірка наявності ключа
     * 
     * @param string $key Ключ
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Очищення всього кешу
     * 
     * @return bool
     */
    public function clear(): bool;
}
