<?php

/**
 * Оптимізатор SQL запитів
 *
 * Аналізує та оптимізує SQL запити для покращення продуктивності.
 *
 * @package Flowaxy\Core\Infrastructure\Persistence
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Infrastructure\Persistence;

final class QueryOptimizer
{
    public function __construct(
        private Database $database
    ) {
    }

    /**
     * Аналіз запиту через EXPLAIN
     *
     * @param string $sql SQL запит
     * @return array<string, mixed>|null
     */
    public function explain(string $sql): ?array
    {
        $explainSql = "EXPLAIN {$sql}";

        try {
            $result = $this->database->query($explainSql);
            return $result[0] ?? null;
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger()->logError("Помилка EXPLAIN для запиту: " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Перевірка, чи використовується індекс
     *
     * @param string $sql SQL запит
     * @return bool
     */
    public function usesIndex(string $sql): bool
    {
        $explain = $this->explain($sql);
        if ($explain === null) {
            return false;
        }

        $key = $explain['key'] ?? $explain['Key'] ?? null;
        return $key !== null && $key !== '';
    }

    /**
     * Перевірка, чи використовується повне сканування таблиці (FULL TABLE SCAN)
     *
     * @param string $sql SQL запит
     * @return bool
     */
    public function usesFullTableScan(string $sql): bool
    {
        $explain = $this->explain($sql);
        if ($explain === null) {
            return false;
        }

        $type = $explain['type'] ?? $explain['Type'] ?? '';
        return strtoupper($type) === 'ALL';
    }

    /**
     * Отримання рекомендацій щодо оптимізації запиту
     *
     * @param string $sql SQL запит
     * @return array<string, string>
     */
    public function getOptimizationRecommendations(string $sql): array
    {
        $recommendations = [];
        $explain = $this->explain($sql);

        if ($explain === null) {
            return ['Помилка аналізу запиту'];
        }

        // Перевірка на повне сканування таблиці
        if ($this->usesFullTableScan($sql)) {
            $recommendations[] = 'Використовується повне сканування таблиці. Рекомендується додати індекси на колонки, що використовуються в WHERE/JOIN/ORDER BY.';
        }

        // Перевірка на відсутність індексів
        if (!$this->usesIndex($sql)) {
            $recommendations[] = 'Запит не використовує індекси. Рекомендується додати індекси для покращення продуктивності.';
        }

        // Перевірка на використання SELECT *
        if (preg_match('/SELECT\s+\*\s+FROM/i', $sql)) {
            $recommendations[] = 'Використовується SELECT *. Рекомендується вказувати конкретні колонки для зменшення обсягу даних.';
        }

        // Перевірка на відсутність LIMIT у великих запитах
        if (!preg_match('/LIMIT\s+\d+/i', $sql) && preg_match('/SELECT/i', $sql)) {
            $recommendations[] = 'Запит не має обмеження LIMIT. Рекомендується додати LIMIT для обмеження кількості результатів.';
        }

        // Перевірка на використання LIKE з початковим %
        if (preg_match('/LIKE\s+[\'"]%.*?[\'"]/i', $sql)) {
            $recommendations[] = 'Використовується LIKE з початковим %. Це запобігає використанню індексів. Розгляньте можливість використання FULLTEXT індексів.';
        }

        return $recommendations;
    }

    /**
     * Отримання статистики виконання запиту
     *
     * @param string $sql SQL запит
     * @return array<string, mixed>|null
     */
    public function getQueryStats(string $sql): ?array
    {
        $explain = $this->explain($sql);
        if ($explain === null) {
            return null;
        }

        return [
            'type' => $explain['type'] ?? $explain['Type'] ?? 'unknown',
            'possible_keys' => $explain['possible_keys'] ?? $explain['Possible_keys'] ?? null,
            'key' => $explain['key'] ?? $explain['Key'] ?? null,
            'key_len' => $explain['key_len'] ?? $explain['Key_len'] ?? null,
            'rows' => $explain['rows'] ?? $explain['Rows'] ?? null,
            'extra' => $explain['Extra'] ?? $explain['extra'] ?? null,
        ];
    }
}
