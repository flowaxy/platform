<?php
/**
 * Компонент карточки темы
 *
 * @param array $theme Данные темы
 * @param array $features Функции темы
 * @param bool $supportsCustomization Поддерживает кастомизацию
 * @param bool $supportsNavigation Поддерживает навигацию
 * @param bool $hasSettings Есть настройки
 * @param string $colClass CSS класс колонки (по умолчанию col-lg-6)
 */
if (! isset($theme) || ! is_array($theme)) {
    return;
}
if (! isset($colClass)) {
    $colClass = 'col-lg-6';
}
if (! isset($features)) {
    $features = [];
}
if (! isset($supportsCustomization)) {
    $supportsCustomization = false;
}
if (! isset($supportsNavigation)) {
    $supportsNavigation = false;
}
if (! isset($hasSettings)) {
    $hasSettings = false;
}

$pluginManagerInstance = function_exists('pluginManager') ? pluginManager() : null;
$themeEditorPluginActive = $pluginManagerInstance && method_exists($pluginManagerInstance, 'isPluginActive') && $pluginManagerInstance->isPluginActive('theme-editor');
$themeCustomizerPluginActive = $pluginManagerInstance && method_exists($pluginManagerInstance, 'isPluginActive') && $pluginManagerInstance->isPluginActive('theme-customizer');

$themeName = htmlspecialchars($theme['name'] ?? 'Неизвестная тема');
$themeSlug = htmlspecialchars($theme['slug'] ?? '');
$themeVersion = htmlspecialchars($theme['version'] ?? '1.0.0');
$themeDescription = htmlspecialchars($theme['description'] ?? 'Опис відсутній');
$isActive = ($theme['is_active'] ?? 0) == 1;
$isDefault = ($theme['is_default'] ?? false);
$hasScssSupport = isset($theme['has_scss']) ? (bool)$theme['has_scss'] : (function_exists('themeManager') && themeManager()->hasScssSupport($themeSlug));

$status = $isActive ? 'active' : 'inactive';
?>
<div class="<?= htmlspecialchars($colClass) ?> mb-3 theme-item-wrapper" data-status="<?= $status ?>" data-name="<?= strtolower($themeName) ?>">
    <div class="theme-card <?= $isActive ? 'theme-active' : '' ?>">
        <div class="theme-header">
            <h6 class="theme-name"><?= $themeName ?></h6>
            <div class="theme-badges">
                <?php
                if ($isActive) {
                    include __DIR__ . '/badge.php';
                    $text = 'Активна';
                    $type = 'active';
                    unset($icon);
                } else {
                    include __DIR__ . '/badge.php';
                    $text = 'Неактивна';
                    $type = 'inactive';
                    unset($icon);
                }
?>
                <span class="theme-version">v<?= $themeVersion ?></span>
            </div>
        </div>
        
        <p class="theme-description"><?= $themeDescription ?></p>
        
        <?php if (! empty($features) && (($features['header'] ?? false) || ($features['parameters'] ?? false) || ($features['customization'] ?? false) || ($features['logo'] ?? false) || ($features['favicon'] ?? false))): ?>
            <div class="theme-features">
                <?php
$featureIcons = [
    'header' => ['icon' => 'header', 'title' => 'Підтримка хедера', 'text' => 'Header'],
    'parameters' => ['icon' => 'sliders-h', 'title' => 'Параметри налаштувань', 'text' => 'Параметри'],
    'customization' => ['icon' => 'paint-brush', 'title' => 'Кастомізація теми', 'text' => 'Кастомізація'],
    'logo' => ['icon' => 'image', 'title' => 'Логотип', 'text' => 'Логотип'],
    'favicon' => ['icon' => 'star', 'title' => 'Фавікон', 'text' => 'Фавікон'],
];

            foreach ($featureIcons as $key => $featureInfo):
                if ($features[$key] ?? false):
                    ?>
                    <span class="theme-feature-badge" title="<?= htmlspecialchars($featureInfo['title']) ?>">
                        <i class="fas fa-<?= htmlspecialchars($featureInfo['icon']) ?>"></i><?= htmlspecialchars($featureInfo['text']) ?>
                    </span>
                <?php
                endif;
            endforeach;
            ?>
            </div>
        <?php endif; ?>
        
        <div class="theme-actions">
            <?php
            // Перевірка прав доступу
            $session = sessionManager();
