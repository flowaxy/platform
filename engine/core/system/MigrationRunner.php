<?php

/**
 * Система миграцій бази даних
 * Автоматично знаходить та виконує всі міграції
 *
 * @package Engine\Core\System
 */

declare(strict_types=1);

final class MigrationRunner
{
    private string $migrationsDir;
    private ?\PDO $db = null;

    public function __construct(string $migrationsDir, ?\PDO $db = null)
    {
        $this->migrationsDir = rtrim($migrationsDir, '/\\');
        $this->db = $db;
    }

    /**
     * Виконання всіх міграцій
     */
    public function run(): void
    {
        if (! file_exists($this->migrationsDir)) {
            return;
        }

        $db = $this->getDatabase();
        if (! $db) {
            return;
        }

        // Створюємо таблицю для відстеження виконаних міграцій
        $this->ensureMigrationsTable($db);

        // Знаходимо всі файли міграцій
        $migrations = $this->findMigrations();

        foreach ($migrations as $migration) {
            if ($this->isMigrationExecuted($db, $migration['name'])) {
                continue;
            }

            $this->executeMigration($db, $migration);
        }
    }

    /**
     * Створення таблиці для відстеження міграцій
     */
    private function ensureMigrationsTable(\PDO $db): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        try {
            $db->exec($sql);
        } catch (\Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('Failed to create migrations table', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Пошук всіх файлів міграцій
     *
     * @return array<int, array{name: string, file: string}>
     */
    private function findMigrations(): array
    {
        $migrations = [];
        $files = glob($this->migrationsDir . '/*.php');

        if (! $files) {
            return $migrations;
        }

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $migrations[] = [
                'name' => $name,
                'file' => $file,
            ];
        }

        // Сортуємо за ім'ям файлу
        usort($migrations, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $migrations;
    }

    /**
     * Перевірка, чи виконана міграція
     */
    private function isMigrationExecuted(\PDO $db, string $migrationName): bool
    {
        try {
            $stmt = $db->prepare('SELECT COUNT(*) FROM migrations WHERE migration = ?');
            $stmt->execute([$migrationName]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Виконання міграції
     */
    private function executeMigration(\PDO $db, array $migration): void
    {
        try {
            require_once $migration['file'];

            // Шукаємо функцію міграції (формат: migration_*)
            $functionName = 'migration_' . str_replace('-', '_', $migration['name']);

            if (! function_exists($functionName)) {
                if (class_exists('Logger')) {
                    Logger::getInstance()->logWarning("Migration function not found: {$functionName}", [
                        'migration' => $migration['name'],
                    ]);
                }

                return;
            }

            // Виконуємо міграцію
            $result = $functionName($db);

            if ($result === false) {
                if (class_exists('Logger')) {
                    Logger::getInstance()->logError("Migration failed: {$migration['name']}");
                }

                return;
            }

            // Позначаємо міграцію як виконану
            $stmt = $db->prepare('INSERT INTO migrations (migration) VALUES (?)');
            $stmt->execute([$migration['name']]);

            if (class_exists('Logger')) {
                Logger::getInstance()->logInfo("Migration executed: {$migration['name']}");
            }
        } catch (\Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError("Migration error: {$migration['name']}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Отримання підключення до БД
     */
    private function getDatabase(): ?\PDO
    {
        if ($this->db !== null) {
            return $this->db;
        }

        if (class_exists('DatabaseHelper') && function_exists('DatabaseHelper::getConnection')) {
            return DatabaseHelper::getConnection();
        }

        return null;
    }
}
