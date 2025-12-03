<?php
/**
 * Шаблон сторінки управління плагінами
 * Стиль як у cache-view: статистичні картки зверху, список плагінів, інформаційний блок
 */
$rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 4);
$componentsPath = $rootDir . '/engine/interface/admin-ui/components/';
$stats = $stats ?? [
    'total' => 0,
    'installed' => 0,
    'active' => 0,
    'inactive' => 0,
];
$installedPlugins = $installedPlugins ?? [];

// Показуємо кастомне уведомлення замість стандартного alert
if (!empty($message)) {
    $type = $messageType ?? 'info';
    $messageJson = json_encode($message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    $typeJson = json_encode($type, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<script>';
    echo '(function() {';
    echo '    function showCustomNotification() {';
    echo '        if (typeof window.showNotification !== "undefined") {';
    echo '            window.showNotification(' . $messageJson . ', ' . $typeJson . ');';
    echo '        } else if (typeof window.Notifications !== "undefined" && typeof window.Notifications.show === "function") {';
    echo '            window.Notifications.show(' . $messageJson . ', ' . $typeJson . ');';
    echo '        } else {';
    echo '            setTimeout(showCustomNotification, 100);';
    echo '        }';
    echo '    }';
    echo '    if (document.readyState === "loading") {';
    echo '        document.addEventListener("DOMContentLoaded", showCustomNotification);';
    echo '    } else {';
    echo '        setTimeout(showCustomNotification, 100);';
    echo '    }';
    echo '})();';
    echo '</script>';
}
?>
<div class="plugins-page">
    <!-- Статистичні картки -->
    <div class="plugins-stats-section">
        <?php
        $cards = [
            [
                'title' => 'Всього плагінів',
                'value' => number_format($stats['total'], 0, ',', ' '),
                'icon' => 'puzzle-piece',
                'color' => 'primary'
            ],
            [
                'title' => 'Встановлено',
                'value' => number_format($stats['installed'], 0, ',', ' '),
                'icon' => 'check-circle',
                'color' => 'info'
            ],
            [
                'title' => 'Активні',
                'value' => $stats['active'] > 0
                    ? '<span class="text-success">' . number_format($stats['active'], 0, ',', ' ') . '</span>'
                    : '<span class="text-muted">0</span>',
                'icon' => 'power-off',
                'color' => $stats['active'] > 0 ? 'success' : 'secondary',
                'valueClass' => 'h5'
            ],
            [
                'title' => 'Неактивні',
                'value' => $stats['inactive'] > 0
                    ? '<span class="text-warning">' . number_format($stats['inactive'], 0, ',', ' ') . '</span>'
                    : '<span class="text-muted">0</span>',
                'icon' => 'pause-circle',
                'color' => $stats['inactive'] > 0 ? 'warning' : 'secondary',
                'valueClass' => 'h5'
            ]
        ];
        include $componentsPath . 'stats-cards.php';
        ?>
    </div>

    <!-- Список плагінів -->
    <div class="plugins-list-section">
        <div class="card border-0">
            <div class="card-body p-0">
                <?php if (!empty($installedPlugins)): ?>
                    <div class="plugins-list">
                        <div class="row">
                            <?php foreach ($installedPlugins as $plugin): ?>
                                <?php
                                $colClass = 'col-12 col-sm-6 col-lg-4 col-xl-3';
                                include $componentsPath . 'plugin-card.php';
                                ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                    // Порожній стан
                    $icon = 'puzzle-piece';
                    $title = 'Плагіни відсутні';
                    $message = 'Встановіть плагін за замовчуванням або завантажте новий плагін з маркетплейсу.';
                    include $componentsPath . 'empty-state.php';
                    ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Інформаційний блок -->
    <div class="plugins-info-section">
        <?php
        $title = 'Про плагіни';
        $titleIcon = 'info-circle';
        $sections = [
            [
                'title' => 'Що таке плагіни:',
                'icon' => 'question-circle',
                'iconColor' => 'primary',
                'items' => [
                    'Розширюють функціональність системи',
                    'Дозволяють додавати нові можливості без змін ядра',
                    'Можуть бути активовані та деактивовані в будь-який час',
                    'Встановлюються через ZIP архів з файлом plugin.json'
                ]
            ],
            [
                'title' => 'Управління плагінами:',
                'icon' => 'cog',
                'iconColor' => 'info',
                'items' => [
                    'Активуйте плагіни для їх використання',
                    'Деактивуйте перед видаленням',
                    'Перевіряйте сумісність з версією системи',
                    'Регулярно оновлюйте для безпеки та нових функцій'
                ]
            ]
        ];
        include $componentsPath . 'info-block.php';
        ?>
    </div>
</div>

<script>
function togglePlugin(slug, activate) {
    const action = activate ? 'activate' : 'deactivate';
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?= SecurityHelper::csrfToken() ?>">
        <input type="hidden" name="action" value="${action}">
        <input type="hidden" name="plugin_slug" value="${slug}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function installPlugin(slug) {
    if (confirm('Встановити цей плагін?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= SecurityHelper::csrfToken() ?>">
            <input type="hidden" name="action" value="install">
            <input type="hidden" name="plugin_slug" value="${slug}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function uninstallPlugin(slug) {
    if (confirm('Ви впевнені, що хочете видалити цей плагін? Всі дані плагіна будуть втрачені.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= SecurityHelper::csrfToken() ?>">
            <input type="hidden" name="action" value="uninstall">
            <input type="hidden" name="plugin_slug" value="${slug}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function filterPlugins() {
    const statusFilter = document.getElementById('statusFilter')?.value || 'all';
    const searchFilter = document.getElementById('searchFilter')?.value.toLowerCase() || '';
    const plugins = document.querySelectorAll('.plugin-item');
    
    plugins.forEach(plugin => {
        const status = plugin.dataset.status;
        const name = plugin.dataset.name;
        
        let showStatus = statusFilter === 'all' || status === statusFilter;
        let showSearch = searchFilter === '' || name.includes(searchFilter);
        
        plugin.style.display = showStatus && showSearch ? 'block' : 'none';
    });
}

function resetFilters() {
    const statusFilter = document.getElementById('statusFilter');
    const searchFilter = document.getElementById('searchFilter');
    if (statusFilter) statusFilter.value = 'all';
    if (searchFilter) searchFilter.value = '';
    filterPlugins();
}
</script>

<style>
/* Стилі в стилі cache-view */
.plugins-page {
    background: transparent;
}

/* Відступи між секціями */
.plugins-stats-section {
    margin-bottom: 2rem;
}

.plugins-list-section {
    margin-bottom: 0;
}

.plugins-info-section {
    margin-bottom: 0;
}

/* Картка списку плагінів */
.plugins-list-section .card {
    border: none;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
}

.plugins-list-section .card-body {
    padding: 0;
}

/* Список плагінів */
.plugins-list {
    padding: 0;
}

.plugins-list .row {
    display: flex;
    flex-wrap: wrap;
    margin-left: -12px;
    margin-right: -12px;
}

.plugins-list .row > [class*="col-"] {
    padding-left: 12px;
    padding-right: 12px;
}

.plugins-list .plugin-item {
    display: flex;
    flex-direction: column;
    margin-bottom: 16px;
}

.plugins-list .plugin-item:last-child {
    margin-bottom: 0;
}

/* Картка плагіна - строгий дизайн */
.plugin-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 0;
    margin-bottom: 16px;
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: border-color 0.2s ease;
    position: relative;
    overflow: hidden;
}

.plugin-card:last-child {
    margin-bottom: 0;
}

.plugin-card:hover {
    border-color: #d1d5db;
}

.plugin-header-section {
    padding: 14px 16px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    flex-shrink: 0;
    box-sizing: border-box;
}

.plugin-description-section {
    padding: 14px 16px;
    border-bottom: 1px solid #e5e7eb;
    flex-grow: 1;
    box-sizing: border-box;
}

.plugin-footer-section {
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
    min-height: 0;
    box-sizing: border-box;
}

.plugin-footer-section .plugin-meta {
    flex: 0 0 auto;
    min-width: 0;
}

.plugin-footer-section .plugin-actions {
    flex: 0 0 auto;
}

.plugin-name {
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
    margin: 0;
    line-height: 1.3;
    flex: 1;
    min-width: 0;
}

.plugin-version-top {
    font-size: 0.8125rem;
    color: #9ca3af;
    font-weight: 400;
    white-space: nowrap;
    flex-shrink: 0;
}

.plugin-badges {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-shrink: 0;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.plugins-page .badge {
    padding: 4px 10px;
    font-size: 0.6875rem;
    font-weight: 600;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    line-height: 1.4;
}

.plugins-page .badge-active {
    background: #22c55e;
    color: #ffffff;
}

.plugins-page .badge-installed {
    background: #64748b;
    color: #ffffff;
}

.plugins-page .badge-available {
    background: #3b82f6;
    color: #ffffff;
}

.plugin-version {
    font-size: 0.8125rem;
    color: #94a3b8;
    font-weight: 500;
    white-space: nowrap;
}

.plugin-description {
    color: #6b7280;
    font-size: 0.8125rem;
    margin: 0;
    line-height: 1.5;
    min-height: 36px;
}


.plugin-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    margin: 0;
    font-size: 0.8125rem;
    line-height: 1.5;
}

.plugin-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.plugin-meta-label {
    color: #94a3b8;
    font-weight: 500;
}

.plugin-author-link {
    color: #94a3b8;
    text-decoration: none;
    transition: color 0.2s ease;
    font-weight: 500;
}

.plugin-author-link:hover {
    color: #64748b;
    text-decoration: underline;
}

.plugin-author-text {
    color: #94a3b8;
    font-weight: 500;
}

.plugin-meta .plugin-version {
    color: #64748b;
    font-weight: 500;
    font-size: 0.8125rem;
}

.plugin-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.plugin-actions .btn {
    border-radius: 4px;
    border: 1px solid transparent;
    padding: 6px 12px;
    font-weight: 500;
    font-size: 0.8125rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    white-space: nowrap;
    min-height: 32px;
    box-sizing: border-box;
    transition: background-color 0.2s ease, border-color 0.2s ease;
    line-height: 1.4;
}

.plugin-actions .btn .btn-icon {
    display: inline-flex;
    align-items: center;
    line-height: 1;
    font-size: 0.875rem;
}

.plugin-actions .btn .btn-text {
    display: inline;
}

.plugin-actions .btn-primary {
    background: #3b82f6;
    border-color: #3b82f6;
    color: #ffffff;
}

.plugin-actions .btn-primary:hover:not(:disabled) {
    background: #2563eb;
    border-color: #2563eb;
}

.plugin-actions .btn-success {
    background: #22c55e;
    border-color: #22c55e;
    color: #ffffff;
}

.plugin-actions .btn-success:hover:not(:disabled) {
    background: #16a34a;
    border-color: #16a34a;
}

.plugin-actions .btn-warning {
    background: #eab308;
    border-color: #eab308;
    color: #ffffff;
}

.plugin-actions .btn-warning:hover:not(:disabled) {
    background: #ca8a04;
    border-color: #ca8a04;
}

.plugin-actions .btn-danger {
    background: #fca5a5;
    border-color: #fca5a5;
    color: #ffffff;
    padding: 6px 10px;
    min-width: 32px;
}

.plugin-actions .btn-danger:hover:not(:disabled) {
    background: #f87171;
    border-color: #f87171;
}

.plugin-actions .btn-danger:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    background: #f1f5f9;
    border-color: #e2e8f0;
    color: #94a3b8;
}

.plugin-actions .btn-secondary {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #475569;
    padding: 6px 10px;
    min-width: 32px;
}

.plugin-actions .btn-secondary:hover:not(:disabled) {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #334155;
}

/* Адаптивність для планшетів (2 колонки) */
@media (min-width: 576px) and (max-width: 991.98px) {
    .plugins-list .row {
        margin-left: -10px;
        margin-right: -10px;
    }
    
    .plugins-list .row > [class*="col-"] {
        padding-left: 10px;
        padding-right: 10px;
    }
    
    .plugin-card {
        margin-bottom: 20px;
    }
    
    .plugin-header-section,
    .plugin-description-section,
    .plugin-footer-section {
        padding: 18px;
    }
    
    .plugin-name {
        font-size: 1.0625rem;
    }
    
    .plugin-description {
        font-size: 0.875rem;
        line-height: 1.5;
        margin-bottom: 16px;
        min-height: 40px;
    }
    
    .plugin-footer {
        padding-top: 14px;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .plugin-meta {
        font-size: 0.8125rem;
    }
    
    .plugin-actions {
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .plugin-actions .btn {
        padding: 8px 14px;
        font-size: 0.8125rem;
        min-height: 34px;
    }
}

/* Адаптивність для мобільних */
@media (max-width: 767.98px) {
    .plugins-list-section {
        margin-left: -1rem;
        margin-right: -1rem;
        padding: 0 1rem;
        width: calc(100% + 2rem);
        max-width: 100vw;
        overflow-x: hidden;
        box-sizing: border-box;
    }
    
    .plugins-list-section .card {
        background: transparent;
        border: none;
        box-shadow: none;
    }
    
    .plugins-list-section .card-body {
        padding: 0;
    }
    
    .plugins-list .row {
        margin-left: 0;
        margin-right: 0;
    }
    
    .plugins-list .row > [class*="col-"] {
        padding-left: 0;
        padding-right: 0;
    }
    
    .plugin-card {
        margin-bottom: 12px;
    }
    
    .plugin-actions {
        flex-direction: row;
        align-items: center;
        gap: 8px;
        justify-content: flex-end;
    }
    
    .plugin-actions .btn {
        width: auto;
        padding: 8px 12px;
        min-width: 36px;
        justify-content: center;
    }
    
    /* Приховуємо текст на мобільних, залишаємо тільки іконки */
    .plugin-actions .btn .btn-text {
        display: none;
    }
    
    .plugin-actions .btn .btn-icon {
        margin: 0;
    }
}

/* На планшетах та середніх екранах приховуємо текст, залишаємо тільки іконки */
@media (min-width: 768px) and (max-width: 1399.98px) {
    .plugin-actions .btn .btn-text {
        display: none;
    }
    
    .plugin-actions .btn .btn-icon {
        margin: 0;
    }
}

/* На великих ПК показуємо іконки та текст */
@media (min-width: 1400px) {
    .plugin-actions .btn .btn-text {
        display: inline;
    }
    
    .plugin-actions .btn .btn-icon {
        margin-right: 0;
    }
}

/* Оптимізація для середніх екранів (3 колонки) */
@media (min-width: 992px) and (max-width: 1199.98px) {
    .plugins-list .row {
        margin-left: -12px;
        margin-right: -12px;
    }
    
    .plugins-list .row > [class*="col-"] {
        padding-left: 12px;
        padding-right: 12px;
    }
}

/* Оптимізація для великих екранів (4 колонки) */
@media (min-width: 1200px) {
    .plugins-list .row {
        margin-left: -12px;
        margin-right: -12px;
    }
    
    .plugins-list .row > [class*="col-"] {
        padding-left: 12px;
        padding-right: 12px;
    }
    
}

/* Оптимізація для планшетів (2 колонки) - покриває 576px-991px */
@media (min-width: 576px) and (max-width: 991.98px) {
    .plugins-list .row {
        margin-left: -10px;
        margin-right: -10px;
    }
    
    .plugins-list .row > [class*="col-"] {
        padding-left: 10px;
        padding-right: 10px;
    }
}
</style>

<!-- Модальне вікно завантаження плагіна через ModalHandler -->
<?php if (!empty($uploadModalHtml)): ?>
    <?= $uploadModalHtml ?>
<?php endif; ?>
