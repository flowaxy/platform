<?php

/**
 * Менеджер індексів бази даних
 *
 * Дозволяє створювати, видаляти та аналізувати індекси для оптимізації запитів.
 *
 * @package Flowaxy\Core\Infrastructure\Persistence
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Infrastructure\Persistence;

final class IndexManager
{
    public function __construct(
        private Database $database
    ) {
    }

    /**
     * Створення індексу
     *
     * @param string $table Назва таблиці
     * @param string|array<string> $columns Колонки для індексу
     * @param string|null $indexName Назва індексу (опціонально)
     * @param string $type Тип індексу (INDEX, UNIQUE, FULLTEXT)
     * @return bool
     */
    public function createIndex(
        string $table,
        string|array $columns,
        ?string $indexName = null,
        string $type = 'INDEX'
    ): bool {
        if (is_string($columns)) {
            $columns = [$columns];
        }

        if ($indexName === null) {
            $indexName = 'idx_' . $table . '_' . implode('_', $columns);
        }

        $columnsStr = implode(', ', $columns);
        $sql = "CREATE {$type} {$indexName} ON {$table} ({$columnsStr})";

        try {
            $this->database->query($sql);
            return true;
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger()->logError("Помилка створення індексу '{$indexName}': " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Видалення індексу
     *
     * @param string $table Назва таблиці
     * @param string $indexName Назва індексу
     * @return bool
     */
    public function dropIndex(string $table, string $indexName): bool
    {
        $sql = "DROP INDEX {$indexName} ON {$table}";

        try {
            $this->database->query($sql);
            return true;
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger()->logError("Помилка видалення індексу '{$indexName}': " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Отримання списку індексів для таблиці
     *
     * @param string $table Назва таблиці
     * @return array<string, array<string, mixed>>
     */
    public function getIndexes(string $table): array
    {
        $sql = "SHOW INDEXES FROM {$table}";

        try {
            $result = $this->database->query($sql);
            $indexes = [];

            foreach ($result as $row) {
                $indexName = $row['Key_name'] ?? $row['key_name'] ?? '';
                if (!isset($indexes[$indexName])) {
                    $indexes[$indexName] = [
                        'name' => $indexName,
                        'columns' => [],
                        'unique' => ($row['Non_unique'] ?? $row['non_unique'] ?? 1) == 0,
                        'type' => $row['Index_type'] ?? $row['index_type'] ?? 'BTREE',
                    ];
                }
                $indexes[$indexName]['columns'][] = $row['Column_name'] ?? $row['column_name'] ?? '';
            }

            return $indexes;
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger()->logError("Помилка отримання індексів для таблиці '{$table}': " . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Перевірка, чи існує індекс
     *
     * @param string $table Назва таблиці
     * @param string $indexName Назва індексу
     * @return bool
     */
    public function indexExists(string $table, string $indexName): bool
    {
        $indexes = $this->getIndexes($table);
        return isset($indexes[$indexName]);
    }

    /**
     * Аналіз використання індексів для запиту
     *
     * @param string $sql SQL запит
     * @return array<string, mixed>|null
     */
    public function analyzeQuery(string $sql): ?array
    {
        $explainSql = "EXPLAIN {$sql}";

        try {
            $result = $this->database->query($explainSql);
            return $result[0] ?? null;
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger()->logError("Помилка аналізу запиту: " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Рекомендації щодо індексів на основі повільних запитів
     *
     * @param array<string, array<string, mixed>> $slowQueries Масив повільних запитів
     * @return array<string, array<string, mixed>>
     */
    public function recommendIndexes(array $slowQueries): array
    {
        $recommendations = [];

        foreach ($slowQueries as $query) {
            $sql = $query['sql'] ?? '';
            $table = $this->extractTable($sql);

            if ($table === null) {
                continue;
            }

            $whereColumns = $this->extractWhereColumns($sql);
            $joinColumns = $this->extractJoinColumns($sql);
            $orderByColumns = $this->extractOrderByColumns($sql);

            $candidateColumns = array_unique(array_merge($whereColumns, $joinColumns, $orderByColumns));

            if (!empty($candidateColumns)) {
                $recommendations[] = [
                    'table' => $table,
                    'columns' => $candidateColumns,
                    'reason' => 'Використовується в WHERE/JOIN/ORDER BY',
                    'query' => $sql,
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Витягування назви таблиці з SQL
     *
     * @param string $sql
     * @return string|null
     */
    private function extractTable(string $sql): ?string
    {
        if (preg_match('/FROM\s+`?(\w+)`?/i', $sql, $matches)) {
            return $matches[1];
        }
        if (preg_match('/UPDATE\s+`?(\w+)`?/i', $sql, $matches)) {
            return $matches[1];
        }
        if (preg_match('/INTO\s+`?(\w+)`?/i', $sql, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Витягування колонок з WHERE
     *
     * @param string $sql
     * @return array<string>
     */
    private function extractWhereColumns(string $sql): array
    {
        $columns = [];
        if (preg_match('/WHERE\s+(.+?)(?:\s+GROUP|\s+ORDER|\s+LIMIT|$)/i', $sql, $matches)) {
            $whereClause = $matches[1];
            if (preg_match_all('/`?(\w+)`?\s*[=<>!]/i', $whereClause, $colMatches)) {
                $columns = $colMatches[1];
            }
        }
        return $columns;
    }

    /**
     * Витягування колонок з JOIN
     *
     * @param string $sql
     * @return array<string>
     */
    private function extractJoinColumns(string $sql): array
    {
        $columns = [];
        if (preg_match_all('/JOIN\s+`?(\w+)`?\s+ON\s+`?(\w+)`?/i', $sql, $matches)) {
            $columns = array_merge($columns, $matches[2]);
        }
        return $columns;
    }

    /**
     * Витягування колонок з ORDER BY
     *
     * @param string $sql
     * @return array<string>
     */
    private function extractOrderByColumns(string $sql): array
    {
        $columns = [];
        if (preg_match('/ORDER\s+BY\s+(.+?)(?:\s+LIMIT|$)/i', $sql, $matches)) {
            $orderClause = $matches[1];
            if (preg_match_all('/`?(\w+)`?/i', $orderClause, $colMatches)) {
                $columns = $colMatches[1];
            }
        }
        return $columns;
    }
}
