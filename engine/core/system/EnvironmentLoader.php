<?php

/**
 * Стандартизований завантажувач environment конфігурацій
 * Підтримка INI/JSON форматів та layered overrides
 *
 * @package Engine\Core\System
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../../infrastructure/filesystem/Ini.php';
require_once __DIR__ . '/../../infrastructure/filesystem/Json.php';

class EnvironmentLoader
{
    private string $rootDir;
    private string $configDir;
    private string $environment;
    private array $loadedConfigs = [];
    private array $envVariables = [];

    /**
     * @param string $rootDir Корінь проекту
     * @param string|null $configDir Директорія для конфігурацій
     * @param string|null $environment Середовище (development, staging, production)
     */
    public function __construct(string $rootDir, ?string $configDir = null, ?string $environment = null)
    {
        $this->rootDir = rtrim($rootDir, '/\\');
        $this->configDir = $configDir ?? $this->rootDir . '/storage/config/';
        $this->configDir = rtrim($this->configDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->environment = $environment ?? $this->detectEnvironment();
    }

    /**
     * Автоматичне визначення середовища
     */
    private function detectEnvironment(): string
    {
        // Перевіряємо змінну оточення
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV');
        if ($env !== false && ! empty($env)) {
            return strtolower((string)$env);
        }

        // Перевіряємо домен
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1') || str_contains($host, '.local')) {
            return 'development';
        }

        if (str_contains($host, 'staging') || str_contains($host, 'stage')) {
            return 'staging';
        }

        // За замовчуванням - production
        return 'production';
    }

    /**
     * Завантаження всіх environment конфігурацій
     *
     * @return array<string, mixed> Об'єднана конфігурація з урахуванням overrides
     */
    public function load(): array
    {
        // Завантажуємо .env файли (якщо є)
        $this->loadEnvFiles();

        // Завантажуємо конфігурації з пріоритетом:
        // 1. Базова конфігурація (загальна)
        // 2. Environment-специфічна конфігурація
        // 3. Local конфігурація (найвищий пріоритет)

        $config = [];

        // 1. Базова конфігурація
        $config = $this->mergeConfig($config, $this->loadConfigFile('environment.json'));
        $config = $this->mergeConfig($config, $this->loadConfigFile('environment.ini'));

        // 2. Environment-специфічна конфігурація
        $config = $this->mergeConfig($config, $this->loadConfigFile("environment.{$this->environment}.json"));
        $config = $this->mergeConfig($config, $this->loadConfigFile("environment.{$this->environment}.ini"));

        // 3. Local конфігурація (найвищий пріоритет)
        $config = $this->mergeConfig($config, $this->loadConfigFile('environment.local.json'));
        $config = $this->mergeConfig($config, $this->loadConfigFile('environment.local.ini'));

        // 4. Застосовуємо змінні оточення як overrides
        $config = $this->applyEnvironmentOverrides($config);

        $this->loadedConfigs = $config;

        return $config;
    }

    /**
     * Завантаження .env файлів
     */
    private function loadEnvFiles(): void
    {
        $envFiles = [
            $this->rootDir . '/.env',
            $this->rootDir . '/.env.local',
            $this->rootDir . "/.env.{$this->environment}",
            $this->rootDir . "/.env.{$this->environment}.local",
        ];

        foreach ($envFiles as $file) {
            if (file_exists($file) && is_readable($file)) {
                $this->parseEnvFile($file);
            }
        }
    }

    /**
     * Парсинг .env файлу
     */
    private function parseEnvFile(string $filePath): void
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            // Пропускаємо коментарі
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Парсимо KEY=VALUE
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Видаляємо лапки
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                // Зберігаємо змінну оточення
                $this->envVariables[$key] = $value;
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }

    /**
     * Завантаження конфігураційного файлу
     *
     * @param string $filename Ім'я файлу
     * @return array<string, mixed>
     */
    private function loadConfigFile(string $filename): array
    {
        $filePath = $this->configDir . $filename;

        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return [];
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        try {
            if ($extension === 'json') {
                $json = new Json($filePath);
                $json->load(true);
                return $json->getAll([]);
            } elseif ($extension === 'ini') {
                $ini = new Ini($filePath);
                $ini->load();
                // Повертаємо структуру як є (з секціями), а не плоску
                return $ini->all();
            }
        } catch (Exception $e) {
            logger()->logError("EnvironmentLoader: помилка завантаження {$filename}: " . $e->getMessage(), [
                'exception' => $e,
                'filename' => $filename
            ]);
        }

        return [];
    }

    /**
     * Перетворення INI структури в плоский масив (використовується для setValueByPath)
     * Залишаємо метод для зворотної сумісності, але не використовуємо при завантаженні
     *
     * @param array<string, mixed> $iniData
     * @return array<string, mixed>
     */
    private function flattenIni(array $iniData): array
    {
        $result = [];
        foreach ($iniData as $section => $values) {
            if (is_array($values)) {
                foreach ($values as $key => $value) {
                    $result["{$section}.{$key}"] = $value;
                }
            } else {
                $result[$section] = $values;
            }
        }
        return $result;
    }

    /**
     * Об'єднання конфігурацій з пріоритетом (другий масив перезаписує перший)
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function mergeConfig(array $base, array $override): array
    {
        if (empty($override)) {
            return $base;
        }

        // Рекурсивне об'єднання з перезаписом
        foreach ($override as $key => $value) {
            if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
                $base[$key] = $this->mergeConfig($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Застосування перевизначень з environment змінних
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function applyEnvironmentOverrides(array $config): array
    {
        foreach ($this->envVariables as $key => $value) {
            // Пропускаємо системні змінні
            if (in_array($key, ['APP_ENV', 'PATH', 'HOME'], true)) {
                continue;
            }

            // Перетворюємо значення
            $convertedValue = $this->convertValue($value);

            // Підтримка нотації з підкресленнями для вкладених ключів
            // APP_DATABASE_HOST -> database.host
            $configKey = $this->convertEnvKeyToConfigKey($key);

            // Встановлюємо значення за шляхом
            $this->setValueByPath($config, $configKey, $convertedValue);
        }

        return $config;
    }

    /**
     * Перетворення ключа environment змінної в конфігураційний ключ
     * APP_DATABASE_HOST -> database.host
     * APP_CACHE_ENABLED -> cache.enabled
     */
    private function convertEnvKeyToConfigKey(string $envKey): string
    {
        // Прибираємо префікс APP_ якщо є
        $key = str_replace('APP_', '', $envKey);

        // Розділяємо по підкресленням та перетворюємо на нижній регістр
        $parts = explode('_', $key);
        $parts = array_map('strtolower', $parts);

        // Об'єднуємо через крапку
        return implode('.', $parts);
    }

    /**
     * Перетворення значення з рядка в потрібний тип
     */
    private function convertValue(string $value): mixed
    {
        // Boolean
        if (in_array(strtolower($value), ['true', '1', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array(strtolower($value), ['false', '0', 'no', 'off', ''], true)) {
            return false;
        }

        // Числа
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float)$value : (int)$value;
        }

        // JSON
        if ((str_starts_with($value, '[') && str_ends_with($value, ']')) ||
            (str_starts_with($value, '{') && str_ends_with($value, '}'))) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    /**
     * Встановлення значення за шляхом (точкова нотація)
     */
    private function setValueByPath(array &$data, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $lastKey = array_pop($keys);
        $target = &$data;

        foreach ($keys as $key) {
            if (! isset($target[$key]) || ! is_array($target[$key])) {
                $target[$key] = [];
            }
            $target = &$target[$key];
        }

        $target[$lastKey] = $value;
    }

    /**
     * Отримання значення конфігурації
     *
     * @param string $key Ключ (може бути з точкою для вкладених значень)
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (empty($this->loadedConfigs)) {
            $this->load();
        }

        return $this->getValueByPath($this->loadedConfigs, $key, $default);
    }

    /**
     * Отримання значення за шляхом
     */
    private function getValueByPath(array $data, string $path, $default = null)
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Перевірка наявності ключа
     */
    public function has(string $key): bool
    {
        if (empty($this->loadedConfigs)) {
            $this->load();
        }

        return $this->hasValueByPath($this->loadedConfigs, $key);
    }

    /**
     * Перевірка наявності значення за шляхом
     */
    private function hasValueByPath(array $data, string $path): bool
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Отримання всієї конфігурації
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if (empty($this->loadedConfigs)) {
            $this->load();
        }

        return $this->loadedConfigs;
    }

    /**
     * Отримання поточного середовища
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Отримання змінних оточення
     *
     * @return array<string, string>
     */
    public function getEnvVariables(): array
    {
        return $this->envVariables;
    }

    /**
     * Перезавантаження конфігурації
     */
    public function reload(): void
    {
        $this->loadedConfigs = [];
        $this->envVariables = [];
        $this->load();
    }
}

