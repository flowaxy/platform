<?php
/**
 * Компонент таблиці даних з адаптивністю
 *
 * Універсальний компонент для відображення таблиць даних у плагінах та системних сторінках.
 * Підтримує десктопну таблицю та мобільні картки.
 *
 * @param array $headers Масив заголовків колонок, кожен елемент містить:
 *   - 'text' => 'Текст заголовка'
 *   - 'icon' => 'назва іконки Font Awesome (необов'язково)'
 *   - 'class' => 'CSS класи (необов'язково)'
 *   - 'width' => 'ширина колонки (необов'язково)'
 * @param array $rows Масив рядків даних, кожен рядок - масив комірок
 *   Кожна комірка може бути:
 *   - Рядок: просто текст
 *   - Масив: ['content' => '...', 'icon' => '...', 'class' => '...', 'type' => 'key|size|date|text|html']
 * @param array $mobileConfig Конфігурація для мобільних карток:
 *   - 'keyColumn' => індекс колонки з ключем (для мобільних карток)
 *   - 'showColumns' => масив індексів колонок для відображення на мобільних
 *   - 'deleteButton' => налаштування кнопки видалення ['modal' => '...', 'dataAttribute' => '...']
 * @param string $emptyMessage Повідомлення, коли немає даних
 * @param string $emptyIcon Іконка для порожнього стану
 * @param array $classes Додаткові CSS класи
 *
 * Приклад використання:
 * ```php
 * $componentsPath = __DIR__ . '/../../engine/interface/admin-ui/components/';
 * $headers = [
 *     ['text' => 'Ключ', 'icon' => 'key'],
 *     ['text' => 'Розмір'],
 *     ['text' => 'Оновлено'],
 *     ['text' => 'Дії', 'class' => 'text-end']
 * ];
 * $rows = [
 *     [
 *         ['content' => 'key1', 'type' => 'key', 'icon' => 'key'],
 *         ['content' => 1024, 'type' => 'size'],
 *         ['content' => '2025-11-30 12:00:00', 'type' => 'date'],
 *         ['content' => '<button>...</button>', 'type' => 'html', 'class' => 'text-end']
 *     ]
 * ];
 * $mobileConfig = [
 *     'keyColumn' => 0,
 *     'deleteButton' => ['modal' => 'deleteModal', 'dataAttribute' => 'data-item-key']
 * ];
 * include $componentsPath . 'data-table.php';
 * ```
 */
if (! isset($headers)) {
    $headers = [];
}
if (! isset($rows)) {
    $rows = [];
}
if (! isset($mobileConfig)) {
    $mobileConfig = [];
}
if (! isset($emptyMessage)) {
    $emptyMessage = 'Немає даних для відображення';
}
if (! isset($emptyIcon)) {
    $emptyIcon = 'database';
}
if (! isset($classes)) {
    $classes = [];
}

$keyColumn = $mobileConfig['keyColumn'] ?? 0;
$showColumns = $mobileConfig['showColumns'] ?? null; // null = всі окрім останньої (дії)
$deleteButton = $mobileConfig['deleteButton'] ?? null;

$containerClasses = ['data-table'];
if (! empty($classes)) {
    $containerClasses = array_merge($containerClasses, $classes);
}
$containerClass = implode(' ', array_map('htmlspecialchars', $containerClasses));

// Функція для форматування розміру
if (!function_exists('formatFileSizeDataTable')) {
    function formatFileSizeDataTable($bytes) {
        if ($bytes < 1024) {
            return number_format($bytes, 0, ',', ' ') . ' B';
        }
        $sizeKB = $bytes / 1024;
        $sizeMB = $sizeKB / 1024;
        if ($sizeMB >= 1) {
            return number_format($sizeMB, 2, ',', ' ') . ' MB';
        }
        return number_format($sizeKB, 2, ',', ' ') . ' KB';
    }
}

