<?php

/**
 * Реєстрація маршрутів адмінки
 * Використовується в index.php для автоматичної реєстрації маршрутів адмінки
 *
 * @param Router $router Роутер для адмінки
 * @return void
 */

declare(strict_types=1);

// Реєструємо базові маршрути адмінки
// Увага: всі сторінки адмінки повинні підтримувати GET та POST для AJAX запитів
$router->add(['GET', 'POST'], '', 'DashboardPage');
$router->add(['GET', 'POST'], 'dashboard', 'DashboardPage');
$router->add(['GET', 'POST'], 'login', 'LoginPage');
$router->add(['GET', 'POST'], 'logout', 'LogoutPage');
$router->add(['GET', 'POST'], 'settings', 'SettingsPage');
$router->add(['GET', 'POST'], 'site-settings', 'SiteSettingsPage');
$router->add(['GET', 'POST'], 'cache-clear', 'CacheClearPage');
// Removed: admin-customization - removed to keep clean brand design
// Removed: cache-view - now in plugins/cache-view
// Removed: logs-view - now in plugins/logs-view  
// Removed: storage-management - now in plugins/storage-management
$router->add(['GET', 'POST'], 'profile', 'ProfilePage');
$router->add(['GET', 'POST'], 'plugins', 'PluginsPage');
$router->add(['GET', 'POST'], 'themes', 'ThemesPage');
// Removed: customizer - now in plugins/theme-customizer
// Системні сторінки управління користувачами та ролями
$router->add(['GET', 'POST'], 'roles', 'RolesPage');
$router->add(['GET', 'POST'], 'users', 'UsersPage');
// Видалено: development сторінка

// Маршрут меню тепер реєструється через плагін menu-plugin

// Ініціалізуємо плагіни перед викликом хука, щоб вони могли зареєструвати свої маршрути
if (function_exists('pluginManager')) {
    $pluginManager = pluginManager();
    if ($pluginManager && method_exists($pluginManager, 'initializePlugins')) {
        $pluginManager->initializePlugins();
    }
}

// Хук для реєстрації маршрутів модулів та плагінів
hook_dispatch('admin_register_routes', $router);

// Реєструємо маршрути плагінів
$activePlugins = pluginManager()->getActivePlugins();
foreach ($activePlugins as $slug => $plugin) {
    // Пропускаємо плагін example - він не має адмін-сторінки
    if ($slug === 'example') {
        continue;
    }

    $pluginDir = dirname(__DIR__, 4) . '/plugins/' . $slug;

    // Перетворюємо slug в ім'я класу адмін-сторінки
    $parts = explode('-', $slug);
    $className = '';
    foreach ($parts as $part) {
        $className .= ucfirst($part);
    }
    $className .= 'AdminPage';

    // Спочатку пробуємо нову структуру (src/admin/pages/), потім стару (admin/)
    $adminPageFile = $pluginDir . '/src/admin/pages/' . $className . '.php';
    if (! file_exists($adminPageFile)) {
        $adminPageFile = $pluginDir . '/admin/' . $className . '.php';
    }

    // Використовуємо File клас для перевірки існування файлу
    if (class_exists('File')) {
        $file = new File($adminPageFile);
        if ($file->exists()) {
            require_once $adminPageFile;
            $router->add(['GET', 'POST'], $slug, $className);
        }
    } elseif (file_exists($adminPageFile)) {
        require_once $adminPageFile;
        $router->add(['GET', 'POST'], $slug, $className);
    }

    // Спеціальна реєстрація маршруту /admin/menus для плагіна menu-plugin
    if ($slug === 'menu-plugin') {
        $menusPageFile = $pluginDir . '/admin/MenusPage.php';
        if (file_exists($menusPageFile)) {
            require_once $menusPageFile;
            if (class_exists('MenusPage')) {
                $router->add(['GET', 'POST'], 'menus', 'MenusPage');
            }
        }
    }

    // Спеціальна реєстрація маршруту /admin/telegram-history для плагіна telegram-plugin
    if ($slug === 'telegram-plugin') {
        $historyPageFile = $pluginDir . '/src/admin/pages/TelegramHistoryPage.php';
        if (file_exists($historyPageFile)) {
            require_once $historyPageFile;
            if (class_exists('TelegramHistoryPage')) {
                $router->add(['GET', 'POST'], 'telegram-history', 'TelegramHistoryPage');
            }
        }
    }
}
