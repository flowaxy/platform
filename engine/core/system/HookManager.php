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

namespace Flowaxy\Core\System;

// use statements - класи будуть завантажені через autoloader
// Важливо: завантажуємо в правильному порядку
use Flowaxy\Core\System\Hooks\HookType;
use Flowaxy\Core\System\Hooks\HookSource;
use Flowaxy\Core\System\Hooks\HookListener;
use Flowaxy\Core\System\Hooks\HookRegistry;
use Flowaxy\Core\System\Hooks\HookPerformanceMonitor;
use Flowaxy\Core\System\Hooks\HookMiddlewareInterface;
use Flowaxy\Core\System\Hooks\HookMiddleware;
use Flowaxy\Core\System\EventDispatcher;
use Flowaxy\Core\System\Events\Event;

final class HookManager implements \Flowaxy\Core\Contracts\HookManagerInterface
{
    /**
     * @var array<string,SplPriorityQueue>
     */
    private array $listeners = [];

    /**
     * @var array<string,int>
     */
    private array $callStats = [];

    /**
     * Реєстр хуків з метаданими
     */
    private HookRegistry $registry;

    /**
     * Моніторинг продуктивності хуків
     */
    private HookPerformanceMonitor $performanceMonitor;

    /**
     * @var array<int, HookMiddlewareInterface>
     */
    private array $middleware = [];

    /**
     * Диспетчер подій (опціонально)
     *
     * @var \Flowaxy\Core\System\EventDispatcher|null
     */
    private ?\Flowaxy\Core\System\EventDispatcher $eventDispatcher = null;

    public function __construct()
    {
        // Завантажуємо класи через autoloader перед використанням
        // Використовуємо повні імена класів для перевірки
        if (!class_exists(\Flowaxy\Core\System\Hooks\HookRegistry::class)) {
            // Спробуємо завантажити через autoloader
            if (function_exists('spl_autoload_call')) {
                spl_autoload_call(\Flowaxy\Core\System\Hooks\HookRegistry::class);
            }
            if (!class_exists(\Flowaxy\Core\System\Hooks\HookRegistry::class)) {
                throw new \RuntimeException('HookRegistry class not found. Autoloader may not be working correctly.');
            }
        }

        if (!class_exists(\Flowaxy\Core\System\Hooks\HookPerformanceMonitor::class)) {
            // Спробуємо завантажити через autoloader
            if (function_exists('spl_autoload_call')) {
                spl_autoload_call(\Flowaxy\Core\System\Hooks\HookPerformanceMonitor::class);
            }
            if (!class_exists(\Flowaxy\Core\System\Hooks\HookPerformanceMonitor::class)) {
                throw new \RuntimeException('HookPerformanceMonitor class not found. Autoloader may not be working correctly.');
            }
        }

        $this->registry = new HookRegistry();
        $this->performanceMonitor = new HookPerformanceMonitor();
    }

    public function on(string $hookName, callable $listener, int $priority = 10, bool $once = false): void
    {
        $this->register($hookName, HookType::Action, $listener, $priority, $once);
    }

    public function filter(string $hookName, callable $listener, int $priority = 10): void
    {
        $this->register($hookName, HookType::Filter, $listener, $priority);
    }

    /**
     * Реєстрація хука з плагіна
     *
     * @param string $hookName Назва хука
     * @param callable $listener Callback
     * @param string $pluginSlug Slug плагіна
     * @param object|null $container Контейнер плагіна
     * @param int $priority Пріоритет
     * @param bool $once Чи виконати тільки один раз
     * @return void
     */
    public function onFromPlugin(
        string $hookName,
        callable $listener,
        string $pluginSlug,
        ?object $container = null,
        int $priority = 10,
        bool $once = false
    ): void {
        $this->register(
            $hookName,
            HookType::Action,
            $listener,
            $priority,
            $once,
            HookSource::Plugin,
            $pluginSlug,
            $container
        );
    }

    /**
     * Реєстрація фільтра з плагіна
     *
     * @param string $hookName Назва хука
     * @param callable $listener Callback
     * @param string $pluginSlug Slug плагіна
     * @param object|null $container Контейнер плагіна
     * @param int $priority Пріоритет
     * @return void
     */
    public function filterFromPlugin(
        string $hookName,
        callable $listener,
        string $pluginSlug,
        ?object $container = null,
        int $priority = 10
    ): void {
        $this->register(
            $hookName,
            HookType::Filter,
            $listener,
            $priority,
            false,
            HookSource::Plugin,
            $pluginSlug,
            $container
        );
    }

