<?php

/**
 * Базовий клас Hook Middleware
 *
 * @package Engine\System\Hooks
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Hooks;

require_once __DIR__ . '/HookMiddlewareInterface.php';

use Flowaxy\Core\System\Hooks\HookMiddlewareInterface;

abstract class HookMiddleware implements HookMiddlewareInterface
{
    protected int $priority = 10;

    /**
     * Конструктор
     *
     * @param int $priority Пріоритет middleware
     */
    public function __construct(int $priority = 10)
    {
        $this->priority = $priority;
    }

    /**
     * {@inheritDoc}
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * {@inheritDoc}
     */
    abstract public function handle(string $hookName, array $payload): array;
}
