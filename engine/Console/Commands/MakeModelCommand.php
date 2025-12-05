<?php

/**
 * Команда генерації моделі
 *
 * @package Flowaxy\Core\System\Commands
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Commands;

class MakeModelCommand extends MakeCommand
{
    /**
     * Генерація моделі
     *
     * @param array $args Аргументи команди
     * @return void
     */
    public function run(array $args): void
    {
        if (empty($args[0])) {
            echo "Помилка: не вказано ім'я моделі\n";
            echo "Використання: make:model <ModelName> [--namespace=Namespace] [--table=table_name]\n";
            exit(1);
        }

        $modelName = $this->normalizeClassName($args[0]);
        $namespace = $args['namespace'] ?? 'Application\\Models';
        $tableName = $args['table'] ?? $this->normalizeSlug($args[0]);

        // Визначаємо шлях до файлу
        $namespacePath = str_replace('\\', DIRECTORY_SEPARATOR, $namespace);
        $modelDir = $this->rootDir . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'models';
        if ($namespace !== 'Application\\Models') {
            $modelDir = $this->rootDir . DIRECTORY_SEPARATOR . strtolower($namespacePath);
        }

        $modelFile = $modelDir . DIRECTORY_SEPARATOR . $modelName . '.php';

        if ($this->fileExists($modelFile)) {
            echo "Помилка: файл {$modelFile} вже існує\n";
            exit(1);
        }

        // Генеруємо вміст моделі
        $content = $this->generateModelContent($modelName, $namespace, $tableName);

        if ($this->writeFile($modelFile, $content)) {
            echo "✓ Модель {$modelName} успішно створена: {$modelFile}\n";
        } else {
            echo "✗ Помилка при створенні моделі {$modelName}\n";
            exit(1);
        }
    }

    /**
     * Генерація вмісту моделі
     *
     * @param string $modelName
     * @param string $namespace
     * @param string $tableName
     * @return string
     */
    private function generateModelContent(string $modelName, string $namespace, string $tableName): string
    {
        $namespaceEscaped = str_replace('\\', '\\\\', $namespace);

        return <<<PHP
<?php

/**
 * Модель {$modelName}
 *
 * @package {$namespaceEscaped}
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace {$namespace};

require_once __DIR__ . '/../../Database/Database.php';

use Flowaxy\Core\Infrastructure\Persistence\Database;

class {$modelName}
{
    /**
     * @var string Назва таблиці
     */
    protected string \$table = '{$tableName}';

    /**
     * @var Database|null Екземпляр бази даних
     */
    protected ?Database \$db = null;

    /**
     * Конструктор
     */
    public function __construct()
    {
        \$this->db = Database::getInstance();
    }

    /**
     * Отримання всіх записів
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return \$this->db->query("SELECT * FROM {\$this->table}")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Отримання запису за ID
     *
     * @param int \$id
     * @return array<string, mixed>|null
     */
    public function find(int \$id): ?array
    {
        \$stmt = \$this->db->query("SELECT * FROM {\$this->table} WHERE id = :id", ['id' => \$id]);
        \$result = \$stmt->fetch(\PDO::FETCH_ASSOC);
        return \$result ?: null;
    }

    /**
     * Створення нового запису
     *
     * @param array<string, mixed> \$data
     * @return int|false ID нового запису або false при помилці
     */
    public function create(array \$data)
    {
        \$columns = implode(', ', array_keys(\$data));
        \$placeholders = ':' . implode(', :', array_keys(\$data));

        \$sql = "INSERT INTO {\$this->table} ({$columns}) VALUES ({$placeholders})";
        \$stmt = \$this->db->query(\$sql, \$data);

        return \$this->db->lastInsertId();
    }

    /**
     * Оновлення запису
     *
     * @param int \$id
     * @param array<string, mixed> \$data
     * @return bool
     */
    public function update(int \$id, array \$data): bool
    {
        \$set = [];
        foreach (array_keys(\$data) as \$key) {
            \$set[] = "{\$key} = :{\$key}";
        }
        \$setClause = implode(', ', \$set);

        \$data['id'] = \$id;
        \$sql = "UPDATE {\$this->table} SET {\$setClause} WHERE id = :id";
        \$stmt = \$this->db->query(\$sql, \$data);

        return \$stmt->rowCount() > 0;
    }

    /**
     * Видалення запису
     *
     * @param int \$id
     * @return bool
     */
    public function delete(int \$id): bool
    {
        \$stmt = \$this->db->query("DELETE FROM {\$this->table} WHERE id = :id", ['id' => \$id]);
        return \$stmt->rowCount() > 0;
    }

    /**
     * Отримання назви таблиці
     *
     * @return string
     */
    public function getTable(): string
    {
        return \$this->table;
    }
}
PHP;
    }
}
