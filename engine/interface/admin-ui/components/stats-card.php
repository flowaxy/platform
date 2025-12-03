<?php
/**
 * Компонент статистической карточки
 *
 * @param string $label Метка/заголовок
 * @param string|int $value Значение
 * @param string $icon Иконка Font Awesome
 * @param string $color Цвет (primary, success, warning, danger, info)
 * @param string $size Размер значения (sm, md, lg, xl)
 * @param array $classes Дополнительные CSS классы
 */
if (! isset($label)) {
    $label = '';
}
if (! isset($value)) {
    $value = '0';
}
if (! isset($icon)) {
    $icon = '';
}
if (! isset($color)) {
    $color = 'primary';
}
if (! isset($size)) {
    $size = 'md';
}
if (! isset($classes)) {
    $classes = [];
}

$containerClasses = ['text-center', 'p-2', 'bg-light', 'rounded'];
if (! empty($classes)) {
    $containerClasses = array_merge($containerClasses, $classes);
}
$containerClass = implode(' ', array_map('htmlspecialchars', $containerClasses));

$valueClasses = ['mb-0', 'text-' . htmlspecialchars($color)];
$valueSizeClasses = [
    'sm' => 'h6',
    'md' => 'h5',
    'lg' => 'h4',
    'xl' => 'h3',
];
$valueClasses[] = $valueSizeClasses[$size] ?? 'h5';
$valueClass = implode(' ', $valueClasses);
?>
<div class="<?= $containerClass ?>">
    <div class="fw-semibold text-muted small"><?= htmlspecialchars($label) ?></div>
    <div class="<?= $valueClass ?>">
        <?php if (! empty($icon)): ?>
        <i class="fas fa-<?= htmlspecialchars($icon) ?> me-1"></i>
        <?php endif; ?>
        <?= htmlspecialchars($value) ?>
    </div>
</div>

