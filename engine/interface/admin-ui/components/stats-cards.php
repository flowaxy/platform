<?php
/**
 * Компонент статистичних карток
 *
 * Універсальний компонент для відображення статистичних карток у плагінах та системних сторінках.
 * Підтримує різну кількість карток з автоматичним розподілом по колонках.
 *
 * @param array $cards Масив карток, кожна картка містить:
 *   - 'title' => 'Заголовок картки'
 *   - 'value' => 'Значення (може бути HTML)'
 *   - 'icon' => 'назва іконки Font Awesome (без fas fa-)'
 *   - 'color' => 'primary|info|success|warning|danger (за замовчуванням: primary)'
 *   - 'valueClass' => 'h4|h5|h6 (розмір значення, за замовчуванням: h4)'
 * @param int $columnsPerRow Кількість колонок в рядку (за замовчуванням: автоматично на основі кількості карток)
 * @param array $classes Додаткові CSS класи для контейнера
 *
 * Приклад використання:
 * ```php
 * $componentsPath = __DIR__ . '/../../engine/interface/admin-ui/components/';
 * $cards = [
 *     [
 *         'title' => 'Всього файлів',
 *         'value' => '14',
 *         'icon' => 'file',
 *         'color' => 'primary'
 *     ],
 *     [
 *         'title' => 'Загальний розмір',
 *         'value' => '10,19 KB',
 *         'icon' => 'hdd',
 *         'color' => 'info'
 *     ],
 *     [
 *         'title' => 'Статус',
 *         'value' => '<span class="text-success">Активний</span>',
 *         'icon' => 'check-circle',
 *         'color' => 'success',
 *         'valueClass' => 'h5'
 *     ]
 * ];
 * include $componentsPath . 'stats-cards.php';
 * ```
 */
if (! isset($cards) || ! is_array($cards)) {
    $cards = [];
}
if (! isset($classes)) {
    $classes = [];
}

// Визначаємо кількість колонок та розподіл
$cardCount = count($cards);
$columnsPerRow = null;
$fixedWidth = false;

if (! isset($columnsPerRow) || empty($columnsPerRow)) {
    // Автоматичне визначення кількості колонок
    if ($cardCount <= 2) {
        // Для 1-2 карток: розподіл порівну
        $columnsPerRow = $cardCount;
        $fixedWidth = false;
    } elseif ($cardCount <= 5) {
        // Для 3-5 карток: розтягнути на всю ширину
        $columnsPerRow = $cardCount;
        $fixedWidth = true; // Фіксована ширина для рівномірного розподілу
    } else {
        // Для 6+ карток: 4 в рядку, решта переноситься
        $columnsPerRow = 4;
        $fixedWidth = true;
    }
}

// Розраховуємо розмір колонки
if ($fixedWidth) {
    // Використовуємо фіксовану ширину з flex для рівномірного розподілу
    $columnClass = 'col-12 col-md';
    $minWidth = $cardCount <= 5 ? (100 / $cardCount) : 25; // Для 5- карток - розподіл порівну, для 6+ - 25% (4 в рядку)
} else {
    // Для 1-2 карток використовуємо стандартну Bootstrap сітку
    $columnSize = 12 / $columnsPerRow;
    $columnClass = 'col-12 col-md-' . $columnSize;
    $minWidth = null;
}

// Колір лівої смуги за замовчуванням
$defaultColors = [
    'primary' => '#0073aa',
    'info' => '#00a0d2',
    'success' => '#46b450',
    'warning' => '#ffb900',
    'danger' => '#dc3232'
];

$containerClasses = ['stats-cards'];
if (! empty($classes)) {
    $containerClasses = array_merge($containerClasses, $classes);
}
$containerClass = implode(' ', array_map('htmlspecialchars', $containerClasses));

// Додаємо атрибути для CSS
$dataAttributes = '';
if ($fixedWidth && isset($minWidth)) {
    $dataAttributes = ' data-fixed-width="true" data-min-width="' . htmlspecialchars($minWidth) . '%"';
}
if ($cardCount > 5) {
    $dataAttributes .= ' data-cards-per-row="' . htmlspecialchars($columnsPerRow) . '"';
}
?>
<div class="<?= $containerClass ?>" data-cards-count="<?= $cardCount ?>"<?= $dataAttributes ?>>
    <div class="row g-3">
        <?php foreach ($cards as $card): ?>
            <?php
            $title = $card['title'] ?? '';
            $value = $card['value'] ?? '';
            $icon = $card['icon'] ?? '';
            $color = $card['color'] ?? 'primary';
            $valueClass = $card['valueClass'] ?? 'h4';
            
            // Колір лівої смуги
            $borderColor = $defaultColors[$color] ?? $defaultColors['primary'];
            $borderClass = 'border-left-' . $color;
            ?>
            <div class="<?= $columnClass ?>">
                <div class="card <?= $borderClass ?> h-100">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <?php if (! empty($title)): ?>
                                <div class="stats-card-title">
                                    <?= htmlspecialchars($title) ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($value) && $value !== '' && $value !== null): ?>
                                <div class="stats-card-value <?= htmlspecialchars($valueClass) ?> mb-0 font-weight-bold">
                                    <?= $value // Може містити HTML ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (! empty($icon)): ?>
                            <div class="col-auto">
                                <i class="fas fa-<?= htmlspecialchars($icon) ?> stats-card-icon"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.stats-cards .card {
    border: none;
    border-radius: 0;
    background: #ffffff;
    position: relative;
    box-sizing: border-box;
}

.stats-cards .card-body {
    padding: 1rem 1.25rem;
}

