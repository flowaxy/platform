<?php

/**
 * Інтерфейс реєстру компонентів
 *
 * @package Flowaxy\Core\Contracts
 */

declare(strict_types=1);

namespace Flowaxy\Core\Contracts;

interface ComponentRegistryInterface
{
    /**
     * @param string $contract
     * @param callable|string $resolver
     * @param int $priority
     * @param array<string, mixed> $meta
     * @return void
     */
    public function register(string $contract, callable|string $resolver, int $priority = 10, array $meta = []): void;

    public function resolve(string $contract): mixed;

    public function has(string $contract): bool;

    /**
     * @return array<string, mixed>
     */
    public function all(): array;

    public function clear(?string $contract = null): void;
}
