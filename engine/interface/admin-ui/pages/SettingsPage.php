<?php

/**
 * Сторінка налаштувань (головна сторінка зі списком посилань)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/AdminPage.php';

class SettingsPage extends AdminPage
{
    public function __construct()
    {
        parent::__construct();

        $this->pageTitle = 'Налаштування - Flowaxy CMS';
        $this->templateName = 'settings';

        $this->setPageHeader(
            'Налаштування',
            'Управління системою та конфігурацією',
            'fas fa-cog'
        );
        
        // Додаємо хлібні крихти
        $this->setBreadcrumbs([
            ['title' => 'Головна', 'url' => UrlHelper::admin('dashboard')],
            ['title' => 'Налаштування'],
        ]);
    }

    public function handle()
    {
        // Отримуємо базові категорії
        $settingsCategories = $this->getDefaultSettingsCategories();
        
        // Дозволяємо плагінам додавати свої елементи до категорій через хук
        // Плагіни перевіряють свою активність самостійно
        $settingsCategories = applyFilter('settings_categories', $settingsCategories);

        // Видаляємо дублікати з категорій (за URL)
        foreach ($settingsCategories as $key => $category) {
            if (isset($category['items']) && is_array($category['items'])) {
                $seenUrls = [];
                $uniqueItems = [];
                
                foreach ($category['items'] as $item) {
                    $url = $item['url'] ?? '';
                    if (!empty($url) && !in_array($url, $seenUrls, true)) {
                        $seenUrls[] = $url;
                        $uniqueItems[] = $item;
                    } elseif (empty($url)) {
                        // Якщо немає URL, залишаємо (хоча це майже неможливо)
                        $uniqueItems[] = $item;
                    }
                }
                
                $settingsCategories[$key]['items'] = $uniqueItems;
            }
        }

        // Фильтруем элементы по правам доступа
        foreach ($settingsCategories as $key => $category) {
            $settingsCategories[$key]['items'] = array_filter($category['items'], function ($item) {
                if (isset($item['permission']) && $item['permission'] !== null) {
                    if (function_exists('current_user_can')) {
                        $session = sessionManager();
                        $userId = $session->get('admin_user_id');
                        // Для первого пользователя всегда разрешаем доступ
                        if ($userId == 1) {
                            return true;
                        }

                        return current_user_can($item['permission']);
                    }

                    return false;
                }

                return true; // Если permission не указан, доступен всем
            });
        }

        // Видаляємо порожні категорії
        $settingsCategories = array_filter($settingsCategories, function ($category) {
            return ! empty($category['items']);
        });

        // Рендеримо сторінку
        $this->render([
            'settingsCategories' => $settingsCategories,
        ]);
    }

    /**
     * Отримання категорій налаштувань за замовчуванням
     *
     * @return array<string, mixed>
     */
    private function getDefaultSettingsCategories(): array
    {
        $categories = [];

        // Основні налаштування
        $categories['general'] = [
            'title' => 'Основні налаштування',
            'icon' => 'fas fa-cog',
            'items' => [
                [
                    'title' => 'Налаштування сайту',
                    'description' => 'Email, часовий пояс, кеш, логування',
                    'url' => UrlHelper::admin('site-settings'),
                    'icon' => 'fas fa-globe',
                    'permission' => 'admin.access',
                ],
            ],
        ];

        // Користувачі та права
        $categories['users'] = [
            'title' => 'Користувачі та права',
            'icon' => 'fas fa-users',
            'items' => [],
        ];

        // Ролі та права
        if (function_exists('current_user_can')) {
            $session = sessionManager();
            $userId = $session->get('admin_user_id');
            $hasRolesAccess = ($userId == 1) || current_user_can('admin.access');

            if ($hasRolesAccess) {
                $categories['users']['items'][] = [
                    'title' => 'Ролі та права',
                    'description' => 'Управління ролями та правами доступу',
                    'url' => UrlHelper::admin('roles'),
                    'icon' => 'fas fa-user-shield',
                    'permission' => 'admin.access',
                ];
            }
        }

        // Користувачі (тепер через плагін users)
        // Меню реєструється автоматично через плагін, тут тільки додаємо в категорію для SettingsPage
        if (function_exists('current_user_can')) {
            $session = sessionManager();
            $userId = $session->get('admin_user_id');
            $hasUsersAccess = ($userId == 1) || current_user_can('admin.access');

            if ($hasUsersAccess) {
                $categories['users']['items'][] = [
                    'title' => 'Користувачі',
                    'description' => 'Управління користувачами системи',
                    'url' => UrlHelper::admin('users'),
                    'icon' => 'fas fa-users',
                    'permission' => 'admin.access',
                ];
            }
        }

        // Профіль
        $categories['users']['items'][] = [
            'title' => 'Мій профіль',
            'description' => 'Особисті налаштування та дані',
            'url' => UrlHelper::admin('profile'),
            'icon' => 'fas fa-user',
            'permission' => null, // Доступен всем авторизованным
        ];

        // Расширения
        $categories['extensions'] = [
            'title' => 'Розширення',
            'icon' => 'fas fa-puzzle-piece',
            'items' => [
                [
                    'title' => 'Плагіни',
                    'description' => 'Управління плагінами',
                    'url' => UrlHelper::admin('plugins'),
                    'icon' => 'fas fa-plug',
                    'permission' => 'admin.access',
                ],
                [
                    'title' => 'Теми',
                    'description' => 'Управління темами',
                    'url' => UrlHelper::admin('themes'),
                    'icon' => 'fas fa-paint-brush',
                    'permission' => 'admin.access',
                ],
            ],
        ];

        $pluginManagerInstance = function_exists('pluginManager') ? pluginManager() : null;
        $apiKeysPluginActive = $pluginManagerInstance && method_exists($pluginManagerInstance, 'isPluginActive') && $pluginManagerInstance->isPluginActive('api-keys');
        $devTestsPluginActive = $pluginManagerInstance && method_exists($pluginManagerInstance, 'isPluginActive') && $pluginManagerInstance->isPluginActive('dev-tests');

        $categories['api'] = [
            'title' => 'API та інтеграції',
            'icon' => 'fas fa-code',
            'items' => [],
        ];

        if ($apiKeysPluginActive) {
            $categories['api']['items'][] = [
                'title' => 'API ключі',
                'description' => 'Управління API ключами',
                'url' => UrlHelper::admin('api-keys'),
                'icon' => 'fas fa-key',
                'permission' => 'admin.access',
            ];
        }

        // Інструменти
        $categories['tools'] = [
            'title' => 'Інструменти',
            'icon' => 'fas fa-tools',
            'items' => [],
        ];

        if ($devTestsPluginActive) {
            $categories['tools']['items'][] = [
                'title' => 'Тестування',
                'description' => 'Запуск та управління тестами системи',
                'url' => UrlHelper::admin('development/tools/tests'),
                'icon' => 'fas fa-vial',
                'permission' => 'admin.access',
            ];
        }

        // Система - ініціалізуємо порожньою, плагіни додадуть свої елементи через хук
        $categories['system'] = [
            'title' => 'Система',
            'icon' => 'fas fa-server',
            'items' => [],
        ];

        return $categories;
    }
}
