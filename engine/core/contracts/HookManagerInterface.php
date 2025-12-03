<?php

/**
 * Контракт системи хуків.
 *
 * @package Engine\Interfaces
 */

declare(strict_types=1);

interface HookManagerInterface extends HookRegistryInterface
{
    public function on(string $hookName, callable $listener, int $priority = 10, bool $once = false): void;

    public function filter(string $hookName, callable $listener, int $priority = 10): void;

    public function dispatch(string $hookName, mixed ...$payload): void;

    /**
     * @param string $hookName
     * @param mixed $value
     * @param array<string, mixed> $context
     * @return mixed
     */
    public function apply(string $hookName, mixed $value, array $context = []): mixed;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function getAllHooks(): array;

    /**
     * @return array<string, int|array<string, int>>
     */
    public function getStats(): array;
}
