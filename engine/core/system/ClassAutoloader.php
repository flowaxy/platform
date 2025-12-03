<?php

/**
 * Клас для лінивого автозавантаження системних класів.
 *
 * @package Engine\System
 */

declare(strict_types=1);

require_once __DIR__ . '/../contracts/AutoloaderInterface.php';
require_once __DIR__ . '/../contracts/LoggerInterface.php';

final class ClassAutoloader implements AutoloaderInterface
{
    /**
     * @var array<string,string>
     */
    private array $classMap = [];

    /**
     * @var array<string,string>
     */
    private array $resolvedMap = [];

    /**
     * @var string[]
     */
    private array $directories = [];

    /**
     * @var callable|null
     */
    private $loaderCallable = null;

    private ?LoggerInterface $logger = null;
    private bool $logMissingClasses = false;

    /**
     * @var array<string,int>
     */
    private array $stats = [
        'map_hits' => 0,
        'dir_hits' => 0,
        'misses' => 0,
        'loaded' => 0,
    ];

    public function __construct(private readonly string $rootDir)
    {
    }

    public function register(bool $prepend = false): void
    {
        if ($this->loaderCallable === null) {
            $this->loaderCallable = [$this, 'handleAutoload'];
        }

        spl_autoload_register($this->loaderCallable, true, $prepend);
        $this->logDebug('Автозавантажувач зареєстровано', ['prepend' => $prepend]);
    }

    public function unregister(): void
    {
        if ($this->loaderCallable !== null) {
            spl_autoload_unregister($this->loaderCallable);
            $this->logDebug('Автозавантажувач відвʼязано');
        }
    }

    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function enableMissingClassLogging(bool $enabled = true): void
    {
        $this->logMissingClasses = $enabled;
    }

    public function addClassMap(array $classMap): void
    {
        foreach ($classMap as $className => $relativePath) {
            $path = $this->normalizePath($relativePath);
            $this->classMap[$className] = $path;
        }
    }

    public function addDirectory(string $directory): void
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (! in_array($directory, $this->directories, true)) {
            $this->directories[] = $directory;
            $this->logDebug('Додано директорію до пошуку', ['directory' => $directory]);
        }
    }

    public function addDirectories(array $directories): void
    {
        foreach ($directories as $directory) {
            $this->addDirectory($directory);
        }
    }

    public function loadClass(string $className): bool
    {
        // Перевіряємо class map (працює для класів з namespace та без)
        if (isset($this->classMap[$className])) {
            $this->stats['map_hits']++;

            return $this->requireFile($this->classMap[$className], $className);
        }

        // Для класів з namespace спробуємо конвертувати namespace в шлях
        if (str_contains($className, '\\')) {
            // Перевіряємо resolved map
            if (isset($this->resolvedMap[$className])) {
                $this->stats['dir_hits']++;

                return $this->requireFile($this->resolvedMap[$className], $className);
            }

            // Конвертуємо namespace в шлях файлу
            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';

            // Шукаємо в зареєстрованих директоріях
            foreach ($this->directories as $directory) {
                $file = $directory . $relativePath;
                if (is_file($file)) {
                    $this->resolvedMap[$className] = $file;
                    $this->stats['dir_hits']++;

                    return $this->requireFile($file, $className);
                }
            }

            // Також пробуємо знайти в rootDir/engine/
            $enginePath = $this->rootDir . DIRECTORY_SEPARATOR . $relativePath;
            if (is_file($enginePath)) {
                $this->resolvedMap[$className] = $enginePath;
                $this->stats['dir_hits']++;

                return $this->requireFile($enginePath, $className);
            }

            $this->stats['misses']++;
            $this->logMissing($className, 'Файл для класу з namespace не знайдено');

            return false;
        }

        // Для класів без namespace
        if (isset($this->resolvedMap[$className])) {
            $this->stats['dir_hits']++;

            return $this->requireFile($this->resolvedMap[$className], $className);
        }

        foreach ($this->directories as $directory) {
            $file = $directory . $className . '.php';

            if (is_file($file)) {
                $this->resolvedMap[$className] = $file;
                $this->stats['dir_hits']++;

                return $this->requireFile($file, $className);
            }
        }

        $this->stats['misses']++;
        $this->logMissing($className, 'Файл для класу не знайдено');

        return false;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Основний обробник SPL autoload.
     */
    private function handleAutoload(string $className): void
    {
        $this->loadClass($className);
    }

    private function requireFile(string $file, string $className): bool
    {
        if (! is_file($file) || ! is_readable($file)) {
            $this->stats['misses']++;
            $this->logMissing($className, 'Файл існує, але недоступний', ['file' => $file]);

            return false;
        }

        /** @psalm-suppress UnresolvableInclude */
        require_once $file;
        $this->stats['loaded']++;

        return true;
    }

    private function normalizePath(string $relativePath): string
    {
        if (str_starts_with($relativePath, DIRECTORY_SEPARATOR) || str_contains($relativePath, ':')) {
            return $relativePath;
        }

        return rtrim($this->rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR);
    }

    private function logMissing(string $className, string $message, array $context = []): void
    {
        if (! $this->logMissingClasses) {
            return;
        }

        $this->logWarning(
            $message,
            array_merge(['class' => $className], $context)
        );
    }

    private function logWarning(string $message, array $context = []): void
    {
        if ($this->logger && method_exists($this->logger, 'logWarning')) {
            $this->logger->logWarning($message, $context);
        }
    }

    private function logDebug(string $message, array $context = []): void
    {
        if ($this->logger && method_exists($this->logger, 'logDebug')) {
            $this->logger->logDebug($message, $context);
        }
    }
}
