<?php
/**
 * Компонент кнопки
 * 
 * @param string $text Текст кнопки
 * @param string $type Тип кнопки (primary, secondary, success, danger, warning, info, outline-primary, outline-secondary, etc.)
 * @param string $url URL для ссылки (если указан, создается <a>, иначе <button>)
 * @param string $icon Иконка Font Awesome (без fa-, просто имя, например: 'save', 'trash')
 * @param array $attributes Дополнительные атрибуты (data-*, onclick, class, etc.)
 * @param bool $submit Если true, кнопка будет type="submit"
 */
if (!isset($text)) {
    $text = 'Кнопка';
}
if (!isset($type)) {
    $type = 'primary';
}
if (!isset($icon)) {
    $icon = '';
}
if (!isset($attributes)) {
    $attributes = [];
}
if (!isset($submit)) {
    $submit = false;
}

// Базовый класс кнопки
$buttonClass = 'btn d-inline-flex align-items-center';
if (str_starts_with($type, 'outline-')) {
    $buttonClass .= ' btn-' . $type;
} else {
    $buttonClass .= ' btn-' . $type;
}

// Добавляем дополнительные классы из атрибутов
if (isset($attributes['class'])) {
    $buttonClass .= ' ' . $attributes['class'];
    unset($attributes['class']);
}

// Извлекаем type из атрибутов для кнопок (чтобы не дублировать в HTML)
$buttonType = null;
if (!isset($url) && isset($attributes['type'])) {
    $buttonType = $attributes['type'];
    unset($attributes['type']);
}

// Формируем атрибуты
$attributesString = '';
foreach ($attributes as $key => $value) {
    if ($value !== null) {
        $attributesString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
    } else {
        $attributesString .= ' ' . htmlspecialchars($key);
    }
}

// Формируем иконку
$iconHtml = '';
if (!empty($icon)) {
    $iconClass = 'fas fa-' . $icon;
    $iconHtml = '<i class="' . htmlspecialchars($iconClass) . ' btn-icon"></i>';
}

// Обгортаємо текст в span для можливості приховування на мобільних
$textHtml = '<span class="btn-text">' . htmlspecialchars($text) . '</span>';

// Если указан URL, создаем ссылку
if (isset($url) && !empty($url)):
?>
    <a href="<?= htmlspecialchars($url) ?>" class="<?= $buttonClass ?>"<?= $attributesString ?>>
        <?= $iconHtml ?><?= $textHtml ?>
    </a>
<?php
// Иначе создаем кнопку
else:
    // Використовуємо type з атрибутів, якщо був вказаний, інакше перевіряємо $submit
    if ($buttonType === null) {
        $buttonType = $submit ? 'submit' : 'button';
    }
?>
    <button type="<?= $buttonType ?>" class="<?= $buttonClass ?>"<?= $attributesString ?>>
        <?= $iconHtml ?><?= $textHtml ?>
    </button>
<?php
endif;
?>

