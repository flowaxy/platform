<?php

/**
 * Клас для роботи з XML файлами
 * Парсинг, створення та маніпуляції з XML документами
 *
 * @package Engine\Classes\Files
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/Filesystem/FileInterface.php';
require_once __DIR__ . '/../Contracts/Filesystem/StructuredFileInterface.php';

class Xml implements StructuredFileInterface
{
    private string $filePath = '';
    private ?DOMDocument $document = null;
    private bool $hasData = false;

    /**
     * Конструктор
     *
     * @param string|null $filePath Шлях до XML файлу
     * @param string $version Версія XML (за замовчуванням "1.0")
     * @param string $encoding Кодування (за замовчуванням "UTF-8")
     */
    public function __construct(?string $filePath = null, string $version = '1.0', string $encoding = 'UTF-8')
    {
        $this->document = new DOMDocument($version, $encoding);
        $this->document->formatOutput = true;
        $this->document->preserveWhiteSpace = false;

        if ($filePath !== null) {
            $this->setFile($filePath);
        }
    }

    /**
     * Встановлення шляху до файлу
     *
     * @param string $filePath Шлях до XML файлу
     * @return self
     */
    public function setFile(string $filePath): self
    {
        $this->filePath = $filePath;
        $this->hasData = false;

        if (file_exists($filePath)) {
            $this->load();
        }

        return $this;
    }

    /**
     * Завантаження XML файлу
     *
     * @param int $options Прапорці для завантаження
     * @return self
     * @throws Exception Якщо файл не існує або не може бути прочитаний
     */
    public function load(int $options = 0): self
    {
        if (empty($this->filePath)) {
            throw new Exception('Шлях до файлу не встановлено');
        }

        if (! file_exists($this->filePath)) {
            throw new Exception("XML файл не існує: {$this->filePath}");
        }

        if (! is_readable($this->filePath)) {
            throw new Exception("XML файл недоступний для читання: {$this->filePath}");
        }

        $content = @file_get_contents($this->filePath);

        if ($content === false) {
            throw new Exception("Не вдалося прочитати XML файл: {$this->filePath}");
        }

        if (! @$this->document->loadXML($content, $options)) {
            $errors = libxml_get_errors();
            $errorMsg = ! empty($errors) ? $errors[0]->message : 'Невідома помилка парсингу XML';
            libxml_clear_errors();

            throw new Exception("Помилка парсингу XML файлу '{$this->filePath}': {$errorMsg}");
        }

        $this->hasData = true;

        return $this;
    }

    /**
     * Завантаження XML з рядка
     *
     * @param string $xmlString XML рядок
     * @param int $options Прапорці для завантаження
     * @return self
     * @throws Exception Якщо не вдалося завантажити
     */
    public function loadString(string $xmlString, int $options = 0): self
    {
        if (! @$this->document->loadXML($xmlString, $options)) {
            $errors = libxml_get_errors();
            $errorMsg = ! empty($errors) ? $errors[0]->message : 'Невідома помилка парсингу XML';
            libxml_clear_errors();

            throw new Exception("Помилка парсингу XML рядка: {$errorMsg}");
        }

        $this->hasData = true;

        return $this;
    }

    /**
     * Збереження XML у файл
     *
     * @param string|null $filePath Шлях до файлу (якщо null, використовується поточний)
     * @return bool
     * @throws Exception Якщо не вдалося зберегти
     */
    public function save(?string $filePath = null): bool
    {
        $targetPath = $filePath ?? $this->filePath;

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

        $xml = $this->document->saveXML();

        if ($xml === false) {
            throw new Exception('Не вдалося сформувати XML');
        }

        $result = @file_put_contents($targetPath, $xml, LOCK_EX);

        if ($result === false) {
            throw new Exception("Не вдалося зберегти XML файл: {$targetPath}");
        }

        @chmod($targetPath, 0644);

        if ($filePath === null) {
            $this->filePath = $targetPath;
        }

        return true;
    }

    /**
     * Отримання XML у вигляді рядка
     *
     * @return string
     */
    public function toString(): string
    {
        $xml = $this->document->saveXML();

        return $xml !== false ? $xml : '';
    }

    /**
     * Отримання DOMDocument
     *
     * @return DOMDocument
     */
    public function getDocument(): DOMDocument
    {
        return $this->document;
    }

    /**
     * Отримання кореневого елемента
     *
     * @return DOMElement|null
     */
    public function getRoot(): ?DOMElement
    {
        return $this->document->documentElement;
    }

    /**
     * Отримання значення елемента за XPath
     *
     * @param string $xpath XPath вираз
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function getValue(string $xpath, $default = null)
    {
        if (! $this->hasData) {
            return $default;
        }

        $xpathObj = new DOMXPath($this->document);
        $nodes = $xpathObj->query($xpath);

        if ($nodes === false || $nodes->length === 0) {
            return $default;
        }

        $node = $nodes->item(0);

        if ($node instanceof DOMElement) {
            return $node->nodeValue;
        }

        return $default;
    }

    /**
     * Встановлення значення елемента за XPath
     *
     * @param string $xpath XPath вираз
     * @param string $value Значення
     * @return self
     * @throws Exception Якщо елемент не знайдено
     */
    public function setValue(string $xpath, string $value): self
    {
        if (! $this->hasData) {
            $this->document->appendChild($this->document->createElement('root'));
            $this->hasData = true;
        }

        $xpathObj = new DOMXPath($this->document);
        $nodes = $xpathObj->query($xpath);

        if ($nodes === false || $nodes->length === 0) {
            throw new Exception("Елемент не знайдено за XPath: {$xpath}");
        }

        $nodes->item(0)->nodeValue = $value;

        return $this;
    }

    /**
     * Додавання елемента
     *
     * @param string $tagName Ім'я тега
     * @param string $content Вміст
     * @param string|null $parentXPath XPath батьківського елемента (null = корінь)
     * @return DOMElement
     */
    public function addElement(string $tagName, string $content = '', ?string $parentXPath = null): DOMElement
    {
        $element = $this->document->createElement($tagName, $content);

        if ($parentXPath === null) {
            $root = $this->document->documentElement;
            if ($root === null) {
                $root = $this->document->createElement('root');
                $this->document->appendChild($root);
                $this->hasData = true;
            }
            $root->appendChild($element);
        } else {
            $xpathObj = new DOMXPath($this->document);
            $nodes = $xpathObj->query($parentXPath);

            if ($nodes === false || $nodes->length === 0) {
                throw new Exception("Батьківський елемент не знайдено за XPath: {$parentXPath}");
            }

            $nodes->item(0)->appendChild($element);
        }

        return $element;
    }

    /**
     * Видалення елемента за XPath
     *
     * @param string $xpath XPath вираз
     * @return bool
     */
    public function removeElement(string $xpath): bool
    {
        if (! $this->hasData) {
            return false;
        }

        $xpathObj = new DOMXPath($this->document);
        $nodes = $xpathObj->query($xpath);

        if ($nodes === false || $nodes->length === 0) {
            return false;
        }

        $node = $nodes->item(0);
        $parent = $node->parentNode;

        if ($parent !== null) {
            $parent->removeChild($node);

            return true;
        }

        return false;
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
     * Повертає DOMDocument
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->hasData ? $this->document : null;
    }

    /**
     * Встановлення даних (з StructuredFileInterface)
     * Приймає DOMDocument або XML рядок
     *
     * @param mixed $data Дані (DOMDocument або XML рядок)
     * @return self
     */
    public function setData($data): self
    {
        if ($data instanceof DOMDocument) {
            $this->document = $data;
            $this->hasData = true;
        } elseif (is_string($data)) {
            $this->loadString($data);
        } else {
            throw new Exception('Невідомий тип даних. Очікується DOMDocument або XML рядок');
        }

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
     * Отримання значення за ключем (з StructuredFileInterface)
     * Для XML використовується XPath
     *
     * @param string $key XPath вираз
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->getValue($key, $default);
    }

    /**
     * Встановлення значення за ключем (з StructuredFileInterface)
     * Для XML використовується XPath
     *
     * @param string $key XPath вираз
     * @param mixed $value Значення
     * @return self
     */
    public function set(string $key, $value): self
    {
        return $this->setValue($key, (string)$value);
    }

    /**
     * Перевірка наявності ключа (з StructuredFileInterface)
     * Для XML використовується XPath
     *
     * @param string $key XPath вираз
     * @return bool
     */
    public function has(string $key): bool
    {
        if (! $this->hasData) {
            return false;
        }

        $xpathObj = new DOMXPath($this->document);
        $nodes = $xpathObj->query($key);

        return $nodes !== false && $nodes->length > 0;
    }

    /**
     * Видалення значення за ключем (з StructuredFileInterface)
     * Для XML використовується XPath
     *
     * @param string $key XPath вираз
     * @return self
     */
    public function remove(string $key): self
    {
        $this->removeElement($key);

        return $this;
    }

    /**
     * Очищення всіх даних (з StructuredFileInterface)
     *
     * @return self
     */
    public function clear(): self
    {
        $this->document = new DOMDocument('1.0', 'UTF-8');
        $this->document->formatOutput = true;
        $this->document->preserveWhiteSpace = false;
        $this->hasData = false;

        return $this;
    }

    /**
     * Валідація XML за схемою
     *
     * @param string $schemaPath Шлях до XSD схеми
     * @return bool
     */
    public function validate(string $schemaPath): bool
    {
        if (! $this->hasData) {
            return false;
        }

        if (! file_exists($schemaPath)) {
            throw new Exception("Схема не існує: {$schemaPath}");
        }

        return @$this->document->schemaValidate($schemaPath);
    }

    /**
     * Статичний метод: Читання XML файлу
     *
     * @param string $filePath Шлях до файлу
     * @param int $options Прапорці для завантаження
     * @return Xml
     */
    public static function readFile(string $filePath, int $options = 0): self
    {
        $xml = new self($filePath);
        $xml->load($options);

        return $xml;
    }

    /**
     * Статичний метод: Парсинг XML рядка
     *
     * @param string $xmlString XML рядок
     * @param int $options Прапорці для завантаження
     * @return Xml
     */
    public static function parse(string $xmlString, int $options = 0): self
    {
        $xml = new self();
        $xml->loadString($xmlString, $options);

        return $xml;
    }

    /**
     * Статичний метод: Створення XML з масиву
     *
     * @param array $data Дані
     * @param string $rootElement Ім'я кореневого елемента
     * @return Xml
     */
    /**
     * @param array<string, mixed> $data
     * @param string $rootElement
     * @return self
     */
    public static function fromArray(array $data, string $rootElement = 'root'): self
    {
        $xml = new self();
        $xml->document->appendChild($xml->arrayToXml($data, $rootElement));
        $xml->hasData = true;

        return $xml;
    }

    /**
     * Перетворення масиву в XML елементи
     *
     * @param array $data Дані
     * @param string $rootName Ім'я кореневого елемента
     * @return DOMElement
     */
    /**
     * @param array<string, mixed> $data
     * @param string $rootName
     * @return DOMElement
     */
    private function arrayToXml(array $data, string $rootName): DOMElement
    {
        $root = $this->document->createElement($rootName);

        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item';
            }

            if (is_array($value)) {
                $child = $this->arrayToXml($value, $key);
                $root->appendChild($child);
            } else {
                $element = $this->document->createElement($key, htmlspecialchars((string)$value, ENT_XML1, 'UTF-8'));
                $root->appendChild($element);
            }
        }

        return $root;
    }
}