$userId = (int)$session->get('admin_user_id');
$hasActivateAccess = ($userId === 1) || (function_exists('current_user_can') && current_user_can('admin.access'));
$hasDeleteAccess = ($userId === 1) || (function_exists('current_user_can') && current_user_can('admin.access'));
$hasCustomizeAccess = ($userId === 1) || (function_exists('current_user_can') && current_user_can('admin.access'));
$hasEditAccess = ($userId === 1) || (function_exists('current_user_can') && current_user_can('admin.access'));
?>
            
            <?php if (! $isActive): ?>
                <?php if ($hasActivateAccess): ?>
                    <form method="POST" class="d-inline theme-activate-form" data-theme-slug="<?= $themeSlug ?>" data-has-scss="<?= $hasScssSupport ? '1' : '0' ?>">
                        <input type="hidden" name="csrf_token" value="<?= SecurityHelper::csrfToken() ?>">
                        <input type="hidden" name="theme_slug" value="<?= $themeSlug ?>">
                        <input type="hidden" name="activate_theme" value="1">
                        
                        <?php
            // Кнопка активации с спиннером
            ob_start();
                    $text = 'Активувати';
                    $type = 'primary';
                    $icon = 'check';
                    $attributes = ['type' => 'submit', 'class' => 'theme-activate-btn'];
                    unset($url);
                    include __DIR__ . '/button.php';
                    $activateBtn = ob_get_clean();

                    // Добавляем спиннер если есть SCSS поддержка
                    if ($hasScssSupport) {
                        $activateBtn = str_replace('</button>', '<span class="btn-spinner ms-2" style="display: none;"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span></span></button>', $activateBtn);
                    }
                    echo $activateBtn;
                    ?>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($supportsCustomization && $hasCustomizeAccess && $themeCustomizerPluginActive): ?>
                        <?php
                    $text = 'Кастомізація';
                        $type = 'primary';
                        $icon = 'paint-brush';
                        $url = UrlHelper::admin('theme-customizer');
                        unset($attributes);
                        include __DIR__ . '/button.php';
                        ?>
                    <?php endif; ?>
                    
                    <?php if ($supportsNavigation): ?>
                        <?php
                        $text = 'Навігація';
                        $type = 'primary';
                        $icon = 'bars';
                        $url = UrlHelper::admin('menus');
                        unset($attributes);
                        include __DIR__ . '/button.php';
                        ?>
                    <?php endif; ?>
                    
                    <?php if ($hasSettings && $hasEditAccess): ?>
                        <?php
                        $text = 'Налаштування';
                        $type = 'outline-secondary';
                        $icon = 'cog';
                        $url = UrlHelper::admin($themeSlug . '-theme-settings');
                        $attributes = ['title' => 'Налаштування теми'];
                        include __DIR__ . '/button.php';
                        ?>
                    <?php endif; ?>
                    
                    <?php if ($hasEditAccess && $themeEditorPluginActive): ?>
                        <?php
                        $text = 'Редактор';
                        $type = 'outline-info';
                        $icon = 'code';
                        $url = UrlHelper::admin('theme-editor?theme=' . urlencode($themeSlug));
                        $attributes = ['title' => 'Редактор теми'];
                        include __DIR__ . '/button.php';
                        ?>
                    <?php endif; ?>
                    
                    <?php
                    // Перевірка, чи можна деактивувати тему
                    $canDeactivate = false;
if ($hasActivateAccess) {
    if ($isDefault) {
        // Отримуємо кількість тем
        $allThemes = function_exists('themeManager') ? themeManager()->getAllThemes() : [];
        $themesCount = count($allThemes);

        // Стандартну тему можна деактивувати тільки якщо вона єдина і користувач - розробник
        if ($themesCount === 1 && $userId === 1) {
            $canDeactivate = true;
        }
    } else {
        // Нестандартну тему можна деактивувати звичайно
        $canDeactivate = true;
    }
}

if ($canDeactivate):
    ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= SecurityHelper::csrfToken() ?>">
                            <input type="hidden" name="action" value="deactivate_theme">
                            <input type="hidden" name="theme_slug" value="<?= $themeSlug ?>">
                            <?php
            $text = 'Деактивувати';
    $type = 'outline-warning';
    $icon = 'times';
    $attributes = ['type' => 'submit', 'title' => 'Деактивувати тему'];
    unset($url);
    include __DIR__ . '/button.php';
    ?>
                        </form>
                    <?php endif; ?>
                    
                    <?php if (($features['customization'] ?? false) && !$themeCustomizerPluginActive): ?>
                    <span class="text-muted small d-block mt-2">
                        <i class="fas fa-info-circle me-1"></i>Встановіть плагін <strong>theme-customizer</strong> для розблокування Кастомізації
                    </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php
            // Логіка видалення стандартної теми:
            // - Якщо тема стандартна і єдина - тільки розробник може видалити (навіть якщо активна)
            // - Якщо тема стандартна, але є інші теми - ніхто не може видалити
            // - Якщо тема не стандартна - звичайна перевірка прав (тільки неактивну)
            $canDelete = false;
if ($hasDeleteAccess) {
    if ($isDefault) {
        // Отримуємо кількість тем
        $allThemes = function_exists('themeManager') ? themeManager()->getAllThemes() : [];
        $themesCount = count($allThemes);

        // Стандартну тему можна видалити тільки якщо вона єдина і користувач - розробник
        // (навіть якщо вона активна)
        if ($themesCount === 1 && $userId === 1) {
            $canDelete = true;
        }
    } else {
        // Нестандартну тему можна видаляти тільки якщо вона неактивна
        if (! $isActive) {
            $canDelete = true;
        }
    }
}

if ($canDelete): ?>
                <?php
    $text = '';
    $type = 'danger';
    $icon = 'trash';
    $attributes = ['type' => 'button', 'class' => 'delete-theme-btn', 'data-theme-slug' => $themeSlug, 'title' => 'Видалити тему'];
    unset($url);
    include __DIR__ . '/button.php';
    ?>
            <?php endif; ?>
        </div>
    </div>
</div>

