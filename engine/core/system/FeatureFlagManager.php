<?php

/**
 * Менеджер Feature Flags для ядра
 *
 * Управління прапорцями функцій системи з підтримкою контексту та кешування
 *
 * @package Engine\Core\System
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../contracts/FeatureFlagManagerInterface.php';
require_once __DIR__ . '/../contracts/LoggerInterface.php';

final class FeatureFlagManager implements FeatureFlagManagerInterface
{
    private array $flags = [];
    private array $cache = [];
    private bool $initialized = false;
    private const string FLAGS_KEY_PREFIX = 'feature_flag:';
    private const string FLAGS_CACHE_KEY = 'feature_flags:all';

    public function __construct(
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Ініціалізація менеджера та завантаження прапорців
     */
    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->loadFlags();
        $this->initialized = true;
    }

    /**
     * Завантаження feature flags з джерел
     */
    private function loadFlags(): void
    {
        // Завантаження з кешу, якщо доступний
        if (class_exists('Cache') && method_exists('Cache', 'getInstance')) {
            try {
                $cache = Cache::getInstance();
                $cached = $cache->get(self::FLAGS_CACHE_KEY);
                if ($cached !== null && is_array($cached)) {
                    $this->flags = $cached;
                    return;
                }
            } catch (Exception $e) {
                if ($this->logger) {
                    $this->logger->logWarning('Failed to load feature flags from cache', ['error' => $e->getMessage()]);
                }
            }
        }

        // Завантаження з налаштувань через SettingsManager
        if (function_exists('settingsManager')) {
            try {
                $settings = settingsManager();
                if ($settings !== null) {
                    // Завантаження всіх feature flags (які починаються з префіксу)
                    $allSettings = $settings->getAll();
                    foreach ($allSettings as $key => $value) {
                        if (str_starts_with($key, self::FLAGS_KEY_PREFIX)) {
                            $flagName = substr($key, strlen(self::FLAGS_KEY_PREFIX));
                            $this->flags[$flagName] = $this->normalizeValue($value);
                        }
                    }
                }
            } catch (Exception $e) {
                if ($this->logger) {
                    $this->logger->logWarning('Failed to load feature flags from settings', ['error' => $e->getMessage()]);
                }
            }
        }

        // Завантаження дефолтних feature flags з конфігурації
        $this->loadDefaultFlags();

        // Збереження в кеш
        $this->saveToCache();
    }

    /**
     * Завантаження дефолтних feature flags
     */
    private function loadDefaultFlags(): void
    {
        // Завантаження дефолтних прапорців з конфігураційного файлу
        $configFile = __DIR__ . '/../config/feature-flags.php';
        $defaults = [];

        if (file_exists($configFile)) {
            try {
                $defaults = require $configFile;
                if (! is_array($defaults)) {
                    $defaults = [];
                }
            } catch (Exception $e) {
                if ($this->logger) {
                    $this->logger->logWarning('Failed to load feature flags config file', ['error' => $e->getMessage()]);
                }
                $defaults = [];
            }
        }

        // Якщо файл конфігурації не знайдено або порожній, використовуємо базові дефолти
        if (empty($defaults)) {
            $defaults = [
                'api_enabled' => true,
                'plugin_system' => true,
                'theme_system' => true,
                'maintenance_mode' => false,
                'debug_mode' => false,
                'cache_enabled' => true,
                'logging_enabled' => true,
            ];
        }

        // Мердж з існуючими прапорцями (налаштування мають пріоритет)
        foreach ($defaults as $key => $value) {
            if (! isset($this->flags[$key])) {
                $this->flags[$key] = $value;
            }
        }
    }

    /**
     * Нормалізація значення feature flag
     */
    private function normalizeValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            // Перетворення рядкових значень на bool
            if (in_array(strtolower($value), ['1', 'true', 'yes', 'on', 'enabled'], true)) {
                return true;
            }
            if (in_array(strtolower($value), ['0', 'false', 'no', 'off', 'disabled'], true)) {
                return false;
            }

            return $value;
        }

        if (is_numeric($value)) {
            return (bool)(int)$value;
        }

        return (bool)$value;
    }

    /**
     * Збереження прапорців в кеш
     */
    private function saveToCache(): void
    {
        if (! class_exists('Cache') || ! method_exists('Cache', 'getInstance')) {
            return;
        }

        try {
            $cache = Cache::getInstance();
            $cache->set(self::FLAGS_CACHE_KEY, $this->flags, 3600); // Кеш на 1 годину
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->logWarning('Failed to cache feature flags', ['error' => $e->getMessage()]);
            }
        }
    }

    public function isEnabled(string $flagName, array $context = []): bool
    {
        $this->initialize();
        $value = $this->get($flagName, false, $context);

        // Якщо значення - масив (варіант для A/B тестування), перевіряємо умови
        if (is_array($value)) {
            return $this->evaluateVariant($flagName, $value, $context);
        }

        return (bool)$value;
    }

    public function isDisabled(string $flagName, array $context = []): bool
    {
        return ! $this->isEnabled($flagName, $context);
    }

    public function get(string $flagName, mixed $default = false, array $context = []): mixed
    {
        $this->initialize();

        // Перевірка кешу для конкретного запиту
        $cacheKey = $this->getCacheKey($flagName, $context);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $value = $this->flags[$flagName] ?? $default;

        // Кешування результату
        $this->cache[$cacheKey] = $value;

        return $value;
    }

    public function set(string $flagName, mixed $value): void
    {
        $this->initialize();

        $normalized = $this->normalizeValue($value);
        $this->flags[$flagName] = $normalized;

        // Збереження в налаштуваннях
        if (function_exists('settingsManager')) {
            try {
                $settings = settingsManager();
                if ($settings !== null) {
                    $settingsKey = self::FLAGS_KEY_PREFIX . $flagName;
                    $settings->set($settingsKey, is_bool($normalized) ? ($normalized ? '1' : '0') : (string)$normalized);
                }
            } catch (Exception $e) {
                if ($this->logger) {
                    $this->logger->logError('Failed to save feature flag to settings', [
                        'flag' => $flagName,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Оновлення кешу
        $this->saveToCache();
        $this->clearCache();
    }

    public function all(): array
    {
        $this->initialize();

        return $this->flags;
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }

    public function reload(): void
    {
        $this->initialized = false;
        $this->flags = [];
        $this->clearCache();

        // Очищення кешу з Cache
        if (class_exists('Cache') && method_exists('Cache', 'getInstance')) {
            try {
                $cache = Cache::getInstance();
                $cache->delete(self::FLAGS_CACHE_KEY);
            } catch (Exception $e) {
                // Ігноруємо помилки при очищенні кешу
            }
        }

        $this->initialize();
    }

    /**
     * Оцінка варіанту для A/B тестування
     */
    private function evaluateVariant(string $flagName, array $config, array $context): bool
    {
        // Проста реалізація: якщо config містить 'enabled', використовуємо його
        if (isset($config['enabled'])) {
            return (bool)$config['enabled'];
        }

        // Перевірка умов на основі контексту
        if (isset($config['conditions']) && is_array($config['conditions'])) {
            foreach ($config['conditions'] as $condition) {
                if ($this->evaluateCondition($condition, $context)) {
                    return (bool)($condition['enabled'] ?? false);
                }
            }
        }

        // Якщо не знайдено відповідних умов, використовуємо дефолтне значення
        return (bool)($config['default'] ?? false);
    }

    /**
     * Оцінка умови на основі контексту
     */
    private function evaluateCondition(array $condition, array $context): bool
    {
        // Проста реалізація: перевірка user_id
        if (isset($condition['user_id']) && isset($context['user_id'])) {
            return (string)$condition['user_id'] === (string)$context['user_id'];
        }

        // Перевірка ролі користувача
        if (isset($condition['role']) && isset($context['role'])) {
            return $condition['role'] === $context['role'];
        }

        // Перевірка відсотка (percentage rollout)
        if (isset($condition['percentage']) && isset($context['user_id'])) {
            $percentage = (int)$condition['percentage'];
            $hash = crc32((string)$context['user_id'] . $condition['key'] ?? 'default');
            return ($hash % 100) < $percentage;
        }

        return false;
    }

    /**
     * Генерація ключа кешу на основі назви прапорця та контексту
     */
    private function getCacheKey(string $flagName, array $context): string
    {
        if (empty($context)) {
            return $flagName;
        }

        return $flagName . ':' . md5(serialize($context));
    }
}
