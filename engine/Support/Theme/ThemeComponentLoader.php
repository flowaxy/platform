<?php

/**
 * Завантажувач компонентів теми
 *
 * Забезпечує завантаження та рендеринг компонентів, partials, blocks та sections.
 *
 * @package Flowaxy\Core\Support\Theme
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Support\Theme;

final class ThemeComponentLoader
{
    /**
     * @var array<string, mixed> Кеш завантажених компонентів
     */
    private static array $componentCache = [];

    /**
     * @var string|null Поточна директорія теми
     */
    private static ?string $currentThemeDir = null;

    /**
     * Встановлення поточної директорії теми
     *
     * @param string $themeDir
     * @return void
     */
    public static function setThemeDir(string $themeDir): void
    {
        self::$currentThemeDir = rtrim($themeDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Отримання поточної директорії теми
     *
     * @return string|null
     */
    public static function getThemeDir(): ?string
    {
        return self::$currentThemeDir;
    }

    /**
     * Завантаження та рендеринг компонента
     *
     * @param string $name Ім'я компонента
     * @param array<string, mixed> $props Властивості компонента
     * @param bool $return Чи повертати результат замість виводу
     * @return string|void
     */
    public static function component(string $name, array $props = [], bool $return = false)
    {
        if (self::$currentThemeDir === null) {
            if ($return) {
                return '';
            }
            return;
        }

        $componentDir = self::$currentThemeDir . 'components' . DIRECTORY_SEPARATOR . $name;
        $componentFile = $componentDir . DIRECTORY_SEPARATOR . 'component.php';

        if (!file_exists($componentFile)) {
            if ($return) {
                return '';
            }
            return;
        }

        // Екстракція props у змінні
        extract($props, EXTR_SKIP);

        if ($return) {
            ob_start();
            include $componentFile;
            $output = ob_get_clean();

            // Завантажуємо CSS компонента, якщо існує
            $cssFile = $componentDir . DIRECTORY_SEPARATOR . 'component.css';
            if (file_exists($cssFile)) {
                $cssUrl = self::getComponentAssetUrl($name, 'component.css');
                $output = '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl) . '">' . $output;
            }

            return $output;
        }

        // Завантажуємо CSS компонента, якщо існує
        $cssFile = $componentDir . DIRECTORY_SEPARATOR . 'component.css';
        if (file_exists($cssFile)) {
            $cssUrl = self::getComponentAssetUrl($name, 'component.css');
            echo '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl) . '">';
        }

        include $componentFile;
    }

    /**
     * Завантаження partial (header, footer, sidebar)
     *
     * @param string $name Ім'я partial
     * @param array<string, mixed> $data Дані для передачі в partial
     * @param bool $return Чи повертати результат замість виводу
     * @return string|void
     */
    public static function partial(string $name, array $data = [], bool $return = false)
    {
        if (self::$currentThemeDir === null) {
            if ($return) {
                return '';
            }
            return;
        }

        $partialFile = self::$currentThemeDir . 'partials' . DIRECTORY_SEPARATOR . $name . '.php';

        if (!file_exists($partialFile)) {
            if ($return) {
                return '';
            }
            return;
        }

        // Екстракція даних у змінні
        extract($data, EXTR_SKIP);

        if ($return) {
            ob_start();
            include $partialFile;
            return ob_get_clean();
        }

        include $partialFile;
    }

    /**
     * Завантаження блоку контенту
     *
     * @param string $name Ім'я блоку
     * @param array<string, mixed> $data Дані для передачі в блок
     * @param bool $return Чи повертати результат замість виводу
     * @return string|void
     */
    public static function block(string $name, array $data = [], bool $return = false)
    {
        if (self::$currentThemeDir === null) {
            if ($return) {
                return '';
            }
            return;
        }

        $blockFile = self::$currentThemeDir . 'blocks' . DIRECTORY_SEPARATOR . $name . '.php';

        if (!file_exists($blockFile)) {
            if ($return) {
                return '';
            }
            return;
        }

        // Екстракція даних у змінні
        extract($data, EXTR_SKIP);

        if ($return) {
            ob_start();
            include $blockFile;
            return ob_get_clean();
        }

        include $blockFile;
    }

    /**
     * Завантаження секції
     *
     * @param string $name Ім'я секції
     * @param array<string, mixed> $data Дані для передачі в секцію
     * @param bool $return Чи повертати результат замість виводу
     * @return string|void
     */
    public static function section(string $name, array $data = [], bool $return = false)
    {
        // Секції можуть бути в різних місцях
        $possiblePaths = [
            self::$currentThemeDir . 'sections' . DIRECTORY_SEPARATOR . $name . '.php',
            self::$currentThemeDir . 'templates' . DIRECTORY_SEPARATOR . 'sections' . DIRECTORY_SEPARATOR . $name . '.php',
        ];

        $sectionFile = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $sectionFile = $path;
                break;
            }
        }

        if ($sectionFile === null) {
            if ($return) {
                return '';
            }
            return;
        }

        // Екстракція даних у змінні
        extract($data, EXTR_SKIP);

        if ($return) {
            ob_start();
            include $sectionFile;
            return ob_get_clean();
        }

        include $sectionFile;
    }

    /**
     * Завантаження сніпета
     *
     * @param string $name Ім'я сніпета
     * @param array<string, mixed> $data Дані для передачі в сніпет
     * @param bool $return Чи повертати результат замість виводу
     * @return string|void
     */
    public static function snippet(string $name, array $data = [], bool $return = false)
    {
        if (self::$currentThemeDir === null) {
            if ($return) {
                return '';
            }
            return;
        }

        $snippetFile = self::$currentThemeDir . 'snippets' . DIRECTORY_SEPARATOR . $name . '.php';

        if (!file_exists($snippetFile)) {
            if ($return) {
                return '';
            }
            return;
        }

        // Екстракція даних у змінні
        extract($data, EXTR_SKIP);

        if ($return) {
            ob_start();
            include $snippetFile;
            return ob_get_clean();
        }

        include $snippetFile;
    }

    /**
     * Отримання URL asset компонента
     *
     * @param string $componentName Ім'я компонента
     * @param string $assetFile Ім'я файлу asset
     * @return string
     */
    private static function getComponentAssetUrl(string $componentName, string $assetFile): string
    {
        if (self::$currentThemeDir === null) {
            return '';
        }

        // Визначаємо базовий URL теми
        $themeSlug = basename(self::$currentThemeDir);
        $baseUrl = rtrim(
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? ''),
            '/'
        );

        return $baseUrl . '/themes/' . $themeSlug . '/components/' . $componentName . '/' . $assetFile;
    }

    /**
     * Перевірка існування компонента
     *
     * @param string $name Ім'я компонента
     * @return bool
     */
    public static function hasComponent(string $name): bool
    {
        if (self::$currentThemeDir === null) {
            return false;
        }

        $componentFile = self::$currentThemeDir . 'components' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'component.php';
        return file_exists($componentFile);
    }

    /**
     * Перевірка існування partial
     *
     * @param string $name Ім'я partial
     * @return bool
     */
    public static function hasPartial(string $name): bool
    {
        if (self::$currentThemeDir === null) {
            return false;
        }

        $partialFile = self::$currentThemeDir . 'partials' . DIRECTORY_SEPARATOR . $name . '.php';
        return file_exists($partialFile);
    }

    /**
     * Перевірка існування блоку
     *
     * @param string $name Ім'я блоку
     * @return bool
     */
    public static function hasBlock(string $name): bool
    {
        if (self::$currentThemeDir === null) {
            return false;
        }

        $blockFile = self::$currentThemeDir . 'blocks' . DIRECTORY_SEPARATOR . $name . '.php';
        return file_exists($blockFile);
    }

    /**
     * Очищення кешу компонентів
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$componentCache = [];
    }
}
