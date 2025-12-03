<?php

/**
 * Менеджер хуків та подій системи
 *
 * Управляє фільтрами (filters) та подіями (actions)
 * Фільтри - модифікують дані та повертають результат
 * Події - виконують дії без повернення даних
 *
 * @package Engine\System
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../contracts/HookManagerInterface.php';
require_once __DIR__ . '/../contracts/HookRegistryInterface.php';
require_once __DIR__ . '/hooks/HookListener.php';
require_once __DIR__ . '/hooks/HookType.php';

final class HookManager implements HookManagerInterface
{
    /**
     * @var array<string,SplPriorityQueue>
     */
    private array $listeners = [];

    /**
     * @var array<string,int>
     */
    private array $callStats = [];

    public function on(string $hookName, callable $listener, int $priority = 10, bool $once = false): void
    {
        $this->register($hookName, HookType::Action, $listener, $priority, $once);
    }

    public function filter(string $hookName, callable $listener, int $priority = 10): void
    {
        $this->register($hookName, HookType::Filter, $listener, $priority);
    }

    public function registerAction(string $hookName, callable $listener, int $priority = 10, bool $once = false): void
    {
        $this->on($hookName, $listener, $priority, $once);
    }

    public function registerFilter(string $hookName, callable $listener, int $priority = 10): void
    {
        $this->filter($hookName, $listener, $priority);
    }

    public function remove(string $hookName, ?callable $listener = null): void
    {
        if (! isset($this->listeners[$hookName])) {
            return;
        }

        if ($listener === null) {
            unset($this->listeners[$hookName]);

            return;
        }

        $queue = new SplPriorityQueue();
        $queue->setExtractFlags(SplPriorityQueue::EXTR_DATA);

        foreach ($this->getListeners($hookName) as $stored) {
            if ($stored->listener !== $listener) {
                $queue->insert($stored, $stored->priority);
            }
        }

        $this->listeners[$hookName] = $queue;
    }

    public function dispatch(string $hookName, mixed ...$payload): void
    {
        /**
         * @var array<int, mixed> $payloadArray
         */
        $payloadArray = array_values($payload);
        $listenersCount = 0;
        foreach ($this->iterateListeners($hookName, HookType::Action) as $listener) {
            $this->callListener($hookName, $listener, $payloadArray);
            $listenersCount++;
        }
        
        // DEBUG: логуємо виконання хука
        if ($listenersCount > 0) {
            logger()->logDebug('Хук виконано', [
                'hook' => $hookName,
                'listeners' => $listenersCount,
            ]);
        }
    }

    /**
     * @param string $hookName
     * @param mixed $value
     * @param array<string, mixed> $context
     * @return mixed
     */
    public function apply(string $hookName, mixed $value, array $context = []): mixed
    {
        $payload = [$value, $context];

        foreach ($this->iterateListeners($hookName, HookType::Filter) as $listener) {
            $result = $this->callListener($hookName, $listener, $payload);

            if ($result !== null) {
                $value = $result;
                $payload[0] = $value;
            }
        }

        return $value;
    }

    public function has(string $hookName): bool
    {
        return isset($this->listeners[$hookName]) && $this->listeners[$hookName]->count() > 0;
    }

    public function flush(): void
    {
        $this->listeners = [];
        $this->callStats = [];
    }

    /**
     * @return array<int, HookListener>
     */
    public function getListeners(string $hookName): array
    {
        if (! isset($this->listeners[$hookName])) {
            return [];
        }

        $queue = clone $this->listeners[$hookName];
        $result = [];

        foreach ($queue as $listener) {
            $result[] = $listener;
        }

        return $result;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getAllHooks(): array
    {
        $result = [];

        foreach (array_keys($this->listeners) as $hookName) {
            $result[$hookName] = array_map(
                fn (HookListener $listener) => [
                    'type' => $listener->type->value,
                    'priority' => $listener->priority,
                    'once' => $listener->once,
                    'callback' => $listener->listener,
                ],
                $this->getListeners($hookName)
            );
        }

        return $result;
    }

    /**
     * @return array<string, int|array<string, int>>
     */
    public function getStats(): array
    {
        return [
            'total_hooks' => count($this->listeners),
            'hook_calls' => $this->callStats,
        ];
    }

    private function register(string $hookName, HookType $type, callable $listener, int $priority = 10, bool $once = false): void
    {
        if (! isset($this->listeners[$hookName])) {
            $this->listeners[$hookName] = new SplPriorityQueue();
            $this->listeners[$hookName]->setExtractFlags(SplPriorityQueue::EXTR_DATA);
        }

        $this->listeners[$hookName]->insert(new HookListener($type, $listener, $priority, $once), $priority);
    }

    /**
     * @return iterable<HookListener>
     */
    private function iterateListeners(string $hookName, HookType $type): iterable
    {
        if (! isset($this->listeners[$hookName])) {
            return [];
        }

        $queue = clone $this->listeners[$hookName];
        $queue->rewind();

        while ($queue->valid()) {
            /** @var HookListener $listener */
            $listener = $queue->current();
            $queue->next();

            if ($listener->type !== $type) {
                continue;
            }

            yield $listener;

            if ($listener->once) {
                $this->remove($hookName, $listener->listener);
            }
        }
    }

    /**
     * @param string $hookName
     * @param HookListener $listener
     * @param array<int, mixed> $payload
     * @return mixed
     */
    private function callListener(string $hookName, HookListener $listener, array $payload): mixed
    {
        $this->callStats[$hookName] = ($this->callStats[$hookName] ?? 0) + 1;

        try {
            return ($listener->listener)(...$payload);
        } catch (Throwable $e) {
            $this->logHookError($hookName, $e);

            return null;
        }
    }
    /**
     * Логувати помилку хука
     */
    private function logHookError(string $hookName, Throwable $e): void
    {
        $message = "Помилка виконання хука '{$hookName}': " . $e->getMessage();

        try {
            logger()->logError($message, [
                'hook' => $hookName,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        } catch (Exception $logException) {
            error_log($message);
        }
    }
}
