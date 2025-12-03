<?php
/**
 * Шаблон главной страницы админки
 */
?>

<!-- Уведомления -->
<?php
if (! empty($message)) {
    include __DIR__ . '/../components/alert.php';
    $type = $messageType ?? 'info';
    $dismissible = true;
}
?>

<!-- Виджеты dashboard (добавляются через хук dashboard_widgets) -->
<?php
$widgets = hook_apply('dashboard_widgets', []);
if (! empty($widgets) && is_array($widgets)) {
    // Сортируем виджеты по приоритету (order)
    usort($widgets, function ($a, $b) {
        $orderA = $a['order'] ?? 50;
        $orderB = $b['order'] ?? 50;

        return $orderA - $orderB;
    });

    // Группируем виджеты по колонкам
    $colClass = 'col-xl-4 col-md-6';
    if (count($widgets) === 1) {
        $colClass = 'col-12';
    } elseif (count($widgets) === 2) {
        $colClass = 'col-xl-6 col-md-6';
    } elseif (count($widgets) >= 3) {
        $colClass = 'col-xl-4 col-md-6';
    }
    ?>
    <div class="row mb-4">
        <?php foreach ($widgets as $widget): ?>
            <div class="<?= htmlspecialchars($colClass) ?> mb-3">
                <?php
                // Если виджет - HTML строка, выводим напрямую
                if (is_string($widget)) {
                    echo $widget;
                }
                // Если виджет - массив с данными, формируем карточку
                elseif (is_array($widget)) {
                    $type = $widget['type'] ?? 'default';
                    $color = $widget['color'] ?? 'primary';
                    $icon = $widget['icon'] ?? 'fa-info-circle';
                    $title = $widget['title'] ?? '';
                    $value = $widget['value'] ?? '';
                    $description = $widget['description'] ?? '';
                    $content = $widget['content'] ?? '';
                    $borderClass = 'border-left-' . $color;
                    ?>
                    <div class="card <?= htmlspecialchars($borderClass) ?> h-100 shadow-sm">
                        <div class="card-body">
                            <?php if (! empty($content)): ?>
                                <?= $content ?>
                            <?php else: ?>
                                <div class="row align-items-center">
                                    <div class="col">
                                        <?php if (! empty($title)): ?>
                                            <div class="text-xs font-weight-bold text-<?= htmlspecialchars($color) ?> text-uppercase mb-1">
                                                <?= htmlspecialchars($title) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (! empty($value)): ?>
                                            <div class="h4 mb-0 font-weight-bold text-gray-800"><?= htmlspecialchars($value) ?></div>
                                        <?php endif; ?>
                                        <?php if (! empty($description)): ?>
                                            <div class="text-xs text-muted"><?= htmlspecialchars($description) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (! empty($icon)): ?>
                                        <div class="col-auto">
                                            <i class="fas <?= htmlspecialchars($icon) ?> fa-2x text-<?= htmlspecialchars($color) ?> opacity-25"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
            ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
} else {
    // Пустое состояние, если нет виджетов
    ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-puzzle-piece fa-3x text-muted opacity-25 mb-3"></i>
            <h5 class="text-muted">Немає виджетів</h5>
            <p class="text-muted mb-0">Встановіть плагіни, щоб побачити виджети на dashboard</p>
        </div>
    </div>
    <?php
}
?>
