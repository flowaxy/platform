<?php

declare(strict_types=1);

final class HookListener
{
    public readonly \Closure $listener;

    public function __construct(
        public readonly HookType $type,
        callable $listener,
        public readonly int $priority,
        public bool $once = false
    ) {
        $this->listener = $listener instanceof \Closure
            ? $listener
            : \Closure::fromCallable($listener);
    }
}
