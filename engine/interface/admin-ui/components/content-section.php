<?php
/**
 * Компонент секции контента (content-section)
 * Используется для обертки контента страниц с заголовком
 *
 * @param string $title Заголовок секции
 * @param string $icon Иконка Font Awesome для заголовка
 * @param string $content Содержимое секции (HTML)
 * @param string $header Дополнительный контент в заголовке (кнопки)
 * @param array $classes Дополнительные CSS классы
 */
if (! isset($title)) {
    $title = '';
}
if (! isset($icon)) {
    $icon = '';
}
if (! isset($content)) {
    $content = '';
}
if (! isset($header)) {
    $header = '';
}
if (! isset($classes)) {
    $classes = [];
}

$sectionClasses = ['content-section'];
if (! empty($classes)) {
    $sectionClasses = array_merge($sectionClasses, $classes);
}
$sectionClass = implode(' ', array_map('htmlspecialchars', $sectionClasses));
?>
<div class="<?= $sectionClass ?>">
    <?php if (! empty($title)): ?>
    <div class="content-section-header">
        <span>
            <?php if (! empty($icon)): ?>
            <i class="fas fa-<?= htmlspecialchars($icon) ?> me-2"></i>
            <?php endif; ?>
            <?= htmlspecialchars($title) ?>
        </span>
        <?php if (! empty($header)): ?>
        <div class="content-section-header-actions">
            <?= $header ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if (! empty($content)): ?>
    <div class="content-section-body">
        <?= $content ?>
    </div>
    <?php endif; ?>
</div>

