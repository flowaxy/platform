<?php

/**
 * Модуль управління налаштуваннями сайту
 * Централізована робота з налаштуваннями через клас
 *
 * @package Engine\Modules
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../../Contracts/ContainerInterface.php';

class SettingsManager extends BaseModule
{
    /**
     * @var array<string, string>
     */
    private array $settings = [];
    private bool $loaded = false;

    /**
     * Ініціалізація модуля
     */
    protected function init(): void
    {
        // Налаштування завантажуються ліниво при першому зверненні
    }

    /**
     * Реєстрація хуків модуля
     */
    public function registerHooks(): void
    {
        // Модуль SettingsManager не реєструє хуки
    }

    /**
     * Отримання інформації про модуль
     *
     * @return array<string, string>
     */
    public function getInfo(): array
    {
        return [
            'name' => 'SettingsManager',
            'title' => 'Менеджер налаштувань',
            'description' => 'Централізоване управління налаштуваннями сайту',
            'version' => '1.0.0 Alpha prerelease',
            'author' => 'Flowaxy CMS',
        ];
    }

    /**
     * Отримання API методів модуля
     *
     * @return array<string, string>
     */
    public function getApiMethods(): array
    {
        return [
            'get' => 'Отримання налаштування',
            'set' => 'Збереження налаштування',
            'delete' => 'Видалення налаштування',
            'all' => 'Отримання всіх налаштувань',
            'has' => 'Перевірка існування налаштування',
        ];
    }

    /**
     * Завантаження всіх налаштувань з БД
     *
     * @param bool $force Примусова перезавантаження
     * @return void
     */
    public function load(bool $force = false): void
    {
        if ($this->loaded && ! $force) {
            return;
        }

        // Якщо примусова перезавантаження, очищаємо кеш перед завантаженням
        if ($force && function_exists('cache_forget')) {
            cache_forget('site_settings');
        }

        // Використовуємо кеш тільки якщо не примусова перезавантаження
        if (! $force && function_exists('cache_remember')) {
            $this->settings = cache_remember('site_settings', function (): array {
                return $this->loadFromDatabase();
            }, 3600);
        } else {
            // Завантажуємо безпосередньо з БД (минаючи кеш)
            $this->settings = $this->loadFromDatabase();
        }

        $this->loaded = true;
    }

    /**
     * Завантаження налаштувань з БД
     *
     * @return array<string, string>
     */
    private function loadFromDatabase(): array
    {
        try {
            $db = $this->getDB();
            if ($db === null) {
                return [];
            }

            $stmt = $db->query('SELECT setting_key, setting_value FROM site_settings');

            if ($stmt === false) {
                return [];
            }

            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            return $settings;
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('SettingsManager: Failed to load settings from database', ['error' => $e->getMessage()]);
            } else {
                logger()->logError('Failed to load settings from database', ['error' => $e->getMessage()]);
            }
            return [];
        }
    }

    /**
     * Отримання налаштування
     *
     * @param string $key Ключ налаштування
     * @param string $default Значення за замовчуванням
     * @return string
     */
    public function get(string $key, string $default = ''): string
    {
        $this->load();

        return $this->settings[$key] ?? $default;
    }

    /**
     * Отримання всіх налаштувань
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $this->load();

        return $this->settings;
    }

    /**
     * Збереження налаштування
     *
     * @param string $key Ключ налаштування
     * @param string $value Значення
     * @return bool
     */
    public function set(string $key, string $value): bool
    {
        try {
            $db = $this->getDB();
            if ($db === null) {
                return false;
            }

            $stmt = $db->prepare('
                INSERT INTO site_settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ');
            $result = $stmt->execute([$key, $value]);

            if ($result) {
                // Обновляем локальный кеш
                $this->settings[$key] = $value;

                // Очищаємо кеш
                if (function_exists('cache_forget')) {
                    cache_forget('site_settings');
                }
            }

            return $result;
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('SettingsManager: Failed to save setting', ['key' => $key, 'error' => $e->getMessage()]);
            } else {
                logger()->logError('Failed to save setting', ['key' => $key, 'error' => $e->getMessage()]);
            }
            return false;
        }
    }

    /**
     * Збереження кількох налаштувань
     *
     * @param array $settings Масив налаштувань [key => value]
     * @return bool
     */
    public function setMultiple(array $settings): bool
    {
        try {
            $db = $this->getDB();
            if ($db === null) {
                return false;
            }

            $db->beginTransaction();

            $stmt = $db->prepare('
                INSERT INTO site_settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ');

            foreach ($settings as $key => $value) {
                $stmt->execute([$key, (string)$value]);
                // Обновляем локальный кеш настроек
                $this->settings[$key] = (string)$value;
            }

            $db->commit();

            // Очищаємо кеш Cache перед оновленням локального кешу
            if (function_exists('cache_forget')) {
                cache_forget('site_settings');
            }

            // Помечаем, что настройки загружены (чтобы избежать повторной загрузки)
            $this->loaded = true;

            return true;
        } catch (Exception $e) {
            $db = $this->getDB();
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }

            if (function_exists('logError')) {
                logError('SettingsManager: Failed to save multiple settings', ['error' => $e->getMessage()]);
            } else {
                logger()->logError('Failed to save multiple settings', ['error' => $e->getMessage()]);
            }
            return false;
        }
    }

    /**
     * Видалення налаштування
     *
     * @param string $key Ключ налаштування
     * @return bool
     */
    public function delete(string $key): bool
    {
        try {
            $db = $this->getDB();
            if ($db === null) {
                return false;
            }

            $stmt = $db->prepare('DELETE FROM site_settings WHERE setting_key = ?');
            $result = $stmt->execute([$key]);

            if ($result) {
                unset($this->settings[$key]);

                // Очищаємо кеш
                if (function_exists('cache_forget')) {
                    cache_forget('site_settings');
                }
            }

            return $result;
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('SettingsManager: Failed to delete setting', ['key' => $key, 'error' => $e->getMessage()]);
            } else {
                logger()->logError('Failed to delete setting', ['key' => $key, 'error' => $e->getMessage()]);
            }

            return false;
        }
    }

    /**
     * Перевірка існування налаштування
     *
     * @param string $key Ключ настройки
     * @return bool
     */
    public function has(string $key): bool
    {
        $this->load();

        return isset($this->settings[$key]);
    }

    /**
     * Очищення кешу налаштувань
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->loaded = false;
        $this->settings = [];

        if (function_exists('cache_forget')) {
            cache_forget('site_settings');
        }
    }

    /**
     * Перезавантаження налаштувань
     *
     * @return void
     */
    public function reloadSettings(): void
    {
        $this->load(true);
    }
}

