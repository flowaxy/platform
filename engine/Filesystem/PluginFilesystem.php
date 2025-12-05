<?php

declare(strict_types=1);

final class PluginFilesystem implements PluginFilesystemInterface
{
    private string $pluginsDir;

    public function __construct(?string $pluginsDir = null)
    {
        $rootDir = dirname(__DIR__, 3);
        $this->pluginsDir = $pluginsDir ?? $rootDir . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR;
    }

    public function exists(string $slug): bool
    {
        return is_dir($this->pluginsDir . $slug);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readConfig(string $slug): ?array
    {
        $configFile = $this->pluginsDir . $slug . '/plugin.json';
        if (! is_readable($configFile)) {
            return null;
        }

        try {
            $json = new Json($configFile);
            $json->load(true);
            $config = $json->getAll([]);

            return is_array($config) ? $config : null;
        } catch (Throwable $e) {
            logger()->logError("PluginFilesystem readConfig error for {$slug}: " . $e->getMessage(), ['exception' => $e, 'slug' => $slug]);
            return null;
        }
    }

    public function runMigrations(string $slug): void
    {
        $dbDir = $this->pluginsDir . $slug . '/db';
        if (! is_dir($dbDir)) {
            return;
        }

        $files = [];
        if (file_exists($dbDir . '/install.sql')) {
            $files[] = $dbDir . '/install.sql';
        } else {
            $files = glob($dbDir . '/*.sql') ?: [];
        }

        foreach ($files as $file) {
            $this->executeSqlFile($file);
        }
    }

    public function delete(string $slug): bool
    {
        $dir = $this->pluginsDir . $slug;
        if (! is_dir($dir)) {
            return false;
        }

        $this->deleteDirectory($dir);

        return ! is_dir($dir);
    }

    private function deleteDirectory(string $dir): void
    {
        $items = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    private function executeSqlFile(string $sqlFile): void
    {
        $connection = $this->connection();
        if (! $connection || ! is_readable($sqlFile)) {
            return;
        }

        try {
            $sql = file_get_contents($sqlFile);
            if ($sql === false) {
                return;
            }

            $sql = preg_replace('/--.*$/m', '', $sql);
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
            $queries = explode(';', (string)$sql);

            foreach ($queries as $query) {
                $query = trim($query);
                if ($query === '') {
                    continue;
                }

                try {
                    $connection->exec($query);
                } catch (PDOException $e) {
                    $message = $e->getMessage();
                    if (! str_contains($message, 'Duplicate column') &&
                        ! str_contains($message, 'Duplicate key') &&
                        ! str_contains($message, 'already exists')) {
                        throw $e;
                    }
                }
            }
        } catch (Throwable $e) {
            logger()->logError("PluginFilesystem SQL error ({$sqlFile}): " . $e->getMessage(), ['exception' => $e, 'sql_file' => $sqlFile]);
        }
    }

    private function connection(): ?PDO
    {
        try {
            return Database::getInstance()->getConnection();
        } catch (Throwable $e) {
            logger()->logError('PluginFilesystem connection error: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }
}
