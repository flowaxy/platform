<?php

/**
 * Базовий клас події
 *
 * @package Flowaxy\Core\System\Events
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Events;

abstract class Event
{
    private bool $propagationStopped = false;
    private array $payload = [];

    /**
     * Конструктор
     *
     * @param array<string, mixed> $payload Дані події
     */
    public function __construct(array $payload = [])
    {
        $this->payload = $payload;
    }

    /**
     * Зупинка поширення події
     *
     * @return void
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * Перевірка, чи зупинено поширення
     *
     * @return bool
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Отримання payload події
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Отримання значення з payload
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    /**
     * Встановлення значення в payload
     *
     * @param string $key Ключ
     * @param mixed $value Значення
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->payload[$key] = $value;
    }
}
