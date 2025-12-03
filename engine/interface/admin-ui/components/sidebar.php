<?php
/**
 * Компонент Sidebar
 */
?>
<nav id="sidebarMenu" class="sidebar d-md-block show">
    <div class="sidebar-content">
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
                    renderMenuItem($item, $currentPage);
                }
            endif;
?>
        </ul>
    </div>
</nav>
