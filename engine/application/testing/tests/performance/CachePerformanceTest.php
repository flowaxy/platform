<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests\Performance;

use TestCase;

/**
 * Навантажувальні тести для кешування
 */
final class CachePerformanceTest extends TestCase
{
    private const ITERATIONS = 1000;
    private const KEYS_COUNT = 100;

    public function testCacheSetPerformance(): void
    {
        if (!function_exists('cache')) {
            $this->markTestSkipped('Cache function not available');
            return;
        }

        $cache = cache();
        $cache->clear();

        $start = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $cache->set("perf_key_{$i}", "value_{$i}");
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::ITERATIONS / $duration;

        $this->assertTrue($opsPerSecond > 100, "Cache set should handle at least 100 ops/sec. Got: {$opsPerSecond}");
    }

    public function testCacheGetPerformance(): void
    {
        if (!function_exists('cache')) {
            $this->markTestSkipped('Cache function not available');
            return;
        }

        $cache = cache();
        $cache->clear();

        // Спочатку заповнюємо кеш
        for ($i = 0; $i < self::KEYS_COUNT; $i++) {
            $cache->set("perf_key_{$i}", "value_{$i}");
        }

        $start = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $key = "perf_key_" . ($i % self::KEYS_COUNT);
            $cache->get($key);
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::ITERATIONS / $duration;

        $this->assertTrue($opsPerSecond > 1000, "Cache get should handle at least 1000 ops/sec. Got: {$opsPerSecond}");
    }

    public function testCacheHasPerformance(): void
    {
        if (!function_exists('cache')) {
            $this->markTestSkipped('Cache function not available');
            return;
        }

        $cache = cache();
        $cache->clear();

        // Спочатку заповнюємо кеш
        for ($i = 0; $i < self::KEYS_COUNT; $i++) {
            $cache->set("perf_key_{$i}", "value_{$i}");
        }

        $start = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $key = "perf_key_" . ($i % self::KEYS_COUNT);
            $cache->has($key);
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::ITERATIONS / $duration;

        $this->assertTrue($opsPerSecond > 1000, "Cache has should handle at least 1000 ops/sec. Got: {$opsPerSecond}");
    }

    public function testCacheDeletePerformance(): void
    {
        if (!function_exists('cache')) {
            $this->markTestSkipped('Cache function not available');
            return;
        }

        $cache = cache();

        $start = microtime(true);

        for ($i = 0; $i < self::KEYS_COUNT; $i++) {
            $cache->set("perf_del_key_{$i}", "value_{$i}");
            $cache->delete("perf_del_key_{$i}");
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::KEYS_COUNT / $duration;

        $this->assertTrue($opsPerSecond > 50, "Cache delete should handle at least 50 ops/sec. Got: {$opsPerSecond}");
    }

    public function testCacheClearPerformance(): void
    {
        if (!function_exists('cache')) {
            $this->markTestSkipped('Cache function not available');
            return;
        }

        $cache = cache();

        // Заповнюємо кеш
        for ($i = 0; $i < self::KEYS_COUNT; $i++) {
            $cache->set("perf_clear_key_{$i}", "value_{$i}");
        }

        $start = microtime(true);
        $cache->clear();
        $duration = microtime(true) - $start;

        $this->assertTrue($duration < 1.0, "Cache clear should complete in < 1.0s. Got: {$duration}s");
    }
}
