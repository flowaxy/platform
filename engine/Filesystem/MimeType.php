<?php

/**
 * Клас для визначення MIME типів файлів
 * Визначення типів файлів за розширенням та вмістом
 *
 * @package Engine\Classes\Files
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class MimeType
{
    /**
     * Карта розширень файлів до MIME типів
     *
     * @var array
     */
    private static array $extensionMap = [
        // Зображення
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'bmp' => 'image/bmp',
        'tiff' => 'image/tiff',

        // Аудіо
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'm4a' => 'audio/mp4',
        'flac' => 'audio/flac',

        // Відео
        'mp4' => 'video/mp4',
        'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime',
        'wmv' => 'video/x-ms-wmv',
        'flv' => 'video/x-flv',
        'webm' => 'video/webm',
        'mkv' => 'video/x-matroska',

        // Документи
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',

        // Текстові файли
        'txt' => 'text/plain',
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'csv' => 'text/csv',

        // Архіви
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/x-zip'],
        'rar' => ['application/x-rar-compressed', 'application/x-rar'],
        '7z' => 'application/x-7z-compressed',
        'tar' => 'application/x-tar',
        'gz' => ['application/gzip', 'application/x-gzip'],

        // Інше
        'php' => 'application/x-httpd-php',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
    ];

    /**
     * Визначення MIME типу за розширенням файлу
     *
     * @param string $filePath Шлях до файлу або розширення
     * @return string|false MIME тип або false
     */
    public static function fromExtension(string $filePath)
    {
        // Якщо передано шлях до файлу, витягуємо розширення
        if (str_contains($filePath, '.') && file_exists($filePath)) {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        } else {
            // Припускаємо, що це розширення
            $extension = strtolower(ltrim($filePath, '.'));
        }

        $mimeType = self::$extensionMap[$extension] ?? false;

        // Якщо це масив (кілька варіантів MIME типу), повертаємо перший
        if (is_array($mimeType)) {
            return $mimeType[0];
        }

        return $mimeType;
    }

    /**
     * Отримання всіх можливих MIME типів для розширення
     *
     * @param string $filePath Шлях до файлу або розширення
     * @return array Масив MIME типів
     */
    public static function getAllMimeTypes(string $filePath): array
    {
        // Якщо передано шлях до файлу, витягуємо розширення
        if (str_contains($filePath, '.') && file_exists($filePath)) {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        } else {
            // Припускаємо, що це розширення
            $extension = strtolower(ltrim($filePath, '.'));
        }

        $mimeType = self::$extensionMap[$extension] ?? false;

        // Якщо це масив, повертаємо всі варіанти
        if (is_array($mimeType)) {
            return $mimeType;
        }

        // Якщо це один тип, повертаємо масив з одним елементом
        return $mimeType ? [$mimeType] : [];
    }

    /**
     * Визначення MIME типу за вмістом файлу
     *
     * @param string $filePath Шлях до файлу
     * @return string|false MIME тип або false
     */
    public static function fromContent(string $filePath)
    {
        if (! file_exists($filePath)) {
            return false;
        }

        if (! is_readable($filePath)) {
            return false;
        }

        // Використовуємо mime_content_type якщо доступно
        if (function_exists('mime_content_type')) {
            $mimeType = @mime_content_type($filePath);
            if ($mimeType !== false) {
                return $mimeType;
            }
        }

        // Використовуємо finfo якщо доступно
        if (function_exists('finfo_file')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mimeType = @finfo_file($finfo, $filePath);
                $finfo = null; // В PHP 8.0+ finfo об'єкти автоматично звільняють пам'ять через garbage collector
                if ($mimeType !== false) {
                    return $mimeType;
                }
            }
        }

        return false;
    }

    /**
     * Визначення MIME типу файлу (комбінований метод)
     * Спочатку намагається визначити за вмістом, потім за розширенням
     *
     * @param string $filePath Шлях до файлу
     * @return string MIME тип або 'application/octet-stream' за замовчуванням
     */
    public static function get(string $filePath): string
    {
        // Намагаємося визначити за вмістом
        $mimeType = self::fromContent($filePath);

        if ($mimeType !== false) {
            return $mimeType;
        }

        // Намагаємося визначити за розширенням
        $mimeType = self::fromExtension($filePath);

        if ($mimeType !== false) {
            return $mimeType;
        }

        return 'application/octet-stream';
    }

    /**
     * Перевірка, чи є файл зображенням
     *
     * @param string $filePath Шлях до файлу
     * @return bool
     */
    public static function isImage(string $filePath): bool
    {
        $mimeType = self::get($filePath);

        return str_starts_with($mimeType, 'image/');
    }

    /**
     * Перевірка, чи є файл відео
     *
     * @param string $filePath Шлях до файлу
     * @return bool
     */
    public static function isVideo(string $filePath): bool
    {
        $mimeType = self::get($filePath);

        return str_starts_with($mimeType, 'video/');
    }

    /**
     * Перевірка, чи є файл аудіо
     *
     * @param string $filePath Шлях до файлу
     * @return bool
     */
    public static function isAudio(string $filePath): bool
    {
        $mimeType = self::get($filePath);

        return str_starts_with($mimeType, 'audio/');
    }

    /**
     * Перевірка, чи є файл документом
     *
     * @param string $filePath Шлях до файлу
     * @return bool
     */
    public static function isDocument(string $filePath): bool
    {
        $mimeType = self::get($filePath);
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument',
            'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint',
            'application/vnd.oasis.opendocument',
            'text/plain',
            'text/html',
            'text/css',
        ];

        foreach ($documentTypes as $type) {
            if (str_starts_with($mimeType, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Перевірка, чи є файл архівом
     *
     * @param string $filePath Шлях до файлу
     * @return bool
     */
    public static function isArchive(string $filePath): bool
    {
        $mimeType = self::get($filePath);
        $archiveTypes = [
            'application/zip',
            'application/x-zip-compressed',
            'application/x-zip',
            'application/x-rar-compressed',
            'application/x-rar',
            'application/x-7z-compressed',
            'application/x-tar',
            'application/gzip',
            'application/x-gzip',
        ];

        return in_array($mimeType, $archiveTypes, true);
    }

    /**
     * Отримання розширення файлу за MIME типом
     *
     * @param string $mimeType MIME тип
     * @return string|false Розширення або false
     */
    public static function getExtension(string $mimeType)
    {
        $reverseMap = array_flip(self::$extensionMap);

        return $reverseMap[$mimeType] ?? false;
    }

    /**
     * Додавання користувацького співставлення розширення та MIME типу
     *
     * @param string $extension Розширення
     * @param string $mimeType MIME тип
     * @return void
     */
    public static function addMapping(string $extension, string $mimeType): void
    {
        self::$extensionMap[strtolower($extension)] = $mimeType;
    }

    /**
     * Отримання всіх доступних розширень для MIME типу
     *
     * @param string $mimeType MIME тип
     * @return array Масив розширень
     */
    public static function getExtensions(string $mimeType): array
    {
        $extensions = [];

        foreach (self::$extensionMap as $ext => $mime) {
            if ($mime === $mimeType || str_starts_with($mime, $mimeType)) {
                $extensions[] = $ext;
            }
        }

        return $extensions;
    }

    /**
     * Валідація MIME типу файлу
     *
     * @param string $filePath Шлях до файлу
     * @param array $allowedMimeTypes Дозволені MIME типи
     * @return bool
     */
    public static function validate(string $filePath, array $allowedMimeTypes): bool
    {
        $mimeType = self::get($filePath);

        return in_array($mimeType, $allowedMimeTypes, true);
    }
}
