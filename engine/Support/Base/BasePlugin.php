<?php

/**
 * Базовий клас для всіх плагінів
 *
 * @package Flowaxy\Core\Support\Base
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Support\Base;

require_once __DIR__ . '/../Containers/PluginContainer.php';
require_once __DIR__ . '/../Isolation/PluginIsolation.php';

use Flowaxy\Core\Support\Containers\PluginContainer;
use Flowaxy\Core\Support\Isolation\PluginIsolation;

abstract class BasePlugin
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $pluginData = null;
    protected ?\PDO $db = null;

    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * @var PluginContainer|null Ізольований контейнер плагіна
     */
    protected ?PluginContainer $container = null;

    /**
     * @var string Slug плагіна
     */
    protected string $pluginSlug;

    /**
     * @var string Директорія плагіна
     */
    protected string $pluginDir;

    public function __construct(?PluginContainer $container = null)
    {
        $this->container = $container;

        if ($container !== null) {
            $this->pluginSlug = $container->getPluginSlug();
            $this->pluginDir = $container->getPluginDir();
            $this->config = $container->getConfig();
        } else {
            // Fallback для зворотної сумісності
            $reflection = new \ReflectionClass($this);
            $this->pluginDir = dirname($reflection->getFileName()) . DIRECTORY_SEPARATOR;
            $this->pluginSlug = $this->getSlug();
        }

        try {
            if (class_exists('DatabaseHelper')) {
                $this->db = \DatabaseHelper::getConnection();
            }
            $this->loadConfig();
        } catch (\Exception $e) {
            error_log('BasePlugin constructor error: ' . $e->getMessage());
            $this->db = null;
        }
    }

    /**
     * Завантаження конфігурації плагіна
     *
     * @return void
     */
    private function loadConfig(): void
    {
        if (!empty($this->config)) {
            return; // Конфігурація вже завантажена з контейнера
        }

        $configFile = $this->pluginDir . 'plugin.json';

        if (file_exists($configFile)) {
            $configContent = file_get_contents($configFile);
            if ($configContent !== false) {
                $this->config = json_decode($configContent, true) ?? [];
            }
        }
    }

    /**
     * Ініціалізація плагіна (викликається при завантаженні)
     *
     * @return void
     */
    public function init(): void
    {
        // Автоматично виконуємо оновлення з папки updates
        $this->runUpdates();

        // Реєструємо хуки та роути
        $this->registerHooks();
        $this->registerRoutes();

        // Перевизначається в дочірніх класах
    }

    /**
     * Реєстрація хуків плагіна
     *
     * Перевизначається в дочірніх класах для реєстрації хуків
     * Хуки автоматично реєструються з ізоляцією через PluginContainer
     *
     * @return void
     */
    public function registerHooks(): void
    {
        // Перевизначається в дочірніх класах
    }

    /**
     * Реєстрація роутів плагіна
     *
     * Перевизначається в дочірніх класах для реєстрації роутів
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        // Перевизначається в дочірніх класах
    }

    /**
     * Активування плагіна
     *
     * Викликається при активації плагіна через адмінку
     * Може використовуватися для створення таблиць, налаштувань тощо
     *
     * @return void
     */
    public function activate(): void
    {
        // Оновлюємо статус в контейнері
        if ($this->container !== null) {
            $this->container->activate();
        }

        // Перевизначається в дочірніх класах
    }

    /**
     * Деактивування плагіна
     *
     * Викликається при деактивації плагіна через адмінку
     * Може використовуватися для очищення тимчасових даних
     *
     * @return void
     */
    public function deactivate(): void
    {
        // Оновлюємо статус в контейнері
        if ($this->container !== null) {
            $this->container->deactivate();
        }

        // Перевизначається в дочірніх класах
    }

    /**
     * Встановлення плагіна (створення таблиць, налаштувань тощо)
     *
     * Викликається один раз при встановленні плагіна
     * Може використовуватися для створення таблиць БД, налаштувань тощо
     *
     * @return void
     */
    public function install(): void
    {
        // Перевизначається в дочірніх класах
    }

    /**
     * Видалення плагіна (очищення даних)
     *
     * Викликається при видаленні плагіна
     * Може використовуватися для видалення таблиць БД, налаштувань тощо
     *
     * @return void
     */
    public function uninstall(): void
    {
        // Перевизначається в дочірніх класах
    }

    /**
     * Отримання налаштувань плагіна з кешуванням
     *
     * @return array<string, mixed> Масив налаштувань
     */
    public function getSettings(): array
    {
        if (! $this->db) {
            return [];
        }

        $slug = $this->getSlug();
        $cacheKey = 'plugin_settings_' . $slug;

        return cache_remember($cacheKey, function () use ($slug) {
            $db = DatabaseHelper::getConnection();
            if (! $db) {
                return [];
            }

            try {
                $stmt = $db->prepare('SELECT setting_key, setting_value FROM plugin_settings WHERE plugin_slug = ?');
                $stmt->execute([$slug]);

                $settings = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }

                return $settings;
            } catch (Exception $e) {
                error_log('BasePlugin getSettings помилка: ' . $e->getMessage());

                return [];
            }
        }, 1800); // Кешуємо на 30 хвилин
    }

    /**
     * Збереження налаштування плагіна
     *
     * @param string $key Ключ налаштування
     * @param mixed $value Значення налаштування
     * @return bool Успіх операції
     */
    public function setSetting(string $key, $value): bool
    {
        if (! $this->db || empty($key)) {
            return false;
        }

        try {
            $stmt = $this->db->prepare('
                INSERT INTO plugin_settings (plugin_slug, setting_key, setting_value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ');

            $result = $stmt->execute([$this->getSlug(), $key, $value]);

            if ($result) {
                // Очищаємо кеш налаштувань
                cache_forget('plugin_settings_' . $this->getSlug());
            }

            return $result;
        } catch (Exception $e) {
            error_log('BasePlugin setSetting помилка: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Отримання налаштування плагіна
     *
     * @param string $key Ключ налаштування
     * @param mixed $default Значення за замовчуванням
     * @return mixed Значення налаштування
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->getSettings();

        return $settings[$key] ?? $default;
    }

    /**
     * Валідація налаштування
     *
     * @param string $key Ключ налаштування
     * @param mixed $value Значення для валідації
     * @return array<string, string> Масив помилок валідації (порожній якщо валідація пройшла)
     */
    public function validateSetting(string $key, mixed $value): array
    {
        $schema = $this->getSettingsSchema();

        if (!isset($schema[$key])) {
            return []; // Невідоме налаштування - не валідуємо
        }

        $fieldSchema = $schema[$key];
        $errors = [];

        // Перевірка обов'язковості
        if (!empty($fieldSchema['required']) && empty($value) && $value !== '0') {
            $errors[] = "Поле '{$key}' обов'язкове";
        }

        // Перевірка типу
        if (isset($fieldSchema['type'])) {
            $type = $fieldSchema['type'];

            switch ($type) {
                case 'string':
                    if (!is_string($value)) {
                        $errors[] = "Поле '{$key}' має бути рядком";
                    }
                    break;

                case 'integer':
                case 'int':
                    if (!is_numeric($value)) {
                        $errors[] = "Поле '{$key}' має бути числом";
                    }
                    break;

                case 'boolean':
                case 'bool':
                    if (!is_bool($value) && !in_array($value, ['0', '1', 'true', 'false', 'yes', 'no'], true)) {
                        $errors[] = "Поле '{$key}' має бути булевим значенням";
                    }
                    break;

                case 'email':
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Поле '{$key}' має бути валідною email адресою";
                    }
                    break;

                case 'url':
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[] = "Поле '{$key}' має бути валідною URL адресою";
                    }
                    break;
            }
        }

        // Перевірка мінімальної/максимальної довжини
        if (isset($fieldSchema['min_length']) && strlen((string)$value) < $fieldSchema['min_length']) {
            $errors[] = "Поле '{$key}' має містити мінімум {$fieldSchema['min_length']} символів";
        }

        if (isset($fieldSchema['max_length']) && strlen((string)$value) > $fieldSchema['max_length']) {
            $errors[] = "Поле '{$key}' має містити максимум {$fieldSchema['max_length']} символів";
        }

        // Перевірка значень з переліку
        if (isset($fieldSchema['enum']) && !in_array($value, $fieldSchema['enum'], true)) {
            $errors[] = "Поле '{$key}' має бути одним з: " . implode(', ', $fieldSchema['enum']);
        }

        // Кастомна валідація
        if (isset($fieldSchema['validate']) && is_callable($fieldSchema['validate'])) {
            $customError = $fieldSchema['validate']($value);
            if (!empty($customError)) {
                $errors[] = $customError;
            }
        }

        return $errors;
    }

    /**
     * Отримання схеми налаштувань плагіна
     *
     * Перевизначається в дочірніх класах для визначення структури налаштувань
     *
     * @return array<string, array<string, mixed>> Схема налаштувань
     */
    public function getSettingsSchema(): array
    {
        return [];
    }

    /**
     * Встановлення налаштування з валідацією
     *
     * @param string $key Ключ налаштування
     * @param mixed $value Значення налаштування
     * @return array<string, string> Помилки валідації (порожній масив якщо успіх)
     */
    public function setSettingWithValidation(string $key, mixed $value): array
    {
        $errors = $this->validateSetting($key, $value);

        if (empty($errors)) {
            $this->setSetting($key, $value);
        }

        return $errors;
    }

    /**
     * Масове встановлення налаштувань з валідацією
     *
     * @param array<string, mixed> $settings Масив налаштувань
     * @return array<string, array<string>> Помилки валідації (ключ - назва поля, значення - масив помилок)
     */
    public function setSettingsWithValidation(array $settings): array
    {
        $allErrors = [];

        foreach ($settings as $key => $value) {
            $errors = $this->validateSetting($key, $value);

            if (!empty($errors)) {
                $allErrors[$key] = $errors;
            } else {
                $this->setSetting($key, $value);
            }
        }

        return $allErrors;
    }

    /**
     * Отримання слагу плагіна
     *
     * @return string Слаг плагіна
     */
    public function getSlug(): string
    {
        if (isset($this->pluginSlug)) {
            return $this->pluginSlug;
        }

        return $this->config['slug'] ?? strtolower(str_replace('\\', '_', get_class($this)));
    }

    /**
     * Отримання імені плагіна
     *
     * @return string Ім'я плагіна
     */
    public function getName(): string
    {
        return $this->config['name'] ?? get_class($this);
    }

    /**
     * Отримання версії плагіна
     *
     * @return string Версія плагіна
     */
    public function getVersion(): string
    {
        return $this->config['version'] ?? '1.0.0';
    }

    /**
     * Отримання опису плагіна
     *
     * @return string Опис плагіна
     */
    public function getDescription(): string
    {
        return $this->config['description'] ?? '';
    }

    /**
     * Отримання автора плагіна
     *
     * @return string Автор плагіна
     */
    public function getAuthor(): string
    {
        return $this->config['author'] ?? '';
    }

    /**
     * Отримання URL плагіна
     *
     * @return string URL плагіна
     */
    public function getPluginUrl(): string
    {
        $pluginDir = basename(dirname((new ReflectionClass($this))->getFileName()));
        // Використовуємо UrlHelper для отримання актуального URL з правильним протоколом
        if (class_exists('UrlHelper')) {
            return UrlHelper::site('/plugins/' . $pluginDir . '/');
        }
        // Fallback на константу, якщо UrlHelper не доступний
        $siteUrl = defined('SITE_URL') ? SITE_URL : '';

        return $siteUrl . '/plugins/' . $pluginDir . '/';
    }

    /**
     * Отримання шляху до плагіна
     *
     * @param string $filePath Відносний шлях до файлу
     * @return string Шлях до плагіна або файлу
     */
    public function getPluginPath(string $filePath = ''): string
    {
        if ($this->container !== null) {
            return $this->container->getPluginPath($filePath);
        }

        // Fallback для зворотної сумісності
        $basePath = dirname((new \ReflectionClass($this))->getFileName()) . DIRECTORY_SEPARATOR;

        if (empty($filePath)) {
            return $basePath;
        }

        return $basePath . ltrim($filePath, '/\\');
    }

    /**
     * Отримання контейнера плагіна
     *
     * @return PluginContainer|null
     */
    public function getContainer(): ?PluginContainer
    {
        return $this->container;
    }

    /**
     * Реєстрація хука з ізоляцією
     *
     * Автоматично реєструє хук через HookManager з підтримкою ізоляції
     *
     * @param string $hookName Назва хука
     * @param callable $callback Callback функція
     * @param int $priority Пріоритет
     * @param bool $once Чи виконати тільки один раз
     * @return void
     */
    protected function registerHook(string $hookName, callable $callback, int $priority = 10, bool $once = false): void
    {
        if (!function_exists('hooks')) {
            // Fallback для зворотної сумісності
            if (function_exists('addHook')) {
                addHook($hookName, $callback, $priority);
            }
            return;
        }

        $hookManager = hooks();
        $pluginSlug = $this->getSlug();

        // Використовуємо метод з ізоляцією, якщо доступний
        if (method_exists($hookManager, 'onFromPlugin')) {
            $hookManager->onFromPlugin($hookName, $callback, $pluginSlug, $this->container, $priority, $once);
        } else {
            // Fallback на стандартний метод
            $hookManager->on($hookName, $callback, $priority, $once);
        }
    }

    /**
     * Реєстрація фільтра з ізоляцією
     *
     * Автоматично реєструє фільтр через HookManager з підтримкою ізоляції
     *
     * @param string $hookName Назва хука
     * @param callable $callback Callback функція
     * @param int $priority Пріоритет
     * @return void
     */
    protected function registerFilter(string $hookName, callable $callback, int $priority = 10): void
    {
        if (!function_exists('hooks')) {
            // Fallback для зворотної сумісності
            if (function_exists('addHook')) {
                addHook($hookName, $callback, $priority);
            }
            return;
        }

        $hookManager = hooks();
        $pluginSlug = $this->getSlug();

        // Використовуємо метод з ізоляцією, якщо доступний
        if (method_exists($hookManager, 'filterFromPlugin')) {
            $hookManager->filterFromPlugin($hookName, $callback, $pluginSlug, $this->container, $priority);
        } else {
            // Fallback на стандартний метод
            $hookManager->filter($hookName, $callback, $priority);
        }
    }

    /**
     * Реєстрація роута плагіна
     *
     * Додає роут до глобального Router з префіксом плагіна
     *
     * @param string|array $methods HTTP методи ('GET', 'POST', тощо)
     * @param string $path Шлях роута
     * @param callable|string $handler Обробник роута
     * @param array $options Додаткові опції (middleware, name, тощо)
     * @return void
     */
    protected function registerRoute($methods, string $path, $handler, array $options = []): void
    {
        // Додаємо префікс плагіна до шляху
        $pluginSlug = $this->getSlug();
        $prefixedPath = '/plugins/' . $pluginSlug . '/' . ltrim($path, '/');

        // Отримуємо глобальний Router
        if (!class_exists('Router')) {
            return;
        }

        // Створюємо або отримуємо екземпляр Router
        static $router = null;
        if ($router === null) {
            // Спробуємо отримати Router з контейнера або створити новий
            if (function_exists('container')) {
                try {
                    $container = container();
                    if ($container->has('Router')) {
                        $router = $container->make('Router');
                    }
                } catch (\Exception $e) {
                    // Fallback нижче
                }
            }

            if ($router === null) {
                $router = new \Router();
            }
        }

        // Додаємо роут
        $router->add($methods, $prefixedPath, $handler, $options);
    }

    /**
     * Підключення CSS файлу плагіна
     * Використовує хук theme_head для підключення стилів
     *
     * @param string $handle Ідентифікатор стилю
     * @param string $file Відносний шлях до файлу
     * @param array<string> $dependencies Залежності (не використовується, для сумісності)
     * @return void
     */
    public function enqueueStyle(string $handle, string $file, array $dependencies = []): void
    {
        $url = $this->getPluginUrl() . $file;

        $this->registerHook('theme_head', function () use ($url, $handle) {
            echo "<link rel='stylesheet' id='{$handle}-css' href='{$url}' type='text/css' media='all' />\n";
        });
    }

    /**
     * Підключення JS файлу плагіна
     * Використовує хук theme_footer для підключення скриптів
     *
     * @param string $handle Ідентифікатор скрипту
     * @param string $file Відносний шлях до файлу
     * @param array<string> $dependencies Залежності (не використовується, для сумісності)
     * @param bool $inFooter Чи підключати в футері
     * @return void
     */
    public function enqueueScript(string $handle, string $file, array $dependencies = [], bool $inFooter = true): void
    {
        $url = $this->getPluginUrl() . $file;

        $hookName = $inFooter ? 'theme_footer' : 'theme_head';

        $this->registerHook($hookName, function () use ($url, $handle) {
            echo "<script id='{$handle}-js' src='{$url}'></script>\n";
        });
    }

    /**
     * Додавання пункту меню в адмінку
     *
     * @param string $title Назва пункту меню
     * @param string $capability Право доступу
     * @param string $menuSlug Слаг меню
     * @param callable $callback Функція зворотного виклику
     * @param string $icon Іконка (Font Awesome клас)
     * @return void
     */
    public function addAdminMenu(string $title, string $capability, string $menuSlug, callable $callback, string $icon = ''): void
    {
        $this->registerHook('admin_menu', function () use ($title, $menuSlug, $capability, $callback, $icon) {
            // Логіка додавання меню буде реалізована в адмінці
            // Параметри $capability, $callback, $icon будуть використані в майбутніх версіях
        });
    }

    /**
     * Локалізація скрипту (аналог wp_localize_script)
     *
     * @param string $handle Ідентифікатор скрипту
     * @param string $objectName Назва JavaScript об'єкта
     * @param array $data Дані для передачі
     * @return void
     */
    /**
     * @param string $handle
     * @param string $objectName
     * @param array<string, mixed> $data
     * @return void
     */
    protected function localizeScript(string $handle, string $objectName, array $data): void
    {
        // Додаємо JavaScript змінну
        // $handle не використовується в closure, але зберігається для майбутнього використання
        $this->registerHook('theme_footer', function () use ($objectName, $data) {
            echo "<script>var {$objectName} = " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ";</script>\n";
        });
    }

    /**
     * Створення nonce (аналог wp_create_nonce)
     *
     * @param string $action Дія для nonce
     * @return string Nonce токен
     */
    protected function createNonce(string $action): string
    {
        $session = sessionManager('plugin');
        $nonces = $session->get('nonces', []);

        $nonce = bin2hex(random_bytes(32));
        $nonces[$action] = [
            'nonce' => $nonce,
            'expires' => time() + 3600, // 1 година
        ];

        $session->set('nonces', $nonces);

        return $nonce;
    }

    /**
     * Перевірка nonce (аналог wp_verify_nonce)
     *
     * @param string|null $nonce Nonce токен для перевірки
     * @param string $action Дія для nonce
     * @return bool Чи валідний nonce
     */
    protected function verifyNonce(?string $nonce, string $action): bool
    {
        if (empty($nonce)) {
            return false;
        }

        $session = sessionManager('plugin');
        $nonces = $session->get('nonces', []);

        if (! isset($nonces[$action])) {
            return false;
        }

        $stored = $nonces[$action];

        // Перевіряємо термін дії
        if (isset($stored['expires']) && $stored['expires'] < time()) {
            unset($nonces[$action]);
            $session->set('nonces', $nonces);

            return false;
        }

        // Перевіряємо nonce
        if (isset($stored['nonce']) && hash_equals($stored['nonce'], $nonce)) {
            // Видаляємо використаний nonce (одноразове використання)
            unset($nonces[$action]);
            $session->set('nonces', $nonces);

            return true;
        }

        return false;
    }

    /**
     * Відправка JSON відповіді
     *
     * @param bool $success Чи успішна операція
     * @param mixed $data Дані для відправки
     * @return void
     */
    private function sendJsonResponse(bool $success, $data): void
    {
        if (! headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode(['success' => $success, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * Відправка JSON успіху (аналог wp_send_json_success)
     *
     * @param mixed $data Дані для відправки
     * @return void
     */
    protected function sendJsonSuccess($data): void
    {
        $this->sendJsonResponse(true, $data);
    }

    /**
     * Відправка JSON помилки (аналог wp_send_json_error)
     *
     * @param mixed $data Дані помилки
     * @return void
     */
    protected function sendJsonError($data): void
    {
        $this->sendJsonResponse(false, $data);
    }

    /**
     * Логування дій плагіна
     *
     * @param string $message Повідомлення для логування
     * @param string $level Рівень логування (info, warning, error)
     * @return void
     */
    public function log(string $message, string $level = 'info'): void
    {
        $logMessage = '[' . date('Y-m-d H:i:s') . "] [{$this->getName()}] [{$level}] {$message}";
        error_log($logMessage);
    }

    /**
     * Перевірка залежностей плагіна
     *
     * @return bool Чи всі залежності встановлені
     */
    public function checkDependencies(): bool
    {
        if (! isset($this->config['dependencies']) || ! is_array($this->config['dependencies'])) {
            return true;
        }

        try {
            if (! function_exists('pluginManager')) {
                return false;
            }

            $pluginManager = pluginManager();
            foreach ($this->config['dependencies'] as $dependency) {
                if (! is_string($dependency)) {
                    continue;
                }

                // Перевіряємо формат залежності (може бути просто slug або масив з версією)
                $depSlug = is_array($dependency) ? ($dependency['slug'] ?? '') : $dependency;
                $depVersion = is_array($dependency) ? ($dependency['version'] ?? null) : null;

                if (empty($depSlug)) {
                    continue;
                }

                // Перевіряємо, чи плагін встановлений
                if (! $pluginManager->isPluginInstalled($depSlug)) {
                    return false;
                }

                // Перевіряємо, чи плагін активний
                if (! $pluginManager->isPluginActive($depSlug)) {
                    return false;
                }

                // Перевіряємо версію, якщо вказана
                if ($depVersion !== null) {
                    $installedPlugin = $pluginManager->getPlugin($depSlug);
                    if ($installedPlugin && version_compare($installedPlugin->getVersion(), $depVersion, '<')) {
                        return false;
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Помилка перевірки залежностей плагіна: ' . $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Отримання списку залежностей
     *
     * @return array<string|array<string,mixed>> Масив залежностей
     */
    public function getDependencies(): array
    {
        return $this->config['dependencies'] ?? [];
    }

    /**
     * Отримання помилок залежностей
     *
     * @return array<string, string> Масив помилок (ключ - назва залежності, значення - опис помилки)
     */
    public function getDependencyErrors(): array
    {
        $errors = [];

        if (! isset($this->config['dependencies']) || ! is_array($this->config['dependencies'])) {
            return $errors;
        }

        if (! function_exists('pluginManager')) {
            return ['general' => 'PluginManager недоступний'];
        }

        try {
            $pluginManager = pluginManager();

            foreach ($this->config['dependencies'] as $dependency) {
                $depSlug = is_array($dependency) ? ($dependency['slug'] ?? '') : $dependency;
                $depVersion = is_array($dependency) ? ($dependency['version'] ?? null) : null;

                if (empty($depSlug)) {
                    continue;
                }

                if (! $pluginManager->isPluginInstalled($depSlug)) {
                    $errors[$depSlug] = "Плагін '{$depSlug}' не встановлений";
                    continue;
                }

                if (! $pluginManager->isPluginActive($depSlug)) {
                    $errors[$depSlug] = "Плагін '{$depSlug}' не активований";
                    continue;
                }

                if ($depVersion !== null) {
                    $installedPlugin = $pluginManager->getPlugin($depSlug);
                    if ($installedPlugin && version_compare($installedPlugin->getVersion(), $depVersion, '<')) {
                        $errors[$depSlug] = "Плагін '{$depSlug}' потребує версію {$depVersion} або вищу";
                    }
                }
            }
        } catch (Exception $e) {
            $errors['general'] = 'Помилка перевірки залежностей: ' . $e->getMessage();
        }

        return $errors;
    }

    /**
     * Отримання конфігурації плагіна
     *
     * @return array Масив конфігурації
     */
    public function getConfig(): array
    {
        return $this->config ?? [];
    }

    /**
     * Автоматично виконує файли-оновлення з папки updates
     *
     * Знаходить всі PHP файли в папці updates плагіна та виконує їх,
     * якщо вони ще не були виконані. Файли повинні повертати callable,
     * який приймає PDO як параметр.
     *
     * @return void
     */
    protected function runUpdates(): void
    {
        if (!$this->db) {
            return;
        }

        $pluginDir = $this->getPluginPath();
        $updatesDir = $pluginDir . 'updates';

        if (!is_dir($updatesDir)) {
            return;
        }

        // Знаходимо всі PHP файли в папці updates
        $updateFiles = glob($updatesDir . '/*.php');

        if (empty($updateFiles)) {
            return;
        }

        // Сортуємо файли за назвою (версії)
        sort($updateFiles);

        foreach ($updateFiles as $updateFile) {
            $updateName = basename($updateFile, '.php');
            $pluginSlug = $this->getSlug();
            $migrationName = $pluginSlug . '_' . $updateName;

            // Перевіряємо, чи вже виконано це оновлення
            if ($this->isUpdateExecuted($migrationName)) {
                continue;
            }

            try {
                // Завантажуємо файл оновлення (він повинен повертати callable)
                $update = require $updateFile;

                if (is_callable($update)) {
                    $update($this->db);

                    // Позначаємо оновлення як виконане
                    $this->markUpdateAsExecuted($migrationName);

                    if (function_exists('logger')) {
                        logger()->logInfo("Plugin {$pluginSlug}: Update executed", ['update' => $updateName]);
                    }
                }
            } catch (Exception $e) {
                if (function_exists('logger')) {
                    logger()->logError("Plugin {$pluginSlug}: Error executing update", [
                        'update' => $updateName,
                        'exception' => $e
                    ]);
                }
            }
        }
    }

    /**
     * Перевірити, чи виконано оновлення
     *
     * @param string $migrationName Назва міграції (plugin_slug_version)
     * @return bool
     */
    private function isUpdateExecuted(string $migrationName): bool
    {
        if (!$this->db) {
            return false;
        }

        try {
            // Перевіряємо через таблицю migrations
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
            $stmt->execute([$migrationName]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            // Якщо таблиці migrations немає, повертаємо false
            return false;
        }
    }

    /**
     * Позначити оновлення як виконане
     *
     * @param string $migrationName Назва міграції (plugin_slug_version)
     * @return void
     */
    private function markUpdateAsExecuted(string $migrationName): void
    {
        if (!$this->db) {
            return;
        }

        try {
            // Створюємо таблицю migrations, якщо її немає
            $this->db->exec("CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL UNIQUE,
                `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Додаємо запис про виконане оновлення
            $stmt = $this->db->prepare("INSERT IGNORE INTO migrations (migration) VALUES (?)");
            $stmt->execute([$migrationName]);
        } catch (Exception $e) {
            // Ігноруємо помилки
        }
    }
}
