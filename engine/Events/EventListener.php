<?php

/**
 * Базовий клас слухача подій
 *
 * @package Flowaxy\Core\System\Events
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Events;

abstract class EventListener
{
    /**
     * Обробка події
     *
     * @param Event $event Подія для обробки
     * @return void
     */
    abstract public function handle(Event $event): void;

    /**
     * Отримання пріоритету слухача
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 10;
    }

    /**
     * Перевірка, чи слухач підходить для події
     *
     * @param Event $event Подія
     * @return bool
     */
    public function shouldHandle(Event $event): bool
    {
        return true;
    }
}
