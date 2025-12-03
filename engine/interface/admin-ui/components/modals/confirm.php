<?php
/**
 * Компонент модального вікна підтвердження
 * 
 * @param array $config Конфігурація модального вікна
 *   - id: string - ID модального вікна
 *   - title: string - Заголовок
 *   - message: string - Повідомлення
 *   - icon: string - Тип іконки (warning, danger, info)
 *   - confirmText: string - Текст кнопки підтвердження
 *   - cancelText: string - Текст кнопки скасування
 *   - action: string - Дія при підтвердженні
 *   - method: string - HTTP метод (POST, DELETE)
 */

if (!isset($config) || !is_array($config)) {
    return;
}

$id = $config['id'] ?? 'confirmModal';
$title = $config['title'] ?? 'Підтвердження';
$message = $config['message'] ?? 'Ви впевнені?';
$icon = $config['icon'] ?? 'warning';
$confirmText = $config['confirmText'] ?? 'Підтвердити';
$cancelText = $config['cancelText'] ?? 'Скасувати';
$action = $config['action'] ?? '';
$method = $config['method'] ?? 'POST';

$iconClass = match($icon) {
    'danger' => 'fa-exclamation-circle',
    'info' => 'fa-info-circle',
    default => 'fa-exclamation-triangle'
};

$btnClass = match($icon) {
    'danger' => 'btn-danger',
    default => 'btn-primary'
};
?>

<div class="modal fade" id="<?= htmlspecialchars($id) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= htmlspecialchars($title) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            
            <div class="modal-body">
                <div class="confirm-content">
                    <div class="confirm-icon <?= htmlspecialchars($icon) ?>">
                        <i class="fas <?= $iconClass ?>"></i>
                    </div>
                    <div class="confirm-message"><?= $message ?></div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($cancelText) ?></button>
                <?php if ($action): ?>
                <form method="<?= htmlspecialchars($method) ?>" action="<?= htmlspecialchars($action) ?>" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?= SecurityHelper::csrfToken() ?>">
                    <button type="submit" class="btn <?= $btnClass ?>"><?= htmlspecialchars($confirmText) ?></button>
                </form>
                <?php else: ?>
                <button type="button" class="btn <?= $btnClass ?>" data-confirm="true"><?= htmlspecialchars($confirmText) ?></button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


