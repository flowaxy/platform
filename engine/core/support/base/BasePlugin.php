<?php

/**
 * Базовий клас для всіх плагінів
 *
 * @package Engine\Classes\Base
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

abstract class BasePlugin
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $pluginData = null;
    protected ?PDO $db = null;

    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    public function __construct()
    {
        try {
            $this->db = DatabaseHelper::getConnection();
            $this->loadConfig();
        } catch (Exception $e) {
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
        $reflection = new ReflectionClass($this);
        $pluginDir = dirname($reflection->getFileName());
        $configFile = $pluginDir . '/plugin.json';

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
        // Перевизначається в дочірніх класах
    }

    /**
     * Активування плагіна
     *
     * @return void
     */
    public function activate(): void
    {
        // Перевизначається в дочірніх класах
    }

    /**
     * Деактивування плагіна
     *
     * @return void
     */
    public function deactivate(): void
    {
        // Перевизначається в дочірніх класах
    }

    /**
     * Встановлення плагіна (створення таблиць, налаштувань тощо)
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
     * Отримання слагу плагіна
     *
     * @return string Слаг плагіна
     */
    public function getSlug(): string
    {
        return $this->config['slug'] ?? strtolower(get_class($this));
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
     * @return string Шлях до плагіна
     */
    public function getPluginPath(): string
    {
        return dirname((new ReflectionClass($this))->getFileName()) . '/';
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

        addHook('theme_head', function () use ($url, $handle) {
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

        addHook($hookName, function () use ($url, $handle) {
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
        addHook('admin_menu', function () use ($title, $menuSlug, $capability, $callback, $icon) {
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
        addHook('theme_footer', function () use ($objectName, $data) {
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
                if (! is_string($dependency) || ! $pluginManager->isPluginActive($dependency)) {
                    return false;
                }
            }
        } catch (Exception $e) {
            error_log('Помилка перевірки залежностей плагіна: ' . $e->getMessage());

            return false;
        }

        return true;
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
}
