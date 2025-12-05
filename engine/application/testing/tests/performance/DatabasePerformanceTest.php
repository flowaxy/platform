<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests\Performance;

use Flowaxy\Core\Infrastructure\Persistence\QueryBuilder;
use TestCase;

/**
 * Навантажувальні тести для бази даних
 */
final class DatabasePerformanceTest extends TestCase
{
    private const ITERATIONS = 100;

    public function testQueryBuilderBuildPerformance(): void
    {
        if (!class_exists(QueryBuilder::class)) {
            $this->markTestSkipped('QueryBuilder not available');
            return;
        }

        $start = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $qb = new QueryBuilder();
            $qb->select(['id', 'name', 'email'])
                ->from('users')
                ->where('id', '=', $i)
                ->where('active', '=', 1)
                ->orderBy('name', 'ASC')
                ->limit(10);

            $sql = $qb->toSql();
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::ITERATIONS / $duration;

        $this->assertTrue($opsPerSecond > 100, "QueryBuilder build should handle at least 100 ops/sec. Got: {$opsPerSecond}");
    }

    public function testQueryBuilderComplexQueryPerformance(): void
    {
        if (!class_exists(QueryBuilder::class)) {
            $this->markTestSkipped('QueryBuilder not available');
            return;
        }

        $start = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $qb = new QueryBuilder();
            $qb->select(['u.id', 'u.name', 'p.title'])
                ->from('users', 'u')
                ->leftJoin('posts', 'p', 'u.id', '=', 'p.user_id')
                ->where('u.active', '=', 1)
                ->where('p.published', '=', 1)
                ->groupBy('u.id')
                ->having('COUNT(p.id)', '>', 0)
                ->orderBy('u.name', 'ASC')
                ->limit(20, 0);

            $sql = $qb->toSql();
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::ITERATIONS / $duration;

        $this->assertTrue($opsPerSecond > 50, "QueryBuilder complex query should handle at least 50 ops/sec. Got: {$opsPerSecond}");
    }

    public function testQueryBuilderParameterBindingPerformance(): void
    {
        if (!class_exists(QueryBuilder::class)) {
            $this->markTestSkipped('QueryBuilder not available');
            return;
        }

        $start = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $qb = new QueryBuilder();
            $qb->select('*')
                ->from('users')
                ->where('id', '=', $i)
                ->where('name', 'LIKE', "%test{$i}%")
                ->where('email', '=', "test{$i}@example.com");

            $bindings = $qb->getBindings();
        }

        $duration = microtime(true) - $start;
        $opsPerSecond = self::ITERATIONS / $duration;

        $this->assertTrue($opsPerSecond > 100, "QueryBuilder parameter binding should handle at least 100 ops/sec. Got: {$opsPerSecond}");
    }
}