// Функція для обробки комірки
if (!function_exists('renderCellDataTable')) {
    function renderCellDataTable($cell, $isHeader = false) {
    if (is_array($cell)) {
        $content = $cell['content'] ?? '';
        $class = $cell['class'] ?? '';
        $icon = $cell['icon'] ?? '';
        $type = $cell['type'] ?? 'text';
        
        // Обробка різних типів даних
        if ($type === 'size' && is_numeric($content)) {
            $content = '<span class="fw-medium">' . formatFileSizeDataTable($content) . '</span>';
        } elseif ($type === 'key' && !empty($content)) {
            $iconHtml = $icon ? '<i class="fas fa-' . htmlspecialchars($icon) . ' text-muted me-2" style="font-size: 0.875rem;"></i>' : '';
            $content = '<div class="d-flex align-items-center gap-2">' . 
                       $iconHtml . 
                       '<code class="data-table-key">' . htmlspecialchars($content) . '</code>' . 
                       '</div>';
        } elseif ($type === 'date' && !empty($content)) {
            $content = '<span class="text-muted data-table-mobile-date" style="font-size: 0.8125rem;">' . htmlspecialchars($content) . '</span>';
        } elseif ($type === 'html') {
            // HTML залишаємо як є
        } else {
            $content = htmlspecialchars($content);
        }
        
        if (!empty($icon) && $type !== 'key') {
            $content = '<i class="fas fa-' . htmlspecialchars($icon) . ' me-2"></i>' . $content;
        }
        
        return ['content' => $content, 'class' => $class];
    } else {
        return ['content' => htmlspecialchars($cell), 'class' => ''];
    }
}
}

