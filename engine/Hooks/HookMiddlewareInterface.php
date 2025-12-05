<?php

/**
 * Інтерфейс для Hook Middleware
 *
 * @package Engine\System\Hooks
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Hooks;

interface HookMiddlewareInterface
{
    /**
     * Обробка хука перед виконанням
     *
     * @param string $hookName Назва хука
     * @param array<int, mixed> $payload Дані хука
     * @return array<int, mixed> Модифіковані дані
     */
    public function handle(string $hookName, array $payload): array;

    /**
     * Отримання пріоритету middleware (менше = вище пріоритет)
     *
     * @return int
     */
    public function getPriority(): int;
}
