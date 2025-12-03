<?php

/**
 * Клас для роботи з представленнями (шаблонами)
 * Рендеринг шаблонів з передачею даних
 *
 * @package Engine\Classes\View
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class View
{
    private static string $viewsDir = '';
    private static array $sharedData = [];

    /**
     * Встановлення директорії з шаблонами
     *
     * @param string $dir Шлях до директорії
     * @return void
     */
    public static function setViewsDir(string $dir): void
    {
        self::$viewsDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Отримання директорії з шаблонами
     *
     * @return string
     */
    public static function getViewsDir(): string
    {
        if (empty(self::$viewsDir)) {
            self::$viewsDir = dirname(__DIR__, 2) . '/interface/admin-ui/templates' . DIRECTORY_SEPARATOR;
        }

        return self::$viewsDir;
    }

    /**
     * Встановлення спільних даних для всіх шаблонів
     *
     * @param string $key Ключ
     * @param mixed $value Значення
     * @return void
     */
    public static function share(string $key, $value): void
    {
        self::$sharedData[$key] = $value;
    }

    /**
     * Рендеринг шаблону
     *
     * @param string $template Шлях до шаблону (без розширення)
     * @param array $data Дані для передачі в шаблон
     * @return string HTML вміст
     * @throws Exception Якщо шаблон не знайдено
     */
    public static function render(string $template, array $data = []): string
    {
        $filePath = self::getViewsDir() . $template . '.php';

        if (! file_exists($filePath)) {
            throw new Exception("Шаблон не знайдено: {$template}");
        }

        $data = array_merge(self::$sharedData, $data);
        extract($data, EXTR_SKIP);

        ob_start();
        include $filePath;

        return ob_get_clean();
    }

    /**
     * Перевірка існування шаблону
     *
     * @param string $template Ім'я шаблону
     * @return bool
     */
    public static function exists(string $template): bool
    {
        return file_exists(self::getViewsDir() . $template . '.php');
    }

    /**
     * Включення підшаблону (partial)
     *
     * @param string $template Ім'я шаблону
     * @param array $data Дані
     * @return string
     */
    public static function partial(string $template, array $data = []): string
    {
        return self::render($template, $data);
    }

    /**
     * Екранування HTML
     *
     * @param mixed $value Значення
     * @param string $default Значення за замовчуванням
     * @return string
     */
    public static function escape($value, string $default = ''): string
    {
        if (is_array($value) || is_object($value)) {
            return htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        }

        return htmlspecialchars((string)($value ?: $default), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Екранування атрибутів HTML
     *
     * @param string $value Значення
     * @return string
     */
    public static function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Екранування URL
     *
     * @param string $url URL
     * @return string
     */
    public static function escapeUrl(string $url): string
    {
        return rawurlencode($url);
    }
}