    /**
     * Реєстрація хука з теми
     *
     * @param string $hookName Назва хука
     * @param callable $listener Callback
     * @param string $themeSlug Slug теми
     * @param object|null $container Контейнер теми
     * @param int $priority Пріоритет
     * @param bool $once Чи виконати тільки один раз
     * @return void
     */
    public function onFromTheme(
        string $hookName,
        callable $listener,
        string $themeSlug,
        ?object $container = null,
        int $priority = 10,
        bool $once = false
    ): void {
        $this->register(
            $hookName,
            HookType::Action,
            $listener,
            $priority,
            $once,
            HookSource::Theme,
            $themeSlug,
            $container
        );
    }

    /**
     * Реєстрація фільтра з теми
     *
     * @param string $hookName Назва хука
     * @param callable $listener Callback
     * @param string $themeSlug Slug теми
     * @param object|null $container Контейнер теми
     * @param int $priority Пріоритет
     * @return void
     */
    public function filterFromTheme(
        string $hookName,
        callable $listener,
        string $themeSlug,
        ?object $container = null,
        int $priority = 10
    ): void {
        $this->register(
            $hookName,
            HookType::Filter,
            $listener,
            $priority,
            false,
            HookSource::Theme,
            $themeSlug,
            $container
        );
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

        // Застосовуємо middleware
        $payloadArray = $this->applyMiddleware($hookName, $payloadArray);

        $listenersCount = 0;
        foreach ($this->iterateListeners($hookName, HookType::Action) as $listener) {
            $this->callListener($hookName, $listener, $payloadArray);
            $listenersCount++;
        }

        // Диспетчеризація події через EventDispatcher (якщо встановлено)
        if ($this->eventDispatcher !== null) {
            $hookEvent = new class($hookName, $payloadArray) extends \Flowaxy\Core\System\Events\Event {
                public function __construct(
                    public readonly string $hookName,
                    public readonly array $payload
                ) {
                    parent::__construct(['hook' => $hookName, 'payload' => $payload]);
                }
            };
            $this->eventDispatcher->dispatch($hookEvent);
        }

        // DEBUG: логуємо виконання хука
        if ($listenersCount > 0) {
            if (function_exists('logDebug')) {
                logDebug('HookManager::dispatch: Hook executed', [
                    'hook' => $hookName,
                    'listeners' => $listenersCount,
                ]);
            } elseif (function_exists('logger')) {
                logger()->logDebug('Хук виконано', [
                    'hook' => $hookName,
                    'listeners' => $listenersCount,
                ]);
            }
        } elseif (function_exists('logDebug')) {
            logDebug('HookManager::dispatch: Hook executed with no listeners', ['hook' => $hookName]);
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
        if (function_exists('logDebug')) {
            logDebug('HookManager::apply: Applying filter hook', [
                'hook' => $hookName,
                'context' => $context,
            ]);
        }

        $payload = [$value, $context];

        // Застосовуємо middleware
        $payload = $this->applyMiddleware($hookName, $payload);

        $listenersCount = 0;
        foreach ($this->iterateListeners($hookName, HookType::Filter) as $listener) {
            $result = $this->callListener($hookName, $listener, $payload);

            if ($result !== null) {
                $value = $result;
                $payload[0] = $value;
            }
            $listenersCount++;
        }

        if (function_exists('logDebug')) {
            logDebug('HookManager::apply: Filter hook applied', [
                'hook' => $hookName,
                'listeners' => $listenersCount,
                'value_changed' => $listenersCount > 0,
            ]);
        }

        // Диспетчеризація події через EventDispatcher (якщо встановлено)
        if ($this->eventDispatcher !== null) {
            $filterEvent = new class($hookName, $value, $context) extends \Flowaxy\Core\System\Events\Event {
                public function __construct(
                    public readonly string $hookName,
                    public readonly mixed $value,
                    public readonly array $context
                ) {
                    parent::__construct(['hook' => $hookName, 'value' => $value, 'context' => $context]);
                }
            };
            $this->eventDispatcher->dispatch($filterEvent);
        }

        return $value;
    }

    /**
     * Застосування middleware до payload
     *
     * @param string $hookName Назва хука
     * @param array<int, mixed> $payload Дані хука
     * @return array<int, mixed> Оброблені дані
     */
    private function applyMiddleware(string $hookName, array $payload): array
    {
        foreach ($this->middleware as $middleware) {
            $payload = $middleware->handle($hookName, $payload);
        }

        return $payload;
    }

    public function has(string $hookName): bool
    {
        return isset($this->listeners[$hookName]) && $this->listeners[$hookName]->count() > 0;
    }

    public function flush(): void
    {
        $this->listeners = [];
        $this->callStats = [];
        $this->registry->flush();
    }

    /**
     * Отримання реєстру хуків
     *
     * @return HookRegistry
     */
    public function getRegistry(): HookRegistry
    {
        return $this->registry;
    }

    /**
     * Отримання моніторингу продуктивності
     *
     * @return HookPerformanceMonitor
     */
    public function getPerformanceMonitor(): HookPerformanceMonitor
    {
        return $this->performanceMonitor;
    }

    /**
     * Встановлення диспетчера подій
     *
     * @param \Flowaxy\Core\System\EventDispatcher $eventDispatcher Диспетчер подій
     * @return void
     */
    public function setEventDispatcher(\Flowaxy\Core\System\EventDispatcher $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Отримання диспетчера подій
     *
     * @return \Flowaxy\Core\System\EventDispatcher|null
     */
    public function getEventDispatcher(): ?\Flowaxy\Core\System\EventDispatcher
    {
        return $this->eventDispatcher;
    }

    /**
     * Реєстрація middleware
     *
     * @param HookMiddlewareInterface $middleware Middleware для реєстрації
     * @return void
     */
    public function addMiddleware(HookMiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;

        // Сортуємо middleware за пріоритетом
        usort($this->middleware, function (HookMiddlewareInterface $a, HookMiddlewareInterface $b) {
            return $a->getPriority() <=> $b->getPriority();
        });
    }

    /**
     * Видалення всіх middleware
     *
     * @return void
     */
    public function clearMiddleware(): void
    {
        $this->middleware = [];
    }

    /**
     * Реєстрація хука з метаданими
     *
     * @param string $hookName Назва хука
     * @param HookType $type Тип хука
     * @param string $description Опис хука
     * @param string $version Версія хука
     * @param array<string> $dependencies Залежності від інших хуків
     * @return void
     */
    public function registerWithMetadata(
        string $hookName,
        HookType $type,
        string $description = '',
        string $version = '1.0.0',
        array $dependencies = []
    ): void {
        $this->registry->register($hookName, $type, $description, $version, $dependencies);
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

    /**
     * Реєстрація хука
     *
     * @param string $hookName Назва хука
     * @param HookType $type Тип хука
     * @param callable $listener Callback
     * @param int $priority Пріоритет
     * @param bool $once Чи виконати тільки один раз
     * @param HookSource $source Джерело хука
     * @param string|null $sourceIdentifier Ідентифікатор джерела
     * @param object|null $container Контейнер для ізоляції
     * @return void
     */
    private function register(
        string $hookName,
        HookType $type,
        callable $listener,
        int $priority = 10,
        bool $once = false,
        HookSource $source = HookSource::Core,
        ?string $sourceIdentifier = null,
        ?object $container = null
    ): void {
        if (function_exists('logDebug')) {
            logDebug('HookManager::register: Registering hook', [
                'hook' => $hookName,
                'type' => $type->value,
                'priority' => $priority,
                'source' => $source->value,
                'source_identifier' => $sourceIdentifier,
            ]);
        }

        if (! isset($this->listeners[$hookName])) {
            $this->listeners[$hookName] = new SplPriorityQueue();
            $this->listeners[$hookName]->setExtractFlags(SplPriorityQueue::EXTR_DATA);

            // Реєструємо хук з метаданими, якщо ще не зареєстровано
            if (!$this->registry->has($hookName)) {
                $this->registry->register($hookName, $type);
            }
        }

        $this->listeners[$hookName]->insert(
            new HookListener($type, $listener, $priority, $once, $source, $sourceIdentifier, $container),
            $priority
        );

        if (function_exists('logInfo')) {
            logInfo('HookManager::register: Hook registered successfully', [
                'hook' => $hookName,
                'type' => $type->value,
            ]);
        }
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
     * Виклик слухача хука з підтримкою ізоляції
     *
     * @param string $hookName Назва хука
     * @param HookListener $listener Слухач хука
     * @param array<int, mixed> $payload Дані хука
     * @return mixed
     */
    private function callListener(string $hookName, HookListener $listener, array $payload): mixed
    {
        $this->callStats[$hookName] = ($this->callStats[$hookName] ?? 0) + 1;

        if (function_exists('logDebug')) {
            logDebug('HookManager::callListener: Calling hook listener', [
                'hook' => $hookName,
                'source' => $listener->source->value,
                'source_identifier' => $listener->sourceIdentifier,
            ]);
        }

        // Початок вимірювання продуктивності
        $startTime = $this->performanceMonitor->start($hookName);

        try {
            // Виконуємо хук в ізольованому контейнері, якщо він з плагіна/теми
            if ($listener->source !== HookSource::Core && $listener->container !== null) {
                if (function_exists('logDebug')) {
                    logDebug('HookManager::callListener: Calling in isolated container', [
                        'hook' => $hookName,
                        'source' => $listener->source->value,
                    ]);
                }
                $result = $this->callInIsolatedContainer($listener, $payload);
            } else {
                // Хук з ядра - виконуємо безпосередньо
                $result = ($listener->listener)(...$payload);
            }

            // Завершення вимірювання продуктивності
            $executionTime = $this->performanceMonitor->end($hookName, $startTime);

            if (function_exists('logDebug')) {
                logDebug('HookManager::callListener: Hook listener executed successfully', [
                    'hook' => $hookName,
                    'execution_time' => $executionTime,
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            // Завершення вимірювання навіть при помилці
            $this->performanceMonitor->end($hookName, $startTime);
            $this->logHookError($hookName, $e, $listener->sourceIdentifier);

            return null;
        }
    }

    /**
     * Виклик хука в ізольованому контейнері
     *
     * @param HookListener $listener Слухач хука
     * @param array<int, mixed> $payload Дані хука
     * @return mixed
     */
    private function callInIsolatedContainer(HookListener $listener, array $payload): mixed
    {
        // Отримуємо контейнер
        $container = $listener->container;

        // Перевіряємо, чи контейнер має метод getContainer() (PluginContainer або ThemeContainer)
        if (method_exists($container, 'getContainer')) {
            $isolatedContainer = $container->getContainer();

            // Виконуємо callback в контексті ізольованого контейнера
            // Callback має доступ тільки до сервісів свого контейнера
            return ($listener->listener)(...$payload);
        }

        // Якщо контейнер не підтримує getContainer(), виконуємо безпосередньо
        // (для зворотної сумісності)
        return ($listener->listener)(...$payload);
    }
    /**
     * Логувати помилку хука
     *
     * @param string $hookName Назва хука
     * @param Throwable $e Виняток
     * @param string|null $sourceIdentifier Ідентифікатор джерела (slug плагіна/теми)
     * @return void
     */
    private function logHookError(string $hookName, Throwable $e, ?string $sourceIdentifier = null): void
    {
        $message = "Помилка виконання хука '{$hookName}': " . $e->getMessage();
        if ($sourceIdentifier !== null) {
            $message .= " (джерело: {$sourceIdentifier})";
        }

        try {
            if (function_exists('logError')) {
                logError('HookManager::logHookError: Hook execution error', [
                    'hook' => $hookName,
                    'error' => $e->getMessage(),
                    'source_identifier' => $sourceIdentifier,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'exception' => $e,
                ]);
            } elseif (function_exists('logger')) {
                logger()->logError($message, [
                    'hook' => $hookName,
                    'source' => $sourceIdentifier,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            } else {
                error_log($message);
            }
        } catch (Exception $logException) {
            if (function_exists('logError')) {
                logError('HookManager::logHookError: Failed to log hook error', [
                    'hook' => $hookName,
                    'original_error' => $e->getMessage(),
                    'log_error' => $logException->getMessage(),
                ]);
            } else {
                error_log($message);
            }
        }
    }
}
