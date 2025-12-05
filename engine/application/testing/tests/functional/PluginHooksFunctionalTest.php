<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests\Functional;

use Flowaxy\Core\System\Hooks\Action;
use Flowaxy\Core\System\Hooks\Filter;
use Flowaxy\Core\System\HookManager;
use TestCase;

/**
 * Функціональні тести для реєстрації хуків плагінами
 */
final class PluginHooksFunctionalTest extends TestCase
{
    private ?HookManager $hookManager = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hookManager = new HookManager();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->hookManager = null;
    }

    public function testPluginCanRegisterActionHook(): void
    {
        $called = false;

        Action::add('plugin_test_action', function () use (&$called) {
            $called = true;
        });

        Action::do('plugin_test_action');

        $this->assertTrue($called);
    }

    public function testPluginCanRegisterFilterHook(): void
    {
        Filter::add('plugin_test_filter', function ($value) {
            return $value . '_modified';
        });

        $result = Filter::apply('plugin_test_filter', 'test');

        $this->assertEquals('test_modified', $result);
    }

    public function testPluginHooksExecuteInPriorityOrder(): void
    {
        $order = [];

        Action::add('plugin_priority_test', function () use (&$order) {
            $order[] = 2;
        }, 10);

        Action::add('plugin_priority_test', function () use (&$order) {
            $order[] = 1;
        }, 5);

        Action::do('plugin_priority_test');

        $this->assertEquals([1, 2], $order);
    }

    public function testPluginCanRemoveHook(): void
    {
        $called = false;
        $listener = function () use (&$called) {
            $called = true;
        };

        Action::add('plugin_remove_test', $listener);
        Action::remove('plugin_remove_test', $listener);
        Action::do('plugin_remove_test');

        $this->assertFalse($called);
    }

    public function testMultiplePluginsCanRegisterSameHook(): void
    {
        $count = 0;

        Action::add('plugin_shared_hook', function () use (&$count) {
            $count++;
        });

        Action::add('plugin_shared_hook', function () use (&$count) {
            $count++;
        });

        Action::do('plugin_shared_hook');

        $this->assertEquals(2, $count);
    }
}
