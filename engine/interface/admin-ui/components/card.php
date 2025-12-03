<?php
/**
 * Компонент карточки
 *
 * @param string $title Заголовок карточки
 * @param string $content Содержимое карточки
 * @param string $header Дополнительный контент в заголовке (кнопки, иконки)
 * @param string $footer Контент в футере карточки
 * @param array $classes Дополнительные CSS классы
 */
if (! isset($title)) {
    $title = '';
}
if (! isset($content)) {
    $content = '';
}
if (! isset($header)) {
    $header = '';
}
if (! isset($footer)) {
    $footer = '';
}
if (! isset($classes)) {
    $classes = [];
}

$cardClasses = ['card'];
if (! empty($classes)) {
    $cardClasses = array_merge($cardClasses, $classes);
}
$cardClass = implode(' ', array_map('htmlspecialchars', $cardClasses));
?>
<div class="<?= $cardClass ?>">
    <?php if (! empty($title) || ! empty($header)): ?>
    <div class="card-header">
        <?php if (! empty($title)): ?>
            <h5 class="card-title mb-0"><?= htmlspecialchars($title) ?></h5>
        <?php endif; ?>
        <?php if (! empty($header)): ?>
            <div class="card-header-actions">
                <?= $header ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if (! empty($content)): ?>
    <div class="card-body">
        <?= $content ?>
    </div>
    <?php endif; ?>
    
    <?php if (! empty($footer)): ?>
    <div class="card-footer">
        <?= $footer ?>
    </div>
    <?php endif; ?>
</div>

