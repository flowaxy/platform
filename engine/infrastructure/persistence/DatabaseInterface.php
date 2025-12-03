<?php

/**
 * Інтерфейс для роботи з базою даних
 *
 * Визначає контракт для всіх реалізацій роботи з базою даних
 * Дозволяє замінювати реалізацію БД без зміни коду, що його використовує
 *
 * @package Engine\Interfaces
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

interface DatabaseInterface
{
    /**
     * Отримання підключення до бази даних
     *
     * @return PDO
     * @throws Exception
     */
    public function getConnection(): PDO;

    /**
     * Виконання підготовленого запиту
     *
     * @param string $query SQL запит
     * @param array $params Параметри для підготовленого запиту
     * @param bool $logQuery Логувати запит
     * @return PDOStatement
     * @throws PDOException
     */
    public function query(string $query, array $params = [], bool $logQuery = true): PDOStatement;

    /**
     * Отримання одного рядка
     *
     * @param string $query SQL запит
     * @param array $params Параметри
     * @return array|false
     */
    public function getRow(string $query, array $params = []): array|false;

    /**
     * Отримання всіх рядків
     *
     * @param string $query SQL запит
     * @param array $params Параметри
     * @return array
     */
    public function getAll(string $query, array $params = []): array;

    /**
     * Отримання одного значення
     *
     * @param string $query SQL запит
     * @param array $params Параметри
     * @return mixed
     */
    public function getValue(string $query, array $params = []): mixed;

    /**
     * Вставка запису та отримання ID
     *
     * @param string $query SQL запит INSERT
     * @param array $params Параметри
     * @return int|false ID вставленого запису або false при помилці
     */
    public function insert(string $query, array $params = []): int|false;

    /**
     * Виконання запиту (UPDATE, DELETE, etc.)
     *
     * @param string $query SQL запит
     * @param array $params Параметри
     * @return int Кількість затронутих рядків
     */
    public function execute(string $query, array $params = []): int;

    /**
     * Виконання транзакції
     *
     * @param callable $callback Функція зворотного виклику з PDO як параметром
     * @return mixed Результат виконання callback
     * @throws Exception
     */
    public function transaction(callable $callback);

    /**
     * Екранування рядка для безпечного використання в SQL
     *
     * @param string $string Рядок для екранування
     * @return string Екранований рядок
     */
    public function escape(string $string): string;

    /**
     * Перевірка доступності бази даних
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Перевірка існування бази даних
     *
     * @return bool
     */
    public function databaseExists(): bool;

    /**
     * Перевірка з'єднання з базою даних (ping)
     *
     * @return bool
     */
    public function ping(): bool;

    /**
     * Відключення від бази даних
     *
     * @return void
     */
    public function disconnect(): void;

    /**
     * Отримання статистики запитів
     *
     * @return array Статистика (query_count, total_time, avg_time, etc.)
     */
    public function getStats(): array;

    /**
     * Отримання списку виконаних запитів
     *
     * @return array Масив запитів
     */
    public function getQueryList(): array;

    /**
     * Отримання списку помилок запитів
     *
     * @return array Масив помилок
     */
    public function getQueryErrors(): array;

    /**
     * Встановлення порогу повільних запитів
     *
     * @param float $seconds Поріг у секундах
     * @return void
     */
    public function setSlowQueryThreshold(float $seconds): void;

    /**
     * Очищення статистики
     *
     * @return void
     */
    public function clearStats(): void;
}
