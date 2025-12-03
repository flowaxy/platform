<?php
/**
 * Компонент групи форми
 *
 * @param string $label Мітка поля
 * @param string $name Ім'я поля
 * @param string $type Тип поля (text, email, password, textarea, select, checkbox, etc.)
 * @param mixed $value Значення поля
 * @param string $placeholder Placeholder
 * @param string $helpText Підказка під полем
 * @param array $options Опції для select/radio/checkbox
 * @param array $attributes Додаткові атрибути
 * @param string $id ID поля (якщо не вказано, генерується з name)
 */
if (! isset($type)) {
    $type = 'text';
}
if (! isset($value)) {
    $value = '';
}
if (! isset($placeholder)) {
    $placeholder = '';
}
if (! isset($helpText)) {
    $helpText = '';
}
if (! isset($options)) {
    $options = [];
}
if (! isset($attributes)) {
    $attributes = [];
}
if (! isset($id) && isset($name)) {
    $id = $name;
}
if (! isset($id)) {
    $id = '';
}

$fieldId = ! empty($id) ? htmlspecialchars($id) : 'field_' . uniqid();
$fieldName = isset($name) ? htmlspecialchars($name) : '';
$fieldLabel = isset($label) ? htmlspecialchars($label) : '';
?>
<div class="form-group mb-3">
    <?php if (! empty($fieldLabel) && $type !== 'checkbox' && $type !== 'radio'): ?>
    <label for="<?= $fieldId ?>" class="form-label fw-medium small">
        <?= $fieldLabel ?>
    </label>
    <?php endif; ?>
    
    <?php
    // Формуємо базові атрибути
    // Для checkbox не додаємо form-control, використовуємо form-check-input
    $baseAttributes = [
        'id' => $fieldId,
        'name' => $fieldName,
    ];
    
    // Додаємо клас тільки якщо це не checkbox/radio
    if ($type !== 'checkbox' && $type !== 'radio') {
        $baseAttributes['class'] = 'form-control';
    }

if (! empty($placeholder)) {
    $baseAttributes['placeholder'] = $placeholder;
}

// Об'єднуємо з додатковими атрибутами
$allAttributes = array_merge($baseAttributes, $attributes);

// Формуємо рядок атрибутів
$attrsString = '';
foreach ($allAttributes as $key => $val) {
    if ($val !== null && $val !== '') {
        if ($key === 'class') {
            $attrsString .= ' class="' . htmlspecialchars($val) . '"';
        } else {
            $attrsString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
        }
    }
}
?>
    
    <?php if ($type === 'textarea'): ?>
        <textarea<?= $attrsString ?>><?= htmlspecialchars($value) ?></textarea>
    <?php elseif ($type === 'select'): ?>
        <select<?= $attrsString ?>>
            <?php foreach ($options as $optionValue => $optionLabel): ?>
                <option value="<?= htmlspecialchars($optionValue) ?>" <?= ($value == $optionValue) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($optionLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php elseif ($type === 'checkbox'): ?>
        <div class="form-check form-switch">
            <input type="checkbox"<?= $attrsString ?> class="form-check-input" value="1" <?= ($value == '1' || $value === true) ? 'checked' : '' ?>>
            <?php if (! empty($fieldLabel)): ?>
            <label class="form-check-label" for="<?= $fieldId ?>">
                <?= $fieldLabel ?>
            </label>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <input type="<?= htmlspecialchars($type) ?>" value="<?= htmlspecialchars($value) ?>"<?= $attrsString ?>>
    <?php endif; ?>
    
    <?php if (! empty($helpText)): ?>
    <div class="form-text small"><?= htmlspecialchars($helpText) ?></div>
    <?php endif; ?>
</div>

