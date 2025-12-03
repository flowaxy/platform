<?php

declare(strict_types=1);

final class HookManagerTest extends TestCase
{
    public function testDispatchCallsActionListeners(): void
    {
        $manager = new HookManager();
        $flag = 0;
        $manager->on('demo_action', static function () use (&$flag): void {
            $flag++;
        });

        $manager->dispatch('demo_action');

        $this->assertEquals(1, $flag);
    }

    public function testApplyFiltersValue(): void
    {
        $manager = new HookManager();
        $manager->filter('demo_filter', static function (string $value): string {
            return $value . '2';
        });
        $manager->filter('demo_filter', static function (string $value): string {
            return $value . '3';
        }, priority: 5);

        $result = $manager->apply('demo_filter', '1', []);

        $this->assertEquals('123', $result);
    }

    public function testRemoveClearsListeners(): void
    {
        $manager = new HookManager();
        $listener = static function (): void {
        };
        $manager->on('demo_remove', $listener);
        $this->assertTrue($manager->has('demo_remove'));

        $manager->remove('demo_remove', $listener);

        $this->assertFalse($manager->has('demo_remove'));
    }
}
