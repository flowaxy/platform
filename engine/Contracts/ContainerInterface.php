<?php

/**
 * Контейнер залежностей ядра.
 *
 * @package Flowaxy\Core\Contracts
 */

declare(strict_types=1);

namespace Flowaxy\Core\Contracts;

interface ContainerInterface
{
    /**
     * Реєстрація звичайного бінда.
     *
     * @param string $abstract Ім'я або інтерфейс.
     * @param callable|string|null $concrete Реалізація або фабрика.
     */
    public function bind(string $abstract, callable|string|null $concrete = null): void;

    /**
     * Реєстрація singleton-бінда.
     *
     * @param string $abstract
     * @param callable|string|null $concrete
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void;

    /**
     * Реєстрація готового інстансу.
     *
     * @param string $abstract
     * @param object $instance
     */
    public function instance(string $abstract, object $instance): void;

    /**
     * Перевірка наявності бінда.
     */
    public function has(string $abstract): bool;

    /**
     * Отримання екземпляра.
     *
     * @template T
     * @param class-string<T>|string $abstract
     * @param array<string, mixed> $parameters
     * @return T|mixed
     */
    public function make(string $abstract, array $parameters = []);

    /**
     * Виклик callable з інжектованими залежностями.
     *
     * @param callable $callback
     * @param array<string, mixed> $parameters
     * @return mixed
     */
    public function call(callable $callback, array $parameters = []);

    /**
     * Скидання контейнера (для тестів).
     */
    public function flush(): void;
}
