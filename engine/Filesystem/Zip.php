<?php

/**
 * Клас для роботи з ZIP архівами
 * Створення, витягування та маніпуляції з ZIP файлами
 *
 * @package Engine\Classes
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class Zip
{
    private ?ZipArchive $zip = null;
    private string $filePath;
    private bool $isOpen = false;

    /**
     * Конструктор
     *
     * @param string|null $filePath Шлях до ZIP файлу
     * @param int $flags Прапорці для відкриття архіву
     */
    public function __construct(?string $filePath = null, int $flags = ZipArchive::CREATE)
    {
        if (! extension_loaded('zip')) {
            throw new Exception('Розширення ZIP не встановлено');
        }

        if ($filePath !== null) {
            $this->open($filePath, $flags);
        }
    }

    /**
     * Деструктор - закриття архіву при знищенні об'єкта
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Відкриття ZIP архіву
     *
     * @param string $filePath Шлях до ZIP файлу
     * @param int $flags Прапорці для відкриття (ZipArchive::CREATE, ZipArchive::OVERWRITE тощо)
     * @return self
     * @throws Exception Якщо не вдалося відкрити архів
     */
    public function open(string $filePath, int $flags = ZipArchive::CREATE): self
    {
        $this->close();

        $this->zip = new ZipArchive();
        $this->filePath = $filePath;

        // Створюємо директорію, якщо її немає
        $dir = dirname($filePath);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0755, true)) {
                throw new Exception("Не вдалося створити директорію: {$dir}");
            }
        }

        $result = $this->zip->open($filePath, $flags);

        if ($result !== true) {
            $error = $this->getZipError($result);

            throw new Exception("Не вдалося відкрити ZIP архів '{$filePath}': {$error}");
        }

        $this->isOpen = true;

        return $this;
    }

    /**
     * Закриття ZIP архіву
     *
     * @return bool
     */
    public function close(): bool
    {
        if ($this->isOpen && $this->zip !== null) {
            $result = $this->zip->close();
            $this->isOpen = false;

            if ($result && file_exists($this->filePath)) {
                @chmod($this->filePath, 0644);
            }

            return $result;
        }

        return true;
    }

    /**
     * Додавання файлу в архів
     *
     * @param string $filePath Шлях до файлу для додавання
     * @param string|null $localName Ім'я файлу в архіві (якщо null, використовується ім'я файлу)
     * @return self
     * @throws Exception Якщо файл не існує або не вдалося додати
     */
    public function addFile(string $filePath, ?string $localName = null): self
    {
        $this->ensureOpen();

        if (! file_exists($filePath)) {
            throw new Exception("Файл не існує: {$filePath}");
        }

        if (! is_readable($filePath)) {
            throw new Exception("Файл недоступний для читання: {$filePath}");
        }

        $name = $localName ?? basename($filePath);

        if (! $this->zip->addFile($filePath, $name)) {
            throw new Exception("Не вдалося додати файл '{$filePath}' в архів");
        }

        return $this;
    }

    /**
     * Додавання вмісту рядка в архів
     *
     * @param string $localName Ім'я файлу в архіві
     * @param string $contents Вміст файлу
     * @return self
     * @throws Exception Якщо не вдалося додати вміст
     */
    public function addFromString(string $localName, string $contents): self
    {
        $this->ensureOpen();

        if (! $this->zip->addFromString($localName, $contents)) {
            throw new Exception("Не вдалося додати вміст в архів з ім'ям '{$localName}'");
        }

        return $this;
    }

    /**
     * Додавання директорії в архів
     *
     * @param string $dirPath Шлях до директорії
     * @param string $localPath Шлях в архіві
     * @param array $exclude Паттерни файлів для виключення
     * @return self
     * @throws Exception Якщо директорія не існує
     */
    public function addDirectory(string $dirPath, string $localPath = '', array $exclude = []): self
    {
        $this->ensureOpen();

        if (! is_dir($dirPath)) {
            throw new Exception("Директорія не існує: {$dirPath}");
        }

        $dirPath = rtrim($dirPath, '/\\') . DIRECTORY_SEPARATOR;
        $localPath = trim($localPath, '/\\');

        if (! empty($localPath)) {
            $localPath .= '/';
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $filePath = $file->getRealPath();
            $relativePath = str_replace($dirPath, '', $filePath);
            $relativePath = str_replace('\\', '/', $relativePath);

            // Перевірка на виключення
            $shouldExclude = false;
            foreach ($exclude as $pattern) {
                if (fnmatch($pattern, $relativePath) || fnmatch($pattern, basename($relativePath))) {
                    $shouldExclude = true;

                    break;
                }
            }

            if ($shouldExclude) {
                continue;
            }

            $archivePath = $localPath . $relativePath;

            if (! $this->zip->addFile($filePath, $archivePath)) {
                throw new Exception("Не вдалося додати файл '{$filePath}' в архів");
            }
        }

        return $this;
    }

    /**
     * Витягування всіх файлів з архіву
     *
     * @param string $destinationPath Шлях призначення
     * @param array|null $entries Список файлів для витягування (null = всі)
     * @return bool
     * @throws Exception Якщо не вдалося витягти
     */
    public function extractTo(string $destinationPath, ?array $entries = null): bool
    {
        $this->ensureOpen();

        if (! is_dir($destinationPath)) {
            if (! @mkdir($destinationPath, 0755, true)) {
                throw new Exception("Не вдалося створити директорію: {$destinationPath}");
            }
        }

        $result = $this->zip->extractTo($destinationPath, $entries);

        if (! $result) {
            throw new Exception("Не вдалося витягти файли з архіву в '{$destinationPath}'");
        }

        return true;
    }

    /**
     * Витягування конкретного файлу з архіву
     *
     * @param string $entryName Ім'я файлу в архіві
     * @param string $destinationPath Шлях призначення
     * @return bool
     * @throws Exception Якщо файл не знайдено або не вдалося витягти
     */
    public function extractFile(string $entryName, string $destinationPath): bool
    {
        $this->ensureOpen();

        if (! $this->hasEntry($entryName)) {
            throw new Exception("Файл '{$entryName}' не знайдено в архіві");
        }

        // Створюємо директорію, якщо її немає
        $dir = dirname($destinationPath);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0755, true)) {
                throw new Exception("Не вдалося створити директорію: {$dir}");
            }
        }

        $contents = $this->getEntryContents($entryName);

        if ($contents === false) {
            throw new Exception("Не вдалося прочитати вміст файлу '{$entryName}' з архіву");
        }

        $result = @file_put_contents($destinationPath, $contents, LOCK_EX);

        if ($result === false) {
            throw new Exception("Не вдалося записати файл: {$destinationPath}");
        }

        @chmod($destinationPath, 0644);

        return true;
    }

    /**
     * Отримання вмісту файлу з архіву
     *
     * @param string $entryName Ім'я файлу в архіві
     * @return string|false
     */
    public function getEntryContents(string $entryName)
    {
        $this->ensureOpen();

        return $this->zip->getFromName($entryName);
    }

    /**
     * Видалення файлу з архіву
     *
     * @param string $entryName Ім'я файлу в архіві
     * @return self
     * @throws Exception Якщо не вдалося видалити
     */
    public function deleteEntry(string $entryName): self
    {
        $this->ensureOpen();

        if (! $this->zip->deleteName($entryName)) {
            throw new Exception("Не вдалося видалити файл '{$entryName}' з архіву");
        }

        return $this;
    }

    /**
     * Перейменування файлу в архіві
     *
     * @param string $oldName Старе ім'я
     * @param string $newName Нове ім'я
     * @return self
     * @throws Exception Якщо не вдалося перейменувати
     */
    public function renameEntry(string $oldName, string $newName): self
    {
        $this->ensureOpen();

        if (! $this->zip->renameName($oldName, $newName)) {
            throw new Exception("Не вдалося перейменувати файл '{$oldName}' в '{$newName}'");
        }

        return $this;
    }

    /**
     * Отримання списку всіх файлів в архіві
     *
     * @return array
     */
    public function getEntries(): array
    {
        $this->ensureOpen();

        $entries = [];

        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $entryName = $this->zip->getNameIndex($i);
            if ($entryName !== false) {
                $entries[] = $entryName;
            }
        }

        return $entries;
    }

    /**
     * Отримання інформації про файл в архіві
     *
     * @param string $entryName Ім'я файлу в архіві
     * @return array|false
     */
    public function getEntryInfo(string $entryName)
    {
        $this->ensureOpen();

        $stat = $this->zip->statName($entryName);

        if ($stat === false) {
            return false;
        }

        return [
            'name' => $stat['name'],
            'index' => $stat['index'],
            'crc' => $stat['crc'],
            'size' => $stat['size'],
            'mtime' => $stat['mtime'],
            'comp_size' => $stat['comp_size'],
            'comp_method' => $stat['comp_method'],
        ];
    }

    /**
     * Перевірка наявності файлу в архіві
     *
     * @param string $entryName Ім'я файлу в архіві
     * @return bool
     */
    public function hasEntry(string $entryName): bool
    {
        $this->ensureOpen();

        return $this->zip->locateName($entryName) !== false;
    }

    /**
     * Отримання кількості файлів в архіві
     *
     * @return int
     */
    public function getEntryCount(): int
    {
        $this->ensureOpen();

        return $this->zip->numFiles;
    }

    /**
     * Отримання коментаря архіву
     *
     * @return string|false
     */
    public function getComment()
    {
        $this->ensureOpen();

        return $this->zip->getArchiveComment();
    }

    /**
     * Встановлення коментаря архіву
     *
     * @param string $comment Коментар
     * @return self
     * @throws Exception Якщо не вдалося встановити коментар
     */
    public function setComment(string $comment): self
    {
        $this->ensureOpen();

        if (! $this->zip->setArchiveComment($comment)) {
            throw new Exception('Не вдалося встановити коментар архіву');
        }

        return $this;
    }

    /**
     * Перевірка, чи відкрито архів
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    /**
     * Отримання шляху до файлу архіву
     *
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Створення ZIP архіву з директорії
     *
     * @param string $sourceDir Вихідна директорія
     * @param string $zipPath Шлях до створюваного архіву
     * @param array $exclude Паттерни файлів для виключення
     * @return self
     */
    public static function createFromDirectory(string $sourceDir, string $zipPath, array $exclude = []): self
    {
        $zip = new self($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addDirectory($sourceDir, '', $exclude);

        return $zip;
    }

    /**
     * Перевірка, чи відкрито архів (внутрішній метод)
     *
     * @return void
     * @throws Exception Якщо архів не відкрито
     */
    private function ensureOpen(): void
    {
        if (! $this->isOpen || $this->zip === null) {
            throw new Exception('ZIP архів не відкрито');
        }
    }

    /**
     * Отримання текстового опису помилки ZIP
     *
     * @param int $code Код помилки
     * @return string
     */
    private function getZipError(int $code): string
    {
        $errors = [
            ZipArchive::ER_OK => 'OK',
            ZipArchive::ER_MULTIDISK => 'Multi-disk zip archives not supported',
            ZipArchive::ER_RENAME => 'Renaming temporary file failed',
            ZipArchive::ER_CLOSE => 'Closing zip archive failed',
            ZipArchive::ER_SEEK => 'Seek error',
            ZipArchive::ER_READ => 'Read error',
            ZipArchive::ER_WRITE => 'Write error',
            ZipArchive::ER_CRC => 'CRC error',
            ZipArchive::ER_ZIPCLOSED => 'Containing zip archive was closed',
            ZipArchive::ER_NOENT => 'No such file',
            ZipArchive::ER_EXISTS => 'File already exists',
            ZipArchive::ER_OPEN => 'Can\'t open file',
            ZipArchive::ER_TMPOPEN => 'Failure to create temporary file',
            ZipArchive::ER_ZLIB => 'Zlib error',
            ZipArchive::ER_MEMORY => 'Memory allocation failure',
            ZipArchive::ER_CHANGED => 'Entry has been changed',
            ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported',
            ZipArchive::ER_EOF => 'Premature EOF',
            ZipArchive::ER_INVAL => 'Invalid argument',
            ZipArchive::ER_NOZIP => 'Not a zip archive',
            ZipArchive::ER_INTERNAL => 'Internal error',
            ZipArchive::ER_INCONS => 'Zip archive inconsistent',
            ZipArchive::ER_REMOVE => 'Can\'t remove file',
            ZipArchive::ER_DELETED => 'Entry has been deleted',
        ];

        return $errors[$code] ?? "Unknown error (code: {$code})";
    }

    /**
     * Статичний метод: Розпакування ZIP архіву
     *
     * @param string $zipPath Шлях до ZIP архіву
     * @param string $destinationPath Шлях для розпакування
     * @param array|null $entries Список файлів для витягування (null = всі)
     * @return bool Повертає true при успіху, false при помилці
     */
    public static function unpack(string $zipPath, string $destinationPath, ?array $entries = null): bool
    {
        if (! extension_loaded('zip')) {
            logger()->logError('Zip::unpack error: ZIP розширення не встановлено');
            return false;
        }

        if (! file_exists($zipPath)) {
            logger()->logError("Zip::unpack error: ZIP файл не існує: {$zipPath}", ['zip_path' => $zipPath]);
            return false;
        }

        try {
            $zip = new self($zipPath, ZipArchive::RDONLY);
            $result = $zip->extractTo($destinationPath, $entries);
            $zip->close();

            return $result;
        } catch (Exception $e) {
            logger()->logError('Zip::unpack error: ' . $e->getMessage(), ['exception' => $e, 'zip_path' => $zipPath, 'destination' => $destinationPath]);
            return false;
        }
    }

    /**
     * Статичний метод: Перейменування файлу в ZIP архіві
     *
     * @param string $zipPath Шлях до ZIP архіву
     * @param string $oldName Старе ім'я файлу в архіві
     * @param string $newName Нове ім'я файлу в архіві
     * @return bool Повертає true при успіху, false при помилці
     */
    public static function rename_file(string $zipPath, string $oldName, string $newName): bool
    {
        if (! extension_loaded('zip')) {
            logger()->logError('Zip::rename_file error: ZIP розширення не встановлено');
            return false;
        }

        if (! file_exists($zipPath)) {
            logger()->logError("Zip::rename_file error: ZIP файл не існує: {$zipPath}", ['zip_path' => $zipPath]);
            return false;
        }

        try {
            $zip = new self($zipPath);
            $zip->renameEntry($oldName, $newName);
            $result = $zip->close();

            return $result;
        } catch (Exception $e) {
            logger()->logError('Zip::rename_file error: ' . $e->getMessage(), ['exception' => $e, 'zip_path' => $zipPath, 'old_name' => $oldName, 'new_name' => $newName]);
            return false;
        }
    }

    /**
     * Статичний метод: Упаковка директорії в ZIP архів
     *
     * @param string $sourceDir Вихідна директорія
     * @param string $zipPath Шлях до створюваного архіву
     * @param array $exclude Паттерни файлів для виключення
     * @return bool Повертає true при успіху, false при помилці
     */
    public static function pack(string $sourceDir, string $zipPath, array $exclude = []): bool
    {
        if (! extension_loaded('zip')) {
            logger()->logError('Zip::pack error: ZIP розширення не встановлено');
            return false;
        }

        if (! is_dir($sourceDir)) {
            logger()->logError("Zip::pack error: Директорія не існує: {$sourceDir}", ['source_dir' => $sourceDir]);
            return false;
        }

        try {
            $zip = self::createFromDirectory($sourceDir, $zipPath, $exclude);
            $result = $zip->close();

            return $result;
        } catch (Exception $e) {
            logger()->logError('Zip::pack error: ' . $e->getMessage(), ['exception' => $e, 'source_dir' => $sourceDir, 'zip_path' => $zipPath]);
            return false;
        }
    }

    /**
     * Статичний метод: Отримання списку файлів в ZIP архіві
     *
     * @param string $zipPath Шлях до ZIP архіву
     * @return array|false Масив імен файлів або false при помилці
     */
    public static function listFiles(string $zipPath)
    {
        if (! extension_loaded('zip')) {
            logger()->logError('Zip::listFiles error: ZIP розширення не встановлено');
            return false;
        }

        if (! file_exists($zipPath)) {
            logger()->logError("Zip::listFiles error: ZIP файл не існує: {$zipPath}", ['zip_path' => $zipPath]);
            return false;
        }

        try {
            $zip = new self($zipPath, ZipArchive::RDONLY);
            $files = $zip->getEntries();
            $zip->close();

            return $files;
        } catch (Exception $e) {
            logger()->logError('Zip::listFiles error: ' . $e->getMessage(), ['exception' => $e, 'zip_path' => $zipPath]);
            return false;
        }
    }
}
