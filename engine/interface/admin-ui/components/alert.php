<?php
/**
 * Компонент уведомления/алерта
 *
 * @param string $message Сообщение
 * @param string $type Тип алерта (success, info, warning, danger)
 * @param bool $dismissible Можно ли закрыть алерт
 * @param string $icon Иконка Font Awesome (необязательно, по умолчанию берется из типа)
 * @param array $classes Дополнительные CSS классы
 */
if (! isset($message)) {
    $message = '';
}
if (! isset($type)) {
    $type = 'info';
}
if (! isset($dismissible)) {
    $dismissible = true;
}
if (! isset($icon)) {
    // Автоматически определяем иконку по типу
    $icons = [
        'success' => 'check-circle',
        'info' => 'info-circle',
        'warning' => 'exclamation-triangle',
        'danger' => 'times-circle',
    ];
    $icon = $icons[$type] ?? 'info-circle';
}
if (! isset($classes)) {
    $classes = [];
}

$alertClass = 'alert alert-' . htmlspecialchars($type);
if ($dismissible) {
    $alertClass .= ' alert-dismissible fade show';
}
if (! empty($classes)) {
    $alertClass .= ' ' . implode(' ', array_map('htmlspecialchars', $classes));
}
?>
<?php if (! empty($message)): ?>
<div class="<?= $alertClass ?>" role="alert">
    <?php if (! empty($icon)): ?>
    <i class="fas fa-<?= htmlspecialchars($icon) ?> me-2"></i>
    <?php endif; ?>
    <?= htmlspecialchars($message) ?>
    <?php if ($dismissible): ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрити"></button>
    <?php endif; ?>
</div>
<?php endif; ?>

