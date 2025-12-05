<?php

/**
 * Інтерфейс підписника подій
 *
 * @package Flowaxy\Core\System\Events
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Events;

interface EventSubscriber
{
    /**
     * Отримання підписок на події
     *
     * @return array<string, string|array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array;
}
