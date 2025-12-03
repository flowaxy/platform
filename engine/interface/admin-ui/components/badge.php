<?php
/**
 * Компонент бейджа
 *
 * @param string $text Текст бейджа
 * @param string $type Тип бейджа (primary, secondary, success, danger, warning, info, или кастомный: active, installed, available, inactive)
 * @param array $classes Дополнительные CSS классы
 * @param string $icon Иконка Font Awesome (необязательно)
 */
if (! isset($text)) {
    $text = '';
}
if (! isset($type)) {
    $type = 'secondary';
}
if (! isset($classes)) {
    $classes = [];
}
if (! isset($icon)) {
    $icon = '';
}

// Базовый класс
$badgeClass = 'badge';

// Кастомные типы
$customTypes = ['active', 'installed', 'available', 'inactive'];
if (in_array($type, $customTypes)) {
    $badgeClass .= ' badge-' . $type;
} else {
    $badgeClass .= ' bg-' . $type;
}

// Добавляем дополнительные классы
if (! empty($classes)) {
    $badgeClass .= ' ' . implode(' ', array_map('htmlspecialchars', $classes));
}
?>
<?php if (! empty($text)): ?>
<span class="<?= $badgeClass ?>">
    <?php if (! empty($icon)): ?>
    <i class="fas fa-<?= htmlspecialchars($icon) ?> me-1"></i>
    <?php endif; ?>
    <?= htmlspecialchars($text) ?>
</span>
<?php endif; ?>

