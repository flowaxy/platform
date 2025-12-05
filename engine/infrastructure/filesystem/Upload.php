<?php

/**
 * Клас для завантаження файлів
 * Безпечне завантаження файлів з валідацією, перевіркою типів та розміру
 *
 * @package Engine\Classes\Files
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class Upload
{
    private array $allowedExtensions = [];
    private array $allowedMimeTypes = [];
    private int $maxFileSize = 10485760; // 10 MB за замовчуванням
    private string $uploadDir = '';
    private bool $createDirectories = true;
    private bool $overwriteExisting = false;
    private string $namingStrategy = 'original'; // 'original', 'random', 'timestamp'

    /**
     * Конструктор
     *
     * @param string|null $uploadDir Директорія для завантаження
     */
    public function __construct(?string $uploadDir = null)
    {
        if ($uploadDir !== null) {
            $this->setUploadDir($uploadDir);
        }

        // Завантажуємо параметри з налаштувань, якщо доступні
        $this->loadConfigParams();
    }

    /**
     * Завантаження параметрів конфігурації з налаштувань
     *
     * @return void
     */
    private function loadConfigParams(): void
    {
        if (class_exists('SystemConfig')) {
            $systemConfig = SystemConfig::getInstance();
            $this->maxFileSize = $systemConfig->getUploadMaxFileSize();

            // Завантажуємо дозволені розширення, якщо вони не були встановлені вручну
            if (empty($this->allowedExtensions)) {
                $this->allowedExtensions = $systemConfig->getUploadAllowedExtensions();
            }

            // Завантажуємо дозволені MIME типи, якщо вони не були встановлені вручну
            if (empty($this->allowedMimeTypes)) {
                $this->allowedMimeTypes = $systemConfig->getUploadAllowedMimeTypes();
            }
        }
    }

    /**
     * Встановлення директорії для завантаження
     *
     * @param string $uploadDir Шлях до директорії
     * @return self
     */
    public function setUploadDir(string $uploadDir): self
    {
        $this->uploadDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR;

        return $this;
    }

    /**
     * Встановлення дозволених розширень
     *
     * @param array $extensions Масив розширень (наприклад, ['jpg', 'png', 'pdf'])
     * @return self
     */
    public function setAllowedExtensions(array $extensions): self
    {
        $this->allowedExtensions = array_map('strtolower', $extensions);

        return $this;
    }

    /**
     * Встановлення дозволених MIME типів
     *
     * @param array $mimeTypes Масив MIME типів
     * @return self
     */
    public function setAllowedMimeTypes(array $mimeTypes): self
    {
        $this->allowedMimeTypes = $mimeTypes;

        return $this;
    }

    /**
     * Встановлення максимального розміру файлу
     *
     * @param int $size Розмір у байтах
     * @return self
     */
    public function setMaxFileSize(int $size): self
    {
        $this->maxFileSize = $size;

        return $this;
    }

    /**
     * Установка стратегии именования файлов
     *
     * @param string $strategy 'original', 'random', 'timestamp'
     * @return self
     */
    public function setNamingStrategy(string $strategy): self
    {
        $this->namingStrategy = $strategy;

        return $this;
    }

    /**
     * Разрешить перезапись существующих файлов
     *
     * @param bool $overwrite
     * @return self
     */
    public function setOverwrite(bool $overwrite): self
    {
        $this->overwriteExisting = $overwrite;

        return $this;
    }

    /**
     * Загрузка файла
     *
     * @param array $file Массив $_FILES['field_name']
     * @param string|null $customName Пользовательское имя файла
     * @return array ['success' => bool, 'file' => string, 'error' => string]
     */
    public function upload(array $file, ?string $customName = null): array
    {
        if (function_exists('logDebug')) {
            logDebug('Upload::upload: Starting file upload', [
                'original_name' => $file['name'] ?? 'unknown',
                'size' => $file['size'] ?? 0,
                'type' => $file['type'] ?? 'unknown',
            ]);
        }

        // Проверка наличия файла
        if (! isset($file['tmp_name']) || ! is_uploaded_file($file['tmp_name'])) {
            if (function_exists('logWarning')) {
                logWarning('Upload::upload: File was not uploaded via HTTP POST', [
                    'file_name' => $file['name'] ?? 'unknown',
                ]);
            }
            return [
                'success' => false,
                'file' => '',
                'error' => 'Файл не был загружен через HTTP POST',
            ];
        }

        // Проверка ошибок загрузки
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = $this->getUploadError($file['error']);
            if (function_exists('logError')) {
                logError('Upload::upload: Upload error', [
                    'error_code' => $file['error'],
                    'error_message' => $errorMsg,
                    'file_name' => $file['name'] ?? 'unknown',
                ]);
            }
            return [
                'success' => false,
                'file' => '',
                'error' => $errorMsg,
            ];
        }

        // Проверка размера файла
        if ($file['size'] > $this->maxFileSize) {
            if (function_exists('logWarning')) {
                logWarning('Upload::upload: File size exceeds maximum', [
                    'file_size' => $file['size'],
                    'max_size' => $this->maxFileSize,
                    'file_name' => $file['name'] ?? 'unknown',
                ]);
            }
            return [
                'success' => false,
                'file' => '',
                'error' => 'Размер файла превышает максимально допустимый (' . $this->formatSize($this->maxFileSize) . ')',
            ];
        }

        // Получаем расширение и MIME тип
        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mimeType = $file['type'];

        // Проверка расширения
        if (! empty($this->allowedExtensions) && ! in_array($extension, $this->allowedExtensions, true)) {
            if (function_exists('logWarning')) {
                logWarning('Upload::upload: File extension not allowed', [
                    'extension' => $extension,
                    'allowed_extensions' => $this->allowedExtensions,
                    'file_name' => $originalName,
                ]);
            }
            return [
                'success' => false,
                'file' => '',
                'error' => 'Расширение файла не разрешено. Разрешенные: ' . implode(', ', $this->allowedExtensions),
            ];
        }

        // Проверка MIME типа (только если расширение не прошло проверку или MIME типы установлены)
        // Если расширение разрешено, но MIME тип не совпадает - это предупреждение, но не ошибка
        // Но если MIME типы явно установлены, проверяем их строго
        if (! empty($this->allowedMimeTypes)) {
            $mimeTypeAllowed = in_array($mimeType, $this->allowedMimeTypes, true);

            // Дополнительная проверка MIME типа по содержимому файла
            $realMimeType = $this->getRealMimeType($file['tmp_name']);
            $realMimeTypeAllowed = in_array($realMimeType, $this->allowedMimeTypes, true);

            // Если оба MIME типи не дозволені, але розширення дозволене - це попередження
            // Але якщо розширення не дозволене, то це помилка
            if (! $mimeTypeAllowed && ! $realMimeTypeAllowed) {
                // Перевіряємо, чи розширення дозволене
                $extensionAllowed = ! empty($this->allowedExtensions) && in_array($extension, $this->allowedExtensions, true);

                if (! $extensionAllowed) {
                    // Розширення не дозволене - це помилка
                    $allowedTypes = implode(', ', array_slice($this->allowedMimeTypes, 0, 5));
                    if (count($this->allowedMimeTypes) > 5) {
                        $allowedTypes .= '...';
                    }

                    if (function_exists('logWarning')) {
                        logWarning('Upload::upload: MIME type not allowed', [
                            'mime_type' => $mimeType,
                            'real_mime_type' => $realMimeType,
                            'extension' => $extension,
                            'file_name' => $originalName,
                        ]);
                    }

                    return [
                        'success' => false,
                        'file' => '',
                        'error' => 'Тип файла не разрешен. Загруженный тип: ' . $mimeType . '. Разрешенные типы: ' . $allowedTypes,
                    ];
                }
                // Якщо розширення дозволене, але MIME тип не співпадає - це попередження, але дозволяємо
                // (може бути помилка браузера в визначенні MIME типу)
            }
        }

        // Генерируем имя файла
        if ($customName !== null) {
            $fileName = $customName . ($extension ? '.' . $extension : '');
        } else {
            $fileName = $this->generateFileName($originalName, $extension);
        }

        $destinationPath = $this->uploadDir . $fileName;

        // Проверяем, существует ли файл
        if (file_exists($destinationPath) && ! $this->overwriteExisting) {
            return [
                'success' => false,
                'file' => '',
                'error' => 'Файл с таким именем уже существует',
            ];
        }

        // Создаем директорию, если её нет
        if ($this->createDirectories) {
            $dir = dirname($destinationPath);
            if (! is_dir($dir)) {
                if (! @mkdir($dir, 0755, true)) {
                    return [
                        'success' => false,
                        'file' => '',
                        'error' => 'Не удалось создать директорию для загрузки',
                    ];
                }
            }
        }

        // Перемещаем файл
        if (! @move_uploaded_file($file['tmp_name'], $destinationPath)) {
            if (function_exists('logError')) {
                logError('Upload::upload: Failed to move uploaded file', [
                    'tmp_name' => $file['tmp_name'],
                    'destination' => $destinationPath,
                    'file_name' => $originalName,
                ]);
            }
            return [
                'success' => false,
                'file' => '',
                'error' => 'Не удалось переместить загруженный файл',
            ];
        }

        @chmod($destinationPath, 0644);

        if (function_exists('logInfo')) {
            logInfo('Upload::upload: File uploaded successfully', [
                'file' => $destinationPath,
                'name' => $fileName,
                'size' => $file['size'],
                'mime_type' => $realMimeType ?? $mimeType,
                'extension' => $extension,
            ]);
        }

        return [
            'success' => true,
            'file' => $destinationPath,
            'name' => $fileName,
            'size' => $file['size'],
            'mime_type' => $realMimeType ?? $mimeType,
            'extension' => $extension,
        ];
    }

    /**
     * Загрузка нескольких файлов
     *
     * @param array $files Массив файлов (например, $_FILES['field_name'])
     * @return array Массив результатов загрузки
     */
    public function uploadMultiple(array $files): array
    {
        $results = [];

        // Нормализуем массив файлов
        $normalizedFiles = $this->normalizeFilesArray($files);

        foreach ($normalizedFiles as $file) {
            $results[] = $this->upload($file);
        }

        return $results;
    }

    /**
     * Нормализация массива файлов
     *
     * @param array $files Массив файлов
     * @return array
     */
    private function normalizeFilesArray(array $files): array
    {
        $normalized = [];

        if (isset($files['tmp_name']) && is_array($files['tmp_name'])) {
            // Множественная загрузка
            $count = count($files['tmp_name']);

            for ($i = 0; $i < $count; $i++) {
                if (isset($files['tmp_name'][$i]) && $files['tmp_name'][$i] !== '') {
                    $normalized[] = [
                        'name' => $files['name'][$i] ?? '',
                        'type' => $files['type'][$i] ?? '',
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i] ?? UPLOAD_ERR_OK,
                        'size' => $files['size'][$i] ?? 0,
                    ];
                }
            }
        } else {
            // Один файл
            $normalized[] = $files;
        }

        return $normalized;
    }

    /**
     * Генерация имени файла
     *
     * @param string $originalName Оригинальное имя
     * @param string $extension Расширение
     * @return string
     */
    private function generateFileName(string $originalName, string $extension): string
    {
        $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);

        switch ($this->namingStrategy) {
            case 'random':
                return uniqid('', true) . ($extension ? '.' . $extension : '');

            case 'timestamp':
                // Транскрипция перед добавлением времени
                $transliterated = class_exists('Security') ? Security::transliterate($nameWithoutExt) : $nameWithoutExt;

                return date('Y-m-d_H-i-s') . '_' . $transliterated . ($extension ? '.' . $extension : '');

            case 'original':
            default:
                // Транскрипция русских букв в латиницу
                $transliterated = class_exists('Security') ? Security::transliterate($nameWithoutExt) : $nameWithoutExt;
                // Очищаем имя от небезопасных символов
                $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $transliterated);

                return $name . ($extension ? '.' . $extension : '');
        }
    }

    /**
     * Получение реального MIME типа файла
     *
     * @param string $filePath Путь к файлу
     * @return string
     */
    private function getRealMimeType(string $filePath): string
    {
        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        }

        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            $finfo = null; // В PHP 8.0+ finfo об'єкти автоматично звільняють пам'ять через garbage collector

            return $mimeType;
        }

        return 'application/octet-stream';
    }

    /**
     * Получение описания ошибки загрузки
     *
     * @param int $errorCode Код ошибки
     * @return string
     */
    private function getUploadError(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Размер файла превышает максимально допустимый в PHP',
            UPLOAD_ERR_FORM_SIZE => 'Размер файла превышает максимально допустимый в форме',
            UPLOAD_ERR_PARTIAL => 'Файл был загружен частично',
            UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
            UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная директория',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
            UPLOAD_ERR_EXTENSION => 'Расширение PHP остановило загрузку файла',
        ];

        return $errors[$errorCode] ?? 'Неизвестная ошибка загрузки';
    }

    /**
     * Форматирование размера файла
     *
     * @param int $bytes Размер в байтах
     * @return string
     */
    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Статический метод: Быстрая загрузка файла
     *
     * @param array $file Массив $_FILES
     * @param string $uploadDir Директория для загрузки
     * @param array $allowedExtensions Разрешенные расширения
     * @return array
     */
    public static function quickUpload(array $file, string $uploadDir, array $allowedExtensions = []): array
    {
        $upload = new self($uploadDir);

        if (! empty($allowedExtensions)) {
            $upload->setAllowedExtensions($allowedExtensions);
        }

        return $upload->upload($file);
    }
}
