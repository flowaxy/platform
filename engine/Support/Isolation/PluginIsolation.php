<?php

/**
 * Система ізоляції плагінів
 *
 * Забезпечує, щоб плагіни не могли напряму звертатися до ядра та тем.
 * Всі взаємодії повинні відбуватися через хуки та фільтри.
 *
 * @package Flowaxy\Core\Support\Isolation
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Support\Isolation;

final class PluginIsolation
{
    /**
     * @var string Коренева директорія проекту
     */
    private static string $projectRoot = '';

    /**
     * @var string Директорія ядра
     */
    private static string $engineDir = '';

    /**
     * @var string Директорія тем
     */
    private static string $themesDir = '';

    /**
     * @var string Директорія плагінів
     */
    private static string $pluginsDir = '';

    /**
     * @var bool Чи увімкнена ізоляція
     */
    private static bool $isolationEnabled = true;

    /**
     * Ініціалізація системи ізоляції
     *
     * @param string $projectRoot Коренева директорія проекту
     * @return void
     */
    public static function initialize(string $projectRoot): void
    {
        self::$projectRoot = rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR;
        self::$engineDir = self::$projectRoot . 'engine' . DIRECTORY_SEPARATOR;
        self::$themesDir = self::$projectRoot . 'themes' . DIRECTORY_SEPARATOR;
        self::$pluginsDir = self::$projectRoot . 'plugins' . DIRECTORY_SEPARATOR;
    }

    /**
     * Увімкнути/вимкнути ізоляцію
     *
     * @param bool $enabled
     * @return void
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$isolationEnabled = $enabled;
    }

    /**
     * Перевірка, чи увімкнена ізоляція
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        return self::$isolationEnabled;
    }

    /**
     * Перевірка, чи шлях знаходиться в межах дозволеної директорії плагіна
     *
     * @param string $filePath Шлях до файлу
     * @param string $pluginDir Директорія плагіна
     * @return bool
     */
    public static function isPathAllowed(string $filePath, string $pluginDir): bool
    {
        if (!self::$isolationEnabled) {
            return true;
        }

        $realFilePath = realpath($filePath);
        $realPluginDir = realpath($pluginDir);

        if ($realFilePath === false || $realPluginDir === false) {
            return false;
        }

        // Шлях повинен бути всередині директорії плагіна
        return str_starts_with($realFilePath, $realPluginDir);
    }

    /**
     * Перевірка, чи шлях належить до ядра
     *
     * @param string $filePath Шлях до файлу
     * @return bool
     */
    public static function isEnginePath(string $filePath): bool
    {
        if (empty(self::$engineDir)) {
            return false;
        }

        $realFilePath = realpath($filePath);
        $realEngineDir = realpath(self::$engineDir);

        if ($realFilePath === false || $realEngineDir === false) {
            return false;
        }

        return str_starts_with($realFilePath, $realEngineDir);
    }

    /**
     * Перевірка, чи шлях належить до тем
     *
     * @param string $filePath Шлях до файлу
     * @return bool
     */
    public static function isThemePath(string $filePath): bool
    {
        if (empty(self::$themesDir)) {
            return false;
        }

        $realFilePath = realpath($filePath);
        $realThemesDir = realpath(self::$themesDir);

        if ($realFilePath === false || $realThemesDir === false) {
            return false;
        }

        return str_starts_with($realFilePath, $realThemesDir);
    }

    /**
     * Блокування доступу до файлу ядра або теми
     *
     * @param string $filePath Шлях до файлу
     * @param string $pluginDir Директорія плагіна
     * @return void
     * @throws \RuntimeException Якщо доступ заборонено
     */
    public static function blockAccess(string $filePath, string $pluginDir): void
    {
        if (!self::$isolationEnabled) {
            return;
        }

        // Перевіряємо, чи шлях дозволений для плагіна
        if (self::isPathAllowed($filePath, $pluginDir)) {
            return;
        }

        // Блокуємо доступ до ядра
        if (self::isEnginePath($filePath)) {
            throw new \RuntimeException(
                "Плагін не може напряму звертатися до файлів ядра. " .
                "Використовуйте хуки та фільтри для взаємодії з системою."
            );
        }

        // Блокуємо доступ до тем
        if (self::isThemePath($filePath)) {
            throw new \RuntimeException(
                "Плагін не може напряму звертатися до файлів тем. " .
                "Використовуйте хуки та фільтри для взаємодії з темами."
            );
        }
    }

    /**
     * Перевірка, чи клас належить до ядра
     *
     * @param string $className Повне ім'я класу
     * @return bool
     */
    public static function isCoreClass(string $className): bool
    {
        // Перевіряємо namespace
        $coreNamespaces = [
            'Flowaxy\\Core\\',
            'Engine\\',
        ];

        foreach ($coreNamespaces as $namespace) {
            if (str_starts_with($className, $namespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Блокування прямого створення екземплярів класів ядра
     *
     * @param string $className Повне ім'я класу
     * @param string $pluginSlug Slug плагіна
     * @return void
     * @throws \RuntimeException Якщо доступ заборонено
     */
    public static function blockCoreClassInstantiation(string $className, string $pluginSlug): void
    {
        if (!self::$isolationEnabled) {
            return;
        }

        if (self::isCoreClass($className)) {
            throw new \RuntimeException(
                "Плагін '{$pluginSlug}' не може напряму створювати екземпляри класів ядра. " .
                "Використовуйте хуки та фільтри для взаємодії з системою."
            );
        }
    }

    /**
     * Перевірка, чи дозволено використання функції
     *
     * @param string $functionName Назва функції
     * @return bool
     */
    public static function isFunctionAllowed(string $functionName): bool
    {
        if (!self::$isolationEnabled) {
            return true;
        }

        // Список заборонених функцій для плагінів
        $forbiddenFunctions = [
            'eval',
            'exec',
            'system',
            'shell_exec',
            'passthru',
            'proc_open',
            'popen',
            'file_get_contents', // Може бути використана для читання файлів ядра
            'file_put_contents', // Може бути використана для зміни файлів ядра
            'fopen',
            'fwrite',
            'fread',
            'unlink', // Видалення файлів
            'rmdir',
            'mkdir', // Створення директорій поза плагіном
        ];

        return !in_array(strtolower($functionName), $forbiddenFunctions, true);
    }

    /**
     * Отримання дозволених API для плагінів
     *
     * @return array<string> Список дозволених функцій/класів
     */
    public static function getAllowedApi(): array
    {
        return [
            // Глобальні функції для роботи з хуками
            'hooks',
            'addHook',
            'doHook',
            'applyFilter',

            // Глобальні функції для роботи з контейнером
            'container',

            // Глобальні функції для роботи з кешем
            'cache_get',
            'cache_set',
            'cache_forget',
            'cache_remember',

            // Глобальні функції для логування
            'logger',

            // Глобальні функції для роботи з БД (обмежений доступ)
            'db', // Тільки через обгортку
        ];
    }

    /**
     * Перевірка, чи виклик функції дозволений
     *
     * @param string $functionName Назва функції
     * @param string $pluginSlug Slug плагіна
     * @return void
     * @throws \RuntimeException Якщо виклик заборонено
     */
    public static function validateFunctionCall(string $functionName, string $pluginSlug): void
    {
        if (!self::$isolationEnabled) {
            return;
        }

        if (!self::isFunctionAllowed($functionName)) {
            throw new \RuntimeException(
                "Плагін '{$pluginSlug}' не може викликати функцію '{$functionName}'. " .
                "Ця функція заборонена для безпеки."
            );
        }
    }
}
