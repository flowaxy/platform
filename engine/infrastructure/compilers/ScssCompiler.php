<?php

/**
 * Компілятор SCSS в CSS
 * Підтримка компіляції SCSS файлів для тем
 *
 * @package Core
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class ScssCompiler
{
    private string $scssDir;
    private string $cssDir;
    private string $themePath;
    private string $mainScssFile;
    private string $outputCssFile;

    /**
     * Конструктор
     *
     * @param string $themePath Шлях до папки теми
     * @param string $mainScssFile Головний файл SCSS (наприклад, main.scss)
     * @param string $outputCssFile Вихідний файл CSS
     */
    public function __construct(string $themePath, string $mainScssFile = 'assets/scss/main.scss', string $outputCssFile = 'assets/css/style.css')
    {
        $this->themePath = rtrim($themePath, '/') . '/';
        $this->mainScssFile = $mainScssFile;
        $this->outputCssFile = $outputCssFile;
        $this->scssDir = $this->themePath . dirname($mainScssFile) . '/';
        $this->cssDir = $this->themePath . dirname($outputCssFile) . '/';
    }

    /**
     * Перевірка наявності SCSS файлів в темі
     *
     * @return bool
     */
    public function hasScssFiles(): bool
    {
        $mainFile = $this->themePath . $this->mainScssFile;

        return file_exists($mainFile) && is_readable($mainFile);
    }

    /**
     * Компіляція SCSS в CSS
     *
     * @param bool $force Примусова перекомпіляція
     * @return bool Успішність компіляції
     */
    public function compile(bool $force = false): bool
    {
        $mainFile = $this->themePath . $this->mainScssFile;

        if (! file_exists($mainFile) || ! is_readable($mainFile)) {
            error_log("ScssCompiler: Main SCSS file not found: {$mainFile}");

            return false;
        }

        $outputFile = $this->themePath . $this->outputCssFile;

        // Перевіряємо, чи потрібно компілювати
        if (! $force && file_exists($outputFile)) {
            // Перевіряємо, чи змінилися SCSS файли
            if ($this->isUpToDate($mainFile, $outputFile)) {
                return true; // Вже скомпільовано і актуально
            }
        }

        // Створюємо директорію для CSS, якщо не існує
        if (! is_dir($this->cssDir)) {
            if (! mkdir($this->cssDir, 0755, true)) {
                error_log("ScssCompiler: Cannot create CSS directory: {$this->cssDir}");

                return false;
            }
        }

        // Намагаємося використати scssphp, якщо доступний
        if ($this->compileWithScssphp($mainFile, $outputFile)) {
            return true;
        }

        // Намагаємося використати зовнішній процес (sass/node-sass)
        if ($this->compileWithExternalProcess($mainFile, $outputFile)) {
            return true;
        }

        error_log('ScssCompiler: No SCSS compiler available');

        return false;
    }

    /**
     * Компіляція з використанням scssphp
     *
     * @param string $inputFile Вхідний файл SCSS
     * @param string $outputFile Вихідний файл CSS
     * @return bool
     */
    private function compileWithScssphp(string $inputFile, string $outputFile): bool
    {
        // Намагаємося завантажити scssphp
        $scssphpFile = __DIR__ . '/../vendor/scssphp/scssphp/src/Compiler.php';
        if (file_exists($scssphpFile)) {
            require_once $scssphpFile;
        } elseif (class_exists('ScssPhp\ScssPhp\Compiler')) {
            // Класс уже загружен
        } else {
            // Пробуем найти через autoload
            if (function_exists('spl_autoload_call')) {
                try {
                    spl_autoload_call('ScssPhp\ScssPhp\Compiler');
                } catch (Exception $e) {
                    // Игнорируем ошибку
                }
            }
        }

        $compilerClass = 'ScssPhp\ScssPhp\Compiler';
        if (! class_exists($compilerClass)) {
            return false;
        }

        try {
            // Створюємо екземпляр через змінну для уникнення помилок статичного аналізу
            $compiler = new $compilerClass();
            $compiler->setImportPaths([$this->scssDir]);

            $scssContent = file_get_contents($inputFile);
            if ($scssContent === false) {
                return false;
            }

            $cssContent = $compiler->compileString($scssContent)->getCss();

            if (file_put_contents($outputFile, $cssContent) === false) {
                error_log("ScssCompiler: Cannot write output file: {$outputFile}");

                return false;
            }

            return true;
        } catch (Exception $e) {
            error_log('ScssCompiler: scssphp compilation error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Компиляция с использованием внешнего процесса (sass/node-sass)
     *
     * @param string $inputFile Входной файл SCSS
     * @param string $outputFile Выходной файл CSS
     * @return bool
     */
    private function compileWithExternalProcess(string $inputFile, string $outputFile): bool
    {
        // Пробуем sass (Dart Sass)
        $sassCommand = 'sass';
        if ($this->commandExists($sassCommand)) {
            $command = sprintf(
                '%s "%s" "%s" --no-source-map --style compressed 2>&1',
                escapeshellcmd($sassCommand),
                escapeshellarg($inputFile),
                escapeshellarg($outputFile)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($outputFile)) {
                return true;
            }

            error_log('ScssCompiler: sass command failed: ' . implode("\n", $output));
        }

        // Пробуем node-sass
        $nodeSassCommand = 'node-sass';
        if ($this->commandExists($nodeSassCommand)) {
            $command = sprintf(
                '%s "%s" --output "%s" --output-style compressed --source-map false 2>&1',
                escapeshellcmd($nodeSassCommand),
                escapeshellarg($inputFile),
                escapeshellarg(dirname($outputFile))
            );

            exec($command, $output, $returnCode);

            $expectedOutput = dirname($outputFile) . '/' . basename($inputFile, '.scss') . '.css';
            if ($returnCode === 0 && file_exists($expectedOutput)) {
                // Переименовываем, если нужно
                if ($expectedOutput !== $outputFile) {
                    rename($expectedOutput, $outputFile);
                }

                return true;
            }

            error_log('ScssCompiler: node-sass command failed: ' . implode("\n", $output));
        }

        return false;
    }

    /**
     * Проверка, актуальны ли скомпилированные файлы
     *
     * @param string $mainFile Главный SCSS файл
     * @param string $outputFile Выходной CSS файл
     * @return bool
     */
    private function isUpToDate(string $mainFile, string $outputFile): bool
    {
        if (! file_exists($outputFile)) {
            return false;
        }

        $mainFileTime = filemtime($mainFile);
        $outputFileTime = filemtime($outputFile);

        if ($mainFileTime > $outputFileTime) {
            return false;
        }

        // Проверяем все SCSS файлы в директории
        $scssFiles = $this->getAllScssFiles($this->scssDir);
        foreach ($scssFiles as $file) {
            if (file_exists($file) && filemtime($file) > $outputFileTime) {
                return false;
            }
        }

        return true;
    }

    /**
     * Получение всех SCSS файлов в директории рекурсивно
     *
     * @param string $dir Директория
     * @return array
     */
    private function getAllScssFiles(string $dir): array
    {
        $files = [];

        if (! is_dir($dir)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'scss') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Проверка существования команды в системе
     *
     * @param string $command Команда
     * @return bool
     */
    private function commandExists(string $command): bool
    {
        $whereIsCommand = (PHP_OS === 'WINNT') ? 'where' : 'which';

        $process = proc_open(
            "$whereIsCommand $command",
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if ($process === false) {
            return false;
        }

        $output = stream_get_contents($pipes[1]);
        proc_close($process);

        return ! empty($output);
    }

    /**
     * Получение URL скомпилированного CSS файла
     *
     * @return string|null
     */
    public function getCssUrl(): ?string
    {
        $outputFile = $this->themePath . $this->outputCssFile;

        if (! file_exists($outputFile)) {
            return null;
        }

        $themeSlug = basename($this->themePath);
        // Используем UrlHelper для получения актуального URL с правильным протоколом
        if (class_exists('UrlHelper')) {
            return UrlHelper::site('/themes/' . $themeSlug . '/' . $this->outputCssFile);
        }
        // Fallback на константу, если UrlHelper не доступен
        $siteUrl = defined('SITE_URL') ? SITE_URL : '';

        return $siteUrl . '/themes/' . $themeSlug . '/' . $this->outputCssFile;
    }

    /**
     * Очистка скомпилированных файлов
     *
     * @return bool
     */
    public function clean(): bool
    {
        $outputFile = $this->themePath . $this->outputCssFile;

        if (file_exists($outputFile)) {
            return unlink($outputFile);
        }

        return true;
    }
}
