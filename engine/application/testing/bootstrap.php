<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', '1');

if (! defined('FLOWAXY_TESTS_BOOTSTRAPPED')) {
    define('FLOWAXY_TESTS_BOOTSTRAPPED', true);

    // Мінімальне завантаження ядра для тестів
    // Визначаємо правильний шлях до app.php
    $appBootstrapPath = __DIR__ . '/../../core/bootstrap/app.php';
    if (! file_exists($appBootstrapPath)) {
        // Альтернативний шлях для перевірки
        $appBootstrapPath = dirname(__DIR__, 3) . '/core/bootstrap/app.php';
    }
    require_once $appBootstrapPath;

    foreach (glob(__DIR__ . '/mocks/*.php') as $mockFile) {
        require_once $mockFile;
    }
}
