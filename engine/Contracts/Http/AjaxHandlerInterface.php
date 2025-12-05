<?php

/**
 * Інтерфейс для обробки AJAX запитів
 *
 * Визначає контракт для всіх реалізацій обробки AJAX запитів
 * Дозволяє замінювати реалізацію обробки AJAX без зміни коду, що його використовує
 *
 * @package Engine\Interfaces
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

interface AjaxHandlerInterface
{
    /**
     * Реєстрація обробника дії
     *
     * @param string $action Назва дії
     * @param callable $handler Обробник
     * @param array $options Опції (requireCsrf, requireAuth, validate, method)
     * @return self
     */
    public function register(string $action, callable $handler, array $options = []): self;

    /**
     * Встановлення обробника помилок
     *
     * @param callable $handler
     * @return self
     */
    public function setErrorHandler(callable $handler): self;

    /**
     * Встановлення обробника авторизації
     *
     * @param callable $handler
     * @return self
     */
    public function setAuthCallback(callable $handler): self;

    /**
     * Перевірка чи є запит AJAX
     *
     * @return bool
     */
    public static function isAjax(): bool;

    /**
     * Обробка AJAX запиту
     *
     * @param string|null $action Дія (якщо null, береться з запиту)
     * @return void
     */
    public function handle(?string $action = null): void;

    /**
     * Швидка реєстрація декількох дій
     *
     * @param array $actions Масив дій ['action' => callable, ...]
     * @return self
     */
    public function registerMultiple(array $actions): self;

    /**
     * Отримання санітизованих даних з запиту
     *
     * @param array $keys Ключі для отримання (якщо порожній, повертає всі)
     * @return array
     */
    public static function getSanitizedData(array $keys = []): array;

    /**
     * Отримання файлу з запиту
     *
     * @param string $key Ключ файлу
     * @return array|null
     */
    public static function getFile(string $key): ?array;

    /**
     * Статичний метод: Швидка перевірка AJAX
     *
     * @return bool
     */
    public static function check(): bool;
}
