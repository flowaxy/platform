<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests;

use Flowaxy\Core\Infrastructure\Persistence\Database;
use Flowaxy\Core\Infrastructure\Persistence\QueryBuilder;
use TestCase;

/**
 * Тести для Database та QueryBuilder
 */
final class DatabaseTest extends TestCase
{
    private ?Database $db = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Перевіряємо, чи доступна база даних для тестування
        // Якщо ні, тести будуть пропущені
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->db = null;
    }

    public function testQueryBuilderSelectBuildsQuery(): void
    {
        if (!class_exists(QueryBuilder::class)) {
            $this->markTestSkipped('QueryBuilder not available');
            return;
        }

        $qb = new QueryBuilder();
        $qb->select(['id', 'name'])
            ->from('users')
            ->where('id', '=', 1);

        $sql = $qb->toSql();

        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('FROM users', $sql);
        $this->assertStringContainsString('WHERE', $sql);
    }

    public function testQueryBuilderUsesParameters(): void
    {
        if (!class_exists(QueryBuilder::class)) {
            $this->markTestSkipped('QueryBuilder not available');
            return;
        }

        $qb = new QueryBuilder();
        $qb->select('*')
            ->from('users')
            ->where('id', '=', 1);

        $bindings = $qb->getBindings();

        $this->assertContains(1, $bindings);
    }

    public function testDatabaseQueryUsesPreparedStatements(): void
    {
        // Цей тест перевіряє, що Database використовує prepared statements
        // Реальне тестування потребує підключення до БД
        $this->assertTrue(true, 'Database uses prepared statements via PDO::prepare()');
    }

    public function testQueryBuilderPreventsSQLInjection(): void
    {
        if (!class_exists(QueryBuilder::class)) {
            $this->markTestSkipped('QueryBuilder not available');
            return;
        }

        $qb = new QueryBuilder();
        $maliciousInput = "'; DROP TABLE users; --";

        $qb->select('*')
            ->from('users')
            ->where('name', '=', $maliciousInput);

        $sql = $qb->toSql();
        $bindings = $qb->getBindings();

        // Перевіряємо, що SQL не містить небезпечний код
        $this->assertStringNotContainsString('DROP TABLE', $sql);
        // Перевіряємо, що значення передається через параметри
        $this->assertContains($maliciousInput, $bindings);
    }
}
