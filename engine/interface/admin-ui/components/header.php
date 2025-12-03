<?php
/**
 * Компонент Header
 */
?>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid">
        <!-- Ліва частина навбара -->
        <div class="d-flex align-items-center">
            <!-- Кнопка мобільного меню -->
            <button class="mobile-menu-btn" onclick="toggleMobileSidebar()" aria-label="Відкрити меню" title="Відкрити меню">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Логотип -->
            <a class="navbar-brand d-none d-md-flex align-items-center header-logo" href="<?= UrlHelper::admin('dashboard') ?>">
                <img src="<?= UrlHelper::admin('assets/images/brand/logo-white.png') ?>" alt="Flowaxy CMS" class="admin-logo-img">
            </a>
        </div>

        <!-- Права частина навбара - іконки в ряд -->
        <div class="d-flex align-items-center gap-2 header-right-actions">
            <!-- Іконки швидкого доступу до розділів -->
            <?php
            // Визначаємо поточну сторінку
            $currentPage = Router::getCurrentPage();
$isDashboard = ($currentPage === 'dashboard');
$isPlugins = ($currentPage === 'plugins');
$isThemes = ($currentPage === 'themes');

// Перевірка прав доступу
$session = sessionManager();
$userId = (int)$session->get('admin_user_id');
$hasPluginsAccess = function_exists('current_user_can') && current_user_can('admin.access');
$hasThemesAccess = function_exists('current_user_can') && current_user_can('admin.access');
$pluginManagerInstance = function_exists('pluginManager') ? pluginManager() : null;
$apiKeysPluginActive = $pluginManagerInstance && method_exists($pluginManagerInstance, 'isPluginActive') && $pluginManagerInstance->isPluginActive('api-keys');
$hasApiKeysAccess = $apiKeysPluginActive && (function_exists('current_user_can') && current_user_can('admin.access'));
$hasWebhooksAccess = function_exists('current_user_can') && current_user_can('admin.access');
$hasIntegrationsAccess = $hasApiKeysAccess || $hasWebhooksAccess;
?>
            <div class="header-quick-actions d-flex align-items-center" style="position: relative; overflow: visible;">
                <!-- Панель управління - доступна всім авторизованим -->
                <a href="<?= UrlHelper::admin('dashboard') ?>" class="header-quick-action-btn <?= $isDashboard ? 'active' : '' ?>" title="Панель управління">
                    <i class="fas fa-home"></i>
                    <span class="d-none d-md-inline ms-1">Панель</span>
                </a>
                
                <!-- Плагіни -->
                <?php if ($hasPluginsAccess): ?>
                <a href="<?= UrlHelper::admin('plugins') ?>" class="header-quick-action-btn <?= $isPlugins ? 'active' : '' ?>" title="Плагіни">
                    <i class="fas fa-puzzle-piece"></i>
                    <span class="d-none d-md-inline ms-1">Плагіни</span>
                </a>
                <?php endif; ?>
                
                <!-- Теми -->
                <?php if ($hasThemesAccess): ?>
                <a href="<?= UrlHelper::admin('themes') ?>" class="header-quick-action-btn <?= $isThemes ? 'active' : '' ?>" title="Теми оформлення">
                    <i class="fas fa-palette"></i>
                    <span class="d-none d-md-inline ms-1">Теми</span>
                </a>
                <?php endif; ?>
                
                <!-- Налаштування -->
                <?php
                $isSettings = ($currentPage === 'settings');
                $hasSettingsAccess = function_exists('current_user_can') && current_user_can('admin.access');
                ?>
                <?php if ($hasSettingsAccess): ?>
                <a href="<?= UrlHelper::admin('settings') ?>" class="header-quick-action-btn <?= $isSettings ? 'active' : '' ?>" title="Налаштування">
                    <i class="fas fa-cog"></i>
                    <span class="d-none d-md-inline ms-1">Налаштування</span>
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Перегляд сайту -->
            <a href="<?= class_exists('UrlHelper') ? UrlHelper::site() : '/' ?>" 
               class="header-view-site-btn" 
               target="_blank" 
               title="Сайт"
               rel="noopener noreferrer">
                <i class="fas fa-external-link-square-alt"></i>
            </a>
            
            <!-- Управління кешем -->
            <?php
            $hasCacheAccess = function_exists('current_user_can') && current_user_can('admin.access');
?>
            <?php if ($hasCacheAccess): ?>
            <div class="dropdown">
                <button class="header-dropdown-btn" type="button" data-bs-toggle="dropdown" title="Очистити кеш">
                    <i class="fas fa-database"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <button type="button" class="dropdown-item cache-clear-btn" data-action="clear_all">
                            <i class="fas fa-trash me-2"></i>Очистити весь кеш
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item cache-clear-btn" data-action="clear_expired">
                            <i class="fas fa-clock me-2"></i>Очистити прострочений кеш
                        </button>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
            
            <!-- Dropdown пользователя -->
            <div class="dropdown">
                <button class="header-dropdown-btn" type="button" data-bs-toggle="dropdown" title="Користувач">
                    <i class="fas fa-user"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= UrlHelper::admin('profile') ?>"><i class="fas fa-user me-2"></i>Профіль</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= UrlHelper::admin('logout') ?>?token=<?= SecurityHelper::csrfToken() ?>"><i class="fas fa-sign-out-alt me-2"></i>Вийти</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Мобильная боковая панель -->
<div class="mobile-sidebar-overlay" onclick="toggleMobileSidebar()"></div>
<div class="mobile-sidebar">
    <!-- Заголовок мобильной панели -->
    <div class="mobile-sidebar-header">
        <div class="mobile-sidebar-logo">
            <img src="<?= UrlHelper::admin('assets/images/brand/logo-white.png') ?>" alt="Flowaxy CMS" class="mobile-logo-img">
        </div>
        <button class="mobile-sidebar-close" onclick="toggleMobileSidebar()" aria-label="Закрити меню" title="Закрити меню">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <ul class="nav flex-column wp-menu">
        <?php
        require_once __DIR__ . '/../includes/menu-items.php';
// Router завантажується автоматично через автозавантажувач
$currentPage = Router::getCurrentPage();
$menuItems = getMenuItems();

if (empty($menuItems)):
    ?>
            <li class="nav-item">
                <div class="sidebar-empty-state">
                    <div class="sidebar-empty-icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <p class="sidebar-empty-text">Розділів немає</p>
                    <p class="sidebar-empty-hint">Встановіть плагіни для додавання розділів</p>
                </div>
            </li>
        <?php else:
            foreach ($menuItems as $item) {
                renderMenuItem($item, $currentPage, true);
            }
        endif;
?>
    </ul>
</div>
