<?php
/**
 * Компонент таблиці
 *
 * @param array $headers Заголовки таблиці (масив рядків або ['text' => '...', 'class' => '...'])
 * @param array $rows Рядки таблиці (масив масивів)
 * @param bool $striped Чергування рядків
 * @param bool $hover Ефект при наведенні
 * @param bool $bordered Рамки
 * @param array $classes Додаткові CSS класи
 */
if (! isset($headers)) {
    $headers = [];
}
if (! isset($rows)) {
    $rows = [];
}
if (! isset($striped)) {
    $striped = true;
}
if (! isset($hover)) {
    $hover = true;
}
if (! isset($bordered)) {
    $bordered = false;
}
if (! isset($classes)) {
    $classes = [];
}

$tableClasses = ['table'];
if ($striped) {
    $tableClasses[] = 'table-striped';
}
if ($hover) {
    $tableClasses[] = 'table-hover';
}
if ($bordered) {
    $tableClasses[] = 'table-bordered';
}
if (! empty($classes)) {
    $tableClasses = array_merge($tableClasses, $classes);
}
$tableClass = implode(' ', array_map('htmlspecialchars', $tableClasses));
?>
<table class="<?= $tableClass ?>">
    <?php if (! empty($headers)): ?>
    <thead>
        <tr>
            <?php foreach ($headers as $header): ?>
                <?php
                if (is_array($header)) {
                    $headerText = $header['text'] ?? '';
                    $headerClass = isset($header['class']) ? ' class="' . htmlspecialchars($header['class']) . '"' : '';
                } else {
                    $headerText = $header;
                    $headerClass = '';
                }
                ?>
                <th<?= $headerClass ?>><?= htmlspecialchars($headerText) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <?php endif; ?>
    
    <?php if (! empty($rows)): ?>
    <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <?php foreach ($row as $cell): ?>
                    <?php
                    if (is_array($cell)) {
                        $cellContent = $cell['content'] ?? '';
                        $cellClass = isset($cell['class']) ? ' class="' . htmlspecialchars($cell['class']) . '"' : '';
                    } else {
                        $cellContent = $cell;
                        $cellClass = '';
                    }
                    ?>
                    <td<?= $cellClass ?>><?= $cellContent ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <?php endif; ?>
</table>

