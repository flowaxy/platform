<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests;

use Flowaxy\Core\Infrastructure\Cache\Cache;
use TestCase;

/**
 * Тести для Cache
 */
final class CacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Очищаємо кеш перед кожним тестом
        if (function_exists('cache')) {
            try {
                $cache = cache();
                $cache->clear();
            } catch (\Exception $e) {
                // Ігноруємо помилки
            }
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Очищаємо кеш після кожного тесту
        if (function_exists('cache')) {
            try {
                $cache = cache();
                $cache->clear();
            } catch (\Exception $e) {
                // Ігноруємо помилки
            }
        }
    }

    public function testSetAndGetStoresValue(): void
    {
        if (!function_exists('cache')) {
            $this->markTestSkipped('Cache function not available');
            return;
        }

        $cache = cache();
        $cache->set('test_key', 'test_value');

        $this->assertEquals('test_value', $cache->get('test_key'));
    }

    public function testGetReturnsDefaultWhenNotFound(): void
    {
        if (!function_exists('cache')) {
            $this->markTestSkipped('Cache function not available');
            return;
        }

        $cache = cache();
        $value = $cache->get('non_existent_key', 'default');

        $this->assertEquals('default', $value);
    }

    public function testHasChecksExistence(): void
    {
        if (!function_exists('cache')) {
            $this->markTestSkipped('Cache function not available');
            return;
        }

        $cache = cache();
        $this->assertFalse($cache->has('test_key'));

        $cache->set('test_key', 'test_value');
        $this->assertTrue($cache->has('test_key'));
    }

    public function testDeleteRemovesValue(): void
    {
        if (!function_exists('cache')) {
            $this->markTestSkipped('Cache function not available');
            return;
        }

        $cache = cache();
        $cache->set('test_key', 'test_value');
        $this->assertTrue($cache->has('test_key'));

        $cache->delete('test_key');
        $this->assertFalse($cache->has('test_key'));
    }

    public function testClearRemovesAllValues(): void
    {
        if (!function_exists('cache')) {
            $this->markTestSkipped('Cache function not available');
            return;
        }

        $cache = cache();
        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $cache->clear();

        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
    }

    public function testTTLExpiresValue(): void
    {
        if (!function_exists('cache')) {
            $this->markTestSkipped('Cache function not available');
            return;
        }

        $cache = cache();
        $cache->set('test_key', 'test_value', 1); // 1 секунда TTL

        $this->assertEquals('test_value', $cache->get('test_key'));

        // Чекаємо 2 секунди
        sleep(2);

        $this->assertFalse($cache->has('test_key'));
    }
}
