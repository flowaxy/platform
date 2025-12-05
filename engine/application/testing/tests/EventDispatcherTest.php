<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests;

use Flowaxy\Core\System\EventDispatcher;
use Flowaxy\Core\System\Events\Event;
use TestCase;

/**
 * Тести для EventDispatcher
 */
final class EventDispatcherTest extends TestCase
{
    private ?EventDispatcher $dispatcher = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new EventDispatcher();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->dispatcher = null;
    }

    public function testAddListenerRegistersListener(): void
    {
        $called = false;
        $this->dispatcher->addListener('test.event', function () use (&$called) {
            $called = true;
        });

        $this->dispatcher->dispatch('test.event', new Event());

        $this->assertTrue($called);
    }

    public function testDispatchCallsListenersInPriorityOrder(): void
    {
        $order = [];

        $this->dispatcher->addListener('test.event', function () use (&$order) {
            $order[] = 2;
        }, 10);

        $this->dispatcher->addListener('test.event', function () use (&$order) {
            $order[] = 1;
        }, 20);

        $this->dispatcher->dispatch('test.event', new Event());

        $this->assertEquals([1, 2], $order);
    }

    public function testRemoveListenerRemovesListener(): void
    {
        $called = false;
        $listener = function () use (&$called) {
            $called = true;
        };

        $this->dispatcher->addListener('test.event', $listener);
        $this->dispatcher->removeListener('test.event', $listener);
        $this->dispatcher->dispatch('test.event', new Event());

        $this->assertFalse($called);
    }

    public function testEventPropagationCanBeStopped(): void
    {
        $called1 = false;
        $called2 = false;

        $this->dispatcher->addListener('test.event', function (Event $event) use (&$called1) {
            $called1 = true;
            $event->stopPropagation();
        });

        $this->dispatcher->addListener('test.event', function () use (&$called2) {
            $called2 = true;
        });

        $this->dispatcher->dispatch('test.event', new Event());

        $this->assertTrue($called1);
        $this->assertFalse($called2);
    }
}
