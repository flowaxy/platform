<?php

/**
 * Сторінка налаштувань сайту
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/AdminPage.php';

class SiteSettingsPage extends AdminPage
{
    public function __construct()
    {
        parent::__construct();

        // Перевірка прав доступу
        $session = sessionManager();
        $userId = (int)($session->get('admin_user_id') ?? 0);

        // Перший користувач або користувач з правом admin.access має доступ
        $hasAccess = ($userId === 1) ||
                     (function_exists('user_has_role') && user_has_role($userId, 'developer')) ||
                     (function_exists('current_user_can') && current_user_can('admin.access'));

        if (! $hasAccess) {
            Response::redirectStatic(UrlHelper::admin('dashboard'));
            exit;
        }

        $this->pageTitle = 'Налаштування сайту - Flowaxy CMS';
        $this->templateName = 'site-settings';

        // Перевірка прав доступу для кнопки
        $hasEditAccess = ($userId === 1) ||
                        (function_exists('user_has_role') && user_has_role($userId, 'developer')) ||
                        (function_exists('current_user_can') && current_user_can('admin.access'));

        // Створюємо кнопку "Зберегти налаштування" для header
        $disabledAttr = !$hasEditAccess ? 'disabled' : '';
        $saveButton = '<button type="submit" form="site-settings-form" class="btn btn-primary" ' . $disabledAttr . '>
            <i class="fas fa-save btn-icon"></i>
            <span class="btn-text">Зберегти налаштування</span>
        </button>';

        $this->setPageHeader(
            'Налаштування сайту',
            'Основні налаштування та конфігурація',
            'fas fa-cog',
            $saveButton
        );

        // Додаємо хлібні крихти
        $this->setBreadcrumbs([
            ['title' => 'Головна', 'url' => UrlHelper::admin('dashboard')],
            ['title' => 'Налаштування', 'url' => UrlHelper::admin('settings')],
            ['title' => 'Налаштування сайту'],
        ]);
    }

    public function handle()
    {
        // Обробка збереження
        if ($_POST && isset($_POST['save_settings'])) {
            $this->saveSettings();
        }

        // Отримання налаштувань
        $settings = $this->getSettings();

        // Отримуємо список ролей для вибору ролі за замовчуванням
        $roles = [];
        if (class_exists('RoleManager')) {
            try {
                $roleManager = RoleManager::getInstance();
                $roles = $roleManager->getAllRoles();
            } catch (Exception $e) {
                if (function_exists('logError')) {
                    logError('SiteSettingsPage: Error loading roles', ['error' => $e->getMessage(), 'exception' => $e]);
                } else {
                    logger()->logError('SiteSettingsPage: Error loading roles: ' . $e->getMessage());
                }
            }
        }

        // Рендеримо сторінку
        $this->render([
            'settings' => $settings,
            'roles' => $roles,
        ]);
    }

    /**
     * Збереження налаштувань
     */
    private function saveSettings()
    {
        if (! $this->verifyCsrf()) {
            return;
        }

        // Перевірка прав доступу
        $session = sessionManager();
        $userId = (int)($session->get('admin_user_id') ?? 0);

        // Перший користувач або користувач з правом admin.access має доступ
        $hasAccess = ($userId === 1) ||
                     (function_exists('user_has_role') && user_has_role($userId, 'developer')) ||
                     (function_exists('current_user_can') && current_user_can('admin.access'));

        if (! $hasAccess) {
            $this->setMessage('У вас немає прав на зміну налаштувань', 'danger');

            return;
        }

        $settings = $this->post('settings') ?? [];

        // Обробка checkbox полів (якщо не відмічені, вони не приходять в POST)
        $checkboxFields = ['cache_enabled', 'cache_auto_cleanup', 'logging_enabled', 'users_can_register', 'cookie_secure', 'cookie_httponly'];
        foreach ($checkboxFields as $field) {
            if (! isset($settings[$field])) {
                $settings[$field] = '0';
            }
        }

        // Обробка множинних значень для логування
        if (isset($settings['logging_levels'])) {
            if (is_array($settings['logging_levels'])) {
                // Фільтруємо порожні значення та зберігаємо як рядок через кому
                $filteredLevels = array_filter($settings['logging_levels'], function ($value) {
                    return ! empty(trim($value));
                });
                $settings['logging_levels'] = ! empty($filteredLevels)
                    ? implode(',', $filteredLevels)
                    : 'ERROR,CRITICAL'; // Значення за замовчуванням, якщо нічого не вибрано
            } elseif (is_string($settings['logging_levels'])) {
                // Якщо це рядок, просто залишаємо як є
                $settings['logging_levels'] = trim($settings['logging_levels']);
            } else {
                // Якщо це не масив і не рядок, встановлюємо значення за замовчуванням
                $settings['logging_levels'] = 'ERROR,CRITICAL';
            }
        } else {
            // Якщо поле взагалі не передано (не вибрано жодного рівня), встановлюємо мінімальне значення
            $settings['logging_levels'] = 'ERROR,CRITICAL';
        }
        if (isset($settings['logging_types']) && is_array($settings['logging_types'])) {
            $settings['logging_types'] = implode(',', array_filter($settings['logging_types']));
        }

        // Санітизація значень
        $sanitizedSettings = [];
        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $sanitizedSettings[$key] = array_map('SecurityHelper::sanitizeInput', $value);
            } else {
                $sanitizedSettings[$key] = SecurityHelper::sanitizeInput($value);
            }
        }

        try {
            // Використовуємо SettingsManager для збереження налаштувань
            if (class_exists('SettingsManager')) {
                $settingsManager = settingsManager();

                // Очищаємо кеш налаштувань перед збереженням, щоб гарантувати свіжі дані
                if (method_exists($settingsManager, 'clearCache')) {
                    $settingsManager->clearCache();
                }

                $result = $settingsManager->setMultiple($sanitizedSettings);

                if ($result) {
                    // Очищаємо кеш налаштувань після збереження
                    if (method_exists($settingsManager, 'clearCache')) {
                        $settingsManager->clearCache();
                    }

                    // Перезавантажуємо налаштування в SettingsManager
                    if (method_exists($settingsManager, 'reloadSettings')) {
                        $settingsManager->reloadSettings();
                    }

                    // Оновлюємо налаштування в Cache та Logger
                    if (class_exists('Cache')) {
                        $cacheInstance = cache();
                        if ($cacheInstance && method_exists($cacheInstance, 'reloadSettings')) {
                            $cacheInstance->reloadSettings();
                        }
                    }
                    if (class_exists('Logger')) {
                        $loggerInstance = logger();
                        if ($loggerInstance) {
                            // Очищаємо кеш налаштувань Logger перед перезавантаженням
                            if (method_exists($loggerInstance, 'clearSettingsCache')) {
                                $loggerInstance->clearSettingsCache();
                            }
                            // Перезавантажуємо налаштування Logger
                            if (method_exists($loggerInstance, 'reloadSettings')) {
                                $loggerInstance->reloadSettings();
                            }
                        }
                    }

                    // Застосовуємо timezone, якщо він був змінено
                    if (isset($sanitizedSettings['timezone'])) {
                        $timezone = $sanitizedSettings['timezone'];
                        if (! empty($timezone) && in_array($timezone, timezone_identifiers_list())) {
                            date_default_timezone_set($timezone);
                        }
                    }

                    // Оновлюємо протокол в глобальній змінній, якщо він був змінено
                    if (isset($sanitizedSettings['site_protocol'])) {
                        $protocolSetting = $sanitizedSettings['site_protocol'];
                        if ($protocolSetting === 'https') {
                            $GLOBALS['_SITE_PROTOCOL'] = 'https://';
                        } elseif ($protocolSetting === 'http') {
                            $GLOBALS['_SITE_PROTOCOL'] = 'http://';
                        } else {
                            // Якщо 'auto', очищаємо глобальну змінну для автоматичного визначення
                            unset($GLOBALS['_SITE_PROTOCOL']);
                        }
                    }

                    if (function_exists('logInfo')) {
                        logInfo('SiteSettingsPage: Site settings saved', [
                            'changed_keys' => array_keys($sanitizedSettings),
                        ]);
                    } else {
                        logger()->logInfo('Налаштування сайту збережено', [
                            'changed_keys' => array_keys($sanitizedSettings),
                        ]);
                    }
                    $this->setMessage('Налаштування успішно збережено', 'success');
                    // Редирект після збереження для запобігання повторного виконання
                    $this->redirect('site-settings');
                    exit;
                } else {
                    $this->setMessage('Помилка при збереженні налаштувань', 'danger');
                }
            } else {
                throw new Exception('SettingsManager не доступний');
            }
        } catch (Exception $e) {
            $this->setMessage('Помилка при збереженні налаштувань: ' . $e->getMessage(), 'danger');
            if (function_exists('logError')) {
                logError('SiteSettingsPage: Settings save error', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('Settings save error: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    /**
     * Отримання налаштувань
     *
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        // Значення за замовчуванням (використовуються тільки якщо налаштування відсутнє в БД)
        $defaultSettings = [
            'admin_email' => 'admin@example.com',
            'site_protocol' => 'auto', // Автоматичне визначення протоколу
            // Налаштування сайту
            'site_name' => 'Flowaxy CMS',
            'site_tagline' => '',
            'site_url' => '',
            // Налаштування користувачів
            'users_can_register' => '1',
            'default_user_role' => '2', // ID ролі за замовчуванням
            // Налаштування локалізації
            'site_language' => 'uk',
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i:s',
            'week_starts_on' => '1', // 1 = понеділок, 0 = неділя
            // Налаштування сесій та куків
            'cookie_lifetime' => '86400', // 24 години
            'cookie_path' => '/',
            'cookie_domain' => '',
            'cookie_secure' => '0',
            'cookie_httponly' => '1',
            // Налаштування кешу
            'cache_enabled' => '1',
            'cache_default_ttl' => '3600',
            'cache_auto_cleanup' => '1',
            // Налаштування логування
            'logging_enabled' => '1',
            'logging_level' => 'INFO', // Для зворотної сумісності
            'logging_levels' => 'INFO,WARNING,ERROR,CRITICAL', // Множинний вибір рівнів
            'logging_types' => 'file,db_errors,slow_queries', // Типи логування
            'logging_max_file_size' => '10485760', // 10 MB
            'logging_retention_days' => '30',
            'logging_rotation_type' => 'size', // size, time, both
            'logging_rotation_time' => '24', // годин для ротації за часом
            'logging_rotation_time_unit' => 'hours', // hours, days
            // Налаштування сесій
            'session_lifetime' => '7200', // 2 години
            'session_name' => 'PHPSESSID',
            // Налаштування бази даних
            'db_connection_timeout' => '3',
            'db_max_attempts' => '3',
            'db_host_check_timeout' => '1',
            'db_slow_query_threshold' => '1.0',
            // Налаштування завантаження файлів
            'upload_max_file_size' => '10485760', // 10 MB
            'upload_allowed_extensions' => 'jpg,jpeg,png,gif,pdf,doc,docx,zip',
            'upload_allowed_mime_types' => 'image/jpeg,image/png,image/gif,application/pdf',
            // Налаштування безпеки
            'password_min_length' => '8',
            'csrf_token_lifetime' => '3600', // 1 година
            // Налаштування продуктивності
            'query_optimization_enabled' => '1',
            'max_queries_per_second' => '100',
        ];

        // Використовуємо SettingsManager для отримання налаштувань
        if (class_exists('SettingsManager')) {
            try {
                $settingsManager = settingsManager();

                // Очищаємо кеш Cache перед завантаженням налаштувань, щоб уникнути використання застарілих даних
                if (function_exists('cache_forget')) {
                    cache_forget('site_settings');
                }

                // Очищаємо кеш SettingsManager перед завантаженням
                if (method_exists($settingsManager, 'clearCache')) {
                    $settingsManager->clearCache();
                }

                // Завантажуємо налаштування з БД безпосередньо (з force=true), оминаючи кеш
                if (method_exists($settingsManager, 'load')) {
                    $settingsManager->load(true); // force = true для примусового перезавантаження
                } elseif (method_exists($settingsManager, 'reloadSettings')) {
                    $settingsManager->reloadSettings();
                }

                // Отримуємо всі налаштування з БД
                $settings = $settingsManager->all();

                // Об'єднуємо налаштування: спочатку дефолтні, потім з БД (БД має пріоритет)
                // Це гарантує, що якщо налаштування є в БД (навіть зі значенням '0' або порожнім рядком), воно буде використано
                $result = array_merge($defaultSettings, $settings);

                // ВАЖЛИВО: Для timezone використовуємо тільки значення з БД, без дефолтів
                // Перевіряємо, чи є ключ в БД, і використовуємо його значення (навіть якщо порожнє)
                // Якщо ключа немає в БД, залишаємо порожнє значення
                if (array_key_exists('timezone', $settings)) {
                    // Використовуємо значення з БД (навіть якщо порожнє)
                    $dbTimezone = trim($settings['timezone']);
                    $result['timezone'] = $dbTimezone;
                } else {
                    // Якщо ключа немає в БД, залишаємо порожнє значення
                    $result['timezone'] = '';
                }

                // Конвертуємо рядки в масиви для множинного вибору
                if (isset($result['logging_levels'])) {
                    if (is_string($result['logging_levels'])) {
                        // Розбиваємо рядок на масив, обрізаємо пробіли та фільтруємо порожні значення
                        $levelsArray = array_filter(
                            array_map('trim', explode(',', (string)$result['logging_levels'])),
                            function ($value) {
                                return ! empty($value);
                            }
                        );
                        $result['logging_levels'] = ! empty($levelsArray)
                            ? array_values($levelsArray) // Переіндексуємо масив (0, 1, 2...)
                            : ['ERROR', 'CRITICAL']; // Значення за замовчуванням, якщо порожньо
                    } elseif (! is_array($result['logging_levels'])) {
                        // Якщо це не масив і не рядок, встановлюємо значення за замовчуванням
                        $result['logging_levels'] = ['ERROR', 'CRITICAL'];
                    } else {
                        // Якщо це вже масив, очищаємо від порожніх значень та пробілів
                        $cleanedLevels = array_filter(
                            array_map('trim', (array)$result['logging_levels']),
                            function ($value) {
                                return ! empty($value);
                            }
                        );
                        $result['logging_levels'] = ! empty($cleanedLevels)
                            ? array_values($cleanedLevels) // Переіндексуємо масив
                            : ['ERROR', 'CRITICAL']; // Значення за замовчуванням
                    }
                } else {
                    // Якщо налаштування взагалі немає, встановлюємо значення за замовчуванням
                    $result['logging_levels'] = ['ERROR', 'CRITICAL'];
                }
                if (isset($result['logging_types']) && is_string($result['logging_types'])) {
                    $result['logging_types'] = array_filter(
                        array_map('trim', explode(',', $result['logging_types'])),
                        function ($value) {
                            return ! empty($value);
                        }
                    );
                }

                return $result;
            } catch (Exception $e) {
                if (function_exists('logError')) {
                    logError('SiteSettingsPage: Settings load error', ['error' => $e->getMessage(), 'exception' => $e]);
                } else {
                    logger()->logError('Settings load error: ' . $e->getMessage(), ['exception' => $e]);
                }
                // Конвертуємо рядки в масиви для множинного вибору в дефолтних налаштуваннях
                $defaultSettings['logging_levels'] = explode(',', $defaultSettings['logging_levels']);
                $defaultSettings['logging_types'] = explode(',', $defaultSettings['logging_types']);

                return $defaultSettings;
            }
        }

        // Конвертуємо рядки в масиви для множинного вибору в дефолтних налаштуваннях
        $defaultSettings['logging_levels'] = explode(',', $defaultSettings['logging_levels']);
        $defaultSettings['logging_types'] = explode(',', $defaultSettings['logging_types']);

        return $defaultSettings;
    }
}
