<?php
/**
 * Оптимізована система кешування
 *
 * @package Flowaxy\Core\Infrastructure\Cache
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Infrastructure\Cache {

class Cache
{
    private static ?self $instance = null;
    private static bool $loadingSettings = false; // Прапорець для запобігання рекурсії
    private bool $settingsLoaded = false; // Прапорець завантаження налаштувань
    private string $cacheDir;
    private int $defaultTtl = 3600; // 1 година
    private array $memoryCache = []; // Кеш у пам'яті для поточного запиту
    private bool $enabled = true;
    private bool $autoCleanup = true;
    private int $compressionThreshold = 10240; // 10KB за замовчуванням
    private const CACHE_FILE_EXTENSION = '.cache';

    /**
     * MultiLevelCache драйвер (опціонально)
     */
    private ?object $multiLevelCache = null;
    private bool $useMultiLevelCache = false;

    /**
     * Конструктор (приватний для Singleton)
     */
    private function __construct()
    {
        $this->cacheDir = defined('CACHE_DIR') ? CACHE_DIR : dirname(__DIR__, 2) . '/storage/cache/';
        $this->cacheDir = rtrim($this->cacheDir, '/') . '/';
        $this->ensureCacheDir();

        // Спробуємо ініціалізувати MultiLevelCache, якщо доступний
        $this->initMultiLevelCache();

        // НЕ завантажуємо налаштування в конструкторі, щоб уникнути циклічних залежностей
        // Налаштування будуть завантажені пізніше при першому зверненні або через reloadSettings()
    }

    /**
     * Ініціалізація MultiLevelCache
     */
    private function initMultiLevelCache(): void
    {
        // Перевіряємо, чи доступний MultiLevelCache
        $multiLevelCacheFile = __DIR__ . '/MultiLevelCache.php';
        if (!file_exists($multiLevelCacheFile)) {
            return;
        }

        if (class_exists(\Flowaxy\Core\Infrastructure\Cache\MultiLevelCache::class)) {
            try {
                $this->multiLevelCache = new \Flowaxy\Core\Infrastructure\Cache\MultiLevelCache();
                $this->useMultiLevelCache = true;
            } catch (Throwable $e) {
                // Якщо не вдалося ініціалізувати, використовуємо стару реалізацію
                $this->useMultiLevelCache = false;
            }
        }
    }

    /**
     * Завантаження налаштувань з SettingsManager
     *
     * @param bool $skipCleanup Пропустити автоматичне очищення (для уникнення циклічних залежностей)
     * @return void
     */
    private function loadSettings(bool $skipCleanup = false): void
    {
        // Запобігаємо рекурсії: якщо налаштування вже завантажуються, виходимо
        if (self::$loadingSettings) {
            return;
        }

        // Уникаємо циклічних залежностей: не завантажуємо налаштування, якщо SettingsManager ще не завантажено
        if (! class_exists('SettingsManager')) {
            // Використовуємо значення за замовчуванням
            return;
        }

        // Встановлюємо прапорець завантаження налаштувань
        self::$loadingSettings = true;

        try {
            // Перевіряємо, чи не викликається це під час ініціалізації модулів
            if (function_exists('settingsManager')) {
                $settings = settingsManager();
                if ($settings !== null) {
                    // Завантажуємо налаштування безпосередньо з БД, оминаючи кеш, щоб уникнути рекурсії
                    try {
                        // Використовуємо прямий запит до БД для отримання налаштувань
                        $db = \DatabaseHelper::getConnection();
                        if ($db !== null) {
                            $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('cache_enabled', 'cache_default_ttl', 'cache_auto_cleanup')");
                            if ($stmt !== false) {
                                $dbSettings = [];
                                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                                    $dbSettings[$row['setting_key']] = $row['setting_value'];
                                }

                                // Застосовуємо налаштування з БД
                                $cacheEnabled = $dbSettings['cache_enabled'] ?? '1';
                                $this->enabled = $cacheEnabled === '1';

                                $cacheDefaultTtl = $dbSettings['cache_default_ttl'] ?? '3600';
                                $this->defaultTtl = (int)$cacheDefaultTtl ?: 3600;

                                $cacheAutoCleanup = $dbSettings['cache_auto_cleanup'] ?? '1';
                                $this->autoCleanup = $cacheAutoCleanup === '1';
                            } else {
                                // Якщо не вдалося завантажити з БД, використовуємо settingsManager
                                $cacheEnabled = $settings->get('cache_enabled', '1');
                                if ($cacheEnabled === '' && ! $settings->has('cache_enabled')) {
                                    $cacheEnabled = '1';
                                }
                                $this->enabled = $cacheEnabled === '1';

                                $cacheDefaultTtl = $settings->get('cache_default_ttl', '3600');
                                $this->defaultTtl = (int)$cacheDefaultTtl ?: 3600;

                                $cacheAutoCleanup = $settings->get('cache_auto_cleanup', '1');
                                if ($cacheAutoCleanup === '' && ! $settings->has('cache_auto_cleanup')) {
                                    $cacheAutoCleanup = '1';
                                }
                                $this->autoCleanup = $cacheAutoCleanup === '1';
                            }
                        } else {
                            // Якщо БД недоступна, використовуємо значення за замовчуванням
                            $this->enabled = true;
                            $this->defaultTtl = 3600;
                            $this->autoCleanup = true;
                        }
                    } catch (Exception $e) {
                        if (function_exists('logError')) {
                            logError('Cache::loadSettings DB error', [
                                'error' => $e->getMessage(),
                                'exception' => $e,
                            ]);
                        } elseif (function_exists('logger')) {
                            logger()->logError('Cache::loadSettings DB error: ' . $e->getMessage(), ['exception' => $e]);
                        }
                    }

                    // Виконуємо автоматичне очищення при необхідності (тільки якщо не в конструкторі)
                    if (! $skipCleanup && $this->autoCleanup && mt_rand(1, 1000) <= 1) { // 0.1% шанс на очищення при кожному запиті
                        // Запускаємо очищення у фоні, щоб не блокувати запит
                        register_shutdown_function(function () {
                            $this->cleanup();
                        });
                    }
                }
            }
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('Cache::loadSettings помилка', [
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            } elseif (function_exists('logger')) {
                logger()->logError('Cache::loadSettings помилка: ' . $e->getMessage(), ['exception' => $e]);
            }
        } catch (Error $e) {
            if (function_exists('logCritical')) {
                logCritical('Cache::loadSettings фатальна помилка', [
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            } elseif (function_exists('logger')) {
                logger()->logCritical('Cache::loadSettings фатальна помилка: ' . $e->getMessage(), ['exception' => $e]);
            }
        } finally {
            // Скидаємо прапорець завантаження налаштувань
            self::$loadingSettings = false;
        }
    }

    /**
     * Оновлення налаштувань (викликається після зміни налаштувань)
     *
     * @return void
     */
    public function reloadSettings(): void
    {
        // При оновленні налаштувань дозволяємо автоматичне очищення
        $this->loadSettings(false);
        $this->settingsLoaded = true;
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
     * Створення директорії кешу
     *
     * @return void
     */
    private function ensureCacheDir(): void
    {
        // Перевіряємо існування директорії (створюється через інсталятор)
        if (! is_dir($this->cacheDir)) {
            if (function_exists('logWarning')) {
                logWarning("Cache: Directory does not exist", ['directory' => $this->cacheDir]);
            }
        }
    }

    /**
     * Отримання даних з кешу
     *
     * @param string $key Ключ кешу
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        // Ліниве завантаження налаштувань при першому використанні
        if (! $this->settingsLoaded) {
            $this->loadSettings(true);
            $this->settingsLoaded = true;
        }

        // Якщо кешування вимкнено, повертаємо значення за замовчуванням
        if (! $this->enabled) {
            return $default;
        }

        // Валідація ключа
        if (empty($key)) {
            if (function_exists('logDebug')) {
                logDebug('Cache::get: Empty key provided', ['default' => $default]);
            }
            return $default;
        }

        if (function_exists('logDebug')) {
            logDebug('Cache::get: Retrieving from cache', ['key' => $key]);
        }

        // Використовуємо MultiLevelCache, якщо доступний
        if ($this->useMultiLevelCache && $this->multiLevelCache !== null) {
            try {
                $result = $this->multiLevelCache->get($key, $default);
                if ($result !== $default && function_exists('logDebug')) {
                    logDebug('Cache::get: Retrieved from MultiLevelCache', ['key' => $key]);
                }
                return $result;
            } catch (Throwable $e) {
                if (function_exists('logWarning')) {
                    logWarning('Cache::get: MultiLevelCache error, falling back', [
                        'key' => $key,
                        'error' => $e->getMessage(),
                    ]);
                }
                // Fallback до старої реалізації при помилці
            }
        }

        // Спочатку перевіряємо кеш у пам'яті
        if (isset($this->memoryCache[$key])) {
            if (function_exists('logDebug')) {
                logDebug('Cache::get: Retrieved from memory cache', ['key' => $key]);
            }
            return $this->memoryCache[$key];
        }

        $filename = $this->getFilename($key);

        if (! file_exists($filename) || ! is_readable($filename)) {
            return $default;
        }

        $data = @file_get_contents($filename);
        if ($data === false) {
            return $default;
        }

        try {
            $cached = unserialize($data, ['allowed_classes' => false]);

            // Перевіряємо структуру даних
            if (! is_array($cached) || ! isset($cached['expires']) || ! isset($cached['data'])) {
                @unlink($filename);

                return $default;
            }

            // Перевіряємо термін дії (0 = без обмеження)
            if ($cached['expires'] !== 0 && $cached['expires'] < time()) {
                $this->delete($key);

                return $default;
            }

            // Розпаковуємо стиснуті дані
            $data = $cached['data'];
            if (is_array($data) && isset($data['_compressed']) && $data['_compressed'] === true) {
                if (function_exists('gzuncompress')) {
                    $compressed = base64_decode($data['_data']);
                    $uncompressed = gzuncompress($compressed);
                    if ($uncompressed !== false) {
                        $data = unserialize($uncompressed, ['allowed_classes' => false]);
                    }
                }
            }

            // Зберігаємо в кеш пам'яті
            $this->memoryCache[$key] = $data;

            if (function_exists('logDebug')) {
                logDebug('Cache::get: Retrieved from file cache', ['key' => $key]);
            }

            // Хук для плагінів (відстеження статистики і т.д.)
            if (function_exists('doAction')) {
                doAction('cache_get', $key);
            }

            return $data;
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError("Cache помилка десеріалізації для ключа", [
                    'key' => $key,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            } elseif (function_exists('logger')) {
                logger()->logError("Cache помилка десеріалізації для ключа '{$key}': " . $e->getMessage(), ['key' => $key, 'exception' => $e]);
            }
            @unlink($filename);

            return $default;
        }
    }

    /**
     * Збереження даних у кеш зі стисненням
     *
     * @param string $key Ключ кешу
     * @param mixed $data Дані для кешування
     * @param int|null $ttl Час життя в секундах
     * @param bool $compressed Примусово стиснути дані
     * @return bool
     */
    public function setCompressed(string $key, $data, ?int $ttl = null, bool $compressed = true): bool
    {
        if ($compressed && function_exists('gzcompress')) {
            $serialized = serialize($data);

            // Стискаємо тільки якщо дані досить великі
            if (strlen($serialized) >= $this->compressionThreshold) {
                $compressed = gzcompress($serialized, 6);
                if ($compressed !== false) {
                    $data = [
                        '_compressed' => true,
                        '_data' => base64_encode($compressed),
                    ];
                }
            }
        }

        return $this->set($key, $data, $ttl);
    }

    /**
     * Збереження даних у кеш
     *
     * @param string $key Ключ кешу
     * @param mixed $data Дані для кешування
     * @param int|null $ttl Час життя в секундах
     * @return bool
     */
    public function set(string $key, $data, ?int $ttl = null): bool
    {
        // Ліниве завантаження налаштувань при першому використанні
        if (! $this->settingsLoaded) {
            $this->loadSettings(true);
            $this->settingsLoaded = true;
        }

        // Якщо кешування вимкнено, не зберігаємо
        if (! $this->enabled) {
            return false;
        }

        // Валідація ключа
        if (empty($key)) {
            if (function_exists('logWarning')) {
                logWarning('Cache::set: Empty key provided');
            }
            return false;
        }

        if (function_exists('logDebug')) {
            logDebug('Cache::set: Setting cache value', ['key' => $key, 'ttl' => $ttl]);
        }

        if ($ttl === null) {
            $ttl = $this->defaultTtl;
        }

        // Валідація TTL
        if ($ttl < 0) {
            if (function_exists('logWarning')) {
                logWarning('Cache::set: Invalid TTL, using default', ['provided_ttl' => $ttl, 'default_ttl' => $this->defaultTtl]);
            }
            $ttl = $this->defaultTtl;
        }

        // Автоматичне стиснення великих об'єктів
        $serializedData = serialize($data);
        if (strlen($serializedData) >= $this->compressionThreshold && function_exists('gzcompress')) {
            $compressed = gzcompress($serializedData, 6);
            if ($compressed !== false) {
                $data = [
                    '_compressed' => true,
                    '_data' => base64_encode($compressed),
                ];
            }
        }

        $cached = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time(),
        ];

        try {
            $serialized = serialize($cached);
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError("Cache помилка серіалізації для ключа", [
                    'key' => $key,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            } elseif (function_exists('logger')) {
                logger()->logError("Cache помилка серіалізації для ключа '{$key}': " . $e->getMessage(), ['key' => $key, 'exception' => $e]);
            }

            return false;
        }

        $filename = $this->getFilename($key);
        $result = @file_put_contents($filename, $serialized, LOCK_EX);

        if ($result !== false) {
            // Встановлюємо права доступу (якщо можливо)
            // В WSL/Windows на NTFS файловій системі chmod може не працювати,
            // тому використовуємо безпечний спосіб без генерації warning
            if (file_exists($filename)) {
                $this->setPermissions($filename, 0644);
            }

            // Зберігаємо в кеш пам'яті
            $this->memoryCache[$key] = $data;

            if (function_exists('logInfo')) {
                logInfo('Cache::set: Cache value saved successfully', [
                    'key' => $key,
                    'ttl' => $ttl,
                    'size' => strlen($serialized),
                ]);
            }

            // Хук для плагінів
            if (function_exists('doAction')) {
                doAction('cache_set', $key, $ttl);
            }

            return true;
        }

        if (function_exists('logError')) {
            logError("Cache помилка запису для ключа", [
                'key' => $key,
                'filename' => $filename,
            ]);
        } elseif (function_exists('logger')) {
            logger()->logError("Cache помилка запису для ключа '{$key}' у файл '{$filename}'", ['key' => $key, 'filename' => $filename]);
        }

        return false;
    }

    /**
     * Видалення з кешу
     *
     * @param string $key Ключ кешу
     * @return bool
     */
    public function delete(string $key): bool
    {
        // Валідація ключа
        if (empty($key)) {
            if (function_exists('logWarning')) {
                logWarning('Cache::delete: Empty key provided');
            }
            return false;
        }

        if (function_exists('logDebug')) {
            logDebug('Cache::delete: Deleting cache key', ['key' => $key]);
        }

        // Використовуємо MultiLevelCache, якщо доступний
        if ($this->useMultiLevelCache && $this->multiLevelCache !== null) {
            try {
                $result = $this->multiLevelCache->delete($key);
                unset($this->memoryCache[$key]);
                if ($result && function_exists('logInfo')) {
                    logInfo('Cache::delete: Deleted from MultiLevelCache', ['key' => $key]);
                }
                return $result;
            } catch (Throwable $e) {
                if (function_exists('logWarning')) {
                    logWarning('Cache::delete: MultiLevelCache error, falling back', [
                        'key' => $key,
                        'error' => $e->getMessage(),
                    ]);
                }
                // Fallback до старої реалізації при помилці
            }
        }

        unset($this->memoryCache[$key]);

        $filename = $this->getFilename($key);
        if (file_exists($filename)) {
            $result = @unlink($filename);

            if ($result && function_exists('logInfo')) {
                logInfo('Cache::delete: Cache key deleted successfully', ['key' => $key]);
            } elseif (!$result && function_exists('logWarning')) {
                logWarning('Cache::delete: Failed to delete cache file', ['key' => $key, 'filename' => $filename]);
            }

            // Хук для плагінів
            if ($result && function_exists('doAction')) {
                doAction('cache_delete', $key);
            }

            return $result;
        }

        if (function_exists('logDebug')) {
            logDebug('Cache::delete: Cache file does not exist', ['key' => $key]);
        }

        return true;
    }

    /**
     * Перевірка існування ключа
     *
     * @param string $key Ключ кешу
     * @return bool
     */
    public function has(string $key): bool
    {
        // Ліниве завантаження налаштувань при першому використанні
        if (! $this->settingsLoaded) {
            $this->loadSettings(true);
            $this->settingsLoaded = true;
        }

        // Якщо кешування вимкнено, завжди повертаємо false
        if (! $this->enabled) {
            return false;
        }

        // Валідація ключа
        if (empty($key)) {
            if (function_exists('logDebug')) {
                logDebug('Cache::has: Empty key provided');
            }
            return false;
        }

        if (function_exists('logDebug')) {
            logDebug('Cache::has: Checking cache key existence', ['key' => $key]);
        }

        // Використовуємо MultiLevelCache, якщо доступний
        if ($this->useMultiLevelCache && $this->multiLevelCache !== null) {
            try {
                $result = $this->multiLevelCache->has($key);
                if (function_exists('logDebug')) {
                    logDebug('Cache::has: Checked via MultiLevelCache', ['key' => $key, 'exists' => $result]);
                }
                return $result;
            } catch (Throwable $e) {
                if (function_exists('logWarning')) {
                    logWarning('Cache::has: MultiLevelCache error, falling back', [
                        'key' => $key,
                        'error' => $e->getMessage(),
                    ]);
                }
                // Fallback до старої реалізації при помилці
            }
        }

        if (isset($this->memoryCache[$key])) {
            if (function_exists('logDebug')) {
                logDebug('Cache::has: Key exists in memory cache', ['key' => $key]);
            }
            return true;
        }

        $filename = $this->getFilename($key);

        if (! file_exists($filename) || ! is_readable($filename)) {
            return false;
        }

        $data = @file_get_contents($filename);
        if ($data === false) {
            return false;
        }

        try {
            $cached = unserialize($data, ['allowed_classes' => false]);

            // Перевіряємо структуру даних
            if (! is_array($cached) || ! isset($cached['expires'])) {
                @unlink($filename);

                return false;
            }

            // Перевіряємо термін дії
            if ($cached['expires'] < time()) {
                $this->delete($key);

                return false;
            }

            if (function_exists('logDebug')) {
                logDebug('Cache::has: Key exists in file cache', ['key' => $key]);
            }
            return true;
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError("Cache помилка перевірки для ключа", [
                    'key' => $key,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            } elseif (function_exists('logger')) {
                logger()->logError("Cache помилка перевірки для ключа '{$key}': " . $e->getMessage(), ['key' => $key, 'exception' => $e]);
            }
            @unlink($filename);

            return false;
        }
    }

    /**
     * Отримання або встановлення значення
     *
     * @param string $key Ключ кешу
     * @param callable $callback Функція для отримання даних
     * @param int|null $ttl Час життя в секундах
     * @return mixed
     */
    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        // Ліниве завантаження налаштувань при першому використанні
        if (! $this->settingsLoaded) {
            $this->loadSettings(true);
            $this->settingsLoaded = true;
        }

        // Якщо кешування вимкнено, просто виконуємо callback
        if (! $this->enabled) {
            try {
                return $callback();
            } catch (Exception $e) {
                if (function_exists('logError')) {
                    logError("Cache помилка callback remember для ключа", [
                        'key' => $key,
                        'error' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                } elseif (function_exists('logger')) {
                    logger()->logError("Cache помилка callback remember для ключа '{$key}': " . $e->getMessage(), ['key' => $key, 'exception' => $e]);
                }

                throw $e;
            }
        }

        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        try {
            $value = $callback();
            $this->set($key, $value, $ttl);

            return $value;
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError("Cache помилка callback remember для ключа", [
                    'key' => $key,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            } elseif (function_exists('logger')) {
                logger()->logError("Cache помилка callback remember для ключа '{$key}': " . $e->getMessage(), ['key' => $key, 'exception' => $e]);
            }

            throw $e;
        }
    }

    /**
     * Очищення всього кешу
     *
     * @return bool
     */
    public function clear(): bool
    {
        if (function_exists('logDebug')) {
            logDebug('Cache::clear: Starting cache clear operation', ['cache_dir' => $this->cacheDir]);
        }

        $this->memoryCache = [];

        // Системні файли, які не потрібно видаляти
        $systemFiles = ['.gitkeep', '.htaccess'];

        // Рекурсивне сканування директорії кешу
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            $success = true;
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $filename = $file->getFilename();

                    // Пропускаємо системні файли
                    if (in_array($filename, $systemFiles)) {
                        continue;
                    }

                    // Видаляємо тільки файли кешу
                    if (pathinfo($filename, PATHINFO_EXTENSION) === ltrim(self::CACHE_FILE_EXTENSION, '.')) {
                        if (!@unlink($file->getPathname())) {
                            $success = false;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if (function_exists('logWarning')) {
                logWarning('Cache::clear: Recursive scan failed, using fallback method', [
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
            // Якщо рекурсивне сканування не вдалося, використовуємо старий метод
            $pattern = $this->cacheDir . '*' . self::CACHE_FILE_EXTENSION;
            $files = glob($pattern);

            if ($files === false) {
                if (function_exists('logError')) {
                    logError('Cache::clear: Failed to glob cache files', ['pattern' => $pattern]);
                }
                return false;
            }

            $success = true;
            foreach ($files as $file) {
                if (is_file($file)) {
                    $filename = basename($file);
                    if (!in_array($filename, $systemFiles)) {
                        if (!@unlink($file)) {
                            $success = false;
                        }
                    }
                }
            }
        }

        if ($success && function_exists('logInfo')) {
            logInfo('Cache::clear: Cache cleared successfully');
        } elseif (!$success && function_exists('logWarning')) {
            logWarning('Cache::clear: Some cache files could not be deleted');
        }

        return $success;
    }

    /**
     * Очищення застарілого кешу
     *
     * @return int Кількість видалених файлів
     */
    public function cleanup(): int
    {
        if (function_exists('logDebug')) {
            logDebug('Cache::cleanup: Starting cache cleanup operation', ['cache_dir' => $this->cacheDir]);
        }

        $cleaned = 0;

        // Системні файли, які не потрібно видаляти
        $systemFiles = ['.gitkeep', '.htaccess'];
        $currentTime = time();

        // Рекурсивне сканування директорії кешу
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || !$file->isReadable()) {
                    continue;
                }

                $filename = $file->getFilename();

                // Пропускаємо системні файли
                if (in_array($filename, $systemFiles)) {
                    continue;
                }

                // Перевіряємо тільки файли кешу
                if (pathinfo($filename, PATHINFO_EXTENSION) !== ltrim(self::CACHE_FILE_EXTENSION, '.')) {
                    continue;
                }

                $filePath = $file->getPathname();
                $data = @file_get_contents($filePath);

                if ($data === false) {
                    continue;
                }

                try {
                    $cached = unserialize($data, ['allowed_classes' => false]);

                    if (!is_array($cached) || !isset($cached['expires'])) {
                        @unlink($filePath);
                        $cleaned++;
                        continue;
                    }

                    if ($cached['expires'] < $currentTime) {
                        @unlink($filePath);
                        $cleaned++;
                    }
                } catch (Exception $e) {
                    // Видаляємо пошкоджений файл
                    if (function_exists('logWarning')) {
                        logWarning('Cache::cleanup: Corrupted cache file detected and removed', [
                            'file' => $filePath,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    @unlink($filePath);
                    $cleaned++;
                }
            }
        } catch (Exception $e) {
            if (function_exists('logWarning')) {
                logWarning('Cache::cleanup: Recursive scan failed, using fallback method', [
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
            // Якщо рекурсивне сканування не вдалося, використовуємо старий метод
            $pattern = $this->cacheDir . '*' . self::CACHE_FILE_EXTENSION;
            $files = glob($pattern);

            if ($files === false) {
                if (function_exists('logError')) {
                    logError('Cache::cleanup: Failed to glob cache files', ['pattern' => $pattern]);
                }
                return 0;
            }

            foreach ($files as $file) {
                if (!is_file($file) || !is_readable($file)) {
                    continue;
                }

                $filename = basename($file);
                if (in_array($filename, $systemFiles)) {
                    continue;
                }

                $data = @file_get_contents($file);
                if ($data === false) {
                    continue;
                }

                try {
                    $cached = unserialize($data, ['allowed_classes' => false]);

                    if (!is_array($cached) || !isset($cached['expires'])) {
                        @unlink($file);
                        $cleaned++;
                        continue;
                    }

                    if ($cached['expires'] < $currentTime) {
                        @unlink($file);
                        $cleaned++;
                    }
                } catch (Exception $e) {
                    // Видаляємо пошкоджений файл
                    @unlink($file);
                    $cleaned++;
                }
            }
        }

        if ($cleaned > 0 && function_exists('logInfo')) {
            logInfo('Cache::cleanup: Cleanup completed', ['cleaned_files' => $cleaned]);
        } elseif (function_exists('logDebug')) {
            logDebug('Cache::cleanup: No expired files found');
        }

        return $cleaned;
    }

    /**
     * Отримання статистики кешу
     *
     * @return array<string, int|string>
     */
    public function getStats(): array
    {
        $pattern = $this->cacheDir . '*' . self::CACHE_FILE_EXTENSION;
        $files = glob($pattern);

        if ($files === false) {
            return [
                'total_files' => 0,
                'valid_files' => 0,
                'expired_files' => 0,
                'total_size' => 0,
                'memory_cache_size' => count($this->memoryCache),
            ];
        }

        $totalSize = 0;
        $expired = 0;
        $valid = 0;
        $currentTime = time();

        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }

            $fileSize = @filesize($file);
            if ($fileSize !== false) {
                $totalSize += $fileSize;
            }

            $data = @file_get_contents($file);
            if ($data === false) {
                continue;
            }

            try {
                $cached = unserialize($data, ['allowed_classes' => false]);

                if (! is_array($cached) || ! isset($cached['expires'])) {
                    $expired++;

                    continue;
                }

                // Перевіряємо термін дії (0 = без обмеження)
                if ($cached['expires'] !== 0 && $cached['expires'] < $currentTime) {
                    $expired++;
                } else {
                    $valid++;
                }
            } catch (Exception $e) {
                $expired++;
            }
        }

        return [
            'total_files' => count($files),
            'valid_files' => $valid,
            'expired_files' => $expired,
            'total_size' => $totalSize,
            'memory_cache_size' => count($this->memoryCache),
        ];
    }

    /**
     * Отримання імені файлу для ключа
     *
     * @param string $key Ключ кешу
     * @return string
     */
    private function getFilename(string $key): string
    {
        $hash = md5($key);

        return $this->cacheDir . $hash . self::CACHE_FILE_EXTENSION;
    }

    /**
     * Тегований кеш
     *
     * @param array|string $tags Теги
     * @return TaggedCache
     */
    public function tags($tags): TaggedCache
    {
        return new TaggedCache($this, (array)$tags);
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
        throw new Exception('Неможливо десеріалізувати singleton');
    }

    /**
     * Безпечне встановлення прав доступу на файл
     * Придушує всі попередження, щоб уникнути логування через Logger
     *
     * @param string $path Шлях до файлу
     * @param int $permissions Права доступу
     * @return void
     */
    private function setPermissions(string $path, int $permissions): void
    {
        // На Windows/WSL chmod може не працювати, тому перевіряємо ОС
        // Якщо це Windows, просто не викликаємо chmod
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return;
        }

        // Зберігаємо поточний обробник помилок
        $oldErrorHandler = set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // Ігноруємо тільки помилки chmod
            if ($errno === E_WARNING && strpos($errstr, 'chmod()') !== false) {
                return true; // Ігноруємо помилку
            }

            // Для інших помилок повертаємо false, щоб викликати стандартний обробник
            return false;
        }, E_WARNING);

        // Зберігаємо поточний рівень повідомлень про помилки
        $originalErrorLevel = error_reporting(0);

        // Тиха спроба встановити права доступу
        @chmod($path, $permissions);

        // Відновлюємо рівень повідомлень про помилки
        error_reporting($originalErrorLevel);

        // Відновлюємо старий обробник помилок
        restore_error_handler();
        if ($oldErrorHandler !== null) {
            set_error_handler($oldErrorHandler);
        }
    }
}

/**
 * Тегований кеш
 */
class TaggedCache
{
    private Cache $cache;
    private array $tags;

    /**
     * Конструктор
     *
     * @param Cache $cache Екземпляр кешу
     * @param array<string> $tags Масив тегів
     */
    public function __construct(Cache $cache, array $tags)
    {
        $this->cache = $cache;
        $this->tags = array_filter($tags, function ($tag) {
            return is_string($tag) && ! empty($tag);
        });
    }

    /**
     * Отримання даних з кешу
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->cache->get($this->taggedKey($key), $default);
    }

    /**
     * Збереження даних у кеш
     *
     * @param string $key Ключ
     * @param mixed $data Дані
     * @param int|null $ttl Час життя
     * @return bool
     */
    public function set(string $key, $data, ?int $ttl = null): bool
    {
        $result = $this->cache->set($this->taggedKey($key), $data, $ttl);

        // Зберігаємо інформацію про теги
        foreach ($this->tags as $tag) {
            $tagKey = 'tag:' . $tag;
            $taggedKeys = $this->cache->get($tagKey, []);

            if (! is_array($taggedKeys)) {
                $taggedKeys = [];
            }

            $taggedKeys[] = $this->taggedKey($key);
            $taggedKeys = array_unique($taggedKeys);
            $this->cache->set($tagKey, $taggedKeys, 86400); // 24 години
        }

        return $result;
    }

    /**
     * Очищення всіх даних з вказаними тегами
     *
     * @return void
     */
    public function flush(): void
    {
        foreach ($this->tags as $tag) {
            $tagKey = 'tag:' . $tag;
            $taggedKeys = $this->cache->get($tagKey, []);

            if (is_array($taggedKeys)) {
                foreach ($taggedKeys as $key) {
                    $this->cache->delete($key);
                }
            }

            $this->cache->delete($tagKey);
        }
    }

    /**
     * Генерація тегованого ключа
     *
     * @param string $key Ключ
     * @return string
     */
    private function taggedKey(string $key): string
    {
        $tagsStr = implode(':', array_map('md5', $this->tags));

        return 'tagged:' . $tagsStr . ':' . $key;
    }
}

} // Кінець namespace Flowaxy\Core\Infrastructure\Cache

// Глобальні функції для зручності (винесені за межі namespace)
namespace {
    /**
     * Отримання екземпляра кешу
     *
     * @return \Flowaxy\Core\Infrastructure\Cache\Cache
     */
    function cache(): \Flowaxy\Core\Infrastructure\Cache\Cache
    {
        return \Flowaxy\Core\Infrastructure\Cache\Cache::getInstance();
    }

    /**
     * Отримання даних з кешу
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    function cache_get(string $key, $default = null)
    {
        return cache()->get($key, $default);
    }

    /**
     * Збереження даних у кеш
     *
     * @param string $key Ключ
     * @param mixed $data Дані
     * @param int|null $ttl Час життя
     * @return bool
     */
    function cache_set(string $key, $data, ?int $ttl = null): bool
    {
        return cache()->set($key, $data, $ttl);
    }

    /**
     * Отримання або встановлення значення
     *
     * @param string $key Ключ
     * @param callable $callback Функція
     * @param int|null $ttl Час життя
     * @return mixed
     */
    function cache_remember(string $key, callable $callback, ?int $ttl = null)
    {
        return cache()->remember($key, $callback, $ttl);
    }

    /**
     * Видалення з кешу
     *
     * @param string $key Ключ
     * @return bool
     */
    function cache_forget(string $key): bool
    {
        return cache()->delete($key);
    }

    /**
     * Очищення всього кешу
     *
     * @return bool
     */
    function cache_flush(): bool
    {
        return cache()->clear();
    }
}