// Використання функцій
function renderCell($cell, $isHeader = false) {
    return renderCellDataTable($cell, $isHeader);
}
?>
<div class="<?= $containerClass ?>">
    <?php if (empty($rows)): ?>
        <?php
        // Порожній стан
        $icon = $emptyIcon;
        $title = $emptyMessage;
        include __DIR__ . '/empty-state.php';
        ?>
    <?php else: ?>
        <!-- Десктопна таблиця -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover mb-0">
                <?php if (! empty($headers)): ?>
                <thead>
                    <tr>
                        <?php foreach ($headers as $index => $header): ?>
                            <?php
                            $headerData = is_array($header) ? $header : ['text' => $header];
                            $headerText = $headerData['text'] ?? '';
                            $headerIcon = $headerData['icon'] ?? '';
                            $headerClass = $headerData['class'] ?? '';
                            $headerWidth = isset($headerData['width']) ? ' style="width: ' . htmlspecialchars($headerData['width']) . ';"' : '';
                            $sortable = $headerData['sortable'] ?? false;
                            $sortKey = $headerData['sortKey'] ?? '';
                            $headerId = 'header-' . $index;
                            
                            $sortableClass = $sortable ? ' sortable' : '';
                            $sortableAttrs = $sortable && $sortKey ? ' data-sort-key="' . htmlspecialchars($sortKey) . '" data-column-index="' . $index . '" role="button" tabindex="0"' : '';
                            ?>
                            <th class="fw-semibold<?= $headerClass ? ' ' . htmlspecialchars($headerClass) : '' ?><?= $sortableClass ?><?= $sortableClass ? ' user-select-none' : '' ?>"<?= $headerWidth ?><?= $sortableAttrs ?> id="<?= $headerId ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (! empty($headerIcon)): ?>
                                        <i class="fas fa-<?= htmlspecialchars($headerIcon) ?>"></i>
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($headerText) ?></span>
                                    <?php if ($sortable): ?>
                                        <i class="fas fa-sort sort-icon text-muted ms-auto" style="font-size: 0.75rem; opacity: 0.5;"></i>
                                    <?php endif; ?>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <?php endif; ?>
                
                <tbody>
                    <?php foreach ($rows as $rowIndex => $row): ?>
                        <?php
                        // Отримуємо значення для сортування з комірок рядка
                        $rowKey = '';
                        $rowSource = '';
                        $rowStatus = '';
                        $rowSize = 0;
                        $rowModified = '';
                        
                        if (isset($row[0]) && is_array($row[0])) {
                            $rowKey = $row[0]['sort-value'] ?? $row[0]['content'] ?? '';
                        }
                        if (isset($row[1]) && is_array($row[1])) {
                            $rowSource = $row[1]['sort-value'] ?? strip_tags($row[1]['content'] ?? '');
                        }
                        if (isset($row[2]) && is_array($row[2])) {
                            $rowStatus = $row[2]['sort-value'] ?? strip_tags($row[2]['content'] ?? '');
                        }
                        if (isset($row[3]) && is_array($row[3])) {
                            $sortValue = $row[3]['sort-value'] ?? $row[3]['content'] ?? '';
                            $rowSize = is_numeric($sortValue) ? (int)$sortValue : 0;
                        }
                        if (isset($row[4]) && is_array($row[4])) {
                            $rowModified = $row[4]['sort-value'] ?? $row[4]['content'] ?? '';
                        }
                        ?>
                        <tr data-sort-key="<?= htmlspecialchars($rowKey) ?>"
                            data-sort-source="<?= htmlspecialchars($rowSource) ?>"
                            data-sort-status="<?= htmlspecialchars($rowStatus) ?>"
                            data-sort-size="<?= $rowSize ?>"
                            data-sort-modified="<?= htmlspecialchars($rowModified) ?>">
                            <?php foreach ($row as $index => $cell): ?>
                                <?php
                                $cellData = renderCell($cell);
                                ?>
                                <td<?= $cellData['class'] ? ' class="' . htmlspecialchars($cellData['class']) . '"' : '' ?>>
                                    <?= $cellData['content'] ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Мобільні картки -->
        <div class="d-md-none">
            <?php foreach ($rows as $rowIndex => $row): ?>
                <?php
                // Визначаємо, які колонки показувати на мобільних
                $visibleColumns = $showColumns;
                if ($visibleColumns === null) {
                    // За замовчуванням показуємо всі окрім останньої (дії)
                    $visibleColumns = range(0, count($row) - 2);
                }
                
                // Фільтруємо видимі колонки, виключаючи ключ
                $filteredColumns = array_filter($visibleColumns, function($colIndex) use ($keyColumn) {
                    return $colIndex !== $keyColumn;
                });
                $filteredColumns = array_values($filteredColumns); // Переіндексуємо
                $lastColumnIndex = end($filteredColumns); // Остання колонка
                
                // Ключ для картки
                $keyCell = isset($row[$keyColumn]) ? renderCell($row[$keyColumn]) : ['content' => '', 'class' => ''];
                $keyContent = $keyCell['content'];
                // Витягуємо чистий ключ для data-атрибутів
                if (is_array($row[$keyColumn])) {
                    $keyValue = $row[$keyColumn]['content'] ?? '';
                } else {
                    $keyValue = $row[$keyColumn];
                }
                // Очищаємо від HTML тегів
                $keyValue = htmlspecialchars(strip_tags($keyValue));
                ?>
                <div class="data-table-mobile-card">
                    <div class="data-table-mobile-card-header">
                        <div class="data-table-mobile-header-content">
                            <div class="data-table-mobile-card-key">
                                <?= $keyContent ?>
                            </div>
                            <?php if (isset($row[count($row) - 1])): ?>
                                <?php
                                $actionsCell = renderCell($row[count($row) - 1]);
                                // Перевіряємо чи є кастомні дії
                                $customActions = isset($mobileConfig['customActions']) && $mobileConfig['customActions'];
                                
                                if ($customActions) {
                                    // Використовуємо HTML з останньої колонки (містить обидві кнопки)
                                    echo '<div class="data-table-mobile-actions d-flex gap-1">' . $actionsCell['content'] . '</div>';
                                } elseif ($deleteButton) {
                                    // Створюємо кнопку видалення
                                    $deleteModal = $deleteButton['modal'] ?? '';
                                    $deleteDataAttr = $deleteButton['dataAttribute'] ?? 'data-item-id';
                                    ?>
                                    <button type="button" class="data-table-mobile-delete-btn"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#<?= htmlspecialchars($deleteModal) ?>"
                                            <?= $deleteDataAttr ?>="<?= htmlspecialchars($keyValue) ?>"
                                            title="Видалити">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php } ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="data-table-mobile-card-body">
                        <div class="data-table-mobile-card-info">
                            <?php 
                            foreach ($visibleColumns as $colIndex): 
                                // Пропускаємо колонку з ключем, вона вже показана в заголовку
                                if ($colIndex === $keyColumn) continue;
                                
                                if (!isset($row[$colIndex]) || !isset($headers[$colIndex])) continue;
                                $header = is_array($headers[$colIndex]) ? $headers[$colIndex] : ['text' => $headers[$colIndex]];
                                $headerText = $header['text'] ?? '';
                                $cell = renderCell($row[$colIndex]);
                                
                                // Визначаємо, чи це остання колонка
                                $isLastItem = ($colIndex === $lastColumnIndex);
                            ?>
                                <div class="data-table-mobile-info-item<?= $isLastItem ? ' data-table-mobile-info-item-full' : '' ?>">
                                    <small class="data-table-mobile-label"><?= htmlspecialchars($headerText) ?></small>
                                    <div class="data-table-mobile-value"><?= $cell['content'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* ===== Десктопна таблиця ===== */
.data-table .table {
    margin: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.data-table .table thead th {
    background: #f6f7f7;
    border-bottom: 1px solid #e0e0e0;
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    color: #646970;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table .table tbody td {
    padding: 0.5rem 0.75rem;
    font-size: 0.8125rem;
    border-bottom: 1px solid #f0f0f1;
    vertical-align: middle;
}

.data-table-key {
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 0.75rem;
    background: #f6f7f7;
    padding: 0.25rem 0.5rem;
    border-radius: 0;
    border: 1px solid #e0e0e0;
    color: #23282d;
    word-break: break-all;
}

/* ===== Мобільні картки ===== */
.data-table .d-md-none {
    max-width: 100%;
    overflow-x: hidden;
    padding: 0 0.5rem;
    width: 100%;
    box-sizing: border-box;
}

.data-table-mobile-card {
    background: #ffffff;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    margin-bottom: 1rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.data-table-mobile-card:hover {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08), 0 2px 4px rgba(0, 0, 0, 0.06);
    transform: translateY(-1px);
}

.data-table-mobile-card:last-child {
    margin-bottom: 0;
}

.data-table-mobile-card-header {
    padding: 1rem;
    border-bottom: 1px solid #f0f0f1;
    background: linear-gradient(to bottom, #fafafa 0%, #f6f7f7 100%);
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
    box-sizing: border-box;
}

.data-table-mobile-header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.data-table-mobile-card-key {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    overflow: hidden;
}

.data-table-mobile-card-key .d-flex {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    align-items: center;
    gap: 0.5rem;
}

.data-table-mobile-card-key .fas,
.data-table-mobile-card-key .fa {
    flex-shrink: 0;
    font-size: 0.875rem !important;
}

.data-table-mobile-card-key code,
.data-table-mobile-card-key .data-table-key {
    font-size: 0.75rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    background: #ffffff;
    padding: 0.375rem 0.625rem;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    font-weight: 500;
    color: #23282d;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    flex: 1;
    min-width: 0;
    display: inline-block;
}

.data-table-mobile-actions {
    display: flex;
    gap: 0.375rem;
    align-items: center;
    flex-shrink: 0;
}

.data-table-mobile-actions .btn {
    min-width: 28px;
    height: 28px;
    padding: 0;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.data-table-mobile-actions .btn-info {
    background: #0073aa;
    border-color: #0073aa;
    color: #ffffff;
}

.data-table-mobile-actions .btn-info:hover {
    background: #005a87;
    border-color: #005a87;
    transform: scale(1.05);
}

.data-table-mobile-actions .btn-danger {
    background: #dc3232;
    border-color: #dc3232;
    color: #ffffff;
}

.data-table-mobile-actions .btn-danger:hover {
    background: #b32d2e;
    border-color: #b32d2e;
    transform: scale(1.05);
}

.data-table-mobile-delete-btn {
    background: transparent;
    border: none;
    color: #dc3232;
    font-size: 0.75rem;
    padding: 0;
    cursor: pointer;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.data-table-mobile-delete-btn:hover {
    color: #b32d2e;
    background: rgba(220, 50, 50, 0.1);
    transform: scale(1.05);
}

.data-table-mobile-card-body {
    padding: 1rem;
    background: #ffffff;
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
    box-sizing: border-box;
}

.data-table-mobile-card-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem 0.875rem;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.data-table-mobile-info-item {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    padding: 0.625rem;
    background: #fafafa;
    border-radius: 6px;
    border: 1px solid #f0f0f1;
    transition: background-color 0.2s ease, border-color 0.2s ease;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    overflow: hidden;
    box-sizing: border-box;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.data-table-mobile-info-item:hover {
    background: #f6f7f7;
    border-color: #e0e0e0;
}

/* Якщо тільки один елемент - на всю ширину */
.data-table-mobile-card-info:has(.data-table-mobile-info-item:only-child) {
    grid-template-columns: 1fr;
}

/* Останній елемент на всю ширину */
.data-table-mobile-info-item-full {
    grid-column: 1 / -1;
}

/* Стилі для клікабельних заголовків таблиці */
.data-table thead th.sortable {
    cursor: pointer;
    user-select: none;
    transition: background-color 0.2s ease, color 0.2s ease;
    position: relative;
}

.data-table thead th.sortable:hover {
    background-color: #f8f9fa;
    color: #0073aa;
}

.data-table thead th.sortable:active {
    background-color: #e9ecef;
}

.data-table thead th.sortable .sort-icon {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.data-table thead th.sortable:hover .sort-icon {
    opacity: 1;
}

.data-table thead th.sortable .sort-icon {
    margin-left: auto;
}

.data-table thead th.sortable.sort-asc .sort-icon::after,
.data-table thead th.sortable.sort-desc .sort-icon::after {
    display: inline-block;
    margin-left: 0.25rem;
}

.data-table thead th.sortable.sort-asc .sort-icon::after {
    content: "\f0de"; /* fa-sort-up */
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
}

.data-table thead th.sortable.sort-desc .sort-icon::after {
    content: "\f0dd"; /* fa-sort-down */
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
}

.data-table thead th.sortable.sort-asc .sort-icon::before,
.data-table thead th.sortable.sort-desc .sort-icon::before {
    content: none;
}

.data-table thead th.sortable.sort-asc .sort-icon,
.data-table thead th.sortable.sort-desc .sort-icon {
    opacity: 1;
    color: #0073aa;
}

.data-table thead th.sortable.sort-asc .sort-icon::before,
.data-table thead th.sortable.sort-desc .sort-icon::before {
    content: "";
    display: inline-block;
    width: 0;
    height: 0;
}

.data-table thead th.sortable.sort-asc .sort-icon {
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
}

.data-table thead th.sortable.sort-asc .sort-icon::after {
    content: "\f0de"; /* fa-sort-up */
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
}

.data-table thead th.sortable.sort-desc .sort-icon::after {
    content: "\f0dd"; /* fa-sort-down */
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
}

.data-table thead th.sortable.sort-asc .sort-icon,
.data-table thead th.sortable.sort-desc .sort-icon {
    opacity: 1;
}

.data-table thead th.sortable.sort-asc .sort-icon::after,
.data-table thead th.sortable.sort-desc .sort-icon::after {
    display: inline-block;
    margin-left: 0.25rem;
}

.data-table-mobile-label {
    font-size: 0.6875rem;
    color: #646970;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.125rem;
    max-width: 100%;
    overflow: hidden;
    word-break: break-word;
    overflow-wrap: break-word;
}

.data-table-mobile-value {
    font-size: 0.875rem;
    color: #23282d;
    line-height: 1.5;
    font-weight: 400;
    word-break: break-word;
    overflow-wrap: break-word;
    word-wrap: break-word;
    max-width: 100%;
    overflow: hidden;
    min-width: 0;
}

.data-table-mobile-value code {
    font-size: 0.8125rem;
    background: #ffffff;
    padding: 0.125rem 0.375rem;
    border-radius: 3px;
    border: 1px solid #e0e0e0;
}

.data-table-mobile-value .text-muted {
    font-size: 0.875rem !important;
    color: #646970;
    line-height: 1.5;
}

.data-table-mobile-value .fw-medium {
    font-weight: 500;
    color: #23282d;
}

.data-table-mobile-date {
    font-size: 0.875rem !important;
    color: #646970;
    line-height: 1.5;
}

/* Покращення для badge та інших елементів в мобільних картках */
.data-table-mobile-value .badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.625rem;
    border-radius: 4px;
    font-weight: 500;
    line-height: 1.2;
}

.data-table-mobile-value .d-flex {
    gap: 0.5rem;
}

/* Покращення для іконок в мобільних картках */
.data-table-mobile-value .fas,
.data-table-mobile-value .fa {
    font-size: 0.875rem;
    flex-shrink: 0;
}

/* Обмеження для вкладених елементів */
.data-table-mobile-value .d-flex,
.data-table-mobile-value .badge,
.data-table-mobile-value span,
.data-table-mobile-value div {
    max-width: 100%;
    word-break: break-word;
    overflow-wrap: break-word;
}

.data-table-mobile-value .d-flex {
    min-width: 0;
    flex-wrap: wrap;
}

/* Адаптивність для дуже маленьких екранів */
@media (max-width: 360px) {
    .data-table-mobile-card-info {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .data-table-mobile-card-header {
        padding: 0.875rem;
    }
    
    .data-table-mobile-card-body {
        padding: 0.875rem;
    }
    
    .data-table-mobile-info-item {
        padding: 0.5rem;
    }
    
    .data-table-mobile-card-key code,
    .data-table-mobile-card-key .data-table-key {
        font-size: 0.6875rem;
    }
}
</style>

<script>
(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        const tables = document.querySelectorAll('.data-table');
        
        tables.forEach(function(table) {
            const sortableHeaders = table.querySelectorAll('thead th.sortable[data-sort-key]');
            const tbody = table.querySelector('tbody');
            let currentSort = {
                column: null,
                direction: null // 'asc' or 'desc'
            };
            
            sortableHeaders.forEach(function(header) {
                const sortKey = header.getAttribute('data-sort-key');
                
                // Обробка кліку
                function handleSort(e) {
                    e.preventDefault();
                    
                    const newDirection = currentSort.column === sortKey && currentSort.direction === 'asc' 
                        ? 'desc' 
                        : 'asc';
                    
                    // Оновлюємо стан сортування
                    currentSort.column = sortKey;
                    currentSort.direction = newDirection;
                    
                    // Видаляємо класи сортування з усіх заголовків
                    sortableHeaders.forEach(function(h) {
                        h.classList.remove('sort-asc', 'sort-desc');
                    });
                    
                    // Додаємо клас до поточного заголовка
                    header.classList.add('sort-' + newDirection);
                    
                    // Сортуємо рядки
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    
                    rows.sort(function(a, b) {
                        let aValue, bValue;
                        
                        switch(sortKey) {
                            case 'key':
                                aValue = (a.getAttribute('data-sort-key') || '').toLowerCase();
                                bValue = (b.getAttribute('data-sort-key') || '').toLowerCase();
                                break;
                            case 'source':
                                aValue = (a.getAttribute('data-sort-source') || '').toLowerCase();
                                bValue = (b.getAttribute('data-sort-source') || '').toLowerCase();
                                break;
                            case 'status':
                                aValue = (a.getAttribute('data-sort-status') || '').toLowerCase();
                                bValue = (b.getAttribute('data-sort-status') || '').toLowerCase();
                                break;
                            case 'size':
                                aValue = parseInt(a.getAttribute('data-sort-size') || '0');
                                bValue = parseInt(b.getAttribute('data-sort-size') || '0');
                                break;
                            case 'modified':
                                aValue = a.getAttribute('data-sort-modified') || '';
                                bValue = b.getAttribute('data-sort-modified') || '';
                                break;
                            default:
                                return 0;
                        }
                        
                        let comparison = 0;
                        if (typeof aValue === 'string' && typeof bValue === 'string') {
                            // Для рядків - лексикографічне порівняння
                            if (aValue < bValue) {
                                comparison = -1;
                            } else if (aValue > bValue) {
                                comparison = 1;
                            }
                        } else {
                            // Для чисел
                            comparison = aValue - bValue;
                        }
                        
                        return newDirection === 'asc' ? comparison : -comparison;
                    });
                    
                    // Оновлюємо DOM
                    rows.forEach(function(row) {
                        tbody.appendChild(row);
                    });
                }
                
                header.addEventListener('click', handleSort);
                
                // Підтримка клавіатури
                header.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        handleSort(e);
                    }
                });
            });
        });
    });
})();
</script>

