<?php

/**
 * Клас для роботи з YAML файлами
 * Читання, запис та маніпуляції з YAML даними
 *
 * @package Engine\Classes\Files
 * @version 1.0.0 Alpha prerelease
 *
 * Примітка: Потрібне розширення yaml або symfony/yaml компонент
 */

declare(strict_types=1);

require_once __DIR__ . '/contracts/FileInterface.php';
require_once __DIR__ . '/contracts/StructuredFileInterface.php';

class Yaml implements StructuredFileInterface
{
    private string $filePath = '';
    private mixed $data = null;
    private bool $hasData = false;
    private int $inline = 2; // Рівень вкладеності для інлайн запису
    private int $indent = 2; // Розмір відступу

    /**
     * Конструктор
     *
     * @param string|null $filePath Шлях до YAML файлу
     */
    public function __construct(?string $filePath = null)
    {
        $this->checkExtension();

        if ($filePath !== null) {
            $this->setFile($filePath);
        }
    }

    /**
     * Перевірка наявності розширення YAML
     *
     * @return void
     * @throws Exception Якщо розширення не встановлено
     */
    private function checkExtension(): void
    {
        if (! extension_loaded('yaml') && ! function_exists('yaml_parse')) {
            // Намагаємося використати symfony/yaml якщо доступний через composer
            if (! class_exists('\Symfony\Component\Yaml\Yaml')) {
                throw new Exception('YAML розширення не встановлено. Встановіть php-yaml або symfony/yaml через composer');
            }
        }
    }

    /**
     * Встановлення шляху до файлу
     *
     * @param string $filePath Шлях до YAML файлу
     * @return self
     */
    public function setFile(string $filePath): self
    {
        $this->filePath = $filePath;
        $this->hasData = false;
        $this->data = null;

        if (file_exists($filePath)) {
            $this->load();
        }

        return $this;
    }

    /**
     * Завантаження YAML файлу
     *
     * @param int $pos Позиція початку парсингу (для розширення yaml)
     * @param int $ndocs Кількість документів (для розширення yaml)
     * @param array $callbacks Callbacks для парсингу (для розширення yaml)
     * @return self
     * @throws Exception Якщо файл не існує або не може бути прочитаний
     */
    public function load(int $pos = 0, int &$ndocs = 0, array $callbacks = []): self
    {
        if (empty($this->filePath)) {
            throw new Exception('Шлях до файлу не встановлено');
        }

        if (! file_exists($this->filePath)) {
            throw new Exception("YAML файл не існує: {$this->filePath}");
        }

        if (! is_readable($this->filePath)) {
            throw new Exception("YAML файл недоступний для читання: {$this->filePath}");
        }

        $content = @file_get_contents($this->filePath);

        if ($content === false) {
            throw new Exception("Не вдалося прочитати YAML файл: {$this->filePath}");
        }

        $this->data = $this->parse($content, $pos, $ndocs, $callbacks);
        $this->hasData = true;

        return $this;
    }

    /**
     * Завантаження YAML з рядка
     *
     * @param string $yamlString YAML рядок
     * @param int $pos Позиція початку парсингу
     * @param int $ndocs Кількість документів
     * @param array $callbacks Callbacks
     * @return self
     * @throws Exception Якщо не вдалося завантажити
     */
    public function loadString(string $yamlString, int $pos = 0, int &$ndocs = 0, array $callbacks = []): self
    {
        $this->data = $this->parse($yamlString, $pos, $ndocs, $callbacks);
        $this->hasData = true;

        return $this;
    }

    /**
     * Парсинг YAML рядка
     *
     * @param string $yamlString YAML рядок
     * @param int $pos Позиція початку парсингу
     * @param int $ndocs Кількість документів
     * @param array $callbacks Callbacks
     * @return mixed
     * @throws Exception Якщо не вдалося розпарсити
     */
    private function parse(string $yamlString, int $pos = 0, int &$ndocs = 0, array $callbacks = [])
    {
        // Перевіряємо наявність вбудованого розширення yaml
        if (extension_loaded('yaml') && function_exists('yaml_parse')) {
            // Використовуємо вбудоване розширення через змінну для уникнення помилок статичного аналізу
            $yamlParseFunc = 'yaml_parse';
            $data = @$yamlParseFunc($yamlString, $pos, $ndocs, $callbacks);

            if ($data === false) {
                throw new Exception('Помилка парсингу YAML');
            }

            return $data;
        }

        // Перевіряємо наявність Symfony YAML компонента
        $symfonyYamlClass = '\Symfony\Component\Yaml\Yaml';
        if (class_exists($symfonyYamlClass)) {
            // Використовуємо Symfony YAML компонент
            try {
                return $symfonyYamlClass::parse($yamlString);
            } catch (Exception $e) {
                throw new Exception('Помилка парсингу YAML: ' . $e->getMessage());
            }
        }

        throw new Exception('YAML розширення не доступне');
    }

