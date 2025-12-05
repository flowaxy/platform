<?php

/**
 * Контракт для реєстрації хуків/слухачів.
 *
 * @package Flowaxy\Core\Contracts
 */

declare(strict_types=1);

namespace Flowaxy\Core\Contracts;

interface HookRegistryInterface
{
    public function registerAction(string $hookName, callable $listener, int $priority = 10, bool $once = false): void;

    public function registerFilter(string $hookName, callable $listener, int $priority = 10): void;

    public function remove(string $hookName, ?callable $listener = null): void;

    /**
     * @return array<int, HookListener>
     */
    public function getListeners(string $hookName): array;

    public function has(string $hookName): bool;

    public function flush(): void;
}
