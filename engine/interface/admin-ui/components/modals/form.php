<?php
/**
 * Компонент модального вікна з формою
 * 
 * @param array $config Конфігурація модального вікна
 *   - id: string - ID модального вікна
 *   - title: string - Заголовок
 *   - action: string - Дія форми
 *   - method: string - HTTP метод
 *   - fields: array - Масив полів форми
 *   - submitText: string - Текст кнопки підтвердження
 *   - cancelText: string - Текст кнопки скасування
 */

if (!isset($config) || !is_array($config)) {
    return;
}

$id = $config['id'] ?? 'formModal';
$title = $config['title'] ?? 'Форма';
$action = $config['action'] ?? '';
$method = $config['method'] ?? 'POST';
$fields = $config['fields'] ?? [];
$submitText = $config['submitText'] ?? 'Зберегти';
$cancelText = $config['cancelText'] ?? 'Скасувати';
$submitIcon = $config['submitIcon'] ?? 'fa-save';

$formId = $id . 'Form';

/**
 * Рендер поля форми
 */
function renderFormField(array $field): string {
    $type = $field['type'] ?? 'text';
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? '';
    $value = $field['value'] ?? '';
    $placeholder = $field['placeholder'] ?? '';
    $required = $field['required'] ?? false;
    $hint = $field['hint'] ?? '';
    $options = $field['options'] ?? [];
    
    $html = '<div class="form-group">';
    
    if ($label) {
        $html .= '<label class="form-label" for="' . htmlspecialchars($name) . '">';
        $html .= htmlspecialchars($label);
        if ($required) {
            $html .= ' <span class="text-danger">*</span>';
        }
        $html .= '</label>';
    }
    
    switch ($type) {
        case 'textarea':
            $html .= '<textarea class="form-control" id="' . htmlspecialchars($name) . '" name="' . htmlspecialchars($name) . '"';
            $html .= ' placeholder="' . htmlspecialchars($placeholder) . '"';
            if ($required) $html .= ' required';
            $html .= '>' . htmlspecialchars($value) . '</textarea>';
            break;
            
        case 'select':
            $html .= '<select class="form-control" id="' . htmlspecialchars($name) . '" name="' . htmlspecialchars($name) . '"';
            if ($required) $html .= ' required';
            $html .= '>';
            foreach ($options as $optValue => $optLabel) {
                $selected = $value == $optValue ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars($optValue) . '"' . $selected . '>' . htmlspecialchars($optLabel) . '</option>';
            }
            $html .= '</select>';
            break;
            
        case 'checkbox':
            $checked = $value ? ' checked' : '';
            $html .= '<div class="form-check">';
            $html .= '<input type="checkbox" class="form-check-input" id="' . htmlspecialchars($name) . '" name="' . htmlspecialchars($name) . '" value="1"' . $checked . '>';
            $html .= '<label class="form-check-label" for="' . htmlspecialchars($name) . '">' . htmlspecialchars($label) . '</label>';
            $html .= '</div>';
            break;
            
        case 'hidden':
            $html .= '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '">';
            break;
            
        default:
            $html .= '<input type="' . htmlspecialchars($type) . '" class="form-control" id="' . htmlspecialchars($name) . '"';
            $html .= ' name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '"';
            $html .= ' placeholder="' . htmlspecialchars($placeholder) . '"';
            if ($required) $html .= ' required';
            $html .= '>';
    }
    
    if ($hint) {
        $html .= '<div class="form-text">' . htmlspecialchars($hint) . '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}
?>

<div class="modal fade" id="<?= htmlspecialchars($id) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= htmlspecialchars($title) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            
            <form id="<?= htmlspecialchars($formId) ?>" method="<?= htmlspecialchars($method) ?>" action="<?= htmlspecialchars($action) ?>">
                <input type="hidden" name="modal_id" value="<?= htmlspecialchars($id) ?>">
                <input type="hidden" name="csrf_token" value="<?= SecurityHelper::csrfToken() ?>">
                
                <div class="modal-body form-modal">
                    <?php foreach ($fields as $field): ?>
                        <?= renderFormField($field) ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($cancelText) ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas <?= htmlspecialchars($submitIcon) ?>"></i>
                        <?= htmlspecialchars($submitText) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


