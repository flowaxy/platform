<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests;

use Flowaxy\Core\System\Hooks\Action;
use Flowaxy\Core\System\Hooks\Filter;
use Flowaxy\Core\System\HookManager;
use TestCase;

/**
 * Тести для Action та Filter (WordPress-style hooks)
 */
final class ActionFilterTest extends TestCase
{
    private ?HookManager $hookManager = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Створюємо глобальний HookManager для Action/Filter
        $this->hookManager = new HookManager();
        // Реєструємо в глобальному контейнері, якщо доступний
        if (function_exists('container')) {
            try {
                $container = container();
                if ($container->has(\Flowaxy\Core\Contracts\HookManagerInterface::class)) {
                    $container->singleton(\Flowaxy\Core\Contracts\HookManagerInterface::class, fn() => $this->hookManager);
                }
            } catch (\Exception $e) {
                // Ігноруємо помилки
            }
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->hookManager = null;
    }

    public function testActionAddRegistersAction(): void
    {
        $called = false;
        Action::add('test_action', function () use (&$called) {
            $called = true;
        });

        Action::do('test_action');

        $this->assertTrue($called);
    }

    public function testActionDoCallsAllListeners(): void
    {
        $count = 0;
        Action::add('test_action', function () use (&$count) {
            $count++;
        });
        Action::add('test_action', function () use (&$count) {
            $count++;
        });

        Action::do('test_action');

        $this->assertEquals(2, $count);
    }

    public function testActionRemoveRemovesListener(): void
    {
        $called = false;
        $listener = function () use (&$called) {
            $called = true;
        };

        Action::add('test_action', $listener);
        Action::remove('test_action', $listener);
        Action::do('test_action');

        $this->assertFalse($called);
    }

    public function testActionHasChecksExistence(): void
    {
        $this->assertFalse(Action::has('test_action'));

        Action::add('test_action', function () {});

        $this->assertTrue(Action::has('test_action'));
    }

    public function testFilterAddRegistersFilter(): void
    {
        Filter::add('test_filter', function ($value) {
            return $value . '_modified';
        });

        $result = Filter::apply('test_filter', 'test');

        $this->assertEquals('test_modified', $result);
    }

    public function testFilterApplyCallsAllFiltersInOrder(): void
    {
        Filter::add('test_filter', function ($value) {
            return $value . '_1';
        }, 10);
        Filter::add('test_filter', function ($value) {
            return $value . '_2';
        }, 5);

        $result = Filter::apply('test_filter', 'test');

        $this->assertEquals('test_2_1', $result);
    }

    public function testFilterRemoveRemovesFilter(): void
    {
        $filter = function ($value) {
            return $value . '_modified';
        };

        Filter::add('test_filter', $filter);
        Filter::remove('test_filter', $filter);

        $result = Filter::apply('test_filter', 'test');

        $this->assertEquals('test', $result);
    }

    public function testFilterHasChecksExistence(): void
    {
        $this->assertFalse(Filter::has('test_filter'));

        Filter::add('test_filter', function ($value) {
            return $value;
        });

        $this->assertTrue(Filter::has('test_filter'));
    }
}
