<?php
/**
 * Компонент спиннера загрузки
 *
 * @param string $size Размер (sm, md, lg)
 * @param string $variant Вариант (border, grow)
 * @param string $text Текст рядом со спиннером (необязательно)
 * @param array $classes Дополнительные CSS классы
 */
if (! isset($size)) {
    $size = 'sm';
}
if (! isset($variant)) {
    $variant = 'border';
}
if (! isset($text)) {
    $text = '';
}
if (! isset($classes)) {
    $classes = [];
}

$spinnerClass = 'spinner-' . $variant;
if ($size !== 'md') {
    $spinnerClass .= ' spinner-' . $variant . '-' . $size;
}

$containerClasses = [];
if (! empty($classes)) {
    $containerClasses = array_merge($containerClasses, $classes);
}
$containerClass = ! empty($containerClasses) ? ' ' . implode(' ', array_map('htmlspecialchars', $containerClasses)) : '';
?>
<span class="<?= $spinnerClass ?><?= $containerClass ?>" role="status" aria-hidden="true">
    <span class="visually-hidden">Завантаження...</span>
</span>
<?php if (! empty($text)): ?>
<span class="ms-2"><?= htmlspecialchars($text) ?></span>
<?php endif; ?>

