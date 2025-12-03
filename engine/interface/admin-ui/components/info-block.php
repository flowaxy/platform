<?php
/**
 * Компонент інформаційного блоку
 *
 * Універсальний компонент для відображення інформаційних блоків у плагінах та системних сторінках.
 *
 * @param string $title Заголовок блоку
 * @param string $titleIcon Іконка для заголовка (наприклад: 'info-circle' або повний клас 'fas fa-info-circle')
 * @param array $sections Масив секцій, кожна секція містить:
 *   - 'title' => 'Заголовок секції'
 *   - 'icon' => 'назва іконки Font Awesome (без fas fa-)'
 *   - 'iconColor' => 'primary|info|success|warning|danger (за замовчуванням: primary)'
 *   - 'items' => ['Текст пункту 1', 'Текст пункту 2', ...]
 *   - 'checkIcon' => true/false (показувати іконку галочки, за замовчуванням: true)
 * @param array $classes Додаткові CSS класи для контейнера
 *
 * Приклад використання:
 * ```php
 * $componentsPath = __DIR__ . '/../../engine/interface/admin-ui/components/';
 * $title = 'Про кеш системи';
 * $sections = [
 *     [
 *         'title' => 'Що таке кеш:',
 *         'icon' => 'question-circle',
 *         'iconColor' => 'primary',
 *         'items' => [
 *             'Зберігає результати обчислень та запитів до БД',
 *             'Прискорює роботу системи',
 *             'Автоматично оновлюється при зміні даних'
 *         ]
 *     ],
 *     [
 *         'title' => 'Коли очищати:',
 *         'icon' => 'clock',
 *         'iconColor' => 'info',
 *         'items' => [
 *             'Після оновлення системи',
 *             'При проблемах з відображенням'
 *         ]
 *     ]
 * ];
 * include $componentsPath . 'info-block.php';
 * ```
 */
if (! isset($title)) {
    $title = 'Інформація';
}
if (! isset($titleIcon)) {
    $titleIcon = '';
}
if (! isset($sections) || ! is_array($sections)) {
    $sections = [];
}
if (! isset($classes)) {
    $classes = [];
}

$containerClasses = ['info-block'];
if (! empty($classes)) {
    $containerClasses = array_merge($containerClasses, $classes);
}
$containerClass = implode(' ', array_map('htmlspecialchars', $containerClasses));

// Колір іконок за замовчуванням
$defaultIconColors = [
    'primary' => '#0073aa',
    'info' => '#00a0d2',
    'success' => '#46b450',
    'warning' => '#ffb900',
    'danger' => '#dc3232'
];
?>
<div class="<?= $containerClass ?>">
    <div class="card border-0">
        <?php if (! empty($title)): ?>
        <div class="card-header border-bottom">
            <h5 class="mb-0">
                <?php if (! empty($titleIcon)): ?>
                    <?php
                    // Якщо іконка не містить 'fa-', додаємо стандартний префікс
                    if (strpos($titleIcon, 'fa-') === false && strpos($titleIcon, ' ') === false) {
                        $titleIconClass = 'fas fa-' . htmlspecialchars($titleIcon);
                    } else {
                        $titleIconClass = htmlspecialchars($titleIcon);
                    }
                    ?>
                    <i class="<?= $titleIconClass ?> me-2"></i>
                <?php endif; ?>
                <?= htmlspecialchars($title) ?>
            </h5>
        </div>
        <?php endif; ?>
        
        <div class="card-body">
            <?php if (! empty($sections)): ?>
                <div class="row g-3">
                    <?php foreach ($sections as $section): ?>
                        <?php
                        $sectionTitle = $section['title'] ?? '';
                        $sectionIcon = $section['icon'] ?? '';
                        $iconColor = $section['iconColor'] ?? 'primary';
                        $items = $section['items'] ?? [];
                        $checkIcon = isset($section['checkIcon']) ? (bool)$section['checkIcon'] : true;
                        
                        // Колір іконки
                        $iconColorValue = $defaultIconColors[$iconColor] ?? $defaultIconColors['primary'];
                        ?>
                        <div class="col-12 col-md-6">
                            <?php if (! empty($sectionTitle)): ?>
                            <h6 class="info-block-section-title mb-2" style="color: <?= $iconColorValue ?>;">
                                <?php if (! empty($sectionIcon)): ?>
                                    <i class="fas fa-<?= htmlspecialchars($sectionIcon) ?> me-2"></i>
                                <?php endif; ?>
                                <?= htmlspecialchars($sectionTitle) ?>
                            </h6>
                            <?php endif; ?>
                            
                            <?php if (! empty($items)): ?>
                            <ul class="info-block-list">
                                <?php foreach ($items as $index => $item): ?>
                                    <li class="info-block-item">
                                        <?php if ($checkIcon): ?>
                                            <i class="fas fa-check text-success me-2"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($item) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.info-block .card {
    border: 1px solid #f0f0f1;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.03);
}

.info-block .card-header {
    background: #f6f7f7;
    border-bottom: 1px solid #e0e0e0;
    padding: 0.875rem 1.25rem;
}

.info-block .card-header h5 {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #23282d;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 0;
    display: flex;
    align-items: center;
}

.info-block .card-header h5 i {
    font-size: 0.875rem;
    color: #646970;
}

.info-block .card-body {
    padding: 1.25rem 1.25rem;
}

.info-block-section-title {
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.4;
    margin-bottom: 0.75rem !important;
    display: flex;
    align-items: center;
}

.info-block-section-title i {
    font-size: 0.9375rem;
}

.info-block-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-block-item {
    font-size: 0.8125rem;
    line-height: 1.6;
    color: #646970;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: flex-start;
}

.info-block-item:last-child {
    margin-bottom: 0;
}

.info-block-item i.fa-check {
    font-size: 0.75rem;
    margin-top: 0.25rem;
    flex-shrink: 0;
}

/* Адаптивність */
@media (max-width: 767.98px) {
    .info-block .card-body {
        padding: 1rem;
    }
    
    .info-block-section-title {
        font-size: 0.8125rem;
        margin-bottom: 0.625rem !important;
    }
    
    .info-block-item {
        font-size: 0.75rem;
        margin-bottom: 0.375rem;
    }
}
</style>

