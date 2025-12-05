<?php

/**
 * Конвеєр обробки ресурсів
 * 
 * Мініфікація та об'єднання CSS/JS файлів
 * 
 * @package Engine\Infrastructure\Assets
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/AssetMinifier.php';

final class AssetPipeline
{
    private AssetMinifier $minifier;
    private bool $minifyEnabled = true;
    private bool $combineEnabled = true;
    private string $outputDir;

    public function __construct(string $outputDir = '')
    {
        $this->minifier = new AssetMinifier();
        $this->outputDir = $outputDir ?: (defined('STORAGE_DIR') ? STORAGE_DIR . '/assets' : dirname(__DIR__, 3) . '/storage/assets');
        $this->outputDir = rtrim($this->outputDir, '/') . '/';
        $this->ensureOutputDir();
    }

    /**
     * Обробка та об'єднання файлів
     * 
     * @param array<string> $files Масив шляхів до файлів
     * @param string $type Тип файлів (css/js)
     * @return string Шлях до обробленого файлу
     */
    public function process(array $files, string $type): string
    {
        $combined = $this->combineFiles($files, $type);
        
        if ($this->minifyEnabled) {
            $combined = $this->minifier->minify($combined, $type);
        }

        $hash = md5(implode('|', $files) . $combined);
        $filename = "combined_{$hash}.{$type}";
        $filepath = $this->outputDir . $filename;

        file_put_contents($filepath, $combined);

        return $filepath;
    }

    /**
     * Об'єднання файлів
     */
    private function combineFiles(array $files, string $type): string
    {
        if (!$this->combineEnabled) {
            return '';
        }

        $content = [];

        foreach ($files as $file) {
            if (file_exists($file) && is_readable($file)) {
                $fileContent = file_get_contents($file);
                
                // Додаємо коментар з назвою файлу для відлагодження
                if (!$this->minifyEnabled) {
                    $content[] = "\n/* {$file} */\n";
                }
                
                $content[] = $fileContent;
            }
        }

        return implode("\n", $content);
    }

    /**
     * Увімкнення/вимкнення мініфікації
     */
    public function setMinifyEnabled(bool $enabled): self
    {
        $this->minifyEnabled = $enabled;
        return $this;
    }

    /**
     * Увімкнення/вимкнення об'єднання
     */
    public function setCombineEnabled(bool $enabled): self
    {
        $this->combineEnabled = $enabled;
        return $this;
    }

    /**
     * Створення директорії виводу
     */
    private function ensureOutputDir(): void
    {
        if (!is_dir($this->outputDir)) {
            @mkdir($this->outputDir, 0755, true);
        }
    }
}

