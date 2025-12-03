<?php

/**
 * Flowaxy CMS - Entry Point
 * Точка входу для всіх запитів
 *
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

// Завантажуємо app.php для ініціалізації та запуску системи
$appBootstrapFile = __DIR__ . DIRECTORY_SEPARATOR . 'engine' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
if (!file_exists($appBootstrapFile)) {
    $isCli = php_sapi_name() === 'cli';
    if ($isCli) {
        error_log('Критична помилка: файл engine/core/bootstrap/app.php відсутній.');
        die("Помилка: файл engine/core/bootstrap/app.php відсутній.\n");
    }
    
    // Спробуємо використати error-handler, якщо він доступний
    $errorHandlerFile = __DIR__ . DIRECTORY_SEPARATOR . 'engine' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'error-handler.php';
    if (file_exists($errorHandlerFile) && is_readable($errorHandlerFile)) {
        require_once $errorHandlerFile;
        if (function_exists('showHttpError')) {
            showHttpError(500, 'Файл движка не знайдено', 'Файл engine/core/bootstrap/app.php відсутній.');
        }
    }
    
    // Fallback якщо error-handler недоступний
    error_log('Критична помилка: файл engine/core/bootstrap/app.php відсутній.');
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Помилка</title></head><body><h1>Файл движка не знайдено</h1><p>Файл engine/core/bootstrap/app.php відсутній.</p></body></html>');
}

require_once $appBootstrapFile;
