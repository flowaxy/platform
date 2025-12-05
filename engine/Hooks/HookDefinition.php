<?php

declare(strict_types=1);

final class HookDefinition
{
    /**
     * @param string $name Назва хука
     * @param HookType $type Тип хука
     * @param callable $listener Callback функція для виконання
     * @param int $priority Пріоритет виконання
     * @param bool $once Виконати один раз
     */
    public function __construct(
        public readonly string $name,
        public readonly HookType $type,
        /**
         * @var callable
         */
        public readonly mixed $listener,
        public readonly int $priority = 10,
        public readonly bool $once = false,
    ) {
    }
}