/**
 * Глобальная функция для получения экземпляра SettingsManager
 *
 * @return SettingsManager|null
 */
function settingsManager(): ?SettingsManager
{
    // Уникаємо рекурсії: перевіряємо, що SettingsManager не ініціалізується
    static $initializing = false;
    if ($initializing) {
        return null;
    }

    if (! class_exists('SettingsManager')) {
        return null;
    }

    try {
        // Сначала пытаемся получить через фасад
        if (class_exists('SettingsFacade')) {
            $manager = SettingsFacade::manager();
            if ($manager !== null) {
                return $manager;
            }
        }

        // Fallback на getInstance()
        $initializing = true;
        $instance = SettingsManager::getInstance();
        $initializing = false;

        return $instance;
    } catch (Exception $e) {
        $initializing = false;
        if (function_exists('logError')) {
            logError('settingsManager() error', ['error' => $e->getMessage(), 'exception' => $e]);
        } else {
            logger()->logError('settingsManager() error: ' . $e->getMessage(), ['exception' => $e]);
        }

        return null;
    } catch (Error $e) {
        $initializing = false;
        if (function_exists('logCritical')) {
            logCritical('settingsManager() fatal error', ['error' => $e->getMessage(), 'exception' => $e]);
        } else {
            logger()->logCritical('settingsManager() fatal error: ' . $e->getMessage(), ['exception' => $e]);
        }

        return null;
    }
}

/**
 * Глобальная функция для получения настройки сайта
 *
 * @param string $key Ключ настройки
 * @param string $default Значение по умолчанию
 * @return string
 */
function getSetting(string $key, string $default = ''): string
{
    if (class_exists('SettingsManager')) {
        $manager = settingsManager();
        if ($manager !== null) {
            return $manager->get($key, $default);
        }
    }

    return $default;
}