/* Ліва смуга для статистичних карток */
.stats-cards .card.border-left-primary::before,
.stats-cards .card.border-left-info::before,
.stats-cards .card.border-left-success::before,
.stats-cards .card.border-left-warning::before,
.stats-cards .card.border-left-danger::before {
    display: none !important;
}

.stats-cards .card.border-left-primary {
    border-left: 4px solid #0073aa !important;
    border-top: 1px solid #e0e0e0 !important;
    border-right: 1px solid #e0e0e0 !important;
    border-bottom: 1px solid #e0e0e0 !important;
}

.stats-cards .card.border-left-info {
    border-left: 4px solid #00a0d2 !important;
    border-top: 1px solid #e0e0e0 !important;
    border-right: 1px solid #e0e0e0 !important;
    border-bottom: 1px solid #e0e0e0 !important;
}

.stats-cards .card.border-left-success {
    border-left: 4px solid #46b450 !important;
    border-top: 1px solid #e0e0e0 !important;
    border-right: 1px solid #e0e0e0 !important;
    border-bottom: 1px solid #e0e0e0 !important;
}

.stats-cards .card.border-left-warning {
    border-left: 4px solid #ffb900 !important;
    border-top: 1px solid #e0e0e0 !important;
    border-right: 1px solid #e0e0e0 !important;
    border-bottom: 1px solid #e0e0e0 !important;
}

.stats-cards .card.border-left-danger {
    border-left: 4px solid #dc3232 !important;
    border-top: 1px solid #e0e0e0 !important;
    border-right: 1px solid #e0e0e0 !important;
    border-bottom: 1px solid #e0e0e0 !important;
}

/* Заголовок статистичної картки */
.stats-card-title {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
    display: block;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

.stats-cards .border-left-primary .stats-card-title { color: #0073aa; }
.stats-cards .border-left-info .stats-card-title { color: #00a0d2; }
.stats-cards .border-left-success .stats-card-title { color: #46b450; }
.stats-cards .border-left-warning .stats-card-title { color: #ffb900; }
.stats-cards .border-left-danger .stats-card-title { color: #dc3232; }

/* Значення в статистичній картці */
.stats-card-value {
    font-weight: 700;
    color: #646970;
    line-height: 1.2;
    margin: 0;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

.stats-card-value.h4 { font-size: 1.5rem; }
.stats-card-value.h5 { font-size: 1.25rem; }
.stats-card-value.h6 { font-size: 1rem; }

.stats-card-value .text-success { color: #46b450 !important; }
.stats-card-value .text-muted { color: #646970 !important; }

/* Іконки в статистичних картках */
.stats-card-icon {
    font-size: 2rem;
    opacity: 0.2;
}

.stats-cards .border-left-primary .stats-card-icon { color: #0073aa; }
.stats-cards .border-left-info .stats-card-icon { color: #00a0d2; }
.stats-cards .border-left-success .stats-card-icon { color: #46b450; }
.stats-cards .border-left-warning .stats-card-icon { color: #ffb900; }
.stats-cards .border-left-danger .stats-card-icon { color: #dc3232; }

/* Фіксована ширина для рівномірного розподілу */
.stats-cards[data-fixed-width="true"] .row > [class*="col-md"] {
    flex: 1 1 0%;
    min-width: 0;
    flex-basis: var(--card-min-width, auto);
}

/* Для більше 5 карток - 4 в рядку */
.stats-cards[data-cards-per-row] .row > [class*="col-md"] {
    flex: 0 0 25%;
    max-width: 25%;
}

/* Адаптивність для мобільних */
@media (max-width: 767.98px) {
    .stats-cards .row > [class*="col-md"] {
        flex: 0 0 100% !important;
        max-width: 100% !important;
    }
    
    .stats-cards .card-body {
        padding: 0.875rem 1rem;
    }
    
    .stats-card-title {
        font-size: 0.625rem;
    }
    
    .stats-card-value.h4 { font-size: 1.25rem; }
    .stats-card-value.h5 { font-size: 1.125rem; }
    .stats-card-value.h6 { font-size: 0.9375rem; }
    
    .stats-card-icon {
        font-size: 1.5rem;
    }
}

/* Для планшетів */
@media (min-width: 768px) and (max-width: 991.98px) {
    /* Для 1-2 карток - залишаємо як є */
    .stats-cards[data-cards-count="1"] .row > [class*="col-md-"],
    .stats-cards[data-cards-count="2"] .row > [class*="col-md-2"] {
        flex: 0 0 50%;
        max-width: 50%;
    }
    
    /* Для 3-5 карток - 2 в рядку на планшеті */
    .stats-cards[data-fixed-width="true"]:not([data-cards-per-row]) .row > [class*="col-md"] {
        flex: 0 0 50%;
        max-width: 50%;
    }
    
    /* Для 6+ карток - залишаємо 4 в рядку */
    .stats-cards[data-cards-per-row] .row > [class*="col-md"] {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

/* Для великих моніторів - запобігаємо розтягуванню */
@media (min-width: 1200px) {
    .stats-cards[data-fixed-width="true"] .row > [class*="col-md"] {
        max-width: 300px; /* Максимальна ширина картки */
    }
    
    /* Для 3-5 карток на великих екранах - дозволяємо розтягування */
    .stats-cards[data-fixed-width="true"]:not([data-cards-per-row]) .row > [class*="col-md"] {
        max-width: none;
    }
}
</style>

<?php
// Додаємо CSS змінні для фіксованої ширини
if ($fixedWidth && isset($minWidth)): ?>
<script>
(function() {
    const container = document.querySelector('.stats-cards[data-fixed-width="true"]:last-of-type');
    if (container) {
        const root = document.documentElement;
        root.style.setProperty('--card-min-width', '<?= $minWidth ?>%');
    }
})();
</script>
<?php endif; ?>

