<?php

/**
 * Theme API
 *
 * Централізований API для роботи з темами: рендеринг, assets, layouts, components.
 *
 * @package Flowaxy\Core\Support\Theme
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Support\Theme;

use Flowaxy\Core\Support\Containers\ThemeContainer;
use Flowaxy\Core\Support\Containers\ThemeContainerFactory;

final class Theme
{
    /**
     * @var ThemeContainer|null Поточний контейнер активної теми
     */
    private static ?ThemeContainer $currentContainer = null;

    /**
     * Встановлення активної теми
     *
     * @param string $themeSlug Slug теми
     * @return void
     */
    public static function setActive(string $themeSlug): void
    {
        $container = ThemeContainerFactory::get($themeSlug);
        if ($container === null) {
            throw new \RuntimeException("Theme container not found: {$themeSlug}");
        }

        self::$currentContainer = $container;
        ThemeComponentLoader::setThemeDir($container->getThemeDir());
    }

    /**
     * Отримання поточного контейнера теми
     *
     * @return ThemeContainer|null
     */
    public static function getContainer(): ?ThemeContainer
    {
        if (self::$currentContainer === null) {
            $activeContainer = ThemeContainerFactory::getActive();
            if ($activeContainer !== null) {
                self::$currentContainer = $activeContainer;
                ThemeComponentLoader::setThemeDir($activeContainer->getThemeDir());
            }
        }

        return self::$currentContainer;
    }

    /**
     * Отримання URL asset теми
     *
     * @param string $path Відносний шлях до asset
     * @param bool $versioning Чи додавати версію для cache busting
     * @return string
     */
    public static function asset(string $path, bool $versioning = true): string
    {
        $container = self::getContainer();
        if ($container === null) {
            return '';
        }

        $themeSlug = $container->getThemeSlug();
        $baseUrl = self::getBaseUrl();

        $assetPath = ltrim($path, '/');
        $url = $baseUrl . '/themes/' . $themeSlug . '/assets/' . $assetPath;

        if ($versioning && file_exists($container->getAssetPath($assetPath))) {
            $filePath = $container->getAssetPath($assetPath);
            $version = filemtime($filePath);
            $url .= '?v=' . $version;
        }

        return $url;
    }

    /**
     * Рендеринг шаблону
     *
     * @param string $template Ім'я шаблону
     * @param array<string, mixed> $data Дані для передачі в шаблон
     * @param bool $return Чи повертати результат замість виводу
     * @return string|void
     */
    public static function render(string $template, array $data = [], bool $return = false)
    {
        $container = self::getContainer();
        if ($container === null) {
            if ($return) {
                return '';
            }
            return;
        }

        $templateFile = $container->getTemplatePath($template);

        if (!file_exists($templateFile)) {
            if ($return) {
                return '';
            }
            return;
        }

        // Екстракція даних у змінні
        extract($data, EXTR_SKIP);

        if ($return) {
            ob_start();
            include $templateFile;
            return ob_get_clean();
        }

        include $templateFile;
    }

    /**
     * Рендеринг макету
     *
     * @param string $layout Ім'я макету
     * @param array<string, mixed> $data Дані для передачі в макет
     * @param bool $return Чи повертати результат замість виводу
     * @return string|void
     */
    public static function layout(string $layout, array $data = [], bool $return = false)
    {
        $container = self::getContainer();
        if ($container === null) {
            if ($return) {
                return '';
            }
            return;
        }

        $layoutFile = $container->getThemePath('layouts' . DIRECTORY_SEPARATOR . $layout . '.php');

        if (!file_exists($layoutFile)) {
            if ($return) {
                return '';
            }
            return;
        }

        // Екстракція даних у змінні
        extract($data, EXTR_SKIP);

        if ($return) {
            ob_start();
            include $layoutFile;
            return ob_get_clean();
        }

        include $layoutFile;
    }

    /**
     * Рендеринг компонента
     *
     * @param string $name Ім'я компонента
     * @param array<string, mixed> $props Властивості компонента
     * @param bool $return Чи повертати результат замість виводу
     * @return string|void
     */
    public static function component(string $name, array $props = [], bool $return = false)
    {
        return ThemeComponentLoader::component($name, $props, $return);
    }

    /**
     * Рендеринг partial
     *
     * @param string $name Ім'я partial
     * @param array<string, mixed> $data Дані для передачі в partial
     * @param bool $return Чи повертати результат замість виводу
     * @return string|void
     */
    public static function partial(string $name, array $data = [], bool $return = false)
    {
        return ThemeComponentLoader::partial($name, $data, $return);
    }

    /**
     * Рендеринг блоку
     *
     * @param string $name Ім'я блоку
     * @param array<string, mixed> $data Дані для передачі в блок
     * @param bool $return Чи повертати результат замість виводу
     * @return string|void
     */
    public static function block(string $name, array $data = [], bool $return = false)
    {
        return ThemeComponentLoader::block($name, $data, $return);
    }

    /**
     * Рендеринг секції
     *
     * @param string $name Ім'я секції
     * @param array<string, mixed> $data Дані для передачі в секцію
     * @param bool $return Чи повертати результат замість виводу
     * @return string|void
     */
    public static function section(string $name, array $data = [], bool $return = false)
    {
        return ThemeComponentLoader::section($name, $data, $return);
    }

    /**
     * Рендеринг сніпета
     *
     * @param string $name Ім'я сніпета
     * @param array<string, mixed> $data Дані для передачі в сніпет
     * @param bool $return Чи повертати результат замість виводу
     * @return string|void
     */
    public static function snippet(string $name, array $data = [], bool $return = false)
    {
        return ThemeComponentLoader::snippet($name, $data, $return);
    }

    /**
     * Розширення макету (extend pattern)
     *
     * @param string $layout Ім'я макету для розширення
     * @param array<string, callable> $sections Секції для вставки в макет
     * @param array<string, mixed> $data Додаткові дані для макету
     * @param bool $return Чи повертати результат замість виводу
     * @return string|void
     */
    public static function extend(string $layout, array $sections = [], array $data = [], bool $return = false)
    {
        $container = self::getContainer();
        if ($container === null) {
            if ($return) {
                return '';
            }
            return;
        }

        $layoutFile = $container->getThemePath('layouts' . DIRECTORY_SEPARATOR . $layout . '.php');

        if (!file_exists($layoutFile)) {
            if ($return) {
                return '';
            }
            return;
        }

        // Зберігаємо секції для використання в макеті
        $GLOBALS['_theme_sections'] = $sections;

        // Екстракція даних у змінні
        extract($data, EXTR_SKIP);

        if ($return) {
            ob_start();
            include $layoutFile;
            return ob_get_clean();
        }

        include $layoutFile;
    }

    /**
     * Отримання вмісту секції (для використання в макетах)
     *
     * @param string $name Ім'я секції
     * @param string $default Значення за замовчуванням
     * @return string
     */
    public static function yield(string $name, string $default = ''): string
    {
        $sections = $GLOBALS['_theme_sections'] ?? [];

        if (isset($sections[$name]) && is_callable($sections[$name])) {
            ob_start();
            call_user_func($sections[$name]);
            return ob_get_clean();
        }

        return $default;
    }

    /**
     * Отримання базового URL
     *
     * @return string
     */
    private static function getBaseUrl(): string
    {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return rtrim($protocol . '://' . $host, '/');
    }

    /**
     * Отримання директорії теми
     *
     * @return string|null
     */
    public static function getDir(): ?string
    {
        $container = self::getContainer();
        return $container?->getThemeDir();
    }

    /**
     * Отримання URL теми
     *
     * @return string
     */
    public static function getUrl(): string
    {
        $container = self::getContainer();
        if ($container === null) {
            return '';
        }

        $themeSlug = $container->getThemeSlug();
        $baseUrl = self::getBaseUrl();

        return $baseUrl . '/themes/' . $themeSlug;
    }

    /**
     * Отримання slug теми
     *
     * @return string|null
     */
    public static function getSlug(): ?string
    {
        $container = self::getContainer();
        return $container?->getThemeSlug();
    }

    /**
     * Перевірка існування компонента
     *
     * @param string $name Ім'я компонента
     * @return bool
     */
    public static function hasComponent(string $name): bool
    {
        return ThemeComponentLoader::hasComponent($name);
    }

    /**
     * Перевірка існування partial
     *
     * @param string $name Ім'я partial
     * @return bool
     */
    public static function hasPartial(string $name): bool
    {
        return ThemeComponentLoader::hasPartial($name);
    }

    /**
     * Перевірка існування блоку
     *
     * @param string $name Ім'я блоку
     * @return bool
     */
    public static function hasBlock(string $name): bool
    {
        return ThemeComponentLoader::hasBlock($name);
    }
}
