<?php
/**
 * Компонент модального вікна
 *
 * @param string $id ID модального вікна (обов'язково)
 * @param string $title Заголовок модального вікна
 * @param string $content Вміст модального вікна (HTML)
 * @param string $footer Футер модального вікна (HTML кнопок)
 * @param string $size Розмір модального вікна (sm, lg, xl, або порожньо для звичайного)
 * @param bool $centered Вертикальне центрування
 */
if (! isset($id) || empty($id)) {
    return; // ID обов'язковий
}
if (! isset($title)) {
    $title = '';
}
if (! isset($content)) {
    $content = '';
}
if (! isset($footer)) {
    $footer = '';
}
if (! isset($size)) {
    $size = '';
}
if (! isset($centered)) {
    $centered = false;
}

$dialogClass = 'modal-dialog';
if (! empty($size)) {
    $dialogClass .= ' modal-' . $size;
}
if ($centered) {
    $dialogClass .= ' modal-dialog-centered';
}
?>
<div class="modal fade" id="<?= htmlspecialchars($id) ?>" tabindex="-1" aria-labelledby="<?= htmlspecialchars($id) ?>Label" aria-hidden="true">
    <div class="<?= $dialogClass ?>">
        <div class="modal-content">
            <?php if (! empty($title)): ?>
            <div class="modal-header">
                <h5 class="modal-title" id="<?= htmlspecialchars($id) ?>Label">
                    <?php if (isset($titleIcon) && !empty($titleIcon)): ?>
                        <?= $titleIcon ?>
                    <?php endif; ?>
                    <?= htmlspecialchars($title) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            <?php endif; ?>
            
            <?php if (! empty($content)): ?>
            <div class="modal-body">
                <?= $content ?>
            </div>
            <?php endif; ?>
            
            <?php if (! empty($footer)): ?>
            <div class="modal-footer">
                <?= $footer ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

