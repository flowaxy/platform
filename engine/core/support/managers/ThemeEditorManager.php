<?php

/**
 * Менеджер редактора теми
 *
 * @package Engine\Managers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

// File, Zip, Upload, Ini, Cache, Logger, Validator уже зареєстровані в автозавантажувачі (app.php)
// Підключаємо тільки Directory, який використовує namespace
if (! class_exists('Engine\Classes\Files\Directory', false)) {
    // Використовуємо ENGINE_DIR константу або обчислюємо шлях від __DIR__
    $engineDir = defined('ENGINE_DIR') ? ENGINE_DIR : dirname(__DIR__, 3);
    $directoryPath = $engineDir . '/infrastructure/filesystem/Directory.php';
    $realPath = realpath($directoryPath);
    if ($realPath !== false && file_exists($realPath)) {
        require_once $realPath;
    } else {
        // Fallback: пробуємо альтернативний шлях через __DIR__
        $fallbackPath = __DIR__ . '/../../../infrastructure/filesystem/Directory.php';
        $fallbackRealPath = realpath($fallbackPath);
        if ($fallbackRealPath !== false && file_exists($fallbackRealPath)) {
            require_once $fallbackRealPath;
        }
    }
}

use Engine\Classes\Files\Directory;

class ThemeEditorManager
{
    private static ?ThemeEditorManager $instance = null;
    private ?PDO $db = null;

    private function __construct()
    {
        try {
            $this->db = DatabaseHelper::getConnection();
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('ThemeEditorManager constructor error', ['error' => $e->getMessage()]);
            } else {
                error_log('ThemeEditorManager constructor error: ' . $e->getMessage());
            }
            $this->db = null;
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Отримання списку файлів теми
     */
    /**
     * @param string $themePath
     * @param array<int, string>|null $allowedExtensions
     * @return array<int, array<string, mixed>>
     */
    public function getThemeFiles(string $themePath, array $allowedExtensions = null): array
    {
        // Якщо розширення не вказано (null), показуємо ВСІ файли, включаючи без розширення
        // Якщо передано порожній масив [], також показуємо всі файли
        // Якщо передано масив з розширеннями, показуємо тільки файли з цими розширеннями + файли без розширення
        $showAllFiles = ($allowedExtensions === null || empty($allowedExtensions));
        $files = [];

        if (! is_dir($themePath) || ! is_readable($themePath)) {
            return $files;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($themePath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) { // $file тут - це SplFileInfo об'єкт з RecursiveDirectoryIterator
                    $extension = strtolower($file->getExtension());
                    $fileName = $file->getFilename();

                    // Показуємо всі файли, якщо розширення не вказано або порожньо
                    // Також показуємо файли без розширення
                    $shouldInclude = false;
                    if ($showAllFiles) {
                        // Показуємо всі файли
                        $shouldInclude = true;
                    } elseif (empty($extension)) {
                        // Файл без розширення - завжди показуємо
                        $shouldInclude = true;
                    } else {
                        // Перевіряємо, чи є розширення в списку дозволених
                        $shouldInclude = in_array($extension, $allowedExtensions, true);
                    }

                    if ($shouldInclude) {
                        $relativePath = str_replace($themePath, '', $file->getPathname());
                        $relativePath = str_replace('\\', '/', $relativePath);
                        $relativePath = ltrim($relativePath, '/');

                        $files[] = [
                            'path' => $relativePath,
                            'fullPath' => $file->getPathname(),
                            'name' => $fileName,
                            'extension' => $extension ?: '',
                            'size' => $file->getSize(),
                            'modified' => $file->getMTime(),
                            'directory' => dirname($relativePath) ?: '.',
                        ];
                    }
                }
            }

            // Сортуємо файли: спочатку основні, потім за ім'ям
            usort($files, function ($a, $b) {
                $priority = ['index.php', 'style.css', 'script.js', 'theme.json', 'customizer.php', 'functions.php'];
                $aPriority = array_search($a['name'], $priority, true);
                $bPriority = array_search($b['name'], $priority, true);

                if ($aPriority !== false && $bPriority !== false) {
                    return $aPriority <=> $bPriority;
                }
                if ($aPriority !== false) {
                    return -1;
                }
                if ($bPriority !== false) {
                    return 1;
                }

                return strcmp($a['path'], $b['path']);
            });
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('Error getting theme files', ['error' => $e->getMessage(), 'themePath' => $themePath]);
            } else {
                error_log('Error getting theme files: ' . $e->getMessage());
            }
        }

        return $files;
    }

    /**
     * Отримання вмісту файлу
     */
    public function getFileContent(string $filePath): ?string
    {
        try {
            $file = new File($filePath);

            if (! $file->exists() || ! $file->isReadable()) {
                return null;
            }

            return $file->read();
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('ThemeEditorManager: Error reading file content', ['error' => $e->getMessage(), 'filePath' => $filePath]);
            } else {
                error_log('ThemeEditorManager: Error reading file content: ' . $e->getMessage());
            }

            return null;
        }
    }

    /**
     * Збереження файлу
     */
    /**
     * @param string $filePath
     * @param string $content
     * @param string $themePath
     * @return array<string, mixed>
     */
    public function saveFile(string $filePath, string $content, string $themePath): array
    {
        try {
            // Перевіряємо безпеку шляху
            $realThemePath = realpath($themePath);
            $realFilePath = realpath($filePath);

            // Якщо файл не існує, створюємо його шлях
            if ($realFilePath === false) {
                $realFilePath = realpath(dirname($filePath));
                if ($realFilePath === false) {
                    return ['success' => false, 'error' => 'Невірний шлях до файлу'];
                }
                $realFilePath = $filePath;
            }

            if ($realThemePath === false) {
                return ['success' => false, 'error' => 'Невірний шлях до теми'];
            }

            // Перевіряємо, що файл знаходиться в директорії теми
            $file = new File($filePath);
            if (! $file->isPathSafe($realThemePath)) {
                return ['success' => false, 'error' => 'Файл не належить до теми'];
            }

            // Використовуємо клас File для збереження
            $file = new File($realFilePath);

            // Перевіряємо права на запис
            if ($file->exists() && ! $file->isWritable()) {
                return ['success' => false, 'error' => 'Немає прав на запис файлу'];
            }

            // Зберігаємо файл
            $file->write($content);

            return [
                'success' => true,
                'message' => 'Файл успішно збережено',
                'size' => strlen($content),
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Помилка: ' . $e->getMessage()];
        }
    }

    /**
     * Створення нового файлу
     */
    /**
     * @param string $filePath
     * @param string $themePath
     * @param string $content
     * @return array<string, mixed>
     */
    public function createFile(string $filePath, string $themePath, string $content = ''): array
    {
        $realThemePath = realpath($themePath);
        if ($realThemePath === false) {
            return ['success' => false, 'error' => 'Невірний шлях до теми'];
        }

        // Нормалізуємо шлях
        $filePath = str_replace('\\', '/', $filePath);
        $filePath = ltrim($filePath, '/');
        $fullPath = $realThemePath . '/' . $filePath;

        // Перевіряємо базову безпеку шляху через простe рядкове порівняння
        // (не використовуємо realpath, оскільки директорія може не існувати)
        $normalizedThemePath = str_replace('\\', '/', $realThemePath);
        $normalizedFullPath = str_replace('\\', '/', $fullPath);
        if (! str_starts_with($normalizedFullPath, $normalizedThemePath)) {
            return ['success' => false, 'error' => 'Невірний шлях до файлу'];
        }

        // Перевіряємо, чи файл вже існує
        if (file_exists($fullPath) && is_file($fullPath)) {
            return ['success' => false, 'error' => 'Файл вже існує'];
        }

        // Створюємо директорію, якщо потрібно
        $fileDir = dirname($fullPath);

        try {
            $dir = new Directory($fileDir);
            if (! $dir->exists()) {
                $dir->create(0755, true);
            }
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('ThemeEditorManager: Failed to create directory', [
                    'error' => $e->getMessage(),
                    'directory' => $fileDir,
                    'themePath' => $realThemePath,
                ]);
            }

            return ['success' => false, 'error' => 'Неможливо створити директорію: ' . $e->getMessage()];
        }

        try {
            // Використовуємо клас File для створення
            $file = new File($fullPath);
            $file->write($content);

            // Очищуємо кеш дерева файлів
            if (function_exists('cache_forget')) {
                cache_forget('theme_files_' . md5($realThemePath));
            } elseif (class_exists('Cache')) {
                Cache::getInstance()->delete('theme_files_' . md5($realThemePath));
            }

            return [
                'success' => true,
                'message' => 'Файл успішно створено',
                'path' => $filePath,
            ];
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('ThemeEditorManager: Failed to create file', [
                    'error' => $e->getMessage(),
                    'filePath' => $fullPath,
                ]);
            }

            return ['success' => false, 'error' => 'Помилка: ' . $e->getMessage()];
        }
    }

    /**
     * Видалення файлу
     */
    /**
     * @param string $filePath
     * @param string $themePath
     * @return array<string, mixed>
     */
    public function deleteFile(string $filePath, string $themePath): array
    {
        $realFilePath = realpath($filePath);
        $realThemePath = realpath($themePath);

        if ($realFilePath === false || $realThemePath === false) {
            return ['success' => false, 'error' => 'Невірний шлях'];
        }

        // Перевіряємо, що файл знаходиться в директорії теми
        $file = new File($realFilePath);
        if (! $file->isPathSafe($realThemePath)) {
            return ['success' => false, 'error' => 'Файл не належить до теми'];
        }

        // Забороняємо видалення критичних файлів
        $criticalFiles = ['index.php', 'theme.json', 'functions.php'];
        $file = new File($realFilePath);
        $fileName = $file->getBasename();
        if (in_array($fileName, $criticalFiles, true)) {
            return ['success' => false, 'error' => 'Неможливо видалити критичний файл'];
        }

        try {
            // Використовуємо клас File для видалення
            $file = new File($realFilePath);
            $file->delete();

            return ['success' => true, 'message' => 'Файл успішно видалено'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Помилка: ' . $e->getMessage()];
        }
    }

    /**
     * Видалення директорії
     */
    public function deleteDirectory(string $dirPath, string $themePath): array
    {
        $realThemePath = realpath($themePath);
        if ($realThemePath === false) {
            return ['success' => false, 'error' => 'Невірний шлях до теми'];
        }

        // Нормалізуємо шлях
        $dirPath = str_replace('\\', '/', $dirPath);
        $dirPath = ltrim($dirPath, '/');

        // Забороняємо видалення кореневої папки теми
        if (empty($dirPath)) {
            return ['success' => false, 'error' => 'Неможливо видалити кореневу папку теми'];
        }

        $fullPath = $realThemePath . '/' . $dirPath;

        // Перевіряємо, що директорія знаходиться в теми
        $dir = new Directory($fullPath);
        $file = new File($fullPath);
        if (! $file->isPathSafe($realThemePath)) {
            return ['success' => false, 'error' => 'Невірний шлях до директорії'];
        }

        // Перевіряємо, чи директорія існує
        if (! is_dir($fullPath)) {
            return ['success' => false, 'error' => 'Директорія не існує'];
        }

        try {
            // Використовуємо клас Directory для видалення
            $dir = new Directory($fullPath);
            $dir->delete(true); // true = рекурсивне видалення

            return [
                'success' => true,
                'message' => 'Директорію успішно видалено',
            ];
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('ThemeEditorManager: Failed to delete directory', [
                    'error' => $e->getMessage(),
                    'dirPath' => $dirPath,
                ]);
            }

            return ['success' => false, 'error' => 'Помилка: ' . $e->getMessage()];
        }
    }

    /**
     * Переименование файла
     */
    public function renameFile(string $oldPath, string $newName, string $themePath): array
    {
        $realThemePath = realpath($themePath);
        if ($realThemePath === false) {
            return ['success' => false, 'error' => 'Невірний шлях до теми'];
        }

        // Нормалізуємо шлях (заменяем обратные слеши на прямые для внутренней работы)
        $oldPath = str_replace('\\', '/', $oldPath);
        $oldPath = ltrim($oldPath, '/');
        // Нормализуем путь, заменяя '/' на DIRECTORY_SEPARATOR для файловой системы
        $oldPathNormalized = str_replace('/', DIRECTORY_SEPARATOR, $oldPath);
        $oldFullPath = $realThemePath . DIRECTORY_SEPARATOR . $oldPathNormalized;

        // Перевіряємо, що файл знаходиться в теми
        if (! file_exists($oldFullPath)) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('ThemeEditorManager: File not found for rename', [
                    'oldPath' => $oldPath,
                    'oldFullPath' => $oldFullPath,
                    'themePath' => $realThemePath,
                ]);
            }

            return ['success' => false, 'error' => 'Файл не знайдено: ' . $oldPath];
        }

        if (is_dir($oldFullPath)) {
            return ['success' => false, 'error' => 'Це директорія, а не файл'];
        }

        $file = new File($oldFullPath);
        if (! $file->isPathSafe($realThemePath)) {
            return ['success' => false, 'error' => 'Файл не належить до теми'];
        }

        // Валідація нового імені
        $newName = trim($newName);
        if (empty($newName)) {
            return ['success' => false, 'error' => 'Нове ім\'я файлу не може бути порожнім'];
        }

        // Перевіряємо на небезпечні символи
        if (preg_match('/[\/\\\?\*\|<>:"]/', $newName)) {
            return ['success' => false, 'error' => 'Недопустимі символи в імені файлу'];
        }

        // Забороняємо переименование критичних файлів
        $criticalFiles = ['index.php', 'theme.json', 'functions.php'];
        $oldFileName = basename($oldPath);
        if (in_array($oldFileName, $criticalFiles, true)) {
            return ['success' => false, 'error' => 'Неможливо перейменувати критичний файл'];
        }

        // Формуємо новий шлях
        $parentDir = dirname($oldPath);
        if ($parentDir === '.' || $parentDir === '') {
            $newPath = $newName;
        } else {
            $newPath = $parentDir . '/' . $newName;
        }
        // Нормализуем путь, заменяя '/' на DIRECTORY_SEPARATOR
        $newPathNormalized = str_replace('/', DIRECTORY_SEPARATOR, $newPath);
        $newFullPath = $realThemePath . DIRECTORY_SEPARATOR . $newPathNormalized;

        // Перевіряємо, чи файл з таким ім'ям вже існує
        if (file_exists($newFullPath)) {
            return ['success' => false, 'error' => 'Файл з таким ім\'ям вже існує'];
        }

        try {
            // Перевіряємо, що батьківська директорія існує для нового файлу
            $newParentDir = dirname($newFullPath);
            if (! is_dir($newParentDir)) {
                return ['success' => false, 'error' => 'Батьківська директорія для нового файлу не існує'];
            }

            // Використовуємо прямий rename() для перейменування файлу
            // Это более надежно для переименования в той же директории
            // Очищаємо кеш stat, щоб переконатися, що файл видимий
            clearstatcache(true, $oldFullPath);
            clearstatcache(true, $newFullPath);

            // Убеждаемся, что пути нормализованы
            $oldFullPathNormalized = str_replace('/', DIRECTORY_SEPARATOR, $oldFullPath);
            $newFullPathNormalized = str_replace('/', DIRECTORY_SEPARATOR, $newFullPath);

            if (! @rename($oldFullPathNormalized, $newFullPathNormalized)) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Не вдалося перейменувати файл';

                // Добавляем детальную информацию для отладки
                if (class_exists('Logger')) {
                    Logger::getInstance()->logError('ThemeEditorManager: rename() failed', [
                        'error' => $errorMsg,
                        'oldFullPath' => $oldFullPath,
                        'newFullPath' => $newFullPath,
                        'oldPathExists' => file_exists($oldFullPath),
                        'oldPathIsFile' => is_file($oldFullPath),
                        'newPathExists' => file_exists($newFullPath),
                        'oldPathWritable' => is_writable(dirname($oldFullPath)),
                        'newParentDirWritable' => is_writable(dirname($newFullPath)),
                    ]);
                }

                throw new Exception($errorMsg);
            }

            // Очищаємо кеш дерева файлів, щоб перейменований файл відображався
            if (function_exists('cache_forget')) {
                cache_forget('theme_files_' . md5($themePath));
            }

            return [
                'success' => true,
                'message' => 'Файл успішно перейменовано',
                'old_path' => $oldPath,
                'new_path' => $newPath,
            ];
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('ThemeEditorManager: Failed to rename file', [
                    'error' => $e->getMessage(),
                    'oldPath' => $oldPath,
                    'newName' => $newName,
                    'oldFullPath' => $oldFullPath,
                    'newFullPath' => $newFullPath,
                ]);
            }

            return ['success' => false, 'error' => 'Помилка: ' . $e->getMessage()];
        }
    }

    /**
     * Переименование директорії
     */
    public function renameDirectory(string $oldPath, string $newName, string $themePath): array
    {
        $realThemePath = realpath($themePath);
        if ($realThemePath === false) {
            return ['success' => false, 'error' => 'Невірний шлях до теми'];
        }

        // Нормалізуємо шлях
        $oldPath = str_replace('\\', '/', $oldPath);
        $oldPath = ltrim($oldPath, '/');

        // Забороняємо переименование кореневої папки теми
        if (empty($oldPath)) {
            return ['success' => false, 'error' => 'Неможливо перейменувати кореневу папку теми'];
        }

        $oldFullPath = $realThemePath . '/' . $oldPath;

        // Перевіряємо, що директорія знаходиться в теми
        $file = new File($oldFullPath);
        if (! $file->isPathSafe($realThemePath)) {
            return ['success' => false, 'error' => 'Невірний шлях до директорії'];
        }

        // Перевіряємо, чи директорія існує
        if (! is_dir($oldFullPath)) {
            return ['success' => false, 'error' => 'Директорія не існує'];
        }

        // Валідація нового імені
        $newName = trim($newName);
        if (empty($newName)) {
            return ['success' => false, 'error' => 'Нове ім\'я папки не може бути порожнім'];
        }

        // Перевіряємо на небезпечні символи
        if (preg_match('/[\/\\\?\*\|<>:"]/', $newName)) {
            return ['success' => false, 'error' => 'Недопустимі символи в імені папки'];
        }

        // Формуємо новий шлях
        $parentDir = dirname($oldPath);
        if ($parentDir === '.' || $parentDir === '') {
            $newPath = $newName;
        } else {
            $newPath = $parentDir . '/' . $newName;
        }
        $newFullPath = $realThemePath . '/' . $newPath;

        // Перевіряємо, чи папка з таким ім'ям вже існує
        if (is_dir($newFullPath)) {
            return ['success' => false, 'error' => 'Папка з таким ім\'ям вже існує'];
        }

        try {
            // Використовуємо rename для переименования директорії
            if (! @rename($oldFullPath, $newFullPath)) {
                throw new Exception("Не вдалося перейменувати директорію з '{$oldFullPath}' в '{$newFullPath}'");
            }

            return [
                'success' => true,
                'message' => 'Директорію успішно перейменовано',
                'old_path' => $oldPath,
                'new_path' => $newPath,
            ];
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('ThemeEditorManager: Failed to rename directory', [
                    'error' => $e->getMessage(),
                    'oldPath' => $oldPath,
                    'newName' => $newName,
                ]);
            }

            return ['success' => false, 'error' => 'Помилка: ' . $e->getMessage()];
        }
    }

    /**
     * Створення директорії
     */
    public function createDirectory(string $dirPath, string $themePath): array
    {
        $realThemePath = realpath($themePath);
        if ($realThemePath === false) {
            return ['success' => false, 'error' => 'Невірний шлях до теми'];
        }

        // Нормалізуємо шлях
        $dirPath = str_replace('\\', '/', $dirPath);
        $dirPath = ltrim($dirPath, '/');
        $fullPath = $realThemePath . '/' . $dirPath;

        // Перевіряємо базову безпеку шляху через простe рядкове порівняння
        // (не використовуємо realpath, оскільки директорія може не існувати)
        $normalizedThemePath = str_replace('\\', '/', $realThemePath);
        $normalizedFullPath = str_replace('\\', '/', $fullPath);
        if (! str_starts_with($normalizedFullPath, $normalizedThemePath)) {
            return ['success' => false, 'error' => 'Невірний шлях до директорії'];
        }

        // Перевіряємо, чи директорія вже існує
        if (is_dir($fullPath)) {
            return ['success' => false, 'error' => 'Директорія вже існує'];
        }

        try {
            // Використовуємо клас Directory для створення
            $dir = new Directory($fullPath);
            $dir->create(0755, true);

            // Очищуємо кеш дерева файлів
            if (function_exists('cache_forget')) {
                cache_forget('theme_files_' . md5($realThemePath));
            } elseif (class_exists('Cache')) {
                Cache::getInstance()->delete('theme_files_' . md5($realThemePath));
            }

            return [
                'success' => true,
                'message' => 'Директорію успішно створено',
                'path' => $dirPath,
            ];
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('ThemeEditorManager: Failed to create directory', [
                    'error' => $e->getMessage(),
                    'directory' => $fullPath,
                    'themePath' => $realThemePath,
                ]);
            }

            return ['success' => false, 'error' => 'Помилка: ' . $e->getMessage()];
        }
    }

    /**
     * Отримання структури директорій теми
     */
    public function getDirectoryStructure(string $themePath): array
    {
        $structure = [];

        if (! is_dir($themePath) || ! is_readable($themePath)) {
            return $structure;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($themePath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $relativePath = str_replace($themePath, '', $item->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);
                $relativePath = ltrim($relativePath, '/');

                if ($item->isDir()) {
                    $structure['directories'][] = $relativePath;
                } else {
                    $structure['files'][] = [
                        'path' => $relativePath,
                        'name' => $item->getFilename(),
                        'extension' => strtolower($item->getExtension()),
                        'size' => $item->getSize(),
                    ];
                }
            }
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('Error getting directory structure', ['error' => $e->getMessage(), 'themePath' => $themePath]);
            } else {
                error_log('Error getting directory structure: ' . $e->getMessage());
            }
        }

        return $structure;
    }

    /**
     * Завантаження файлу в папку теми
     */
    public function uploadFile(array $uploadedFile, string $folderPath, string $themePath): array
    {
        try {
            // Перевірка безпеки шляху
            $realThemePath = realpath($themePath);
            if ($realThemePath === false) {
                return ['success' => false, 'error' => 'Невірний шлях до теми'];
            }

            // Формуємо повний шлях до директорії завантаження
            $targetDir = rtrim($realThemePath, '/\\') . DIRECTORY_SEPARATOR;
            if (! empty($folderPath)) {
                $targetDir .= trim($folderPath, '/\\') . DIRECTORY_SEPARATOR;
            }

            // Перевірка безпеки шляху директорії
            $realTargetDir = realpath($targetDir);
            if ($realTargetDir === false) {
                // Спробуємо створити директорію
                try {
                    $dir = new Directory($targetDir);
                    $dir->create(0755, true);
                    $realTargetDir = realpath($targetDir);
                } catch (Exception $e) {
                    if (class_exists('Logger')) {
                        Logger::getInstance()->logError('ThemeEditorManager: Failed to create upload directory', ['error' => $e->getMessage(), 'targetDir' => $targetDir]);
                    }

                    return ['success' => false, 'error' => 'Не вдалося створити директорію: ' . $e->getMessage()];
                }
            }

            // Перевіряємо безпеку шляху через клас File
            $targetFile = new File($realTargetDir . DIRECTORY_SEPARATOR . ($uploadedFile['name'] ?? 'temp'));
            if (! $targetFile->isPathSafe($realThemePath)) {
                return ['success' => false, 'error' => 'Недопустимий шлях для завантаження'];
            }

            // Використовуємо клас Upload для завантаження файлу
            $upload = new Upload($realTargetDir);

            // Встановлюємо дозволені розширення для редактора теми
            // Дозволяємо всі стандартні файли теми: PHP, JS, CSS, HTML, зображення, конфігураційні файли
            $upload->setAllowedExtensions([
                'php', 'js', 'css', 'html', 'htm', 'xml', 'json', 'yaml', 'yml', 'ini', 'txt', 'md',
                'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'bmp', 'tiff',
                'zip', 'tar', 'gz',
                'woff', 'woff2', 'ttf', 'eot', 'otf',
                'htaccess', 'htpasswd',
            ]);

            // Валідуємо ім'я файлу через Validator
            $fileName = $uploadedFile['name'] ?? '';
            if (empty($fileName)) {
                return ['success' => false, 'error' => 'Ім\'я файлу не може бути порожнім'];
            }

            // Перевіряємо на небезпечні символи (використовуємо валідацію slug як базову, але розширюємо для файлів)
            if (! Validator::validateString($fileName, 1, 255) || preg_match('/[\/\\\?\*\|<>:"]/', $fileName)) {
                return ['success' => false, 'error' => 'Недопустимі символи в імені файлу'];
            }

            // Завантажуємо файл
            $result = $upload->upload($uploadedFile);

            if (! $result['success']) {
                if (class_exists('Logger')) {
                    Logger::getInstance()->logError('ThemeEditorManager: File upload failed', ['error' => $result['error'] ?? 'Unknown error', 'fileName' => $fileName]);
                }

                return ['success' => false, 'error' => $result['error'] ?? 'Помилка завантаження файлу'];
            }

            // Отримуємо відносний шлях від теми
            $relativePath = str_replace($realThemePath, '', $result['file']);
            $relativePath = str_replace('\\', '/', $relativePath);
            $relativePath = ltrim($relativePath, '/');

            if (class_exists('Logger')) {
                Logger::getInstance()->logInfo('ThemeEditorManager: File uploaded successfully', [
                    'fileName' => $result['name'] ?? $fileName,
                    'path' => $relativePath,
                    'size' => $result['size'] ?? 0,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Файл успішно завантажено',
                'path' => $relativePath,
                'name' => $result['name'] ?? $fileName,
                'size' => $result['size'] ?? 0,
            ];
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('ThemeEditorManager: Exception in uploadFile', [
                    'error' => $e->getMessage(),
                    'folderPath' => $folderPath,
                    'themePath' => $themePath,
                ]);
            }

            return ['success' => false, 'error' => 'Помилка: ' . $e->getMessage()];
        }
    }

    /**
     * Створення ZIP архіву папки
     */
    public function createFolderZip(string $folderPath, string $themePath): ?string
    {
        try {
            // Нормалізуємо шлях до папки
            $folderPath = trim($folderPath, '/\\');
            $fullFolderPath = rtrim($themePath, '/\\') . '/' . $folderPath;

            // Перевірка безпеки шляху
            $realThemePath = realpath($themePath);
            $realFolderPath = realpath($fullFolderPath);

            if ($realThemePath === false || $realFolderPath === false) {
                if (class_exists('Logger')) {
                    Logger::getInstance()->logError('ThemeEditorManager: Invalid paths', ['theme' => $themePath, 'folder' => $fullFolderPath]);
                } else {
                    error_log("ThemeEditorManager: Invalid paths - theme: {$themePath}, folder: {$fullFolderPath}");
                }

                return null;
            }

            // Перевіряємо безпеку шляху через клас File
            $folderFile = new File($realFolderPath);
            if (! $folderFile->isPathSafe($realThemePath)) {
                if (class_exists('Logger')) {
                    Logger::getInstance()->logWarning('ThemeEditorManager: Folder path is outside theme directory', ['folderPath' => $realFolderPath, 'themePath' => $realThemePath]);
                } else {
                    error_log('ThemeEditorManager: Folder path is outside theme directory');
                }

                return null;
            }

            if (! is_dir($realFolderPath) || ! is_readable($realFolderPath)) {
                if (class_exists('Logger')) {
                    Logger::getInstance()->logError('ThemeEditorManager: Folder does not exist or is not readable', ['folderPath' => $realFolderPath]);
                } else {
                    error_log("ThemeEditorManager: Folder does not exist or is not readable: {$realFolderPath}");
                }

                return null;
            }

            // Створюємо тимчасовий файл для архіву
            $tempDir = sys_get_temp_dir();
            if (! is_writable($tempDir)) {
                if (class_exists('Logger')) {
                    Logger::getInstance()->logError('ThemeEditorManager: Temporary directory is not writable', ['tempDir' => $tempDir]);
                } else {
                    error_log("ThemeEditorManager: Temporary directory is not writable: {$tempDir}");
                }

                return null;
            }

            $zipPath = rtrim($tempDir, '/\\') . DIRECTORY_SEPARATOR . 'theme_folder_' . uniqid() . '.zip';

            // Створюємо ZIP архів використовуючи клас Zip
            try {
                $zip = new Zip($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

                // Додаємо директорію до архіву
                $folderFile = new File($realFolderPath);
                $baseName = $folderFile->getBasename();
                $zip->addDirectory($realFolderPath, $baseName);

                // Закриваємо архів
                if (! $zip->close()) {
                    if (class_exists('Logger')) {
                        Logger::getInstance()->logError('ThemeEditorManager: Failed to close ZIP archive', ['zipPath' => $zipPath]);
                    } else {
                        error_log('ThemeEditorManager: Failed to close ZIP archive');
                    }
                    @unlink($zipPath);

                    return null;
                }

                // Перевіряємо, що файл архіву створено і доступний
                $zipFile = new File($zipPath);
                if (! $zipFile->exists() || ! $zipFile->isReadable()) {
                    if (class_exists('Logger')) {
                        Logger::getInstance()->logError('ThemeEditorManager: ZIP archive file was not created or is not readable', ['zipPath' => $zipPath]);
                    } else {
                        error_log("ThemeEditorManager: ZIP archive file was not created or is not readable: {$zipPath}");
                    }

                    return null;
                }
            } catch (Exception $e) {
                if (class_exists('Logger')) {
                    Logger::getInstance()->logError('ThemeEditorManager: Error creating ZIP archive', ['error' => $e->getMessage(), 'zipPath' => $zipPath]);
                } else {
                    error_log('ThemeEditorManager: Error creating ZIP archive: ' . $e->getMessage());
                }
                if (file_exists($zipPath)) {
                    @unlink($zipPath);
                }

                return null;
            }

            return $zipPath;
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('ThemeEditorManager: Exception in createFolderZip', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'folderPath' => $folderPath,
                    'themePath' => $themePath,
                ]);
            } else {
                error_log('ThemeEditorManager: Exception in createFolderZip: ' . $e->getMessage());
                error_log('ThemeEditorManager: Stack trace: ' . $e->getTraceAsString());
            }

            return null;
        }
    }
}
