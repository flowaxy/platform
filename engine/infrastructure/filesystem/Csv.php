<?php

/**
 * Клас для роботи з CSV файлами
 * Читання, запис та маніпуляції з CSV даними
 *
 * @package Engine\Classes\Files
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class Csv
{
    private string $filePath;
    private array $data = [];
    private bool $hasData = false;
    private string $delimiter = ',';
    private string $enclosure = '"';
    private string $escape = '\\';

    /**
     * Конструктор
     *
     * @param string|null $filePath Шлях до CSV файлу
     * @param string $delimiter Роздільник полів (за замовчуванням ',')
     * @param string $enclosure Символ обрамлення полів (за замовчуванням '"')
     * @param string $escape Символ екранування (за замовчуванням '\\')
     */
    public function __construct(?string $filePath = null, string $delimiter = ',', string $enclosure = '"', string $escape = '\\')
    {
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escape = $escape;

        if ($filePath !== null) {
            $this->setFile($filePath);
        }
    }

    /**
     * Встановлення шляху до файлу
     *
     * @param string $filePath Шлях до CSV файлу
     * @return self
     */
    public function setFile(string $filePath): self
    {
        $this->filePath = $filePath;
        $this->hasData = false;
        $this->data = [];

        if (file_exists($filePath)) {
            $this->load();
        }

        return $this;
    }

    /**
     * Завантаження CSV файлу
     *
     * @param bool $hasHeader Чи має файл заголовки в першому рядку
     * @return self
     * @throws Exception Якщо файл не існує або не може бути прочитаний
     */
    public function load(bool $hasHeader = true): self
    {
        if (empty($this->filePath)) {
            throw new Exception('Шлях до файлу не встановлено');
        }

        if (! file_exists($this->filePath)) {
            throw new Exception("CSV файл не існує: {$this->filePath}");
        }

        if (! is_readable($this->filePath)) {
            throw new Exception("CSV файл недоступний для читання: {$this->filePath}");
        }

        $this->data = [];
        $header = null;

        if (($handle = @fopen($this->filePath, 'r')) === false) {
            throw new Exception("Не вдалося відкрити CSV файл: {$this->filePath}");
        }

        $lineNumber = 0;

        while (($row = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== false) {
            if ($lineNumber === 0 && $hasHeader) {
                $header = $row;
            } else {
                if ($hasHeader && $header !== null) {
                    // Використовуємо заголовки як ключі
                    $rowData = [];
                    foreach ($header as $index => $key) {
                        $rowData[$key] = $row[$index] ?? '';
                    }
                    $this->data[] = $rowData;
                } else {
                    $this->data[] = $row;
                }
            }
            $lineNumber++;
        }

        fclose($handle);
        $this->hasData = true;

        return $this;
    }

    /**
     * Збереження даних у CSV файл
     *
     * @param string|null $filePath Шлях до файлу (якщо null, використовується поточний)
     * @param bool $writeHeader Записувати чи заголовки
     * @return bool
     * @throws Exception Якщо не вдалося зберегти
     */
    public function save(?string $filePath = null, bool $writeHeader = true): bool
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

        if (($handle = @fopen($targetPath, 'w')) === false) {
            throw new Exception("Не вдалося відкрити CSV файл для запису: {$targetPath}");
        }

        if (! empty($this->data)) {
            // Визначаємо заголовки
            $headers = null;
            if ($writeHeader && is_array($this->data[0]) && ! is_numeric(key($this->data[0]))) {
                $headers = array_keys($this->data[0]);
                fputcsv($handle, $headers, $this->delimiter, $this->enclosure, $this->escape);
            }

            // Записуємо дані
            foreach ($this->data as $row) {
                if ($headers !== null) {
                    // Використовуємо порядок заголовків
                    $orderedRow = [];
                    foreach ($headers as $key) {
                        $orderedRow[] = $row[$key] ?? '';
                    }
                    fputcsv($handle, $orderedRow, $this->delimiter, $this->enclosure, $this->escape);
                } else {
                    fputcsv($handle, $row, $this->delimiter, $this->enclosure, $this->escape);
                }
            }
        }

        fclose($handle);
        @chmod($targetPath, 0644);

        if ($filePath === null) {
            $this->filePath = $targetPath;
        }

        return true;
    }

    /**
     * Отримання всіх даних
     *
     * @return array
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Отримання рядка за індексом
     *
     * @param int $index Індекс рядка
     * @param array $default Значення за замовчуванням
     * @return array
     */
    public function getRow(int $index, array $default = []): array
    {
        return $this->data[$index] ?? $default;
    }

    /**
     * Додавання рядка
     *
     * @param array $row Дані рядка
     * @return self
     */
    public function addRow(array $row): self
    {
        $this->data[] = $row;
        $this->hasData = true;

        return $this;
    }

    /**
     * Оновлення рядка
     *
     * @param int $index Індекс рядка
     * @param array $row Нові дані
     * @return self
     */
    public function updateRow(int $index, array $row): self
    {
        if (isset($this->data[$index])) {
            $this->data[$index] = $row;
        }

        return $this;
    }

    /**
     * Видалення рядка
     *
     * @param int $index Індекс рядка
     * @return self
     */
    public function removeRow(int $index): self
    {
        if (isset($this->data[$index])) {
            unset($this->data[$index]);
            $this->data = array_values($this->data); // Переіндексація
        }

        return $this;
    }

    /**
     * Отримання кількості рядків
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Очищення даних
     *
     * @return self
     */
    public function clear(): self
    {
        $this->data = [];
        $this->hasData = false;

        return $this;
    }

    /**
     * Встановлення даних
     *
     * @param array $data Дані
     * @return self
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        $this->hasData = true;

        return $this;
    }

    /**
     * Встановлення роздільника
     *
     * @param string $delimiter Роздільник
     * @return self
     */
    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;

        return $this;
    }

    /**
     * Встановлення символу обрамлення
     *
     * @param string $enclosure Символ обрамлення
     * @return self
     */
    public function setEnclosure(string $enclosure): self
    {
        $this->enclosure = $enclosure;

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
     * Статичний метод: Читання CSV файлу
     *
     * @param string $filePath Шлях до файлу
     * @param string $delimiter Роздільник
     * @param string $enclosure Символ обрамлення
     * @param bool $hasHeader Чи має файл заголовки
     * @return array
     */
    public static function read(string $filePath, string $delimiter = ',', string $enclosure = '"', bool $hasHeader = true): array
    {
        $csv = new self($filePath, $delimiter, $enclosure);
        $csv->load($hasHeader);

        return $csv->all();
    }

    /**
     * Статичний метод: Запис CSV файлу
     *
     * @param string $filePath Шлях до файлу
     * @param array $data Дані
     * @param string $delimiter Роздільник
     * @param string $enclosure Символ обрамлення
     * @param bool $writeHeader Записувати чи заголовки
     * @return bool
     */
    public static function write(string $filePath, array $data, string $delimiter = ',', string $enclosure = '"', bool $writeHeader = true): bool
    {
        $csv = new self($filePath, $delimiter, $enclosure);
        $csv->setData($data);

        return $csv->save(null, $writeHeader);
    }

    /**
     * Статичний метод: Конвертація масиву в CSV рядок
     *
     * @param array $data Дані
     * @param string $delimiter Роздільник
     * @param string $enclosure Символ обрамлення
     * @return string
     */
    public static function arrayToString(array $data, string $delimiter = ',', string $enclosure = '"'): string
    {
        $output = fopen('php://temp', 'r+');

        foreach ($data as $row) {
            fputcsv($output, $row, $delimiter, $enclosure);
        }

        rewind($output);
        $string = stream_get_contents($output);
        fclose($output);

        return $string;
    }
}
