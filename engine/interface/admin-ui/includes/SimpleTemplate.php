<?php

/**
 * Простий шаблонізатор без складностей
 * Тільки основні функції без MVC
 */

declare(strict_types=1);

class SimpleTemplate
{
    private string $templateDir;
    private array $data = [];

    public function __construct()
    {
        $this->templateDir = __DIR__ . '/../templates/';
    }

    /**
     * Рендерити шаблон
     */
    public function render(string $template, array $data = []): void
    {
        // Переконуємося, що всі необхідні класи завантажені
        $this->ensureClassesLoaded();

        // Об'єднуємо дані
        $this->data = array_merge($this->data, $data);

        // Витягуємо змінні
        extract($this->data);

        // Підключаємо базовий layout
        include __DIR__ . '/../layouts/base.php';
    }

    private function ensureClassesLoaded(): void
    {
        // Класи завантажуються автоматично через автозавантажувач
    }

    /**
     * Отримує контент шаблону (використовує View клас)
     */
    public function getContent(string $templateName, array $data = []): string
    {
        $templateData = array_merge($this->data, $data);

        try {
            // Використовуємо View клас для рендерингу
            $viewPath = str_replace('.php', '', $templateName);

            return View::render($viewPath, $templateData);
        } catch (Exception $e) {
            // Fallback на старий метод
            extract($templateData);
            $templateFile = $this->templateDir . $templateName . '.php';
            if (file_exists($templateFile)) {
                ob_start();
                include $templateFile;

                return ob_get_clean();
            }
        }

        return '';
    }

    /**
     * Рендерить компонент
     */
    public function component(string $component, array $data = []): void
    {
        $componentData = array_merge($this->data, $data);
        extract($componentData);

        // Компоненти тепер знаходяться в engine/interface/admin-ui/components/
        $componentFile = __DIR__ . '/../components/' . $component . '.php';
        if (file_exists($componentFile)) {
            include $componentFile;
        }
    }

    /**
     * Додає дані
     */
    public function assign(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Екранує HTML (використовує Security клас)
     */
    public function escape(string $string): string
    {
        return Security::clean($string);
    }

    /**
     * Форматує дату
     */
    public function formatDate(string|DateTime $date, string $format = 'd.m.Y H:i'): string
    {
        if (is_string($date)) {
            $date = new DateTime($date);
        }

        return $date instanceof DateTime ? $date->format($format) : '';
    }

    /**
     * Форматує число
     */
    public function formatNumber(int|float $number, int $decimals = 0): string
    {
        return number_format((float)$number, $decimals, ',', ' ');
    }
}

/**
 * Глобальна функція для рендерингу
 */
function renderTemplate(string $template, array $data = []): void
{
    $engine = new SimpleTemplate();
    $engine->render($template, $data);
}
