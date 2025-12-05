<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests\Performance;

use Flowaxy\Core\System\HookManager;
use Flowaxy\Core\System\Hooks\Action;
use Flowaxy\Core\System\Hooks\Filter;
use TestCase;

/**
 * Навантажувальні тести для хуків
 */
final class HooksPerformanceTest extends TestCase
{
    private const ITERATIONS = 1000;
    private const HOOKS_COUNT = 100;

    public function testHookDispatchPerformance(): void
    {
        $manager = new HookManager();

        // Реєструємо багато хуків
        for ($i = 0; $i < self::HOOKS_COUNT; $i++) {
            $manager->on('performance_test', function () {
                // Простий callback
            });
        }

        $start = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $manager->dispatch('performance_test');
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::ITERATIONS / $duration;

        $this->assertTrue($opsPerSecond > 1000, "Hook dispatch should handle at least 1000 ops/sec. Got: {$opsPerSecond}");
    }

    public function testFilterApplyPerformance(): void
    {
        $manager = new HookManager();

        // Реєструємо багато фільтрів
        for ($i = 0; $i < self::HOOKS_COUNT; $i++) {
            $manager->filter('performance_filter', function ($value) {
                return $value;
            });
        }

        $start = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $manager->apply('performance_filter', 'test');
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::ITERATIONS / $duration;

        $this->assertTrue($opsPerSecond > 1000, "Filter apply should handle at least 1000 ops/sec. Got: {$opsPerSecond}");
    }

    public function testActionAddPerformance(): void
    {
        $start = microtime(true);

        for ($i = 0; $i < self::HOOKS_COUNT; $i++) {
            Action::add("perf_action_{$i}", function () {});
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::HOOKS_COUNT / $duration;

        $this->assertTrue($opsPerSecond > 100, "Action add should handle at least 100 ops/sec. Got: {$opsPerSecond}");
    }

    public function testFilterAddPerformance(): void
    {
        $start = microtime(true);

        for ($i = 0; $i < self::HOOKS_COUNT; $i++) {
            Filter::add("perf_filter_{$i}", function ($value) {
                return $value;
            });
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::HOOKS_COUNT / $duration;

        $this->assertTrue($opsPerSecond > 100, "Filter add should handle at least 100 ops/sec. Got: {$opsPerSecond}");
    }

    public function testHookPrioritySortingPerformance(): void
    {
        $manager = new HookManager();

        // Реєструємо хуки з різними пріоритетами
        for ($i = 0; $i < self::HOOKS_COUNT; $i++) {
            $priority = rand(1, 100);
            $manager->on('priority_test', function () {}, $priority);
        }

        $start = microtime(true);
        $manager->dispatch('priority_test');
        $duration = microtime(true) - $start;

        $this->assertTrue($duration < 0.1, "Hook dispatch with priorities should complete in < 0.1s. Got: {$duration}s");
    }
}
