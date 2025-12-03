<?php

/**
 * Хелпер для роботи з компонентами та шаблонами
 * Спрощує включення компонентів у шаблони
 */

declare(strict_types=1);

/**
 * Включити компонент
 *
 * @param string $componentName Ім'я компонента (без розширення .php)
 * @param array $data Дані для передачі в компонент (будуть витягнуті як змінні)
 * @return void
 */
function includeComponent(string $componentName, array $data = []): void
{
    $componentPath = __DIR__ . '/../components/' . $componentName . '.php';

    if (! file_exists($componentPath)) {
        trigger_error("Component not found: {$componentName}", E_USER_WARNING);

        return;
    }

    // Витягуємо змінні з даних
    extract($data);

    // Включаємо компонент
    include $componentPath;
}

/**
 * Отримати вміст компонента в рядок
 *
 * @param string $componentName Ім'я компонента
 * @param array $data Дані для передачі в компонент
 * @return string Вміст компонента
 */
function getComponent(string $componentName, array $data = []): string
{
    ob_start();
    includeComponent($componentName, $data);

    return ob_get_clean();
}

/**
 * Включити шаблон
 *
 * @param string $templateName Ім'я шаблону (без розширення .php)
 * @param array $data Дані для передачі в шаблон
 * @return void
 */
function includeTemplate(string $templateName, array $data = []): void
{
    $templatePath = __DIR__ . '/../templates/' . $templateName . '.php';

    if (! file_exists($templatePath)) {
        trigger_error("Template not found: {$templateName}", E_USER_WARNING);

        return;
    }

    // Витягуємо змінні з даних
    extract($data);

    // Включаємо шаблон
    include $templatePath;
}

/**
 * Отримати вміст шаблону в рядок
 *
 * @param string $templateName Ім'я шаблону
 * @param array $data Дані для передачі в шаблон
 * @return string Вміст шаблону
 */
function getTemplate(string $templateName, array $data = []): string
{
    ob_start();
    includeTemplate($templateName, $data);

    return ob_get_clean();
}

/**
 * Отримати шлях до ассету
 *
 * @param string $assetPath Шлях до ассету (відносно assets/)
 * @return string Повний URL до ассету
 */
function asset(string $assetPath): string
{
    return UrlHelper::admin('assets/' . ltrim($assetPath, '/'));
}
