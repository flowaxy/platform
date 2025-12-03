<?php
/**
 * Сторінка помилки підключення до бази даних
 * Перейменовано з database-error.php для професійної структури
 */

$errorCode = '503';
$errorTitle = 'Помилка підключення до бази даних';
$errorMessage = 'Не вдалося підключитися до бази даних. Перевірте налаштування підключення або зверніться до адміністратора.';
$title = 'Помилка підключення до бази даних';

$actions = [
    ['text' => 'Оновити сторінку', 'onclick' => 'location.reload()', 'type' => 'primary'],
    ['text' => 'Налаштування', 'href' => '/install', 'type' => 'outline'],
    ['text' => 'На головну', 'href' => '/', 'type' => 'secondary'],
];

// Додаємо debug інформацію про БД
$debugInfo = null;
if (isset($errorDetails) && is_array($errorDetails)) {
    $showDebug = defined('DEBUG_MODE') && constant('DEBUG_MODE');
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = in_array($host, ['localhost', '127.0.0.1']) ||
               str_ends_with($host, '.local') ||
               str_contains($host, 'dev.flowaxy.com');
    
    if ($showDebug || $isLocal) {
        $debugInfo = [];
        if (isset($errorDetails['host'])) $debugInfo['host'] = $errorDetails['host'];
        if (isset($errorDetails['port'])) $debugInfo['port'] = $errorDetails['port'];
        if (isset($errorDetails['database'])) $debugInfo['database'] = $errorDetails['database'];
        if (isset($errorDetails['error'])) $debugInfo['error'] = $errorDetails['error'];
        if (isset($errorDetails['code'])) $debugInfo['code'] = $errorDetails['code'];
    }
}

require __DIR__ . '/../layout.php';

