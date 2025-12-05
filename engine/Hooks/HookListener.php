<?php

declare(strict_types=1);

namespace Flowaxy\Core\System\Hooks;

/**
 * Джерело хука для ізоляції
 */
enum HookSource: string
{
    case Core = 'core';      // Хук з ядра
    case Plugin = 'plugin';  // Хук з плагіна
    case Theme = 'theme';    // Хук з теми
}

final class HookListener
{
    public readonly \Closure $listener;

    /**
     * @param HookType $type Тип хука (Action або Filter)
     * @param callable $listener Callback для виконання
     * @param int $priority Пріоритет виконання
     * @param bool $once Чи виконати тільки один раз
     * @param HookSource $source Джерело хука (Core, Plugin, Theme)
     * @param string|null $sourceIdentifier Ідентифікатор джерела (slug плагіна/теми)
     * @param object|null $container Контейнер для ізольованого виконання (PluginContainer або ThemeContainer)
     */
    public function __construct(
        public readonly HookType $type,
        callable $listener,
        public readonly int $priority,
        public bool $once = false,
        public readonly HookSource $source = HookSource::Core,
        public readonly ?string $sourceIdentifier = null,
        public readonly ?object $container = null
    ) {
        $this->listener = $listener instanceof \Closure
            ? $listener
            : \Closure::fromCallable($listener);
    }
}
