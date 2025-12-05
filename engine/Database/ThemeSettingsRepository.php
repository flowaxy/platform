<?php

declare(strict_types=1);

final class ThemeSettingsRepository implements ThemeSettingsRepositoryInterface
{
    private ?\PDO $connection = null;

    public function __construct()
    {
        try {
            $this->connection = \Flowaxy\Core\Infrastructure\Persistence\Database::getInstance()->getConnection();
        } catch (Throwable $e) {
            logger()->logError('ThemeSettingsRepository ctor error: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function get(string $themeSlug): array
    {
        if ($this->connection === null || $themeSlug === '') {
            return [];
        }

        try {
            $stmt = $this->connection->prepare('SELECT setting_key, setting_value FROM theme_settings WHERE theme_slug = ?');
            $stmt->execute([$themeSlug]);

            $settings = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            return $settings;
        } catch (\PDOException $e) {
            error_log('ThemeSettingsRepository get error: ' . $e->getMessage());

            return [];
        }
    }

    public function getValue(string $themeSlug, string $key, mixed $default = null): mixed
    {
        $settings = $this->get($themeSlug);

        return $settings[$key] ?? $default;
    }

    public function setValue(string $themeSlug, string $key, mixed $value): bool
    {
        if ($this->connection === null || $themeSlug === '' || $key === '') {
            return false;
        }

        $valueStr = $this->normalizeValue($value);

        try {
            $stmt = $this->connection->prepare('
                INSERT INTO theme_settings (theme_slug, setting_key, setting_value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ');

            $stmt->execute([$themeSlug, $key, $valueStr]);

            return true;
        } catch (\PDOException $e) {
            error_log('ThemeSettingsRepository setValue error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @param string $themeSlug
     * @param array<string, mixed> $settings
     * @return bool
     */
    public function setMany(string $themeSlug, array $settings): bool
    {
        if ($this->connection === null || $themeSlug === '') {
            return false;
        }

        try {
            \Flowaxy\Core\Infrastructure\Persistence\Database::getInstance()->transaction(function () use ($themeSlug, $settings): void {
                foreach ($settings as $key => $value) {
                    if (! is_string($key) || $key === '') {
                        continue;
                    }

                    $valueStr = $this->normalizeValue($value);
                    $stmt = $this->connection->prepare('
                        INSERT INTO theme_settings (theme_slug, setting_key, setting_value)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ');
                    $stmt->execute([$themeSlug, $key, $valueStr]);
                }
            });

            return true;
        } catch (Throwable $e) {
            error_log('ThemeSettingsRepository setMany error: ' . $e->getMessage());

            return false;
        }
    }

    public function clearCache(string $themeSlug): void
    {
        if ($themeSlug === '') {
            return;
        }

        if (function_exists('cache_forget')) {
            cache_forget('theme_settings_' . $themeSlug);
        }
    }

    private function normalizeValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string)$value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
