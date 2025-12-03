<?php
/**
 * Компонент пустого стану (empty state)
 *
 * Універсальний компонент для відображення порожніх станів у плагінах та системних сторінках.
 *
 * @param string $icon Іконка Font Awesome (без fas fa-, наприклад: 'database' або повний клас 'fas fa-database')
 * @param string $title Заголовок
 * @param string $message Повідомлення/опис
 * @param string $actions HTML кнопок/дій (необов'язково)
 * @param array $classes Додаткові CSS класи
 *
 * Приклад використання:
 * ```php
 * $componentsPath = __DIR__ . '/../../engine/interface/admin-ui/components/';
 * $icon = 'database';
 * $title = 'Кеш порожній';
 * $message = 'На даний момент у системі немає збережених елементів кешу.';
 * $actions = '<button class="btn btn-primary">Створити</button>';
 * include $componentsPath . 'empty-state.php';
 * ```
 */
if (! isset($icon)) {
    $icon = 'folder-open';
}
if (! isset($title)) {
    $title = 'Немає елементів';
}
if (! isset($message)) {
    $message = '';
}
if (! isset($actions)) {
    $actions = '';
}
if (! isset($classes)) {
    $classes = [];
}

// Якщо іконка не містить 'fa-', додаємо стандартний префікс
if (strpos($icon, 'fa-') === false && strpos($icon, ' ') === false) {
    $iconClass = 'fas fa-' . htmlspecialchars($icon);
} else {
    // Якщо це повний клас іконки, використовуємо як є
    $iconClass = htmlspecialchars($icon);
}

$containerClasses = ['empty-state'];
if (! empty($classes)) {
    $containerClasses = array_merge($containerClasses, $classes);
}
$containerClass = implode(' ', array_map('htmlspecialchars', $containerClasses));
?>
<div class="<?= $containerClass ?>">
    <?php if (! empty($icon)): ?>
    <div class="empty-state-icon">
        <i class="<?= $iconClass ?>"></i>
    </div>
    <?php endif; ?>
    
    <?php if (! empty($title)): ?>
    <h4 class="empty-state-title"><?= htmlspecialchars($title) ?></h4>
    <?php endif; ?>
    
    <?php if (! empty($message)): ?>
    <p class="empty-state-message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    
    <?php if (! empty($actions)): ?>
    <div class="empty-state-actions">
        <?= $actions ?>
    </div>
    <?php endif; ?>
</div>

<style>
.empty-state {
    text-align: center;
    padding: 80px 30px;
    max-width: 600px;
    margin: 0 auto;
}

.empty-state-icon {
    width: auto;
    height: auto;
    margin: 0 auto 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-state-icon i {
    font-size: 5rem;
    color: #cbd5e0;
    opacity: 0.6;
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.empty-state:hover .empty-state-icon i {
    opacity: 0.8;
    transform: scale(1.05);
}

.empty-state-title {
    color: #23282d;
    font-weight: 600;
    margin-bottom: 12px;
    font-size: 1.5rem;
    letter-spacing: -0.01em;
    line-height: 1.3;
}

.empty-state-message {
    color: #646970;
    font-size: 1rem;
    line-height: 1.6;
    margin-bottom: 0;
    max-width: 480px;
    margin-left: auto;
    margin-right: auto;
}

.empty-state-actions {
    margin-top: 32px;
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* Адаптивність */
@media (max-width: 767.98px) {
    .empty-state {
        padding: 60px 20px;
    }
    
    .empty-state-icon i {
        font-size: 4rem;
    }
    
    .empty-state-title {
        font-size: 1.25rem;
    }
    
    .empty-state-message {
        font-size: 0.9375rem;
    }
}
</style>

