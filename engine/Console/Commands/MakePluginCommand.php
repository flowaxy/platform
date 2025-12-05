<?php

/**
 * Команда генерації плагіна
 *
 * @package Flowaxy\Core\System\Commands
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Commands;

class MakePluginCommand extends MakeCommand
{
    /**
     * Генерація плагіна
     *
     * @param array $args Аргументи команди
     * @return void
     */
    public function run(array $args): void
    {
        if (empty($args[0])) {
            echo "Помилка: не вказано slug плагіна\n";
            echo "Використання: make:plugin <plugin-slug> [--name=Plugin Name] [--version=1.0.0]\n";
            exit(1);
        }

        $pluginSlug = $this->normalizeSlug($args[0] ?? '');
        if (empty($pluginSlug)) {
            echo "Помилка: не вказано slug плагіна\n";
            echo "Використання: make:plugin <plugin-slug> [--name=Plugin Name] [--version=1.0.0]\n";
            exit(1);
        }

        $pluginName = $args['name'] ?? ucfirst(str_replace('-', ' ', $pluginSlug));
        $version = $args['version'] ?? '1.0.0';
        $author = $args['author'] ?? 'Flowaxy CMS';

        $pluginDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $pluginSlug;

        if (is_dir($pluginDir)) {
            echo "Помилка: директорія плагіна {$pluginDir} вже існує\n";
            exit(1);
        }

        // Створюємо структуру плагіна
        $this->createPluginStructure($pluginDir, $pluginSlug, $pluginName, $version, $author);

        echo "✓ Плагін {$pluginName} успішно створено: {$pluginDir}\n";
        echo "  Структура:\n";
        echo "    - plugin.json\n";
        echo "    - Plugin.php\n";
        echo "    - src/Controllers/\n";
        echo "    - src/Models/\n";
        echo "    - assets/css/\n";
        echo "    - assets/js/\n";
        echo "    - templates/\n";
        echo "    - routes.php\n";
        echo "    - README.md\n";
    }

    /**
     * Створення структури плагіна
     *
     * @param string $pluginDir
     * @param string $pluginSlug
     * @param string $pluginName
     * @param string $version
     * @param string $author
     * @return void
     */
    private function createPluginStructure(
        string $pluginDir,
        string $pluginSlug,
        string $pluginName,
        string $version,
        string $author
    ): void {
        // Створюємо директорії
        $dirs = [
            $pluginDir,
            $pluginDir . DIRECTORY_SEPARATOR . 'src',
            $pluginDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Controllers',
            $pluginDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Models',
            $pluginDir . DIRECTORY_SEPARATOR . 'assets',
            $pluginDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css',
            $pluginDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js',
            $pluginDir . DIRECTORY_SEPARATOR . 'templates',
            $pluginDir . DIRECTORY_SEPARATOR . 'config',
        ];

        foreach ($dirs as $dir) {
            $this->ensureDirectory($dir);
        }

        // Створюємо plugin.json
        $pluginJson = $this->generatePluginJson($pluginSlug, $pluginName, $version, $author);
        $this->writeFile($pluginDir . DIRECTORY_SEPARATOR . 'plugin.json', $pluginJson);

        // Створюємо Plugin.php
        $pluginPhp = $this->generatePluginPhp($pluginSlug, $pluginName);
        $this->writeFile($pluginDir . DIRECTORY_SEPARATOR . 'Plugin.php', $pluginPhp);

        // Створюємо routes.php
        $routesPhp = $this->generateRoutesPhp($pluginSlug);
        $this->writeFile($pluginDir . DIRECTORY_SEPARATOR . 'routes.php', $routesPhp);

        // Створюємо README.md
        $readme = $this->generateReadme($pluginName, $pluginSlug, $version);
        $this->writeFile($pluginDir . DIRECTORY_SEPARATOR . 'README.md', $readme);
    }

    /**
     * Генерація plugin.json
     *
     * @param string $pluginSlug
     * @param string $pluginName
     * @param string $version
     * @param string $author
     * @return string
     */
    private function generatePluginJson(
        string $pluginSlug,
        string $pluginName,
        string $version,
        string $author
    ): string {
        $json = [
            'name' => $pluginName,
            'slug' => $pluginSlug,
            'version' => $version,
            'description' => 'Опис плагіна',
            'author' => $author,
            'license' => 'MIT',
            'min_cms_version' => '1.0.0',
            'dependencies' => [],
            'features' => []
        ];

        return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Генерація Plugin.php
     *
     * @param string $pluginSlug
     * @param string $pluginName
     * @return string
     */
    private function generatePluginPhp(string $pluginSlug, string $pluginName): string
    {
        $className = str_replace('-', '', ucwords($pluginSlug, '-')) . 'Plugin';
        $namespace = 'Plugins\\' . str_replace('-', '', ucwords($pluginSlug, '-'));

        return <<<PHP
<?php

/**
 * Плагін {$pluginName}
 *
 * @package {$namespace}
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace {$namespace};

require_once __DIR__ . '/../../engine/Support/Base/BasePlugin.php';

use Flowaxy\Core\Support\Base\BasePlugin;

final class {$className} extends BasePlugin
{
    /**
     * Ініціалізація плагіна
     *
     * @return void
     */
    public function init(): void
    {
        parent::init();
        // Ініціалізація плагіна
    }

    /**
     * Реєстрація хуків
     *
     * @return void
     */
    public function registerHooks(): void
    {
        parent::registerHooks();
        // Реєстрація хуків плагіна
        // \$this->registerHook('my_action', [\$this, 'myActionCallback']);
    }

    /**
     * Реєстрація маршрутів
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        parent::registerRoutes();
        // Реєстрація маршрутів плагіна
        // \$this->registerRoute('GET', '/my-plugin-route', [\$this, 'myRouteHandler']);
    }

    /**
     * Активація плагіна
     *
     * @return void
     */
    public function activate(): void
    {
        parent::activate();
        // Логіка активації плагіна (наприклад, створення таблиць БД)
    }

    /**
     * Деактивація плагіна
     *
     * @return void
     */
    public function deactivate(): void
    {
        parent::deactivate();
        // Логіка деактивації плагіна
    }

    /**
     * Встановлення плагіна
     *
     * @return void
     */
    public function install(): void
    {
        parent::install();
        // Логіка встановлення плагіна
    }

    /**
     * Видалення плагіна
     *
     * @return void
     */
    public function uninstall(): void
    {
        parent::uninstall();
        // Логіка видалення плагіна
    }
}
PHP;
    }

    /**
     * Генерація routes.php
     *
     * @param string $pluginSlug
     * @return string
     */
    private function generateRoutesPhp(string $pluginSlug): string
    {
        return <<<PHP
<?php

/**
 * Маршрути плагіна {$pluginSlug}
 *
 * @package Plugins\\{$pluginSlug}
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

/**
 * @var \Flowaxy\Core\Interface\Http\Router\Router \$router
 * @var \Plugins\\{$pluginSlug}\\Plugin \$pluginInstance
 */

// Публічні маршрути
// \$router->add('GET', '/{$pluginSlug}/hello', [Controller::class, 'hello']);

// Адмін-маршрути (з префіксом /admin/plugins/{$pluginSlug}/)
// \$router->add('GET', '/admin/plugins/{$pluginSlug}/settings', [Controller::class, 'showSettings']);
// \$router->add('POST', '/admin/plugins/{$pluginSlug}/settings', [Controller::class, 'saveSettings']);
PHP;
    }

    /**
     * Генерація README.md
     *
     * @param string $pluginName
     * @param string $pluginSlug
     * @param string $version
     * @return string
     */
    private function generateReadme(string $pluginName, string $pluginSlug, string $version): string
    {
        return <<<MD
# {$pluginName}

Версія: {$version}

## Опис

Опис функціоналу плагіна.

## Встановлення

1. Скопіюйте директорію плагіна в `plugins/{$pluginSlug}/`
2. Активуйте плагін через адмін-панель

## Використання

Інструкції з використання плагіна.

## Налаштування

Опис налаштувань плагіна.

## Розробка

### Структура

- `Plugin.php` - Головний клас плагіна
- `src/Controllers/` - Контролери
- `src/Models/` - Моделі
- `assets/` - Статичні ресурси
- `templates/` - Шаблони
- `routes.php` - Маршрути

## Ліцензія

MIT
MD;
    }
}