    /**
     * Збереження даних у YAML файл
     *
     * @param string|null $filePath Шлях до файлу (якщо null, використовується поточний)
     * @param mixed $data Дані для збереження (якщо null, використовуються поточні)
     * @return bool
     * @throws Exception Якщо не вдалося зберегти
     */
    public function save(?string $filePath = null, $data = null): bool
    {
        $targetPath = $filePath ?? $this->filePath;
        $dataToSave = $data ?? $this->data;

        if (empty($targetPath)) {
            throw new Exception('Шлях до файлу не встановлено');
        }

        // Створюємо директорію, якщо її немає
        $dir = dirname($targetPath);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0755, true)) {
                throw new Exception("Не вдалося створити директорію: {$dir}");
            }
        }

        $yaml = $this->dump($dataToSave);

        $result = @file_put_contents($targetPath, $yaml, LOCK_EX);

        if ($result === false) {
            throw new Exception("Не вдалося зберегти YAML файл: {$targetPath}");
        }

        @chmod($targetPath, 0644);

        // Оновлюємо поточні дані
        if ($filePath === null) {
            $this->data = $dataToSave;
            $this->hasData = true;
        }

        return true;
    }

    /**
     * Перетворення даних у YAML рядок
     *
     * @param mixed $data Дані
     * @return string
     * @throws Exception Якщо не вдалося перетворити
     */
    private function dump($data): string
    {
        // Перевіряємо наявність вбудованого розширення yaml
        if (extension_loaded('yaml') && function_exists('yaml_emit')) {
            // Використовуємо вбудоване розширення через змінну для уникнення помилок статичного аналізу
            $yamlEmitFunc = 'yaml_emit';
            // Перевіряємо наявність константи YAML_UTF8_ENCODING
            $encoding = 0;
            if (defined('YAML_UTF8_ENCODING')) {
                $encoding = constant('YAML_UTF8_ENCODING');
            }
            $yaml = @$yamlEmitFunc($data, $encoding);

            if ($yaml === false) {
                throw new Exception('Помилка перетворення даних у YAML');
            }

            return $yaml;
        }

        // Перевіряємо наявність Symfony YAML компонента
        $symfonyYamlClass = '\Symfony\Component\Yaml\Yaml';
        if (class_exists($symfonyYamlClass)) {
            // Використовуємо Symfony YAML компонент
            try {
                return $symfonyYamlClass::dump($data, $this->inline, $this->indent);
            } catch (Exception $e) {
                throw new Exception('Помилка перетворення даних у YAML: ' . $e->getMessage());
            }
        }

        throw new Exception('YAML розширення не доступне');
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
     * Отримання значення за шляхом (крапкова нотація)
     *
     * @param string $path Шлях до значення
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function getDataPath(string $path, $default = null)
    {
        if (! $this->hasData || ! is_array($this->data)) {
            return $default;
        }

        $keys = explode('.', $path);
        $value = $this->data;

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
     * Очищення даних
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

    // ===== Методи з FileInterface =====

    /**
     * Встановлення шляху до файлу (з FileInterface)
     *
     * @param string $filePath Шлях до файлу
     * @return self
     */
    public function setPath(string $filePath): self
    {
        $this->filePath = $filePath;
        $this->hasData = false;
        $this->data = null;

        return $this;
    }

    /**
     * Отримання шляху до файлу (з FileInterface)
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->filePath;
    }

    /**
     * Перевірка існування файлу (з FileInterface)
     *
     * @return bool
     */
    public function exists(): bool
    {
        return ! empty($this->filePath) && file_exists($this->filePath);
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

    /**
     * Встановлення рівня вкладеності для інлайн запису
     *
     * @param int $inline Рівень вкладеності
     * @return self
     */
    public function setInline(int $inline): self
    {
        $this->inline = $inline;

        return $this;
    }

    /**
     * Встановлення розміру відступу
     *
     * @param int $indent Розмір відступу
     * @return self
     */
    public function setIndent(int $indent): self
    {
        $this->indent = $indent;

        return $this;
    }

    /**
     * Статичний метод: Читання YAML файлу
     *
     * @param string $filePath Шлях до файлу
     * @return mixed
     */
    public static function readFile(string $filePath)
    {
        $yaml = new self($filePath);
        $yaml->load();

        return $yaml->getAll();
    }

    /**
     * Статичний метод: Запис YAML файлу
     *
     * @param string $filePath Шлях до файлу
     * @param mixed $data Дані
     * @return bool
     */
    public static function writeFile(string $filePath, $data): bool
    {
        $yaml = new self();
        $yaml->setData($data);

        return $yaml->save($filePath);
    }

    /**
     * Статичний метод: Парсинг YAML рядка
     *
     * @param string $yamlString YAML рядок
     * @return mixed
     */
    public static function parseString(string $yamlString)
    {
        $yaml = new self();

        return $yaml->loadString($yamlString)->getAll();
    }

    /**
     * Статичний метод: Перетворення даних у YAML рядок
     *
     * @param mixed $data Дані
     * @return string
     */
    public static function dumpData($data): string
    {
        $yaml = new self();

        return $yaml->dump($data);
    }
}
