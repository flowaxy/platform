<?php

/**
 * Універсальні пункти меню для sidebar та мобільного меню
 * Повертає масив пунктів меню
 */

declare(strict_types=1);

/**
 * Перевірка підтримки кастомізації активною темою
 * Використовує оптимізований метод ThemeManager
 */
function themeSupportsCustomization()
{
    return themeManager()->supportsCustomization();
}

/**
 * Перевірка підтримки навігації активною темою
 * Використовує оптимізований метод ThemeManager
 */
function themeSupportsNavigation()
{
    return themeManager()->supportsNavigation();
}

function getMenuItems()
{
    // Створюємо унікальний ключ кешу на основі активних плагінів та користувача
    // (різні користувачі мають різні права доступу, тому меню може відрізнятися)
    $pluginsHash = cache_remember('active_plugins_hash', function () {
        $activePlugins = pluginManager()->getActivePlugins();

        return md5(implode(',', array_keys($activePlugins)));
    }, 3600); // Кешуємо хеш плагінів на 1 годину

    // Додаємо ID користувача до ключа кешу для урахування прав доступу
    $session = sessionManager();
    $userId = (int)$session->get('admin_user_id');
    $userHash = $userId > 0 ? md5((string)$userId) : 'guest';

    $cacheKey = 'admin_menu_items_' . $pluginsHash . '_' . $userHash;

    return cache_remember($cacheKey, function () {
        // Починаємо з порожнього меню - всі пункти додаються через плагіни
        $menu = [];

        // Ініціалізуємо плагіни перед викликом хука admin_menu
        // Це потрібно, щоб плагіни могли зареєструвати свої пункти меню
        if (function_exists('pluginManager')) {
            $pluginManager = pluginManager();
            if ($pluginManager && method_exists($pluginManager, 'initializePlugins')) {
                $pluginManager->initializePlugins();
            }
        }

        // Застосовуємо хук для додавання пунктів меню від плагінів
        $menu = hook_apply('admin_menu', $menu);

        // Фільтруємо меню по правам доступу
        $filteredMenu = [];
        foreach ($menu as $item) {
            $hasAccess = true;

            // Перевіряємо права доступу для основного пункту меню
            $permission = $item['permission'] ?? null;
            if ($permission !== null && is_string($permission)) {
                // Для першого користувача завжди дозволяємо доступ
                $session = sessionManager();
                $userId = (int)$session->get('admin_user_id');
                if ($userId === 1) {
                    $hasAccess = true;
                } elseif (function_exists('current_user_can')) {
                    $hasAccess = current_user_can($permission);
                } else {
                    $hasAccess = false;
                }
            }

            // Якщо є підменю, перевіряємо права для кожного підпункту
            if ($hasAccess && isset($item['submenu']) && is_array($item['submenu'])) {
                $filteredSubmenu = [];
                foreach ($item['submenu'] as $subItem) {
                    $subHasAccess = true;
                    $subPermission = $subItem['permission'] ?? null;
                    if ($subPermission !== null && is_string($subPermission)) {
                        $session = sessionManager();
                        $userId = (int)$session->get('admin_user_id');
                        if ($userId === 1) {
                            $subHasAccess = true;
                        } elseif (function_exists('current_user_can')) {
                            $subHasAccess = current_user_can($subPermission);
                        } else {
                            $subHasAccess = false;
                        }
                    }
                    if ($subHasAccess) {
                        $filteredSubmenu[] = $subItem;
                    }
                }
                $item['submenu'] = $filteredSubmenu;

                // Якщо після фільтрації підменю порожнє і це не прямий посилання, не додаємо пункт меню
                if (empty($filteredSubmenu) && ($item['href'] ?? '#') === '#') {
                    $hasAccess = false;
                }
            }

            if ($hasAccess) {
                $filteredMenu[] = $item;
            }
        }

        $menu = $filteredMenu;

        // Сортуємо за order
        usort($menu, function ($a, $b) {
            $orderA = $a['order'] ?? 50;
            $orderB = $b['order'] ?? 50;

            return $orderA - $orderB;
        });

        // Сортуємо підменю для пунктів з підменю
        foreach ($menu as $key => $item) {
            if (isset($item['submenu']) && is_array($item['submenu'])) {
                usort($menu[$key]['submenu'], function ($a, $b) {
                    $orderA = $a['order'] ?? 50;
                    $orderB = $b['order'] ?? 50;

                    return $orderA - $orderB;
                });
            }
        }

        // Видаляємо меню з порожніми підменю
        $menuKeysToRemove = [];
        foreach ($menu as $key => $item) {
            if (isset($item['submenu']) && is_array($item['submenu']) && empty($item['submenu'])) {
                // Видаляємо тільки якщо це не прямий посилання (href !== '#')
                if (($item['href'] ?? '#') === '#') {
                    $menuKeysToRemove[] = $key;
                }
            }
        }

        // Видаляємо знайдені меню
        foreach ($menuKeysToRemove as $key) {
            unset($menu[$key]);
        }

        // Переіндексуємо масив після видалення
        if (! empty($menuKeysToRemove)) {
            $menu = array_values($menu);
        }

        // Повертаємо меню
        return $menu;
    }, 3600); // Кешуємо на 1 годину (меню змінюється рідко, тільки при зміні плагінів)
}

