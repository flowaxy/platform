<?php

/**
 * Query Builder з fluent interface
 *
 * Дозволяє будувати SQL запити через метод chaining
 *
 * @package Flowaxy\Core\Infrastructure\Persistence
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Infrastructure\Persistence;

final class QueryBuilder
{
    private ?object $db = null;
    private array $select = [];
    private array $from = [];
    private array $joins = [];
    private array $wheres = [];
    private array $groupBy = [];
    private array $having = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $bindings = [];

    public function __construct(?object $db = null)
    {
        $this->db = $db;
    }

    public function select(string|array $columns = '*'): self
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }

        $this->select = array_merge($this->select, $columns);
        return $this;
    }

    public function from(string $table, ?string $alias = null): self
    {
        $this->from[] = $alias ? "{$table} AS {$alias}" : $table;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    public function where(string|callable $column, ?string $operator = null, mixed $value = null, string $boolean = 'AND'): self
    {
        if (is_callable($column)) {
            $query = new self($this->db);
            $column($query);
            $this->wheres[] = [
                'type' => 'group',
                'query' => $query,
                'boolean' => $boolean,
            ];
            if (function_exists('logDebug')) {
                logDebug('QueryBuilder::where: Added grouped where clause', ['boolean' => $boolean]);
            }
            return $this;
        }

        if ($operator === null) {
            $value = $column;
            $column = $operator;
            $operator = '=';
        }

        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        if (function_exists('logDebug')) {
            logDebug('QueryBuilder::where: Adding where condition', [
                'column' => $column,
                'operator' => $operator,
                'boolean' => $boolean,
            ]);
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        if (!($value instanceof self)) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function orWhere(string|callable $column, ?string $operator = null, mixed $value = null): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereIn(string $column, array $values): self
    {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
        ];

        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'not_in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
        ];

        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function groupBy(string|array $columns): self
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }

        $this->groupBy = array_merge($this->groupBy, $columns);
        return $this;
    }

    public function having(string $column, string $operator, mixed $value): self
    {
        $this->having[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        $this->bindings[] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = [
            'column' => $column,
            'direction' => strtoupper($direction),
        ];

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function toSql(): string
    {
        $sql = 'SELECT ';

        if (empty($this->select)) {
            $sql .= '*';
        } else {
            $sql .= implode(', ', $this->select);
        }

        if (!empty($this->from)) {
            $sql .= ' FROM ' . implode(', ', $this->from);
        }

        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }

        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        if (!empty($this->having)) {
            $sql .= ' HAVING ' . $this->buildHavingClause();
        }

        if (!empty($this->orderBy)) {
            $orderParts = array_map(
                fn(array $order) => "{$order['column']} {$order['direction']}",
                $this->orderBy
            );
            $sql .= ' ORDER BY ' . implode(', ', $orderParts);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        // DEBUG логування для побудови запиту
        if (function_exists('logDebug')) {
            logDebug('QueryBuilder::toSql: SQL query built', [
                'sql' => $sql,
                'bindings' => $this->bindings,
                'tables' => $this->from,
            ]);
        }

        return $sql;
    }

    private function buildWhereClause(): string
    {
        $clauses = [];

        foreach ($this->wheres as $index => $where) {
            $boolean = $index > 0 ? " {$where['boolean']} " : '';

            if ($where['type'] === 'group') {
                $clauses[] = $boolean . '(' . $where['query']->buildWhereClause() . ')';
            } elseif ($where['type'] === 'in') {
                $placeholders = implode(',', array_fill(0, count($where['values']), '?'));
                $clauses[] = $boolean . "{$where['column']} IN ({$placeholders})";
            } elseif ($where['type'] === 'not_in') {
                $placeholders = implode(',', array_fill(0, count($where['values']), '?'));
                $clauses[] = $boolean . "{$where['column']} NOT IN ({$placeholders})";
            } elseif ($where['type'] === 'null') {
                $clauses[] = $boolean . "{$where['column']} IS NULL";
            } elseif ($where['type'] === 'not_null') {
                $clauses[] = $boolean . "{$where['column']} IS NOT NULL";
            } else {
                $value = $where['value'] instanceof self
                    ? '(' . $where['value']->toSql() . ')'
                    : '?';
                $clauses[] = $boolean . "{$where['column']} {$where['operator']} {$value}";
            }
        }

        return implode('', $clauses);
    }

    private function buildHavingClause(): string
    {
        $clauses = [];

        foreach ($this->having as $having) {
            $clauses[] = "{$having['column']} {$having['operator']} ?";
        }

        return implode(' AND ', $clauses);
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function get(): array
    {
        $db = $this->getDatabase();
        if (!$db) {
            if (function_exists('logWarning')) {
                logWarning('QueryBuilder::get: Database connection not available');
            }
            return [];
        }

        $sql = $this->toSql();
        $bindings = $this->getBindings();

        try {
            if (function_exists('logDebug')) {
                logDebug('QueryBuilder::get: Executing query', [
                    'sql' => $sql,
                    'bindings' => $bindings,
                ]);
            }

            $result = $db->getAll($sql, $bindings);

            if (function_exists('logInfo')) {
                logInfo('QueryBuilder::get: Query executed successfully', [
                    'row_count' => count($result),
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            if (function_exists('logDbError')) {
                logDbError('QueryBuilder::get: Query execution failed', [
                    'sql' => $sql,
                    'bindings' => $bindings,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
            throw $e;
        }
    }

    public function first(): array|false
    {
        $db = $this->getDatabase();
        if (!$db) {
            if (function_exists('logWarning')) {
                logWarning('QueryBuilder::first: Database connection not available');
            }
            return false;
        }

        $sql = $this->toSql();
        $bindings = $this->getBindings();

        try {
            if (function_exists('logDebug')) {
                logDebug('QueryBuilder::first: Executing query for first row', [
                    'sql' => $sql,
                    'bindings' => $bindings,
                ]);
            }

            $result = $db->getRow($sql, $bindings);

            if ($result !== false && function_exists('logInfo')) {
                logInfo('QueryBuilder::first: First row retrieved successfully');
            } elseif ($result === false && function_exists('logDebug')) {
                logDebug('QueryBuilder::first: No rows found');
            }

            return $result;
        } catch (\Exception $e) {
            if (function_exists('logDbError')) {
                logDbError('QueryBuilder::first: Query execution failed', [
                    'sql' => $sql,
                    'bindings' => $bindings,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
            throw $e;
        }
    }

    private function getDatabase(): ?object
    {
        if ($this->db !== null) {
            return $this->db;
        }

        if (class_exists(\Flowaxy\Core\Infrastructure\Persistence\Database::class)) {
            return \Flowaxy\Core\Infrastructure\Persistence\Database::getInstance();
        }

        return null;
    }
}
