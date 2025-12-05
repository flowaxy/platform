<?php

/**
 * Валідатор структури адмін-панелі
 *
 * Перевіряє, чи відповідає структура адмін-панелі встановленим стандартам.
 *
 * @package Flowaxy\Core\Interface\AdminUI
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Interface\AdminUI;

use Flowaxy\Core\Infrastructure\Filesystem\Directory;
use Flowaxy\Core\Infrastructure\Filesystem\File;

final class AdminStructureValidator
{
    /**
     * Стандартна структура адмін-панелі
     *
     * @var array<string, array<string, mixed>>
     */
    private array $standardStructure = [
        'assets' => ['required' => true, 'type' => 'directory', 'description' => 'Статичні ресурси (CSS, JS, зображення)'],
        'assets/images' => ['required' => false, 'type' => 'directory', 'description' => 'Зображення'],
        'assets/scripts' => ['required' => false, 'type' => 'directory', 'description' => 'JavaScript файли'],
        'assets/styles' => ['required' => false, 'type' => 'directory', 'description' => 'CSS файли'],
        'components' => ['required' => true, 'type' => 'directory', 'description' => 'UI компоненти'],
        'includes' => ['required' => true, 'type' => 'directory', 'description' => 'Допоміжні класи та файли'],
        'includes/AdminPage.php' => ['required' => true, 'type' => 'file', 'description' => 'Базовий клас для сторінок'],
        'layouts' => ['required' => true, 'type' => 'directory', 'description' => 'Макети сторінок'],
        'layouts/base.php' => ['required' => true, 'type' => 'file', 'description' => 'Базовий макет'],
        'pages' => ['required' => true, 'type' => 'directory', 'description' => 'Класи сторінок'],
        'templates' => ['required' => true, 'type' => 'directory', 'description' => 'Шаблони сторінок'],
    ];

    /**
     * Валідує структуру адмін-панелі
     *
     * @param string $adminDir Абсолютний шлях до директорії адмін-панелі
     * @return array<string, mixed> Результат валідації (valid, errors, warnings, structure)
     */
    public function validate(string $adminDir): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'structure' => [],
        ];

        if (!is_dir($adminDir)) {
            $results['valid'] = false;
            $results['errors'][] = "Директорія адмін-панелі не існує: {$adminDir}";
            return $results;
        }

        foreach ($this->standardStructure as $item => $rules) {
            $path = $adminDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $item);
            $exists = false;

            if ($rules['type'] === 'file') {
                $exists = File::exists($path);
            } elseif ($rules['type'] === 'directory') {
                $exists = Directory::exists($path);
            }

            if ($rules['required'] && !$exists) {
                $results['valid'] = false;
                $results['errors'][] = "Відсутній обов'язковий компонент: {$item}";
            } elseif (!$rules['required'] && !$exists) {
                $results['warnings'][] = "Рекомендований компонент відсутній: {$item}";
            }

            $results['structure'][$item] = [
                'exists' => $exists,
                'required' => $rules['required'],
                'type' => $rules['type'],
                'description' => $rules['description'],
            ];
        }

        // Додаткові перевірки
        $this->validatePages($adminDir, $results);
        $this->validateTemplates($adminDir, $results);
        $this->validateComponents($adminDir, $results);

        return $results;
    }

    /**
     * Перевірка сторінок
     *
     * @param string $adminDir
     * @param array<string, mixed> $results
     * @return void
     */
    private function validatePages(string $adminDir, array &$results): void
    {
        $pagesDir = $adminDir . DIRECTORY_SEPARATOR . 'pages';
        if (!Directory::exists($pagesDir)) {
            return;
        }

        $pages = glob($pagesDir . DIRECTORY_SEPARATOR . '*Page.php');
        if (empty($pages)) {
            $results['warnings'][] = 'Не знайдено жодного класу сторінки в директорії pages/';
            return;
        }

        foreach ($pages as $pageFile) {
            $content = File::get($pageFile);
            if ($content === null) {
                continue;
            }

            // Перевіряємо, чи клас розширює AdminPage
            if (!str_contains($content, 'extends AdminPage') && !str_contains($content, 'extends \\Flowaxy\\Core\\Interface\\AdminUI\\AdminPage')) {
                $pageName = basename($pageFile);
                $results['warnings'][] = "Клас сторінки {$pageName} не розширює AdminPage";
            }
        }
    }

    /**
     * Перевірка шаблонів
     *
     * @param string $adminDir
     * @param array<string, mixed> $results
     * @return void
     */
    private function validateTemplates(string $adminDir, array &$results): void
    {
        $templatesDir = $adminDir . DIRECTORY_SEPARATOR . 'templates';
        if (!Directory::exists($templatesDir)) {
            return;
        }

        $templates = glob($templatesDir . DIRECTORY_SEPARATOR . '*.php');
        if (empty($templates)) {
            $results['warnings'][] = 'Не знайдено жодного шаблону в директорії templates/';
        }
    }

    /**
     * Перевірка компонентів
     *
     * @param string $adminDir
     * @param array<string, mixed> $results
     * @return void
     */
    private function validateComponents(string $adminDir, array &$results): void
    {
        $componentsDir = $adminDir . DIRECTORY_SEPARATOR . 'components';
        if (!Directory::exists($componentsDir)) {
            return;
        }

        $components = glob($componentsDir . DIRECTORY_SEPARATOR . '*.php');
        if (empty($components)) {
            $results['warnings'][] = 'Не знайдено жодного компонента в директорії components/';
        }
    }

    /**
     * Повертає стандартну структуру адмін-панелі
     *
     * @return array<string, array<string, mixed>>
     */
    public function getStandardStructure(): array
    {
        return $this->standardStructure;
    }
}

