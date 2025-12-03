<?php

/**
 * Інтерфейс для всіх типів сховища
 * Єдиний API для роботи з cookies, sessions, localStorage, sessionStorage
 *
 * @package Engine\Interfaces
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

interface StorageInterface
{
    /**
     * Отримання значення з сховища
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Встановлення значення в сховище
     *
     * @param string $key Ключ
     * @param mixed $value Значення
     * @return bool
     */
    public function set(string $key, $value): bool;

    /**
     * Перевірка наявності ключа в сховищі
     *
     * @param string $key Ключ
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Видалення значення з сховища
     *
     * @param string $key Ключ
     * @return bool
     */
    public function remove(string $key): bool;

    /**
     * Отримання всіх даних з сховища
     *
     * @return array
     */
    public function all(): array;

    /**
     * Очищення всіх даних з сховища
     *
     * @return bool
     */
    public function clear(): bool;

    /**
     * Отримання кількох значень за ключами
     *
     * @param array $keys Масив ключів
     * @return array
     */
    public function getMultiple(array $keys): array;

    /**
     * Встановлення кількох значень
     *
     * @param array $values Масив ключ => значення
     * @return bool
     */
    public function setMultiple(array $values): bool;

    /**
     * Видалення кількох значень
     *
     * @param array $keys Масив ключів
     * @return bool
     */
    public function removeMultiple(array $keys): bool;
}
