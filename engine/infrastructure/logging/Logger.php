<?php

/**
 * Централізована система логування
 * Підтримка різних рівнів логування, ротації файлів, фільтрації
 *
 * @package Engine\Classes\Data
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/contracts/LoggerInterface.php';

class Logger implements LoggerInterface
{
    private static ?self $instance = null;
    private static bool $loadingSettings = false; // Прапорець для запобігання рекурсії
    private bool $settingsLoaded = false; // Прапорець завантаження налаштувань
    private string $logDir;
    private string $logFile;
    private int $maxFileSize = 10 * 1024 * 1024; // 10 MB
    private int $maxFiles = 5;
    private array $settings = [];
    private ?int $lastRotationTime = null;

    // Рівні логування
    public const int LEVEL_DEBUG = 0;
    public const int LEVEL_INFO = 1;
    public const int LEVEL_WARNING = 2;
    public const int LEVEL_ERROR = 3;
    public const int LEVEL_CRITICAL = 4;

    private const LEVEL_NAMES = [
        self::LEVEL_DEBUG => 'DEBUG',
        self::LEVEL_INFO => 'INFO',
        self::LEVEL_WARNING => 'WARNING',
        self::LEVEL_ERROR => 'ERROR',
        self::LEVEL_CRITICAL => 'CRITICAL',
    ];

    /**
     * Конструктор (приватний для Singleton)
     */
    private function __construct()
    {
        $this->logDir = defined('LOGS_DIR') ? LOGS_DIR : dirname(__DIR__, 2) . '/storage/logs/';
        $this->logDir = rtrim($this->logDir, '/') . '/';
        $this->ensureLogDir();

        // Ім'я файлу логу з датою
        $this->logFile = $this->logDir . 'app-' . date('Y-m-d') . '.log';

        // НЕ завантажуємо налаштування в конструкторі, щоб уникнути циклічних залежностей
        // Налаштування будуть завантажені пізніше при першому логуванні або через reloadSettings()
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
     * Створення директорії логів
     *
     * @return void
     */
    private function ensureLogDir(): void
    {
        // Перевіряємо існування директорії (створюється через інсталятор)
        if (! is_dir($this->logDir)) {
            error_log("Logger: Directory does not exist: {$this->logDir}. Please run installer to create storage directories.");
        }
    }

    /**
     * Завантаження налаштувань з БД або файлу
     *
     * @return void
     */
    private function loadSettings(): void
    {
        // Запобігаємо рекурсії: якщо налаштування вже завантажуються, виходимо
        if (self::$loadingSettings) {
            return;
        }

        // Уникаємо циклічних залежностей: не завантажуємо налаштування, якщо SettingsManager ще не завантажено
        if (! class_exists('SettingsManager')) {
            // Використовуємо значення за замовчуванням
            $this->settings = [
                'enabled' => true,
                'max_file_size' => $this->maxFileSize,
                'max_files' => $this->maxFiles,
                'retention_days' => 30,
                'logging_levels' => 'DEBUG,INFO,WARNING,ERROR,CRITICAL',
                'logging_types' => 'file',
            ];

            return;
        }

        // Встановлюємо прапорець завантаження налаштувань
        self::$loadingSettings = true;

        // Налаштування за замовчуванням
        $this->settings = [
            'enabled' => true,
            'max_file_size' => $this->maxFileSize,
            'max_files' => $this->maxFiles,
            'retention_days' => 30,
            'rotation_type' => 'size',
            'rotation_time' => 24,
            'rotation_time_unit' => 'hours',
            'logging_levels' => 'DEBUG,INFO,WARNING,ERROR,CRITICAL',
            'logging_types' => 'file',
        ];

        // Завантажуємо з БД, якщо доступна
        if (function_exists('settingsManager')) {
            try {
                $settings = settingsManager();
                if ($settings !== null) {
                    // Завантажуємо налаштування напряму з БД, обходячи кеш, щоб уникнути рекурсії
                    try {
                        // Використовуємо прямий запит до БД для отримання налаштувань
                        $db = DatabaseHelper::getConnection();
                        if ($db !== null) {
                            $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('logging_enabled', 'logging_level', 'logging_levels', 'logging_types', 'logging_max_file_size', 'logging_retention_days', 'logging_rotation_type', 'logging_rotation_time', 'logging_rotation_time_unit')");
                            if ($stmt !== false) {
                                $dbSettings = [];
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $dbSettings[$row['setting_key']] = $row['setting_value'];
                                }

                                // Застосовуємо налаштування з БД
                                $loggingEnabled = $dbSettings['logging_enabled'] ?? '1';
                                $this->settings['enabled'] = $loggingEnabled === '1';

                                // Рівні логування (множественний вибір)
                                if (isset($dbSettings['logging_levels']) && ! empty($dbSettings['logging_levels'])) {
                                    $this->settings['logging_levels'] = $dbSettings['logging_levels'];
                                }

                                // Типи логування
                                if (isset($dbSettings['logging_types']) && ! empty($dbSettings['logging_types'])) {
                                    $this->settings['logging_types'] = $dbSettings['logging_types'];
                                } else {
                                    // Значення за замовчуванням
                                    $this->settings['log_to_file'] = $this->settings['enabled'];
                                }

                                // Налаштування файлів
                                $maxFileSize = (int)($dbSettings['logging_max_file_size'] ?? $this->maxFileSize);
                                if ($maxFileSize > 0) {
                                    $this->settings['max_file_size'] = $maxFileSize;
                                    $this->maxFileSize = $maxFileSize;
                                }

                                // Дні зберігання логів
                                $retentionDays = (int)($dbSettings['logging_retention_days'] ?? 30);
                                if ($retentionDays > 0) {
                                    $this->settings['retention_days'] = $retentionDays;
                                    $this->settings['max_files'] = max(5, $retentionDays + 1);
                                    $this->maxFiles = $this->settings['max_files'];
                                }

                                // Тип ротації
                                $this->settings['rotation_type'] = $dbSettings['logging_rotation_type'] ?? 'size';
                                $this->settings['rotation_time'] = (int)($dbSettings['logging_rotation_time'] ?? 24);
                                $this->settings['rotation_time_unit'] = $dbSettings['logging_rotation_time_unit'] ?? 'hours';
                            } else {
                                // Якщо не вдалося завантажити з БД, використовуємо settingsManager
                                $loggingEnabled = $settings->get('logging_enabled', '1');
                                if ($loggingEnabled === '' && ! $settings->has('logging_enabled')) {
                                    $loggingEnabled = '1';
                                }
                                $this->settings['enabled'] = $loggingEnabled === '1';

                                // Рівні логування (множественний вибір)
                                $levelsStr = $settings->get('logging_levels', '');
                                if (! empty($levelsStr)) {
                                    $this->settings['logging_levels'] = $levelsStr;
                                }

                                // Типи логування
                                $typesStr = $settings->get('logging_types', '');
                                if (! empty($typesStr)) {
                                    $this->settings['logging_types'] = $typesStr;
                                }
                                $maxFileSize = (int)$settings->get('logging_max_file_size', (string)$this->maxFileSize);
                                if ($maxFileSize > 0) {
                                    $this->settings['max_file_size'] = $maxFileSize;
                                    $this->maxFileSize = $maxFileSize;
                                }

                                $retentionDays = (int)$settings->get('logging_retention_days', '30');
                                if ($retentionDays > 0) {
                                    $this->settings['retention_days'] = $retentionDays;
                                    $this->settings['max_files'] = max(5, $retentionDays + 1);
                                    $this->maxFiles = $this->settings['max_files'];
                                }

                                // Тип ротації
                                $this->settings['rotation_type'] = $settings->get('logging_rotation_type', 'size');
                                $this->settings['rotation_time'] = (int)$settings->get('logging_rotation_time', '24');
                                $this->settings['rotation_time_unit'] = $settings->get('logging_rotation_time_unit', 'hours');
                            }
                        } else {
                            // Якщо БД недоступна, використовуємо значення за замовчуванням
                            $this->settings['enabled'] = true;
                        }
                    } catch (Exception $e) {
                        // У разі помилки використовуємо значення за замовчуванням
                        error_log('Logger::loadSettings помилка БД: ' . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                // У разі помилки використовуємо значення за замовчуванням
                error_log('Logger::loadSettings помилка: ' . $e->getMessage());
            } catch (Error $e) {
                // У разі фатальної помилки використовуємо значення за замовчуванням
                error_log('Logger::loadSettings фатальна помилка: ' . $e->getMessage());
            } finally {
                // Скидаємо прапорець завантаження налаштувань
                self::$loadingSettings = false;
            }
        }
    }

    /**
     * Отримання налаштування
     *
     * @param string $key Ключ налаштування
     * @param string $default Значення за замовчуванням
     * @return string
     */
    public function getSetting(string $key, string $default = ''): string
    {
        return (string)($this->settings[$key] ?? $default);
    }

    /**
     * Встановлення налаштування
     *
     * @param string $key Ключ налаштування
     * @param string $value Значення
     * @return void
     */
    public function setSetting(string $key, string $value): void
    {
        $this->settings[$key] = $value;

        // Зберігаємо в БД, якщо доступна
        if (class_exists('SettingsManager')) {
            settingsManager()->set('logger_' . $key, $value);
        }
    }

    /**
     * Перезавантаження налаштувань з БД
     * Викликати після зміни налаштувань логування
     * 
     * @return void
     */
    public function reloadSettings(): void
    {
        $this->settingsLoaded = false;
        self::$loadingSettings = false;
        $this->loadSettings();
        $this->settingsLoaded = true;
    }

    /**
     * Логування повідомлення
     *
     * @param int $level Рівень логування
     * @param string $message Повідомлення
     * @param array $context Контекст (додаткові дані)
     * @return void
     */
    public function log(int $level, string $message, array $context = []): void
    {
        // Ліниве завантаження налаштувань при першому використанні
        if (! $this->settingsLoaded) {
            $this->loadSettings();
            $this->settingsLoaded = true;
        }

        // Якщо логування вимкнено, не логуємо
        if (! $this->settings['enabled']) {
            return;
        }

        // Перевіряємо чи дозволено логування цього рівня
        $levelName = self::LEVEL_NAMES[$level] ?? 'UNKNOWN';
        $allowedLevels = $this->getAllowedLevels();

        if (! in_array($levelName, $allowedLevels, true)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Формуємо контекстний рядок
        $contextStr = '';
        if (! empty($context)) {
            $contextStr = ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Формуємо рядок логу
        $logLine = sprintf(
            "[%s] %s: %s | IP: %s | %s %s%s\n",
            $timestamp,
            $levelName,
            $message,
            $ip,
            $method,
            $uri,
            $contextStr
        );

        // Отримуємо дозволені типи логування
        $loggingTypes = $this->getLoggingTypes();

        // Логуємо у файл
        if (in_array('file', $loggingTypes)) {
            $this->writeToFile($logLine);
        }

        // Логуємо в error_log
        if (in_array('error_log', $loggingTypes)) {
            error_log(trim($logLine));
        }
    }

    /**
     * Запис у файл з ротацією
     *
     * @param string $logLine Рядок для запису
     * @return void
     */
    private function writeToFile(string $logLine): void
    {
        $rotationType = $this->settings['rotation_type'] ?? 'size';
        $shouldRotate = false;

        // Перевіряємо ротацію по розміру
        if ($rotationType === 'size' || $rotationType === 'both') {
            if (file_exists($this->logFile) && filesize($this->logFile) >= $this->settings['max_file_size']) {
                $shouldRotate = true;
            }
        }

        // Перевіряємо ротацію по часу
        if ($rotationType === 'time' || $rotationType === 'both') {
            $rotationTime = (int)($this->settings['rotation_time'] ?? 24);
            $rotationTimeUnit = $this->settings['rotation_time_unit'] ?? 'hours';

            // Конвертуємо в секунди
            $rotationSeconds = $rotationTimeUnit === 'days'
                ? $rotationTime * 24 * 60 * 60
                : $rotationTime * 60 * 60;

            $currentTime = time();

            // Якщо файл існує, перевіряємо час останньої ротації
            if (file_exists($this->logFile)) {
                if ($this->lastRotationTime === null) {
                    // Встановлюємо час створення файлу як час останньої ротації
                    $this->lastRotationTime = filemtime($this->logFile);
                }

                if (($currentTime - $this->lastRotationTime) >= $rotationSeconds) {
                    $shouldRotate = true;
                }
            } else {
                // Новий файл - встановлюємо час ротації
                $this->lastRotationTime = $currentTime;
            }
        }

        // Виконуємо ротацію якщо потрібно
        if ($shouldRotate) {
            $this->rotateLogs();
            $this->lastRotationTime = time();
        }

        // Записуємо у файл
        @file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Ротація логів
     *
     * @return void
     */
    private function rotateLogs(): void
    {
        $pattern = $this->logDir . 'app-*.log';
        $files = glob($pattern);

        if ($files === false) {
            return;
        }

        $retentionDays = $this->settings['retention_days'] ?? 30;
        $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);

        // Видаляємо старі файли за датою створення
        foreach ($files as $file) {
            $fileTime = @filemtime($file);
            if ($fileTime !== false && $fileTime < $cutoffTime) {
                @unlink($file);
            }
        }

        // Отримуємо список файлів заново після видалення
        $files = glob($pattern);
        if ($files === false) {
            $files = [];
        }

        // Сортуємо за датою зміни (нові першими)
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Видаляємо старі файли понад ліміт
        $maxFiles = $this->settings['max_files'];
        for ($i = $maxFiles; $i < count($files); $i++) {
            @unlink($files[$i]);
        }

        // Перейменовуємо поточний файл
        if (file_exists($this->logFile)) {
            $newName = $this->logDir . 'app-' . date('Y-m-d') . '-' . time() . '.log';
            @rename($this->logFile, $newName);
        }
    }

    /**
     * Логування DEBUG
     *
     * @param string $message Повідомлення
     * @param array $context Контекст
     * @return void
     */
    public function logDebug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Логування INFO
     *
     * @param string $message Повідомлення
     * @param array $context Контекст
     * @return void
     */
    public function logInfo(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Логування WARNING
     *
     * @param string $message Повідомлення
     * @param array $context Контекст
     * @return void
     */
    public function logWarning(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Логування ERROR
     *
     * @param string $message Повідомлення
     * @param array $context Контекст
     * @return void
     */
    public function logError(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Логування CRITICAL
     *
     * @param string $message Повідомлення
     * @param array $context Контекст
     * @return void
     */
    public function logCritical(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Логування винятку
     *
     * @param Throwable $exception Виняток
     * @param array $context Додатковий контекст
     * @return void
     */
    public function logException(\Throwable $exception, array $context = []): void
    {
        $context['exception'] = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        $this->logError('Виняток: ' . $exception->getMessage(), $context);
    }

    /**
     * Отримання останніх записів логу
     *
     * @param int $lines Кількість рядків
     * @return array
     */
    public function getRecentLogs(int $lines = 100): array
    {
        if (! file_exists($this->logFile)) {
            return [];
        }

        $content = @file_get_contents($this->logFile);
        if ($content === false) {
            return [];
        }

        $allLines = explode("\n", $content);
        $allLines = array_filter($allLines, fn ($line) => ! empty(trim($line)));

        return array_slice($allLines, -$lines);
    }

    /**
     * Очищення логів
     *
     * @return bool
     */
    public function clearLogs(): bool
    {
        $pattern = $this->logDir . 'app-*.log';
        $files = glob($pattern);

        if ($files === false) {
            return false;
        }

        $success = true;
        foreach ($files as $file) {
            if (! @unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Отримання статистики логів
     *
     * @return array
     */
    /**
     * Отримання статистики логів
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $pattern = $this->logDir . 'app-*.log';
        $files = glob($pattern);

        if ($files === false) {
            return [
                'total_files' => 0,
                'total_size' => 0,
                'latest_file' => null,
                'latest_size' => 0,
            ];
        }

        $totalSize = 0;
        $latestFile = null;
        $latestTime = 0;

        foreach ($files as $file) {
            $size = @filesize($file);
            if ($size !== false) {
                $totalSize += $size;
            }

            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime > $latestTime) {
                $latestTime = $mtime;
                $latestFile = $file;
            }
        }

        $latestSize = $latestFile ? @filesize($latestFile) : 0;

        return [
            'total_files' => count($files),
            'total_size' => $totalSize,
            'latest_file' => $latestFile ? basename($latestFile) : null,
            'latest_size' => $latestSize !== false ? $latestSize : 0,
        ];
    }

    /**
     * Отримання дозволених рівнів логування
     *
     * @return array
     */
    /**
     * Отримання дозволених рівнів логування
     *
     * @return array<int, string>
     */
    private function getAllowedLevels(): array
    {
        // Перевіряємо налаштування (множественний вибір)
        if (isset($this->settings['logging_levels']) && ! empty($this->settings['logging_levels'])) {
            $levels = is_array($this->settings['logging_levels'])
                ? $this->settings['logging_levels']
                : explode(',', $this->settings['logging_levels']);

            return array_map('trim', array_filter($levels));
        }

        // Якщо налаштування не встановлено, дозволяємо всі рівні
        return ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];
    }

    /**
     * Отримання дозволених типів логування
     *
     * @return array
     */
    /**
     * Отримання дозволених типів логування
     *
     * @return array<int, string>
     */
    private function getLoggingTypes(): array
    {
        if (isset($this->settings['logging_types']) && ! empty($this->settings['logging_types'])) {
            $types = is_array($this->settings['logging_types'])
                ? $this->settings['logging_types']
                : explode(',', $this->settings['logging_types']);

            return array_map('trim', array_filter($types));
        }

        // Якщо налаштування не встановлено, дозволяємо тільки файл за замовчуванням
        return ['file'];
    }

    // Запобігання клонуванню
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
}

/**
 * Глобальна функція для отримання екземпляра Logger
 *
 * @return LoggerInterface
 */
function logger(): LoggerInterface
{
    return Logger::getInstance();
}
