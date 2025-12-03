<?php

declare(strict_types=1);

final class PluginRepository implements PluginRepositoryInterface
{
    private ?PDO $connection = null;
    private string $pluginsDir;

    public function __construct()
    {
        try {
            $this->connection = Database::getInstance()->getConnection();
        } catch (Throwable $e) {
            error_log('PluginRepository ctor error: ' . $e->getMessage());
        }

        $rootDir = dirname(__DIR__, 3);
        $this->pluginsDir = $rootDir . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR;
    }

    /**
     * @return array<int, Plugin>
     */
    public function all(): array
    {
        $plugins = [];
        $directories = glob($this->pluginsDir . '*', GLOB_ONLYDIR) ?: [];

        foreach ($directories as $dir) {
            $slug = basename($dir);
            $plugins[] = $this->mapPlugin($slug);
        }

        return array_filter($plugins);
    }

    public function find(string $slug): ?Plugin
    {
        return $this->mapPlugin($slug);
    }

    public function install(Plugin $plugin): bool
    {
        if ($this->connection === null) {
            return false;
        }

        try {
            $stmt = $this->connection->prepare('
                INSERT INTO plugins (name, slug, description, version, author, is_active)
                VALUES (?, ?, ?, ?, ?, 0)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    description = VALUES(description),
                    version = VALUES(version),
                    author = VALUES(author)
            ');

            return $stmt->execute([
                $plugin->name,
                $plugin->slug,
                $plugin->description,
                $plugin->version,
                $plugin->author,
            ]);
        } catch (PDOException $e) {
            error_log('PluginRepository install error: ' . $e->getMessage());

            return false;
        }
    }

    public function uninstall(string $slug): bool
    {
        if ($this->connection === null) {
            return false;
        }

        try {
            $stmt = $this->connection->prepare('DELETE FROM plugin_settings WHERE plugin_slug = ?');
            $stmt->execute([$slug]);

            $stmt = $this->connection->prepare('DELETE FROM plugins WHERE slug = ?');

            return $stmt->execute([$slug]);
        } catch (PDOException $e) {
            error_log('PluginRepository uninstall error: ' . $e->getMessage());

            return false;
        }
    }

    public function activate(string $slug): bool
    {
        return $this->updateActiveState($slug, true);
    }

    public function deactivate(string $slug): bool
    {
        return $this->updateActiveState($slug, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(string $slug): array
    {
        if ($this->connection === null) {
            return [];
        }

        try {
            $stmt = $this->connection->prepare('SELECT setting_key, setting_value FROM plugin_settings WHERE plugin_slug = ?');
            $stmt->execute([$slug]);
            $settings = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            return $settings;
        } catch (PDOException $e) {
            error_log('PluginRepository getSettings error: ' . $e->getMessage());

            return [];
        }
    }

    public function setSetting(string $slug, string $key, mixed $value): bool
    {
        if ($this->connection === null) {
            return false;
        }

        try {
            $stmt = $this->connection->prepare('
                INSERT INTO plugin_settings (plugin_slug, setting_key, setting_value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ');

            return $stmt->execute([$slug, $key, $value]);
        } catch (PDOException $e) {
            error_log('PluginRepository setSetting error: ' . $e->getMessage());

            return false;
        }
    }

    private function updateActiveState(string $slug, bool $active): bool
    {
        if ($this->connection === null) {
            return false;
        }

        try {
            $stmt = $this->connection->prepare('UPDATE plugins SET is_active = ? WHERE slug = ?');

            return $stmt->execute([$active ? 1 : 0, $slug]);
        } catch (PDOException $e) {
            error_log('PluginRepository updateActiveState error: ' . $e->getMessage());

            return false;
        }
    }

    private function mapPlugin(string $slug): ?Plugin
    {
        $config = $this->readConfig($slug);
        $dbData = $this->fetchDbRecord($slug);

        if ($config === null && $dbData === null) {
            return null;
        }

        $merged = array_merge(
            [
                'slug' => $slug,
                'name' => ucfirst($slug),
                'version' => '1.0.0',
                'description' => '',
                'author' => '',
                'is_active' => 0,
            ],
            $dbData ?? [],
            $config ?? []
        );

        return new Plugin(
            slug: $merged['slug'],
            name: $merged['name'],
            version: $merged['version'],
            active: (bool)($merged['is_active'] ?? 0),
            description: $merged['description'] ?? '',
            author: $merged['author'] ?? '',
            meta: $merged
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readConfig(string $slug): ?array
    {
        $file = $this->pluginsDir . $slug . '/plugin.json';
        if (! file_exists($file) || ! is_readable($file)) {
            return null;
        }

        try {
            $json = file_get_contents($file);
            $config = json_decode($json ?: '', true);

            return is_array($config) ? $config : null;
        } catch (Throwable $e) {
            error_log("PluginRepository readConfig error ({$slug}): " . $e->getMessage());

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchDbRecord(string $slug): ?array
    {
        if ($this->connection === null) {
            return null;
        }

        try {
            $stmt = $this->connection->prepare('SELECT * FROM plugins WHERE slug = ?');
            $stmt->execute([$slug]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            return $record ?: null;
        } catch (PDOException $e) {
            error_log('PluginRepository fetchDbRecord error: ' . $e->getMessage());

            return null;
        }
    }
}
