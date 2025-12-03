<?php

/**
 * Централізована система конфігурації параметрів системи
 * Управління всіма параметрами через SettingsManager
 *
 * @package Engine\Classes\Data
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class SystemConfig
{
    private static ?self $instance = null;
    private array $cache = [];

    /**
     * Конструктор (приватний для Singleton)
     */
    private function __construct()
    {
    }

    /**
     * Отримання екземпляра класу (Singleton)
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Отримання параметра з кешуванням
     *
     * @param string $key Ключ параметра
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    private function getParam(string $key, $default = null)
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        if (class_exists('SettingsManager') && function_exists('settingsManager')) {
            $settings = settingsManager();
            if ($settings !== null) {
                $value = $settings->get($key, (string)$default);
                // Перетворюємо рядок на потрібний тип
                $this->cache[$key] = $this->convertValue($value, $default);

                return $this->cache[$key];
            }
        }

        $this->cache[$key] = $default;

        return $default;
    }

    /**
     * Перетворення значення на потрібний тип
     *
     * @param mixed $value Значення
     * @param mixed $default Значення за замовчуванням (для визначення типу)
     * @return mixed
     */
    private function convertValue($value, $default)
    {
        if ($value === '' || $value === null) {
            return $default;
        }

        // Визначаємо тип за замовчуванням
        if (is_int($default)) {
            return (int)$value;
        } elseif (is_float($default)) {
            return (float)$value;
        } elseif (is_bool($default)) {
            return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
        }

        return $value;
    }

    /**
     * Очищення кешу
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    // ========== Параметри сесій ==========

    /**
     * Час життя сесії (в секундах)
     *
     * @return int
     */
    public function getSessionLifetime(): int
    {
        return $this->getParam('session_lifetime', 7200); // 2 години за замовчуванням
    }

    /**
     * Ім'я сесії
     *
     * @return string
     */
    public function getSessionName(): string
    {
        return $this->getParam('session_name', 'PHPSESSID');
    }

    // ========== Параметри бази даних ==========

    /**
     * Таймаут підключення до БД (в секундах)
     *
     * @return int
     */
    public function getDbConnectionTimeout(): int
    {
        return $this->getParam('db_connection_timeout', 3);
    }

    /**
     * Максимальна кількість спроб підключення до БД
     *
     * @return int
     */
    public function getDbMaxAttempts(): int
    {
        return $this->getParam('db_max_attempts', 3);
    }

    /**
     * Таймаут перевірки хоста БД (в секундах)
     *
     * @return int
     */
    public function getDbHostCheckTimeout(): int
    {
        return $this->getParam('db_host_check_timeout', 1);
    }

    /**
     * Поріг повільних запитів (в секундах)
     *
     * @return float
     */
    public function getDbSlowQueryThreshold(): float
    {
        return (float)$this->getParam('db_slow_query_threshold', 1.0);
    }

    // ========== Параметри кешу ==========

    /**
     * Увімкнено кеш
     *
     * @return bool
     */
    public function isCacheEnabled(): bool
    {
        return $this->getParam('cache_enabled', true);
    }

    /**
     * Час життя кешу за замовчуванням (в секундах)
     *
     * @return int
     */
    public function getCacheDefaultTtl(): int
    {
        return $this->getParam('cache_default_ttl', 3600); // 1 година
    }

    /**
     * Автоматичне очищення кешу
     *
     * @return bool
     */
    public function isCacheAutoCleanup(): bool
    {
        return $this->getParam('cache_auto_cleanup', true);
    }

    // ========== Параметри логування ==========

    /**
     * Увімкнено логування
     *
     * @return bool
     */
    public function isLoggingEnabled(): bool
    {
        return $this->getParam('logging_enabled', true);
    }

    /**
     * Мінімальний рівень логування
     *
     * @return string
     */
    public function getLoggingLevel(): string
    {
        return $this->getParam('logging_level', 'INFO');
    }

    /**
     * Максимальний розмір файлу логу (в байтах)
     *
     * @return int
     */
    public function getLoggingMaxFileSize(): int
    {
        return $this->getParam('logging_max_file_size', 10485760); // 10 MB
    }

    /**
     * Кількість днів зберігання логів
     *
     * @return int
     */
    public function getLoggingRetentionDays(): int
    {
        return $this->getParam('logging_retention_days', 30);
    }

    // ========== Параметри завантаження файлів ==========

    /**
     * Максимальний розмір завантажуваного файлу (в байтах)
     *
     * @return int
     */
    public function getUploadMaxFileSize(): int
    {
        return $this->getParam('upload_max_file_size', 10485760); // 10 MB
    }

    /**
     * Дозволені розширення файлів
     *
     * @return array
     */
    public function getUploadAllowedExtensions(): array
    {
        $extensions = $this->getParam('upload_allowed_extensions', 'jpg,jpeg,png,gif,pdf,doc,docx,zip');
        if (is_string($extensions)) {
            return array_map('trim', explode(',', $extensions));
        }

        return is_array($extensions) ? $extensions : [];
    }

    /**
     * Дозволені MIME типи
     *
     * @return array
     */
    public function getUploadAllowedMimeTypes(): array
    {
        $mimeTypes = $this->getParam('upload_allowed_mime_types', 'image/jpeg,image/png,image/gif,application/pdf');
        if (is_string($mimeTypes)) {
            return array_map('trim', explode(',', $mimeTypes));
        }

        return is_array($mimeTypes) ? $mimeTypes : [];
    }

    // ========== Параметри безпеки ==========

    /**
     * Мінімальна довжина пароля
     *
     * @return int
     */
    public function getPasswordMinLength(): int
    {
        return $this->getParam('password_min_length', 8);
    }

    /**
     * Час життя CSRF токена (в секундах)
     *
     * @return int
     */
    public function getCsrfTokenLifetime(): int
    {
        return $this->getParam('csrf_token_lifetime', 3600); // 1 година
    }

    // ========== Параметри продуктивності ==========

    /**
     * Увімкнено оптимізацію запитів
     *
     * @return bool
     */
    public function isQueryOptimizationEnabled(): bool
    {
        return $this->getParam('query_optimization_enabled', true);
    }

    /**
     * Максимальна кількість запитів в секунду
     *
     * @return int
     */
    public function getMaxQueriesPerSecond(): int
    {
        return $this->getParam('max_queries_per_second', 100);
    }

    // Запобігання клонуванню та десеріалізації
    private function __clone()
    {
    }

    /**
     * @return void
     * @throws Exception
     */
    public function __wakeup(): void
    {
        throw new Exception('Cannot unserialize singleton');
    }
}

/**
 * Глобальна функція для отримання екземпляра SystemConfig
 *
 * @return SystemConfig
 */
function systemConfig(): SystemConfig
{
    return SystemConfig::getInstance();
}
