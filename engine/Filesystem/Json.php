<?php

/**
 * Клас для роботи з JSON файлами
 * Читання, запис та валідація JSON даних
 *
 * @package Engine\Classes\Files
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/Filesystem/FileInterface.php';
require_once __DIR__ . '/../Contracts/Filesystem/StructuredFileInterface.php';

class Json implements StructuredFileInterface
{
    private string $filePath = '';
    private mixed $data = null;
    private bool $hasData = false;
    private int $encodeFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
    private int $decodeFlags = JSON_BIGINT_AS_STRING;

    /**
     * Конструктор
     *
     * @param string|null $filePath Шлях до JSON файлу
     */
    public function __construct(?string $filePath = null)
    {
        if ($filePath !== null) {
            $this->setFile($filePath);
        }
    }

    /**
     * Встановлення шляху до файлу
     *
     * @param string $filePath Шлях до JSON файлу
     * @return self
     * @throws Exception Якщо файл існує, але недоступний для читання
     */
    public function setFile(string $filePath): self
    {
        if (! is_readable($filePath) && file_exists($filePath)) {
            throw new Exception("JSON файл існує, але недоступний для читання: {$filePath}");
        }

        $this->filePath = $filePath;
        $this->hasData = false;
        $this->data = null;

        if (file_exists($filePath)) {
            $this->load();
        }

        return $this;
    }

    /**
     * Завантаження даних з JSON файлу
     *
     * @param bool $assoc Перетворювати в асоціативний масив
     * @param int $depth Максимальна глибина вкладеності
     * @return self
     * @throws Exception Якщо файл не існує або не може бути прочитаний
     */
    public function load(bool $assoc = true, int $depth = 512): self
    {
        if (empty($this->filePath)) {
            throw new Exception('Шлях до файлу не встановлено');
        }

        if (! file_exists($this->filePath)) {
            throw new Exception("JSON файл не існує: {$this->filePath}");
        }

        if (! is_readable($this->filePath)) {
            throw new Exception("JSON файл недоступний для читання: {$this->filePath}");
        }

        $content = @file_get_contents($this->filePath);

        if ($content === false) {
            throw new Exception("Не вдалося прочитати JSON файл: {$this->filePath}");
        }

        $content = trim($content);
        if ($content === '') {
            $this->data = $assoc ? [] : new stdClass();
            $this->hasData = true;

            return $this;
        }

        $data = json_decode($content, $assoc, $depth, $this->decodeFlags);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Помилка парсингу JSON файлу '{$this->filePath}': " . json_last_error_msg());
        }

        $this->data = $data;
        $this->hasData = true;

        return $this;
    }

    /**
     * Збереження даних у JSON файл
     *
     * @param string|null $filePath Шлях до файлу (якщо null, використовується поточний)
     * @param mixed|null $data Дані для збереження (якщо null, використовуються поточні)
     * @param int|null $encodeFlags Прапорці кодування (якщо null, використовуються поточні)
     * @return bool
     * @throws Exception Якщо не вдалося зберегти файл
     */
    public function save(?string $filePath = null, mixed $data = null, ?int $encodeFlags = null): bool
    {
        $targetPath = $filePath ?? $this->filePath;
        $dataToSave = $data ?? $this->data;
        $flags = $encodeFlags ?? $this->encodeFlags;

        if (empty($targetPath)) {
            throw new Exception('Шлях до файлу не встановлено');
        }

        $dir = dirname($targetPath);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true)) {
            throw new Exception("Не вдалося створити директорію: {$dir}");
        }

        $json = json_encode($dataToSave, $flags);

        if ($json === false) {
            throw new Exception('Помилка кодування JSON: ' . json_last_error_msg());
        }

        if (@file_put_contents($targetPath, $json, LOCK_EX) === false) {
            throw new Exception("Не вдалося зберегти JSON файл: {$targetPath}");
        }

        @chmod($targetPath, 0644);

        if ($filePath === null) {
            $this->data = $dataToSave;
            $this->hasData = true;
        }

        return true;
    }

    /**
     * Отримання значення за ключем (з StructuredFileInterface)
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (! $this->hasData) {
            return $default;
        }

        if (is_array($this->data)) {
            return $this->data[$key] ?? $default;
        }

        if (is_object($this->data)) {
            return $this->data->$key ?? $default;
        }

        return $default;
    }

    /**
     * Встановлення значення за ключем (з StructuredFileInterface)
     *
     * @param string $key Ключ
     * @param mixed $value Значення
     * @return self
     */
    public function set(string $key, $value): self
    {
        if (! $this->hasData) {
            $this->data = [];
            $this->hasData = true;
        }

        if (is_array($this->data)) {
            $this->data[$key] = $value;
        } elseif (is_object($this->data)) {
            $this->data->$key = $value;
        } else {
            $this->data = [$key => $value];
            $this->hasData = true;
        }

        return $this;
    }

    /**
     * Отримання всіх даних
     * Для отримання значення за ключем використовуйте get($key)
     *
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function getAll(mixed $default = null): mixed
    {
        return $this->hasData ? $this->data : $default;
    }

    /**
     * Встановлення всіх даних
     * Для встановлення значення за ключем використовуйте set($key, $value)
     *
     * @param mixed $data Дані
     * @return self
     */
    public function setAll(mixed $data): self
    {
        $this->data = $data;
        $this->hasData = true;

        return $this;
    }

    /**
     * Отримання значення за шляхом в даних (крапкова нотація)
     * Використовується для роботи з вкладеними даними через dot notation
     *
     * @param string $path Шлях до значення (наприклад, "user.name" або "users.0.email")
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    private function getDataPathInternal(string $path, mixed $default = null): mixed
    {
        if (! $this->hasData) {
            return $default;
        }

        $keys = explode('.', $path);
        $value = $this->data;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } elseif (is_object($value) && isset($value->$key)) {
                $value = $value->$key;
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Видалення значення за шляхом
     *
     * @param string $path Шлях до значення
     * @return self
     */
    public function removePath(string $path): self
    {
        if (! $this->hasData) {
            return $this;
        }

        $keys = explode('.', $path);
        $lastKey = array_pop($keys);
        $target = &$this->data;

        foreach ($keys as $key) {
            if (is_array($target) && isset($target[$key])) {
                $target = &$target[$key];
            } elseif (is_object($target) && isset($target->$key)) {
                $target = &$target->$key;
            } else {
                return $this;
            }
        }

        if (is_array($target)) {
            unset($target[$lastKey]);
        } elseif (is_object($target)) {
            unset($target->$lastKey);
        }

        return $this;
    }

    /**
     * Перевірка наявності значення за шляхом
     *
     * @param string $path Шлях до значення
     * @return bool
     */
    public function hasPath(string $path): bool
    {
        if (! $this->hasData) {
            return false;
        }

        $keys = explode('.', $path);
        $value = $this->data;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } elseif (is_object($value) && isset($value->$key)) {
                $value = $value->$key;
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Очищення всіх даних
     *
     * @return self
     */
    public function clear(): self
    {
        $this->data = null;
        $this->hasData = false;

        return $this;
    }

    /**
     * Перевірка, чи завантажені дані
     *
     * @return bool
     */
    public function isLoaded(): bool
    {
        return $this->hasData;
    }

    /**
     * Отримання шляху до файлу
     *
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Встановлення прапорців кодування
     *
     * @param int $flags Прапорці кодування
     * @return self
     */
    public function setEncodeFlags(int $flags): self
    {
        $this->encodeFlags = $flags;

        return $this;
    }

    /**
     * Встановлення прапорців декодування
     *
     * @param int $flags Прапорці декодування
     * @return self
     */
    public function setDecodeFlags(int $flags): self
    {
        $this->decodeFlags = $flags;

        return $this;
    }

    /**
     * Отримання прапорців кодування
     *
     * @return int
     */
    public function getEncodeFlags(): int
    {
        return $this->encodeFlags;
    }

    /**
     * Отримання прапорців декодування
     *
     * @return int
     */
    public function getDecodeFlags(): int
    {
        return $this->decodeFlags;
    }

    /**
     * Валідація JSON рядка
     *
     * @param string $json JSON рядок
     * @param bool $assoc Перетворювати в асоціативний масив
     * @return array ['valid' => bool, 'data' => mixed|null, 'error' => string|null]
     */
    /**
     * @param string $json
     * @param bool $assoc
     * @return array<string, mixed>|array<int, mixed>
     */
    public static function validate(string $json, bool $assoc = true): array
    {
        $json = trim($json);

        if ($json === '') {
            return ['valid' => false, 'data' => null, 'error' => 'Порожній рядок'];
        }

        $data = json_decode($json, $assoc);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['valid' => false, 'data' => null, 'error' => json_last_error_msg()];
        }

        return ['valid' => true, 'data' => $data, 'error' => null];
    }

    /**
     * Кодування даних у JSON рядок
     *
     * @param mixed $data Дані
     * @param int $flags Прапорці кодування
     * @return string
     * @throws Exception Якщо не вдалося закодувати
     */
    public static function encode(mixed $data, int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT): string
    {
        $json = json_encode($data, $flags);

        if ($json === false) {
            throw new Exception('Помилка кодування JSON: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Декодування JSON рядка
     *
     * @param string $json JSON рядок
     * @param bool $assoc Перетворювати в асоціативний масив
     * @param int $depth Максимальна глибина вкладеності
     * @return mixed
     * @throws Exception Якщо не вдалося декодувати
     */
    public static function decode(string $json, bool $assoc = true, int $depth = 512): mixed
    {
        $json = trim($json);

        if ($json === '') {
            return $assoc ? [] : new stdClass();
        }

        $data = json_decode($json, $assoc, $depth);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Помилка декодування JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Читання JSON файлу (статичний метод)
     *
     * @param string $filePath Шлях до файлу
     * @param bool $assoc Перетворювати в асоціативний масив
     * @return mixed
     */
    public static function readFile(string $filePath, bool $assoc = true): mixed
    {
        return (new self($filePath))->load($assoc)->getAll();
    }

    /**
     * Запис JSON файлу (статичний метод)
     *
     * @param string $filePath Шлях до файлу
     * @param mixed $data Дані
     * @param int $flags Прапорці кодування
     * @return bool
     */
    public static function writeFile(string $filePath, mixed $data, int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT): bool
    {
        return (new self())->save($filePath, $data, $flags);
    }

    /**
     * Парсинг JSON рядка
     *
     * @param string $json JSON рядок
     * @param bool $assoc Перетворювати в асоціативний масив
     * @param int $depth Максимальна глибина вкладеності
     * @return mixed
     * @throws Exception Якщо не вдалося декодувати
     */
    public static function parse(string $json, bool $assoc = true, int $depth = 512): mixed
    {
        return self::decode($json, $assoc, $depth);
    }

    /**
     * Перетворення даних у JSON рядок
     *
     * @param mixed $data Дані
     * @param int $flags Прапорці кодування
     * @return string
     * @throws Exception Якщо не вдалося закодувати
     */
    public static function stringify(mixed $data, int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT): string
    {
        return self::encode($data, $flags);
    }

    /**
     * Перевірка, чи є рядок валідним JSON
     *
     * @param string $json JSON рядок
     * @return bool
     */
    public static function isValid(string $json): bool
    {
        return self::validate($json)['valid'];
    }

    /**
     * Мініфікація JSON (видалення пробілів та переносів рядків)
     *
     * @param string $json JSON рядок
     * @return string
     * @throws Exception Якщо не вдалося обробити
     */
    public static function minify(string $json): string
    {
        return self::encode(self::decode($json), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Форматування JSON (додавання відступів)
     *
     * @param string $json JSON рядок
     * @param int $flags Прапорці кодування
     * @return string
     * @throws Exception Якщо не вдалося обробити
     */
    public static function format(string $json, int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT): string
    {
        return self::encode(self::decode($json), $flags);
    }

    /**
     * Злиття JSON даних
     *
     * @param array ...$jsonData Масиви JSON даних для злиття
     * @return array
     */
    /**
     * @param array<string, mixed> ...$jsonData
     * @return array<string, mixed>
     */
    public static function merge(array ...$jsonData): array
    {
        return array_merge_recursive(...$jsonData);
    }

    /**
     * Порівняння двох JSON структур
     *
     * @param mixed $data1 Перші дані
     * @param mixed $data2 Другі дані
     * @param bool $strict Строге порівняння (враховувати порядок ключів)
     * @return bool
     */
    public static function equals(mixed $data1, mixed $data2, bool $strict = false): bool
    {
        if ($strict) {
            return $data1 === $data2;
        }

        try {
            return self::encode($data1, JSON_UNESCAPED_UNICODE) === self::encode($data2, JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Отримання значення за шляхом з JSON даних
     *
     * @param array|object|string $data JSON дані
     * @param string $path Шлях (крапкова нотація, наприклад "user.name")
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public static function getValue(array|object|string $data, string $path, mixed $default = null): mixed
    {
        if (is_string($data)) {
            try {
                $data = self::decode($data);
            } catch (Exception $e) {
                return $default;
            }
        }

        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } elseif (is_object($value) && isset($value->$key)) {
                $value = $value->$key;
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Встановлення значення за шляхом у JSON даних
     *
     * @param array<string, mixed> $data JSON дані (масив)
     * @param string $path Шлях (крапкова нотація)
     * @param mixed $value Значення
     * @return array<string, mixed>
     */
    public static function setValue(array $data, string $path, mixed $value): array
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

        return $data;
    }

    // ===== Реалізація методів з FileInterface =====

    /**
     * Встановлення шляху до файлу (з FileInterface)
     * Аліас для setFile()
     *
     * @param string $filePath Шлях до файлу
     * @return self
     */
    public function setPath(string $filePath): self
    {
        return $this->setFile($filePath);
    }

    /**
     * Отримання шляху до файлу (з FileInterface)
     * Аліас для getFilePath()
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->getFilePath();
    }

    /**
     * Перевірка існування файлу (з FileInterface)
     *
     * @return bool
     */
    public function exists(): bool
    {
        return ! empty($this->filePath) && file_exists($this->filePath) && is_file($this->filePath);
    }

    /**
     * Читання вмісту файлу (з FileInterface)
     *
     * @return string
     * @throws Exception
     */
    public function read(): string
    {
        if (! $this->exists()) {
            throw new Exception("Файл не існує: {$this->filePath}");
        }

        $content = @file_get_contents($this->filePath);
        if ($content === false) {
            throw new Exception("Не вдалося прочитати файл: {$this->filePath}");
        }

        return $content;
    }

    /**
     * Запис вмісту в файл (з FileInterface)
     *
     * @param string $content Вміст для запису
     * @param bool $append Додавати в кінець файлу
     * @return bool
     * @throws Exception
     */
    public function write(string $content, bool $append = false): bool
    {
        if (empty($this->filePath)) {
            throw new Exception('Шлях до файлу не встановлено');
        }

        $dir = dirname($this->filePath);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true)) {
            throw new Exception("Не вдалося створити директорію: {$dir}");
        }

        $flags = $append ? FILE_APPEND | LOCK_EX : LOCK_EX;
        $result = @file_put_contents($this->filePath, $content, $flags);

        if ($result === false) {
            throw new Exception("Не вдалося записати файл: {$this->filePath}");
        }

        @chmod($this->filePath, 0644);

        return true;
    }

    /**
     * Копіювання файлу (з FileInterface)
     *
     * @param string $destinationPath Шлях призначення
     * @return bool
     * @throws Exception
     */
    public function copy(string $destinationPath): bool
    {
        if (! $this->exists()) {
            throw new Exception("Вихідний файл не існує: {$this->filePath}");
        }

        $dir = dirname($destinationPath);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true)) {
            throw new Exception("Не вдалося створити директорію: {$dir}");
        }

        if (! @copy($this->filePath, $destinationPath)) {
            throw new Exception("Не вдалося скопіювати файл з '{$this->filePath}' в '{$destinationPath}'");
        }

        @chmod($destinationPath, 0644);

        return true;
    }

    /**
     * Переміщення/перейменування файлу (з FileInterface)
     *
     * @param string $destinationPath Шлях призначення
     * @return bool
     * @throws Exception
     */
    public function move(string $destinationPath): bool
    {
        if (! $this->exists()) {
            throw new Exception("Вихідний файл не існує: {$this->filePath}");
        }

        $dir = dirname($destinationPath);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true)) {
            throw new Exception("Не вдалося створити директорію: {$dir}");
        }

        if (! @rename($this->filePath, $destinationPath)) {
            throw new Exception("Не вдалося перемістити файл з '{$this->filePath}' в '{$destinationPath}'");
        }

        $this->filePath = $destinationPath;

        return true;
    }

    /**
     * Видалення файлу (з FileInterface)
     *
     * @return bool
     * @throws Exception
     */
    public function delete(): bool
    {
        if (! $this->exists()) {
            return true;
        }

        if (! @unlink($this->filePath)) {
            throw new Exception("Не вдалося видалити файл: {$this->filePath}");
        }

        return true;
    }

    /**
     * Отримання розміру файлу (з FileInterface)
     *
     * @return int
     */
    public function getSize(): int
    {
        return $this->exists() ? filesize($this->filePath) : 0;
    }

    /**
     * Отримання MIME типу файлу (з FileInterface)
     *
     * @return string|false
     */
    public function getMimeType()
    {
        if (! $this->exists()) {
            return false;
        }

        if (function_exists('mime_content_type')) {
            return @mime_content_type($this->filePath);
        }

        if (function_exists('finfo_file')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo === false) {
                return false;
            }

            $mimeType = @finfo_file($finfo, $this->filePath);

            // finfo_close() is deprecated in PHP 8.1+, resource is automatically closed
            return $mimeType;
        }

        return false;
    }

    /**
     * Отримання часу останньої зміни (з FileInterface)
     *
     * @return int|false
     */
    public function getMTime()
    {
        return $this->exists() ? @filemtime($this->filePath) : false;
    }

    /**
     * Отримання розширення файлу (з FileInterface)
     *
     * @return string
     */
    public function getExtension(): string
    {
        return ! empty($this->filePath) ? strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION)) : '';
    }

    /**
     * Отримання імені файлу з розширенням (з FileInterface)
     *
     * @return string
     */
    public function getBasename(): string
    {
        return ! empty($this->filePath) ? pathinfo($this->filePath, PATHINFO_BASENAME) : '';
    }

    /**
     * Отримання імені файлу без шляху та розширення (з FileInterface)
     *
     * @return string
     */
    public function getFilename(): string
    {
        return ! empty($this->filePath) ? pathinfo($this->filePath, PATHINFO_FILENAME) : '';
    }

    /**
     * Перевірка доступності файлу для читання (з FileInterface)
     *
     * @return bool
     */
    public function isReadable(): bool
    {
        return $this->exists() && is_readable($this->filePath);
    }

    /**
     * Перевірка доступності файлу для запису (з FileInterface)
     *
     * @return bool
     */
    public function isWritable(): bool
    {
        return $this->exists() && is_writable($this->filePath);
    }

    // ===== Допоміжні методи для роботи з даними =====

    /**
     * Отримання значення за шляхом в даних (крапкова нотація)
     * Публічний метод для роботи з вкладеними даними
     *
     * @param string $path Шлях до значення
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function getDataPath(string $path, mixed $default = null): mixed
    {
        return $this->getDataPathInternal($path, $default);
    }

    /**
     * Встановлення значення за шляхом в даних (крапкова нотація)
     * Публічний метод для роботи з вкладеними даними
     *
     * @param string $path Шлях до значення
     * @param mixed $value Значення
     * @return self
     */
    public function setDataPath(string $path, mixed $value): self
    {
        if (! $this->hasData) {
            $this->data = [];
            $this->hasData = true;
        }

        $keys = explode('.', $path);
        $lastKey = array_pop($keys);
        $target = &$this->data;

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

        return $this;
    }

    // ===== Методи з StructuredFileInterface =====

    /**
     * Отримання даних (з StructuredFileInterface)
     * Повертає всі дані
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->hasData ? $this->data : null;
    }

    /**
     * Встановлення даних (з StructuredFileInterface)
     * Встановлює всі дані
     *
     * @param mixed $data Дані
     * @return self
     */
    public function setData($data): self
    {
        $this->data = $data;
        $this->hasData = true;

        return $this;
    }

    /**
     * Перевірка наявності завантажених даних (з StructuredFileInterface)
     *
     * @return bool
     */
    public function hasData(): bool
    {
        return $this->hasData;
    }

    /**
     * Перевірка наявності ключа (з StructuredFileInterface)
     *
     * @param string $key Ключ
     * @return bool
     */
    public function has(string $key): bool
    {
        if (! $this->hasData) {
            return false;
        }

        if (is_array($this->data)) {
            return isset($this->data[$key]);
        }

        if (is_object($this->data)) {
            return isset($this->data->$key);
        }

        return false;
    }

    /**
     * Видалення значення за ключем (з StructuredFileInterface)
     *
     * @param string $key Ключ
     * @return self
     */
    public function remove(string $key): self
    {
        if (! $this->hasData) {
            return $this;
        }

        if (is_array($this->data)) {
            unset($this->data[$key]);
        } elseif (is_object($this->data)) {
            unset($this->data->$key);
        }

        return $this;
    }

}
