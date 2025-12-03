<?php

/**
 * Клас для роботи з зображеннями
 * Зміна розміру, обрізання, конвертація форматів та інші операції з зображеннями
 *
 * @package Engine\Classes\Files
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class Image
{
    private string $filePath;
    private ?\GdImage $resource = null;
    private int $width = 0;
    private int $height = 0;
    private int $type = IMAGETYPE_UNKNOWN;
    private string $mimeType = '';

    /**
     * Конструктор
     *
     * @param string|null $filePath Шлях до зображення
     */
    public function __construct(?string $filePath = null)
    {
        if (! extension_loaded('gd')) {
            throw new Exception('Розширення GD не встановлено');
        }

        if ($filePath !== null) {
            $this->load($filePath);
        }
    }

    /**
     * Завантаження зображення
     *
     * @param string $filePath Шлях до зображення
     * @return self
     * @throws Exception Якщо не вдалося завантажити
     */
    public function load(string $filePath): self
    {
        if (! file_exists($filePath)) {
            throw new Exception("Зображення не існує: {$filePath}");
        }

        if (! is_readable($filePath)) {
            throw new Exception("Зображення недоступне для читання: {$filePath}");
        }

        $imageInfo = @getimagesize($filePath);

        if ($imageInfo === false) {
            throw new Exception("Не вдалося визначити розміри зображення: {$filePath}");
        }

        $this->filePath = $filePath;
        $this->width = $imageInfo[0];
        $this->height = $imageInfo[1];
        $this->type = $imageInfo[2];
        $this->mimeType = $imageInfo['mime'];

        // Створюємо ресурс зображення
        switch ($this->type) {
            case IMAGETYPE_JPEG:
                $this->resource = @imagecreatefromjpeg($filePath);

                break;
            case IMAGETYPE_PNG:
                $this->resource = @imagecreatefrompng($filePath);

                break;
            case IMAGETYPE_GIF:
                $this->resource = @imagecreatefromgif($filePath);

                break;
            case IMAGETYPE_WEBP:
                $this->resource = @imagecreatefromwebp($filePath);

                break;
            default:
                throw new Exception("Непідтримуваний тип зображення: {$this->mimeType}");
        }

        if ($this->resource === false) {
            throw new Exception("Не удалось загрузить изображение: {$filePath}");
        }

        // Зберігаємо прозрачність для PNG та GIF
        if ($this->type === IMAGETYPE_PNG || $this->type === IMAGETYPE_GIF) {
            imagealphablending($this->resource, false);
            imagesavealpha($this->resource, true);
        }

        return $this;
    }

    /**
     * Створення нового зображення
     *
     * @param int $width Ширина
     * @param int $height Висота
     * @param array|null $color Колір фону [r, g, b, alpha]
     * @return self
     */
    public function create(int $width, int $height, ?array $color = null): self
    {
        $this->width = $width;
        $this->height = $height;
        $this->type = IMAGETYPE_PNG;
        $this->mimeType = 'image/png';

        $this->resource = imagecreatetruecolor($width, $height);

        imagealphablending($this->resource, false);
        imagesavealpha($this->resource, true);

        if ($color !== null) {
            $bgColor = imagecolorallocatealpha(
                $this->resource,
                $color[0] ?? 255,
                $color[1] ?? 255,
                $color[2] ?? 255,
                $color[3] ?? 0
            );
            imagefill($this->resource, 0, 0, $bgColor);
        }

        return $this;
    }

    /**
     * Изменение размера изображения
     *
     * @param int $width Новая ширина (0 = пропорционально)
     * @param int $height Новая высота (0 = пропорционально)
     * @param bool $crop Обрезать ли изображение
     * @return self
     */
    public function resize(int $width, int $height = 0, bool $crop = false): self
    {
        if ($width === 0 && $height === 0) {
            return $this;
        }

        // Вычисляем размеры
        if ($width === 0) {
            $width = (int)($this->width * $height / $this->height);
        } elseif ($height === 0) {
            $height = (int)($this->height * $width / $this->width);
        }

        if ($crop) {
            // Обрезаем изображение
            $ratio = max($width / $this->width, $height / $this->height);
            $newWidth = (int)($this->width * $ratio);
            $newHeight = (int)($this->height * $ratio);

            $dstX = (int)(($newWidth - $width) / 2);
            $dstY = (int)(($newHeight - $height) / 2);

            $newResource = imagecreatetruecolor($width, $height);

            if ($this->type === IMAGETYPE_PNG || $this->type === IMAGETYPE_GIF) {
                imagealphablending($newResource, false);
                imagesavealpha($newResource, true);
            }

            imagecopyresampled(
                $newResource,
                $this->resource,
                0,
                0,
                $dstX,
                $dstY,
                $width,
                $height,
                $newWidth,
                $newHeight
            );

            // В PHP 8.0+ GdImage об'єкти автоматично звільняють пам'ять
            $this->resource = null;
            $this->resource = $newResource;
        } else {
            // Пропорциональное изменение размера
            $newResource = imagecreatetruecolor($width, $height);

            if ($this->type === IMAGETYPE_PNG || $this->type === IMAGETYPE_GIF) {
                imagealphablending($newResource, false);
                imagesavealpha($newResource, true);
            }

            imagecopyresampled(
                $newResource,
                $this->resource,
                0,
                0,
                0,
                0,
                $width,
                $height,
                $this->width,
                $this->height
            );

            // В PHP 8.0+ GdImage об'єкти автоматично звільняють пам'ять
            $this->resource = null;
            $this->resource = $newResource;
        }

        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    /**
     * Обрезка изображения
     *
     * @param int $x Координата X
     * @param int $y Координата Y
     * @param int $width Ширина обрезки
     * @param int $height Высота обрезки
     * @return self
     */
    public function crop(int $x, int $y, int $width, int $height): self
    {
        $newResource = imagecreatetruecolor($width, $height);

        if ($this->type === IMAGETYPE_PNG || $this->type === IMAGETYPE_GIF) {
            imagealphablending($newResource, false);
            imagesavealpha($newResource, true);
        }

        imagecopyresampled(
            $newResource,
            $this->resource,
            0,
            0,
            $x,
            $y,
            $width,
            $height,
            $width,
            $height
        );

        // В PHP 8.0+ GdImage об'єкти автоматично звільняють пам'ять
        $this->resource = null;
        $this->resource = $newResource;
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    /**
     * Збереження зображення
     *
     * @param string|null $filePath Шлях для збереження (якщо null, використовується поточний)
     * @param int|null $quality Якість (0-100 для JPEG, 0-9 для PNG)
     * @param int|null $type Тип зображення (якщо null, використовується поточний)
     * @return bool
     * @throws Exception Если не удалось сохранить
     */
    public function save(?string $filePath = null, ?int $quality = null, ?int $type = null): bool
    {
        $targetPath = $filePath ?? $this->filePath;
        $targetType = $type ?? $this->type;

        if (empty($targetPath)) {
            throw new Exception('Путь к файлу не установлен');
        }

        // Створюємо директорію, якщо її немає
        $dir = dirname($targetPath);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0755, true)) {
                throw new Exception("Не удалось создать директорию: {$dir}");
            }
        }

        $result = false;

        switch ($targetType) {
            case IMAGETYPE_JPEG:
                $quality = $quality ?? 85;
                $result = @imagejpeg($this->resource, $targetPath, $quality);

                break;
            case IMAGETYPE_PNG:
                $quality = $quality !== null ? (int)($quality / 10) : 6;
                $quality = max(0, min(9, $quality));
                $result = @imagepng($this->resource, $targetPath, $quality);

                break;
            case IMAGETYPE_GIF:
                $result = @imagegif($this->resource, $targetPath);

                break;
            case IMAGETYPE_WEBP:
                $quality = $quality ?? 85;
                $result = @imagewebp($this->resource, $targetPath, $quality);

                break;
            default:
                throw new Exception("Неподдерживаемый тип изображения: {$targetType}");
        }

        if ($result === false) {
            throw new Exception("Не удалось сохранить изображение: {$targetPath}");
        }

        @chmod($targetPath, 0644);

        if ($filePath !== null) {
            $this->filePath = $targetPath;
            $this->type = $targetType;
        }

        return true;
    }

    /**
     * Отримання ширини
     *
     * @return int
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Отримання висоти
     *
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Отримання типу зображення
     *
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * Отримання MIME типу
     *
     * @return string
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Отримання ресурсу зображення
     *
     * @return \GdImage|null
     */
    public function getResource(): ?\GdImage
    {
        return $this->resource;
    }

    /**
     * Звільнення пам'яті
     */
    public function __destruct()
    {
        // В PHP 8.0+ GdImage об'єкти автоматично звільняють пам'ять через garbage collector
        // Встановлення null достатньо для явного звільнення
        $this->resource = null;
    }

    /**
     * Статичний метод: Створення мініатюри
     *
     * @param string $sourcePath Исходный путь
     * @param string $destinationPath Путь назначения
     * @param int $width Ширина
     * @param int $height Высота
     * @param bool $crop Обрезать ли
     * @return bool
     */
    public static function thumbnail(string $sourcePath, string $destinationPath, int $width, int $height = 0, bool $crop = false): bool
    {
        try {
            $image = new self($sourcePath);
            $image->resize($width, $height, $crop);

            return $image->save($destinationPath);
        } catch (Exception $e) {
            logger()->logError('Image::thumbnail error: ' . $e->getMessage(), ['exception' => $e, 'source' => $sourcePath, 'destination' => $destinationPath]);
            return false;
        }
    }

    /**
     * Статический метод: Конвертация формата
     *
     * @param string $sourcePath Исходный путь
     * @param string $destinationPath Путь назначения
     * @param int $targetType Тип изображения (IMAGETYPE_JPEG, IMAGETYPE_PNG и т.д.)
     * @param int|null $quality Качество
     * @return bool
     */
    public static function convert(string $sourcePath, string $destinationPath, int $targetType, ?int $quality = null): bool
    {
        try {
            $image = new self($sourcePath);

            return $image->save($destinationPath, $quality, $targetType);
        } catch (Exception $e) {
            logger()->logError('Image::convert error: ' . $e->getMessage(), ['exception' => $e, 'source' => $sourcePath, 'destination' => $destinationPath]);
            return false;
        }
    }
}
