<?php
/**
 * Шаблон для страниц со списком элементов (plugins, themes, и т.д.)
 *
 * Переменные:
 * - $items: массив элементов для отображения
 * - $itemTemplate: путь к шаблону одного элемента (относительно templates/)
 * - $emptyMessage: сообщение, если список пуст
 * - $emptyIcon: иконка для пустого состояния
 * - $actions: дополнительные действия (кнопки) в заголовке списка
 */
if (! isset($items)) {
    $items = [];
}
if (! isset($emptyMessage)) {
    $emptyMessage = 'Немає елементів';
}
if (! isset($emptyIcon)) {
    $emptyIcon = 'fa-folder-open';
}
if (! isset($actions)) {
    $actions = '';
}
if (! isset($itemTemplate)) {
    $itemTemplate = 'list-item';
}
?>
<div class="list-page-container">
    <?php if (! empty($actions)): ?>
    <div class="list-page-actions mb-3">
        <?= $actions ?>
    </div>
    <?php endif; ?>
    
    <?php if (empty($items)): ?>
    <div class="list-empty-state">
        <div class="list-empty-icon">
            <i class="fas <?= htmlspecialchars($emptyIcon) ?>"></i>
        </div>
        <p class="list-empty-text"><?= htmlspecialchars($emptyMessage) ?></p>
    </div>
    <?php else: ?>
    <div class="list-items">
        <?php foreach ($items as $item): ?>
            <?php
            // Включаем шаблон элемента
            $itemTemplatePath = __DIR__ . '/' . $itemTemplate . '.php';
            if (file_exists($itemTemplatePath)) {
                // Извлекаем переменные из $item для использования в шаблоне
                extract(is_array($item) ? $item : ['item' => $item]);
                include $itemTemplatePath;
            } else {
                // Если шаблон не найден, выводим простой вывод
                echo '<div class="alert alert-warning">Template not found: ' . htmlspecialchars($itemTemplate) . '</div>';
            }
            ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

