<?php

/**
 * Клас для роботи з конфігураціями сайту
 * Керування конфігураційними файлами в директорії engine/data
 *
 * @package Engine\Classes
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../filesystem/Directory.php';

use Engine\Classes\Files\Directory;

class Config
{
    private static ?self $instance = null;
    private string $configDir;
    private array $loadedConfigs = [];
    private const DEFAULT_EXTENSION = '.json';

    /**
     * Конструктор (приватний для Singleton)
     *
     * @param string|null $configDir Директорія для конфігурацій
     */
    private function __construct(?string $configDir = null)
    {
        $this->configDir = $configDir ?? dirname(__DIR__, 2) . '/data/';
        $this->configDir = rtrim($this->configDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->ensureConfigDir();
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
     * Створення директорії конфігурацій
     *
     * @return void
     */
    private function ensureConfigDir(): void
    {
        // Перевіряємо існування директорії (створюється через інсталятор)
        if (! is_dir($this->configDir)) {
            error_log("Config: Directory does not exist: {$this->configDir}. Please run installer to create storage directories.");
        }
    }

    /**
     * Отримання значення конфігурації
     *
     * @param string $key Ключ конфігурації (може бути в форматі "config.key" або "config.section.key")
     * @param mixed $default Значення за замовчуванням
     * @param string|null $extension Розширення файлу (.json або .ini)
     * @return mixed
     */
    public function get(string $key, $default = null, ?string $extension = null)
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;

        // Розбираємо ключ
        if (! str_contains($key, '.')) {
            // Якщо ключ без точки, значить це ім'я конфігу
            $configName = $key;
            $path = null;
        } else {
            $parts = explode('.', $key, 2);
            $configName = $parts[0];
            $path = $parts[1] ?? null;
        }

        // Завантажуємо конфігурацію, якщо її немає в пам'яті
        if (! isset($this->loadedConfigs[$configName])) {
            $this->loadConfig($configName, $extension);
        }

        $config = $this->loadedConfigs[$configName];

        if ($path === null) {
            // Повертаємо весь конфіг
            return $config;
        }

        // Отримуємо значення за шляхом
        return $this->getValueByPath($config, $path, $default);
    }

    /**
     * Встановлення значення конфігурації
     *
     * @param string $key Ключ конфігурації
     * @param mixed $value Значення
     * @param string|null $extension Розширення файлу
     * @return bool
     */
    public function set(string $key, $value, ?string $extension = null): bool
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;

        // Розбираємо ключ
        if (! str_contains($key, '.')) {
            throw new Exception("Для встановлення значення потрібно вказати шлях (наприклад, 'config.key')");
        }

        $parts = explode('.', $key, 2);
        $configName = $parts[0];
        $path = $parts[1];

        // Завантажуємо конфігурацію, якщо її немає в пам'яті
        if (! isset($this->loadedConfigs[$configName])) {
            $this->loadConfig($configName, $extension);
        }

        // Встановлюємо значення за шляхом
        $this->setValueByPath($this->loadedConfigs[$configName], $path, $value);

        // Зберігаємо конфігурацію
        return $this->saveConfig($configName, $extension);
    }

    /**
     * Перевірка наявності ключа в конфігурації
     *
     * @param string $key Ключ конфігурації
     * @param string|null $extension Розширення файлу
     * @return bool
     */
    public function has(string $key, ?string $extension = null): bool
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;

        // Розбираємо ключ
        if (! str_contains($key, '.')) {
            $configName = $key;
            $path = null;
        } else {
            $parts = explode('.', $key, 2);
            $configName = $parts[0];
            $path = $parts[1] ?? null;
        }

        // Завантажуємо конфігурацію, якщо її немає в пам'яті
        if (! isset($this->loadedConfigs[$configName])) {
            $this->loadConfig($configName, $extension);
        }

        if ($path === null) {
            return isset($this->loadedConfigs[$configName]);
        }

        $config = $this->loadedConfigs[$configName];

        return $this->hasValueByPath($config, $path);
    }

    /**
     * Видалення значення з конфігурації
     *
     * @param string $key Ключ конфігурації
     * @param string|null $extension Розширення файлу
     * @return bool
     */
    public function remove(string $key, ?string $extension = null): bool
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;

        if (! str_contains($key, '.')) {
            throw new Exception("Для видалення значення потрібно вказати шлях (наприклад, 'config.key')");
        }

        $parts = explode('.', $key, 2);
        $configName = $parts[0];
        $path = $parts[1];

        // Завантажуємо конфігурацію, якщо її немає в пам'яті
        if (! isset($this->loadedConfigs[$configName])) {
            $this->loadConfig($configName, $extension);
        }

        // Видаляємо значення за шляхом
        $this->removeValueByPath($this->loadedConfigs[$configName], $path);

        // Зберігаємо конфігурацію
        return $this->saveConfig($configName, $extension);
    }

    /**
     * Завантаження конфігурації
     *
     * @param string $configName Ім'я конфігурації (без розширення)
     * @param string|null $extension Розширення файлу
     * @return void
     */
    private function loadConfig(string $configName, ?string $extension = null): void
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;
        $filePath = $this->getConfigPath($configName, $extension);

        if ($extension === '.json') {
            if (file_exists($filePath)) {
                try {
                    $json = new Json($filePath);
                    $json->load(true);
                    $this->loadedConfigs[$configName] = $json->getAll([]);
                } catch (Exception $e) {
                    error_log("Config load error for '{$configName}': " . $e->getMessage());
                    $this->loadedConfigs[$configName] = [];
                }
            } else {
                $this->loadedConfigs[$configName] = [];
            }
        } elseif ($extension === '.ini') {
            if (file_exists($filePath)) {
                try {
                    $ini = new Ini($filePath);
                    $ini->load();
                    $this->loadedConfigs[$configName] = $ini->all();
                } catch (Exception $e) {
                    error_log("Config load error for '{$configName}': " . $e->getMessage());
                    $this->loadedConfigs[$configName] = [];
                }
            } else {
                $this->loadedConfigs[$configName] = [];
            }
        } else {
            throw new Exception("Непідтримуване розширення файлу: {$extension}");
        }
    }

    /**
     * Збереження конфігурації
     *
     * @param string $configName Ім'я конфігурації
     * @param string|null $extension Розширення файлу
     * @return bool
     */
    private function saveConfig(string $configName, ?string $extension = null): bool
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;
        $filePath = $this->getConfigPath($configName, $extension);

        if (! isset($this->loadedConfigs[$configName])) {
            return false;
        }

        $data = $this->loadedConfigs[$configName];

        if ($extension === '.json') {
            try {
                return Json::writeFile($filePath, $data);
            } catch (Exception $e) {
                error_log("Config save error for '{$configName}': " . $e->getMessage());

                return false;
            }
        } elseif ($extension === '.ini') {
            try {
                $ini = new Ini($filePath);
                $ini->setFile($filePath);
                foreach ($data as $section => $values) {
                    if (is_array($values)) {
                        $ini->setSection($section, $values);
                    } else {
                        $ini->set($section, $values);
                    }
                }

                return $ini->save();
            } catch (Exception $e) {
                error_log("Config save error for '{$configName}': " . $e->getMessage());

                return false;
            }
        } else {
            throw new Exception("Непідтримуване розширення файлу: {$extension}");
        }
    }

    /**
     * Отримання шляху до файлу конфігурації
     *
     * @param string $configName Ім'я конфігурації
     * @param string $extension Розширення файлу
     * @return string
     */
    private function getConfigPath(string $configName, string $extension): string
    {
        // Безпека: прибираємо неприпустимі символи з імені конфігу
        $configName = preg_replace('/[^a-zA-Z0-9_-]/', '', $configName);

        return $this->configDir . $configName . $extension;
    }

    /**
     * Отримання значення за шляхом (точкова нотація)
     *
     * @param array $data Дані
     * @param string $path Шлях
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    private function getValueByPath($data, string $path, $default = null)
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value)) {
                if (isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    return $default;
                }
            } elseif (is_object($value)) {
                if (isset($value->$key)) {
                    $value = $value->$key;
                } else {
                    return $default;
                }
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Встановлення значення за шляхом (точкова нотація)
     *
     * @param array $data Дані (передається за посиланням)
     * @param string $path Шлях
     * @param mixed $value Значення
     * @return void
     */
    private function setValueByPath(&$data, string $path, $value): void
    {
        $keys = explode('.', $path);
        $lastKey = array_pop($keys);
        $target = &$data;

        foreach ($keys as $key) {
            if (is_array($target)) {
                if (! isset($target[$key]) || ! is_array($target[$key])) {
                    $target[$key] = [];
                }
                $target = &$target[$key];
            } elseif (is_object($target)) {
                if (! isset($target->$key) || ! is_array($target->$key)) {
                    $target->$key = [];
                }
                $target = &$target->$key;
            } else {
                $target = (array)$target;
                if (! isset($target[$key])) {
                    $target[$key] = [];
                }
                $target = &$target[$key];
            }
        }

        if (is_array($target)) {
            $target[$lastKey] = $value;
        } elseif (is_object($target)) {
            $target->$lastKey = $value;
        } else {
            $target = [$lastKey => $value];
        }
    }

    /**
     * Перевірка наявності значення за шляхом
     *
     * @param mixed $data Дані
     * @param string $path Шлях
     * @return bool
     */
    private function hasValueByPath($data, string $path): bool
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value)) {
                if (! isset($value[$key])) {
                    return false;
                }
                $value = $value[$key];
            } elseif (is_object($value)) {
                if (! isset($value->$key)) {
                    return false;
                }
                $value = $value->$key;
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Видалення значення за шляхом
     *
     * @param mixed $data Дані (передається за посиланням)
     * @param string $path Шлях
     * @return void
     */
    private function removeValueByPath(&$data, string $path): void
    {
        $keys = explode('.', $path);
        $lastKey = array_pop($keys);
        $target = &$data;

        foreach ($keys as $key) {
            if (is_array($target)) {
                if (! isset($target[$key])) {
                    return;
                }
                $target = &$target[$key];
            } elseif (is_object($target)) {
                if (! isset($target->$key)) {
                    return;
                }
                $target = &$target->$key;
            } else {
                return;
            }
        }

        if (is_array($target)) {
            unset($target[$lastKey]);
        } elseif (is_object($target)) {
            unset($target->$lastKey);
        }
    }

    /**
     * Отримання всіх даних конфігурації
     *
     * @param string $configName Ім'я конфігурації
     * @param string|null $extension Розширення файлу
     * @return array
     */
    /**
     * @param string $configName
     * @param string|null $extension
     * @return array<string, mixed>
     */
    public function all(string $configName, ?string $extension = null): array
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;

        if (! isset($this->loadedConfigs[$configName])) {
            $this->loadConfig($configName, $extension);
        }

        return $this->loadedConfigs[$configName] ?? [];
    }

    /**
     * Встановлення всіх даних конфігурації
     *
     * @param string $configName Ім'я конфігурації
     * @param array $data Дані
     * @param string|null $extension Розширення файлу
     * @return bool
     */
    /**
     * @param string $configName
     * @param array<string, mixed> $data
     * @param string|null $extension
     * @return bool
     */
    public function setAll(string $configName, array $data, ?string $extension = null): bool
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;
        $this->loadedConfigs[$configName] = $data;

        return $this->saveConfig($configName, $extension);
    }

    /**
     * Очистка конфигурации из памяти
     *
     * @param string|null $configName Имя конфигурации (если null, очищаются все)
     * @return self
     */
    public function clear(?string $configName = null): self
    {
        if ($configName === null) {
            $this->loadedConfigs = [];
        } else {
            unset($this->loadedConfigs[$configName]);
        }

        return $this;
    }

    /**
     * Перезагрузка конфигурации
     *
     * @param string $configName Имя конфигурации
     * @param string|null $extension Расширение файла
     * @return bool
     */
    public function reload(string $configName, ?string $extension = null): bool
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;
        unset($this->loadedConfigs[$configName]);
        $this->loadConfig($configName, $extension);

        return isset($this->loadedConfigs[$configName]);
    }

    /**
     * Отримання шляху до директорії конфігурацій
     *
     * @return string
     */
    public function getConfigDir(): string
    {
        return $this->configDir;
    }

    /**
     * Отримання списку всіх конфігураційних файлів
     *
     * @param string|null $extension Розширення файлу (якщо null, всі розширення)
     * @return array
     */
    /**
     * @param string|null $extension
     * @return array<int, string>
     */
    public function getConfigFiles(?string $extension = null): array
    {
        $files = [];

        if (! is_dir($this->configDir)) {
            return $files;
        }

        $pattern = $this->configDir . '*' . ($extension ?? '*');
        $foundFiles = glob($pattern);

        if ($foundFiles === false) {
            return $files;
        }

        foreach ($foundFiles as $file) {
            if (is_file($file)) {
                $filename = basename($file);
                // Пропускаємо службові файли
                if (in_array($filename, ['.htaccess', '.gitkeep'], true)) {
                    continue;
                }
                $files[] = $filename;
            }
        }

        return $files;
    }

    /**
     * Експорт конфігурації в JSON
     *
     * @param string $configName Ім'я конфігурації
     * @param string|null $extension Розширення файлу
     * @return string JSON рядок
     * @throws Exception Якщо конфігурацію не знайдено
     */
    public function export(string $configName, ?string $extension = null): string
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;
        $data = $this->all($configName, $extension);

        return Json::stringify($data);
    }

    /**
     * Імпорт конфігурації з JSON
     *
     * @param string $configName Ім'я конфігурації
     * @param string $jsonData JSON рядок
     * @param string|null $extension Розширення файлу
     * @return bool
     * @throws Exception Якщо не вдалося імпортувати
     */
    public function import(string $configName, string $jsonData, ?string $extension = null): bool
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;

        try {
            $data = Json::decode($jsonData);

            return $this->setAll($configName, $data, $extension);
        } catch (Exception $e) {
            error_log("Config import error for '{$configName}': " . $e->getMessage());

            return false;
        }
    }

    /**
     * Копіювання конфігурації
     *
     * @param string $sourceConfig Вихідна конфігурація
     * @param string $targetConfig Цільова конфігурація
     * @param string|null $extension Розширення файлу
     * @return bool
     */
    public function copy(string $sourceConfig, string $targetConfig, ?string $extension = null): bool
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;
        $data = $this->all($sourceConfig, $extension);

        return $this->setAll($targetConfig, $data, $extension);
    }

    /**
     * Видалення конфігураційного файлу
     *
     * @param string $configName Ім'я конфігурації
     * @param string|null $extension Розширення файлу
     * @return bool
     */
    public function deleteFile(string $configName, ?string $extension = null): bool
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;
        $filePath = $this->getConfigPath($configName, $extension);

        // Видаляємо з пам'яті
        unset($this->loadedConfigs[$configName]);

        // Видаляємо файл (використовуємо File клас)
        $file = new File($filePath);
        if ($file->exists()) {
            try {
                return $file->delete();
            } catch (Exception $e) {
                error_log('Config deleteFile error: ' . $e->getMessage());

                return false;
            }
        }

        return true;
    }

    /**
     * Перевірка існування конфігураційного файлу
     *
     * @param string $configName Ім'я конфігурації
     * @param string|null $extension Розширення файлу
     * @return bool
     */
    public function fileExists(string $configName, ?string $extension = null): bool
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;
        $filePath = $this->getConfigPath($configName, $extension);
        $file = new File($filePath);

        return $file->exists();
    }

    /**
     * Отримання розміру конфігураційного файлу
     *
     * @param string $configName Ім'я конфігурації
     * @param string|null $extension Розширення файлу
     * @return int Розмір файлу в байтах, або -1 якщо файл не існує
     */
    public function getFileSize(string $configName, ?string $extension = null): int
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;
        $filePath = $this->getConfigPath($configName, $extension);
        $file = new File($filePath);

        if ($file->exists()) {
            return $file->getSize();
        }

        return -1;
    }

    /**
     * Отримання часу останньої зміни конфігураційного файлу
     *
     * @param string $configName Ім'я конфігурації
     * @param string|null $extension Розширення файлу
     * @return int|false Unix timestamp або false якщо файл не існує
     */
    public function getFileMTime(string $configName, ?string $extension = null)
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;
        $filePath = $this->getConfigPath($configName, $extension);
        $file = new File($filePath);

        if ($file->exists()) {
            return $file->getMTime();
        }

        return false;
    }

    /**
     * Об'єднання двох конфігурацій
     *
     * @param string $configName1 Перша конфігурація
     * @param string $configName2 Друга конфігурація
     * @param string $targetConfig Результуюча конфігурація
     * @param string|null $extension Розширення файлу
     * @return bool
     */
    public function merge(string $configName1, string $configName2, string $targetConfig, ?string $extension = null): bool
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;
        $data1 = $this->all($configName1, $extension);
        $data2 = $this->all($configName2, $extension);
        $merged = array_merge_recursive($data1, $data2);

        return $this->setAll($targetConfig, $merged, $extension);
    }

    /**
     * Резервне копіювання конфігурації
     *
     * @param string $configName Ім'я конфігурації
     * @param string|null $backupPath Шлях для резервної копії (якщо null, створюється автоматично)
     * @param string|null $extension Розширення файлу
     * @return bool|string Шлях до резервної копії або false при помилці
     */
    public function backup(string $configName, ?string $backupPath = null, ?string $extension = null)
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;
        $filePath = $this->getConfigPath($configName, $extension);

        if (! file_exists($filePath)) {
            return false;
        }

        if ($backupPath === null) {
            $timestamp = date('Y-m-d_H-i-s');
            $backupName = $configName . '_backup_' . $timestamp . $extension;
            $backupPath = $this->configDir . $backupName;
        }

        // Створюємо директорію, якщо її немає (використовуємо Directory клас)
        $dir = dirname($backupPath);
        $directory = new Directory($dir);
        if (! $directory->exists()) {
            if (! $directory->create(0755, true)) {
                return false;
            }
        }

        // Копіюємо файл (використовуємо File клас)
        $sourceFile = new File($filePath);
        if ($sourceFile->exists()) {
            try {
                if ($sourceFile->copy($backupPath)) {
                    return $backupPath;
                }
            } catch (Exception $e) {
                error_log('Config backup error: ' . $e->getMessage());

                return false;
            }
        }

        return false;
    }

    /**
     * Відновлення конфігурації з резервної копії
     *
     * @param string $backupPath Шлях до резервної копії
     * @param string $configName Ім'я конфігурації для відновлення
     * @param string|null $extension Розширення файлу
     * @return bool
     */
    public function restore(string $backupPath, string $configName, ?string $extension = null): bool
    {
        $extension = $extension ?? self::DEFAULT_EXTENSION;
        $filePath = $this->getConfigPath($configName, $extension);

        if (! file_exists($backupPath)) {
            return false;
        }

        // Видаляємо з пам'яті
        unset($this->loadedConfigs[$configName]);

        // Копіюємо файл (використовуємо File клас)
        $backupFile = new File($backupPath);
        if ($backupFile->exists()) {
            try {
                if ($backupFile->copy($filePath)) {
                    return true;
                }
            } catch (Exception $e) {
                error_log('Config restore error: ' . $e->getMessage());

                return false;
            }
        }

        return false;
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

// Глобальні функції для зручності
/**
 * Отримання екземпляра конфігурації
 *
 * @return Config
 */
function config(): Config
{
    return Config::getInstance();
}

/**
 * Отримання значення конфігурації
 *
 * @param string $key Ключ
 * @param mixed $default Значення за замовчуванням
 * @return mixed
 */
function config_get(string $key, $default = null)
{
    return config()->get($key, $default);
}

/**
 * Встановлення значення конфігурації
 *
 * @param string $key Ключ
 * @param mixed $value Значення
 * @return bool
 */
function config_set(string $key, $value): bool
{
    return config()->set($key, $value);
}

/**
 * Перевірка наявності ключа в конфігурації
 *
 * @param string $key Ключ
 * @return bool
 */
function config_has(string $key): bool
{
    return config()->has($key);
}
