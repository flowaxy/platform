<?php
/**
 * Компонент карточки плагина
 *
 * @param array $plugin Данные плагина
 * @param string $colClass CSS класс колонки (по умолчанию col-lg-6)
 */
if (! isset($plugin) || ! is_array($plugin)) {
    return;
}
if (! isset($colClass)) {
    $colClass = 'col-12 col-sm-6 col-lg-4 col-xl-3';
}

$pluginName = htmlspecialchars($plugin['name'] ?? 'Неизвестный плагин');
$pluginSlug = htmlspecialchars($plugin['slug'] ?? '');
$pluginVersion = htmlspecialchars($plugin['version'] ?? '1.0.0');
$pluginDescription = htmlspecialchars($plugin['description'] ?? 'Описание отсутствует');
$pluginAuthor = htmlspecialchars($plugin['author'] ?? '');
$pluginAuthorUrl = htmlspecialchars($plugin['author_url'] ?? '');
$isActive = $plugin['is_active'] ?? false;
$isInstalled = $plugin['is_installed'] ?? false;
$hasSettings = $plugin['has_settings'] ?? false;

$status = $isActive ? 'active' : ($isInstalled ? 'inactive' : 'available');
?>
<div class="<?= htmlspecialchars($colClass) ?> mb-3 plugin-item" data-status="<?= $status ?>" data-name="<?= strtolower($pluginName) ?>">
    <div class="plugin-card">
        <div class="plugin-header-section">
            <h6 class="plugin-name"><?= $pluginName ?></h6>
            <span class="plugin-version-top">v<?= $pluginVersion ?></span>
        </div>
        
        <div class="plugin-description-section">
            <p class="plugin-description"><?= $pluginDescription ?></p>
        </div>
        
        <div class="plugin-footer-section">
            <div class="plugin-meta">
                <?php if (!empty($pluginAuthor)): ?>
                    <?php if (!empty($pluginAuthorUrl)): ?>
                        <a href="<?= $pluginAuthorUrl ?>" target="_blank" rel="noopener noreferrer" class="plugin-author-link">
                            <?= $pluginAuthor ?>
                        </a>
                    <?php else: ?>
                        <span class="plugin-author-text"><?= $pluginAuthor ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="plugin-actions">
            <?php
            // Перевірка прав доступу - використовуємо загальне право admin.access
            $hasAccess = function_exists('current_user_can') && current_user_can('admin.access');
            $hasInstallAccess = $hasAccess;
            $hasActivateAccess = $hasAccess;
            $hasDeactivateAccess = $hasAccess;
            $hasDeleteAccess = $hasAccess;
            $hasSettingsAccess = $hasAccess;
            ?>
            
            <?php if (! $isInstalled): ?>
                <?php if ($hasInstallAccess): ?>
                    <?php
        $text = 'Встановити';
                    $type = 'primary';
                    $icon = 'download';
                    $attributes = ['onclick' => "installPlugin('{$pluginSlug}')"];
                    unset($url);
                    include __DIR__ . '/button.php';
                    ?>
                <?php endif; ?>
            <?php elseif ($isActive): ?>
                <?php if ($hasDeactivateAccess): ?>
                    <?php
                    $text = 'Деактивувати';
                    $type = 'warning';
                    $icon = 'pause';
                    $attributes = ['onclick' => "togglePlugin('{$pluginSlug}', false)"];
                    unset($url);
                    include __DIR__ . '/button.php';
                    ?>
                <?php endif; ?>
                
                <?php if ($hasSettings && $hasSettingsAccess): ?>
                    <?php
                    $text = '';
                    $type = 'secondary';
                    $icon = 'cog';
                    $url = UrlHelper::admin($pluginSlug . '-settings');
                    $attributes = ['title' => 'Налаштування плагіна'];
                    include __DIR__ . '/button.php';
                    ?>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($hasActivateAccess): ?>
                    <?php
                    $text = 'Активувати';
                    $type = 'success';
                    $icon = 'play';
                    $attributes = ['onclick' => "togglePlugin('{$pluginSlug}', true)"];
                    unset($url);
                    include __DIR__ . '/button.php';
                    ?>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($isInstalled && $hasDeleteAccess): ?>
                <?php if ($isActive): ?>
                    <?php
                    $text = '';
                    $type = 'danger';
                    $icon = 'trash';
                    $attributes = ['disabled' => true, 'title' => 'Спочатку деактивуйте плагін перед видаленням'];
                    unset($url);
                    include __DIR__ . '/button.php';
                    ?>
                <?php else: ?>
                    <?php
                    $text = '';
                    $type = 'danger';
                    $icon = 'trash';
                    $attributes = ['onclick' => "uninstallPlugin('{$pluginSlug}')", 'title' => 'Видалити плагін'];
                    unset($url);
                    include __DIR__ . '/button.php';
                    ?>
                <?php endif; ?>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

