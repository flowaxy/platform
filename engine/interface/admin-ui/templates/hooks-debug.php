<?php
/**
 * Шаблон сторінки відлагодження хуків
 */
$rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 4);
$componentsPath = $rootDir . '/engine/interface/admin-ui/components/';

$allHooks = $all_hooks ?? [];
$performanceMetrics = $performance_metrics ?? [];
$slowestHooks = $slowest_hooks ?? [];
$mostCalledHooks = $most_called_hooks ?? [];
$performanceSummary = $performance_summary ?? [];
$hookStats = $hook_stats ?? [];
$actions = $actions ?? [];
$filters = $filters ?? [];

// Показуємо уведомлення
if (!empty($message)) {
    include __DIR__ . '/../components/alert.php';
    $type = $messageType ?? 'info';
    $dismissible = true;
}
?>

<div class="hooks-debug-container">
    <!-- Статистичні картки -->
    <div class="hooks-stats-grid">
        <div class="hooks-stat-card">
            <h3>Всього хуків</h3>
            <div class="stat-value"><?= count($allHooks) ?></div>
            <div class="stat-label">Зареєстровано в системі</div>
        </div>
        
        <div class="hooks-stat-card">
            <h3>Загальна кількість викликів</h3>
            <div class="stat-value"><?= number_format($performanceSummary['total_calls'] ?? 0, 0, ',', ' ') ?></div>
            <div class="stat-label">Всі виклики хуків</div>
        </div>
        
        <div class="hooks-stat-card">
            <h3>Загальний час виконання</h3>
            <div class="stat-value"><?= number_format($performanceSummary['total_time'] ?? 0, 4, ',', ' ') ?>s</div>
            <div class="stat-label">Секунд виконання</div>
        </div>
        
        <div class="hooks-stat-card">
            <h3>Середній час на виклик</h3>
            <div class="stat-value"><?= number_format($performanceSummary['average_time_per_call'] ?? 0, 4, ',', ' ') ?>s</div>
            <div class="stat-label">Мілісекунд на хук</div>
        </div>
    </div>

    <!-- Таби -->
    <div class="hooks-tabs">
        <button class="hooks-tab active" data-tab="all-hooks">Всі хуки</button>
        <button class="hooks-tab" data-tab="performance">Продуктивність</button>
        <button class="hooks-tab" data-tab="actions">Actions</button>
        <button class="hooks-tab" data-tab="filters">Filters</button>
    </div>

    <!-- Фільтри -->
    <div class="hooks-filter-bar">
        <input type="text" id="hook-search" placeholder="Пошук хука...">
        <select id="hook-type-filter">
            <option value="">Всі типи</option>
            <option value="action">Actions</option>
            <option value="filter">Filters</option>
        </select>
        <select id="hook-time-filter">
            <option value="">Всі</option>
            <option value="fast">Швидкі (&lt; 0.01s)</option>
            <option value="medium">Середні (0.01-0.1s)</option>
            <option value="slow">Повільні (&gt; 0.1s)</option>
        </select>
    </div>

    <!-- Контент: Всі хуки -->
    <div id="all-hooks" class="hooks-tab-content active">
        <table class="hooks-table">
            <thead>
                <tr>
                    <th data-sort="name">Назва хука</th>
                    <th data-sort="type">Тип</th>
                    <th data-sort="calls">Виклики</th>
                    <th data-sort="avg-time">Середній час</th>
                    <th data-sort="max-time">Максимальний час</th>
                    <th>Опис</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allHooks as $hookName => $metadata): ?>
                    <?php
                    $metrics = $performanceMetrics[$hookName] ?? null;
                    $type = $metadata['type']->value ?? 'action';
                    $typeClass = $type === 'action' ? 'hook-type-action' : 'hook-type-filter';
                    $avgTime = $metrics['avg_time'] ?? 0;
                    $timeClass = $avgTime < 0.01 ? 'time-fast' : ($avgTime < 0.1 ? 'time-medium' : 'time-slow');
                    ?>
                    <tr>
                        <td>
                            <span class="hook-name" data-name="<?= htmlspecialchars($hookName) ?>">
                                <?= htmlspecialchars($hookName) ?>
                            </span>
                        </td>
                        <td>
                            <span class="hook-type-badge <?= $typeClass ?>">
                                <?= htmlspecialchars($type) ?>
                            </span>
                        </td>
                        <td data-calls="<?= $metrics['calls'] ?? 0 ?>">
                            <?= number_format($metrics['calls'] ?? 0, 0, ',', ' ') ?>
                        </td>
                        <td data-avg-time="<?= $avgTime ?>">
                            <span class="time-badge <?= $timeClass ?>">
                                <?= number_format($avgTime, 4, ',', ' ') ?>s
                            </span>
                        </td>
                        <td data-max-time="<?= $metrics['max_time'] ?? 0 ?>">
                            <?= number_format($metrics['max_time'] ?? 0, 4, ',', ' ') ?>s
                        </td>
                        <td>
                            <?= htmlspecialchars($metadata['description'] ?? '') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Контент: Продуктивність -->
    <div id="performance" class="hooks-tab-content">
        <h3>Найповільніші хуки</h3>
        <table class="hooks-table">
            <thead>
                <tr>
                    <th>Назва хука</th>
                    <th>Виклики</th>
                    <th>Середній час</th>
                    <th>Максимальний час</th>
                    <th>Мінімальний час</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($slowestHooks as $hookName => $metrics): ?>
                    <tr>
                        <td><span class="hook-name"><?= htmlspecialchars($hookName) ?></span></td>
                        <td><?= number_format($metrics['calls'], 0, ',', ' ') ?></td>
                        <td><?= number_format($metrics['avg_time'], 4, ',', ' ') ?>s</td>
                        <td><?= number_format($metrics['max_time'], 4, ',', ' ') ?>s</td>
                        <td><?= number_format($metrics['min_time'], 4, ',', ' ') ?>s</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3 style="margin-top: 30px;">Найчастіше викликані хуки</h3>
        <table class="hooks-table">
            <thead>
                <tr>
                    <th>Назва хука</th>
                    <th>Виклики</th>
                    <th>Середній час</th>
                    <th>Загальний час</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mostCalledHooks as $hookName => $metrics): ?>
                    <tr>
                        <td><span class="hook-name"><?= htmlspecialchars($hookName) ?></span></td>
                        <td><?= number_format($metrics['calls'], 0, ',', ' ') ?></td>
                        <td><?= number_format($metrics['avg_time'], 4, ',', ' ') ?>s</td>
                        <td><?= number_format($metrics['total_time'], 4, ',', ' ') ?>s</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Контент: Actions -->
    <div id="actions" class="hooks-tab-content">
        <table class="hooks-table">
            <thead>
                <tr>
                    <th>Назва хука</th>
                    <th>Версія</th>
                    <th>Залежності</th>
                    <th>Опис</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($actions as $hookName => $metadata): ?>
                    <tr>
                        <td><span class="hook-name"><?= htmlspecialchars($hookName) ?></span></td>
                        <td><?= htmlspecialchars($metadata['version'] ?? '1.0.0') ?></td>
                        <td>
                            <?php if (!empty($metadata['dependencies'])): ?>
                                <?= implode(', ', array_map('htmlspecialchars', $metadata['dependencies'])) ?>
                            <?php else: ?>
                                <span class="text-muted">Немає</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($metadata['description'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Контент: Filters -->
    <div id="filters" class="hooks-tab-content">
        <table class="hooks-table">
            <thead>
                <tr>
                    <th>Назва хука</th>
                    <th>Версія</th>
                    <th>Залежності</th>
                    <th>Опис</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filters as $hookName => $metadata): ?>
                    <tr>
                        <td><span class="hook-name"><?= htmlspecialchars($hookName) ?></span></td>
                        <td><?= htmlspecialchars($metadata['version'] ?? '1.0.0') ?></td>
                        <td>
                            <?php if (!empty($metadata['dependencies'])): ?>
                                <?= implode(', ', array_map('htmlspecialchars', $metadata['dependencies'])) ?>
                            <?php else: ?>
                                <span class="text-muted">Немає</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($metadata['description'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

