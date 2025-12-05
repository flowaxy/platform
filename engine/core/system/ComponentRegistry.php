<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Contracts/ComponentRegistryInterface.php';

use Flowaxy\Core\Contracts\ComponentRegistryInterface;

final class ComponentRegistry implements ComponentRegistryInterface
{
    /**
     * @var array<string,SplPriorityQueue>
     */
    private array $components = [];

    /**
     * @param string $contract
     * @param callable|string $resolver
     * @param int $priority
     * @param array<string, mixed> $meta
     * @return void
     */
    public function register(string $contract, callable|string $resolver, int $priority = 10, array $meta = []): void
    {
        if (! isset($this->components[$contract])) {
            $this->components[$contract] = new SplPriorityQueue();
            $this->components[$contract]->setExtractFlags(SplPriorityQueue::EXTR_DATA);
        }

        $this->components[$contract]->insert([
            'resolver' => $resolver,
            'meta' => $meta,
        ], $priority);
    }

    public function resolve(string $contract): mixed
    {
        if (! isset($this->components[$contract])) {
            throw new RuntimeException("Component for {$contract} not registered");
        }

        $queue = clone $this->components[$contract];
        $definition = $queue->current() ?? null;

        if ($definition === null) {
            throw new RuntimeException("No resolver registered for {$contract}");
        }

        $resolver = $definition['resolver'];
        if (is_string($resolver) && class_exists($resolver)) {
            return container()->make($resolver);
        }

        if ($resolver instanceof Closure) {
            return $resolver(container());
        }

        if (is_callable($resolver)) {
            return $resolver(container());
        }

        return $resolver;
    }

    public function has(string $contract): bool
    {
        return isset($this->components[$contract]) && $this->components[$contract]->count() > 0;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->components as $contract => $queue) {
            $entries = [];
            foreach (clone $queue as $item) {
                $entries[] = $item;
            }
            $result[$contract] = $entries;
        }

        return $result;
    }

    public function clear(?string $contract = null): void
    {
        if ($contract === null) {
            $this->components = [];

            return;
        }

        unset($this->components[$contract]);
    }
}
