<?php

/**
 * Диспетчер подій з підтримкою пріоритетів
 *
 * @package Flowaxy\Core\System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Flowaxy\Core\System;

require_once __DIR__ . '/events/Event.php';
require_once __DIR__ . '/events/EventListener.php';
require_once __DIR__ . '/events/EventSubscriber.php';

use Flowaxy\Core\System\Events\Event;
use Flowaxy\Core\System\Events\EventListener;
use Flowaxy\Core\System\Events\EventSubscriber;

final class EventDispatcher
{
    /**
     * @var array<string, SplPriorityQueue>
     */
    private array $listeners = [];

    /**
     * Диспетчеризація події
     *
     * @param Event $event Подія для диспетчеризації
     * @return Event
     */
    public function dispatch(Event $event): Event
    {
        $eventName = get_class($event);

        if (function_exists('logDebug')) {
            logDebug('EventDispatcher::dispatch: Dispatching event', [
                'event' => $eventName,
            ]);
        }

        if (!isset($this->listeners[$eventName])) {
            if (function_exists('logDebug')) {
                logDebug('EventDispatcher::dispatch: No listeners for event', ['event' => $eventName]);
            }
            return $event;
        }

        $queue = clone $this->listeners[$eventName];
        $queue->rewind();

        $listenersCount = 0;
        while ($queue->valid() && !$event->isPropagationStopped()) {
            /** @var EventListener $listener */
            $listener = $queue->current();
            $queue->next();

            if ($listener->shouldHandle($event)) {
                try {
                    $listener->handle($event);
                    $listenersCount++;
                } catch (\Exception $e) {
                    if (function_exists('logError')) {
                        logError('EventDispatcher::dispatch: Listener execution error', [
                            'event' => $eventName,
                            'error' => $e->getMessage(),
                            'exception' => $e,
                        ]);
                    }
                }
            }
        }

        if ($listenersCount > 0 && function_exists('logInfo')) {
            logInfo('EventDispatcher::dispatch: Event dispatched successfully', [
                'event' => $eventName,
                'listeners' => $listenersCount,
            ]);
        }

        return $event;
    }

    /**
     * Додавання слухача події
     *
     * @param string $eventName Назва класу події
     * @param EventListener|callable $listener Слухач події
     * @param int $priority Пріоритет
     * @return void
     */
    public function addListener(string $eventName, EventListener|callable $listener, int $priority = 10): void
    {
        if (function_exists('logDebug')) {
            logDebug('EventDispatcher::addListener: Adding event listener', [
                'event' => $eventName,
                'priority' => $priority,
            ]);
        }

        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = new SplPriorityQueue();
            $this->listeners[$eventName]->setExtractFlags(SplPriorityQueue::EXTR_DATA);
        }

        if ($listener instanceof EventListener) {
            $priority = $listener->getPriority();
        } elseif (is_callable($listener)) {
            // Обгортаємо callable в EventListener для оптимізації
            $listener = new class($listener, $priority) extends EventListener {
                private $callback;
                private int $listenerPriority;

                public function __construct(callable $callback, int $priority)
                {
                    $this->callback = $callback;
                    $this->listenerPriority = $priority;
                }

                public function handle(Event $event): void
                {
                    ($this->callback)($event);
                }

                public function getPriority(): int
                {
                    return $this->listenerPriority;
                }
            };
        }

        $this->listeners[$eventName]->insert($listener, $priority);

        if (function_exists('logInfo')) {
            logInfo('EventDispatcher::addListener: Event listener added', [
                'event' => $eventName,
                'priority' => $priority,
            ]);
        }
    }

    /**
     * Додавання підписника подій
     *
     * @param EventSubscriber $subscriber Підписник
     * @return void
     */
    public function addSubscriber(EventSubscriber $subscriber): void
    {
        $events = $subscriber::getSubscribedEvents();

        foreach ($events as $eventName => $listeners) {
            if (!is_array($listeners)) {
                $listeners = [$listeners];
            }

            foreach ($listeners as $listener) {
                if (is_string($listener)) {
                    $this->addListener($eventName, [$subscriber, $listener]);
                } elseif (is_array($listener)) {
                    $method = $listener[0];
                    $priority = $listener[1] ?? 10;
                    $this->addListener($eventName, [$subscriber, $method], $priority);
                }
            }
        }
    }

    /**
     * Видалення слухача
     *
     * @param string $eventName Назва події
     * @param EventListener|null $listener Слухач для видалення
     * @return void
     */
    public function removeListener(string $eventName, ?EventListener $listener = null): void
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        if ($listener === null) {
            unset($this->listeners[$eventName]);
            return;
        }

        $queue = new SplPriorityQueue();
        $queue->setExtractFlags(SplPriorityQueue::EXTR_DATA);

        foreach ($this->getListeners($eventName) as $stored) {
            if ($stored !== $listener) {
                $queue->insert($stored, $stored->getPriority());
            }
        }

        $this->listeners[$eventName] = $queue;
    }

    /**
     * Отримання слухачів події
     *
     * @param string $eventName Назва події
     * @return array<int, EventListener>
     */
    public function getListeners(string $eventName): array
    {
        if (!isset($this->listeners[$eventName])) {
            return [];
        }

        $queue = clone $this->listeners[$eventName];
        $result = [];

        foreach ($queue as $listener) {
            $result[] = $listener;
        }

        return $result;
    }

    /**
     * Перевірка наявності слухачів
     *
     * @param string $eventName Назва події
     * @return bool
     */
    public function hasListeners(string $eventName): bool
    {
        return isset($this->listeners[$eventName]) && $this->listeners[$eventName]->count() > 0;
    }

    /**
     * Очищення всіх слухачів
     *
     * @return void
     */
    public function clear(): void
    {
        $this->listeners = [];
    }
}