/**
 * Рендерити пункт меню
 */
function renderMenuItem($item, $currentPage, $isMobile = false)
{
    $hasSubmenu = isset($item['submenu']) && ! empty($item['submenu']);
    $target = isset($item['target']) ? ' target="' . $item['target'] . '"' : '';

    // Отримуємо параметр tab з URL один раз
    $request = Request::getInstance();
    $tab = SecurityHelper::sanitizeInput($request->query('tab', ''));

    if ($hasSubmenu) {
        // Меню з підменю (однаково для десктопу та мобільних)
        // Основний пункт меню активний тільки якщо активна одна з його підменю
        $activeClass = '';
        $hasActiveSubmenu = false;

        if (isset($item['submenu'])) {
            foreach ($item['submenu'] as $subItem) {
                if (isset($subItem['page'])) {
                    $subItemPage = $subItem['page'];

                    // Перевіряємо точний збіг з поточною сторінкою (найвищий пріоритет)
                    if ($currentPage === $subItemPage) {
                        // Якщо немає tab, значить це головна сторінка
                        if (empty($tab)) {
                            $hasActiveSubmenu = true;

                            break;
                        }
                    }

                    // Якщо є tab, перевіряємо збіг з урахуванням tab
                    if (! empty($tab)) {
                        $pageWithTab = $currentPage . '-' . $tab;
                        if ($subItemPage === $pageWithTab) {
                            $hasActiveSubmenu = true;

                            break;
                        }
                    }
                }
            }
        }

        // Основний пункт меню активний тільки якщо активна підменю
        if ($hasActiveSubmenu) {
            $activeClass = 'active';
        }

        if ($isMobile) {
            // Мобільна версія з підменю
            echo '<li class="nav-item has-submenu">';
            echo '<a class="nav-link submenu-toggle ' . $activeClass . '" href="#" onclick="toggleSubmenu(this, event)">';
            echo '<i class="' . ($item['icon'] ?? '') . '"></i>';
            echo '<span class="menu-text">' . htmlspecialchars($item['text'] ?? $item['title'] ?? '') . '</span>';
            echo '<i class="fas fa-chevron-down submenu-arrow"></i>';
            echo '</a>';
            echo '<ul class="submenu">';
            foreach ($item['submenu'] as $subItem) {
                // Визначаємо активний пункт підменю з урахуванням tab
                $subActive = false;
                if (isset($subItem['page'])) {
                    $subItemPage = $subItem['page'];
                    // Перевіряємо точний збіг (найвищий пріоритет)
                    if ($currentPage === $subItemPage && empty($tab)) {
                        $subActive = true;
                    }
                    // Якщо є tab, перевіряємо збіг з урахуванням tab
                    if (! empty($tab)) {
                        $pageWithTab = $currentPage . '-' . $tab;
                        if ($subItemPage === $pageWithTab) {
                            $subActive = true;
                        }
                    }
                }
                $subActiveClass = $subActive ? 'active' : '';
                $subIcon = isset($subItem['icon']) ? '<i class="' . $subItem['icon'] . '"></i>' : '';
                echo '<li><a href="' . $subItem['href'] . '" class="submenu-link ' . $subActiveClass . '">' . $subIcon . '<span class="menu-text">' . htmlspecialchars($subItem['text'] ?? $subItem['title'] ?? '') . '</span></a></li>';
            }
            echo '</ul>';
            echo '</li>';
        } else {
            // Десктопна версія з підменю
            echo '<li class="nav-item has-submenu">';
            echo '<a class="nav-link submenu-toggle ' . $activeClass . '" href="#" onclick="toggleSubmenu(this, event)">';
            echo '<i class="' . ($item['icon'] ?? '') . '"></i>';
            echo '<span class="menu-text">' . htmlspecialchars($item['text'] ?? $item['title'] ?? '') . '</span>';
            echo '<i class="fas fa-chevron-down submenu-arrow"></i>';
            echo '</a>';
            echo '<ul class="submenu">';
            foreach ($item['submenu'] as $subItem) {
                // Визначаємо активний пункт підменю з урахуванням tab
                $subActive = false;
                if (isset($subItem['page'])) {
                    $subItemPage = $subItem['page'];
                    // Перевіряємо точний збіг (найвищий пріоритет)
                    if ($currentPage === $subItemPage && empty($tab)) {
                        $subActive = true;
                    }
                    // Якщо є tab, перевіряємо збіг з урахуванням tab
                    if (! empty($tab)) {
                        $pageWithTab = $currentPage . '-' . $tab;
                        if ($subItemPage === $pageWithTab) {
                            $subActive = true;
                        }
                    }
                }
                $subActiveClass = $subActive ? 'active' : '';
                $subIcon = isset($subItem['icon']) ? '<i class="' . $subItem['icon'] . '"></i>' : '';
                echo '<li><a href="' . $subItem['href'] . '" class="' . $subActiveClass . '">' . $subIcon . '<span class="menu-text">' . htmlspecialchars($subItem['text'] ?? $subItem['title'] ?? '') . '</span></a></li>';
            }
            echo '</ul>';
            echo '</li>';
        }
    } else {
        // Звичайний пункт меню (без підменю)
        $isActive = false;
        if (isset($item['page'])) {
            $itemPage = $item['page'];
            // Перевіряємо точний збіг
            if ($currentPage === $itemPage) {
                // Якщо немає tab, значить це активна сторінка
                if (empty($tab)) {
                    $isActive = true;
                }
            }
            // Якщо є tab, перевіряємо збіг з урахуванням tab
            if (! empty($tab)) {
                $pageWithTab = $currentPage . '-' . $tab;
                if ($itemPage === $pageWithTab) {
                    $isActive = true;
                }
            }
        }

        $activeClass = $isActive ? 'active' : '';
        // Завжди використовуємо <li> для валідності HTML (ul повинен містити тільки li, script або template)
        echo '<li class="nav-item">';
        echo '<a class="nav-link ' . $activeClass . '" href="' . $item['href'] . '"' . $target . '>';
        echo '<i class="' . ($item['icon'] ?? '') . '"></i>';
        echo '<span class="menu-text">' . htmlspecialchars($item['text'] ?? $item['title'] ?? '') . '</span>';
        echo '</a>';
        echo '</li>';
    }
}
