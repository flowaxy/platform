<?php

/**
 * Сторінка помилки: потрібна установка системи
 * Відображається, коли база даних не налаштована
 */

// Перевіряємо наявність директорії install
$rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 4);
$installDir = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . 'install';
$installDirExists = is_dir($installDir);

$httpCode = 503;
$errorCode = '503';
$errorTitle = 'Потрібна установка системи';
$errorMessage = $installDirExists 
    ? 'Для початку роботи необхідно встановити Flowaxy CMS. Натисніть кнопку нижче, щоб запустити майстер установки та налаштувати систему.'
    : 'Для встановлення Flowaxy CMS спочатку завантажте установщик з GitHub. Натисніть кнопку вище для завантаження, розпакуйте архів у корінь проекту, після чого оновіть цю сторінку для продовження.';
$title = 'Потрібна установка системи';

// Дії (кнопки) - відображаються в header
$actions = [];

// Кнопка в header - завжди веде на GitHub, але активна тільки якщо install немає
$actions[] = [
    'text' => 'Завантажити установщик',
    'href' => 'https://github.com/flowaxy/install',
    'target' => '_blank',
    'rel' => 'noopener noreferrer',
    'type' => 'primary',
    'icon' => 'fa-solid fa-download',
    'disabled' => $installDirExists, // Якщо install існує - кнопка неактивна
];

// Кнопка після тексту
if ($installDirExists) {
    // Якщо директорія install існує - показуємо кнопку встановлення
    $buttonAfterText = [
        'text' => 'Встановити',
        'href' => '/install',
        'type' => 'primary',
    ];
} else {
    // Якщо директорії install немає - кнопка не виводиться
    $buttonAfterText = null;
}

require __DIR__ . '/../layout.php';

