<?php
/**
 * Базовий layout для сторінок помилок
 * Строгий корпоративний flat стиль, професійний дизайн високого рівня
 * 
 * @var string $title Заголовок сторінки
 * @var string $errorCode Код помилки (404, 500, тощо)
 * @var string $errorTitle Заголовок помилки
 * @var string $errorMessage Повідомлення про помилку
 * @var array $actions Масив дій (кнопки)
 * @var array $debugInfo Масив з debug інформацією (опціонально)
 */

$title = $title ?? 'Помилка';
$errorCode = $errorCode ?? '';
$errorTitle = $errorTitle ?? 'Помилка';
$errorMessage = $errorMessage ?? '';
$actions = $actions ?? [];
$debugInfo = $debugInfo ?? null;
$buttonAfterText = $buttonAfterText ?? null; // Кнопка після тексту повідомлення
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <!-- Font Awesome -->
    <?php
    // Використовуємо прямий шлях до Font Awesome, який працює навіть коли система не встановлена
    $fontAwesomePath = '/engine/interface/admin-ui/assets/styles/font-awesome/css/all.min.css';
    if (class_exists('UrlHelper')) {
        $fontAwesomePath = UrlHelper::admin('assets/styles/font-awesome/css/all.min.css');
    }
    ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($fontAwesomePath, ENT_QUOTES, 'UTF-8') ?>">
    <!-- Fallback CDN для Font Awesome (якщо локальний файл не завантажився) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" media="print" onload="this.media='all'">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --color-primary: #1a1a1a;
            --color-secondary: #4a4a4a;
            --color-accent: #0066cc;
            --color-error: #ef4444;
            --color-error-dark: #dc2626;
            --color-error-light: #f87171;
            --color-warning: #ffc107;
            --color-success: #28a745;
            --color-text: #212529;
            --color-text-light: #6c757d;
            --color-bg: #ffffff;
            --color-bg-light: #f8f9fa;
            --color-border: #dee2e6;
            --color-border-light: #e9ecef;
            --spacing-xs: 4px;
            --spacing-sm: 8px;
            --spacing-md: 16px;
            --spacing-lg: 24px;
            --spacing-xl: 32px;
            --spacing-2xl: 48px;
            --radius-sm: 2px;
            --radius-md: 4px;
            --radius-lg: 6px;
            --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            --font-family-mono: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', 'Courier New', monospace;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        body {
            font-family: var(--font-family);
            font-size: 14px;
            line-height: 1.5;
            color: var(--color-text);
            background: var(--color-bg-light);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: var(--spacing-md);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        .error-container {
            width: 100%;
            max-width: 640px;
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            box-shadow: var(--shadow-lg);
        }
        
        .error-header {
            background: var(--color-primary);
            color: #ffffff;
            padding: var(--spacing-sm) var(--spacing-md);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--spacing-sm);
        }
        
        .error-header-actions {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }
        
        .error-header-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            padding: 0;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-sm);
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.9);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 14px;
            line-height: 1;
            min-width: 32px;
            min-height: 32px;
        }
        
        .error-header-btn:empty::after {
            content: '?';
            font-size: 16px;
            line-height: 1;
        }
        
        .error-header-btn i {
            font-size: 14px;
            line-height: 1;
            display: inline-block;
            font-style: normal;
            font-variant: normal;
            text-rendering: auto;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Забезпечуємо, що Font Awesome іконки використовують правильний font-family */
        .error-header-btn i[class*="fa-"],
        .error-header-btn i[class^="fa-"],
        .error-header-btn i.fa-solid,
        .error-header-btn i.fa-brands,
        .error-header-btn i.fa-regular {
            font-family: "Font Awesome 6 Free", "Font Awesome 6 Pro", "Font Awesome 6 Solid", "Font Awesome 6 Brands", "FontAwesome" !important;
            font-weight: 900;
        }
        
        .error-header-btn i.fa-solid::before {
            font-family: "Font Awesome 6 Free" !important;
            font-weight: 900 !important;
        }
        
        /* Fallback для іконок, якщо Font Awesome не завантажився - тільки для порожніх кнопок */
        .error-header-btn:empty::before,
        .error-header-btn:not(:has(> *))::before {
            content: '?';
            font-size: 14px;
            line-height: 1;
        }
        
        .error-header-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
            color: #ffffff;
        }
        
        .error-header-btn.primary {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .error-header-btn.primary:hover {
            background: rgba(255, 255, 255, 0.25);
        }
        
        .error-header-btn.outline {
            background: transparent;
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .error-header-btn.outline:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .error-code {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.4;
            margin: 0;
            color: var(--color-error);
            letter-spacing: 0.5px;
            flex-shrink: 0;
            text-shadow: 0 0 8px rgba(239, 68, 68, 0.3);
        }
        
        .error-code-separator {
            margin: 0 var(--spacing-sm);
            color: rgba(255, 255, 255, 0.25);
            font-size: 14px;
            font-weight: 300;
            opacity: 0.6;
        }
        
        .error-title {
            font-size: 14px;
            font-weight: 400;
            line-height: 1.4;
            margin: 0;
            color: rgba(255, 255, 255, 0.92);
            letter-spacing: 0.2px;
            flex: 1;
        }
        
        .error-content {
            padding: var(--spacing-md);
            padding-bottom: var(--spacing-sm);
        }
        
        .error-footer {
            margin-top: var(--spacing-sm);
            padding-top: var(--spacing-sm);
            text-align: center;
            font-size: 12px;
            color: var(--color-text-light);
            max-width: 640px;
            width: 100%;
        }
        
        .error-footer a {
            color: var(--color-text-light);
            text-decoration: none;
            border-bottom: 1px solid var(--color-text-light);
            transition: color 0.2s ease;
        }
        
        .error-footer a:hover {
            color: var(--color-text);
            border-bottom-color: var(--color-text);
        }
        
        .error-message {
            font-size: 15px;
            line-height: 1.6;
            color: var(--color-text);
            margin-bottom: var(--spacing-sm);
            padding-bottom: 0;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            line-height: 1.5;
            text-decoration: none;
            border: 1px solid transparent;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.15s ease;
            background: var(--color-primary);
            color: #ffffff;
            min-height: 40px;
            letter-spacing: 0.2px;
        }
        
        .btn-icon-spacer {
            width: 10px;
            flex-shrink: 0;
        }
        
        .btn i {
            flex-shrink: 0;
            display: inline-block;
            margin-right: 0;
        }
        
        .btn i + .btn-icon-spacer {
            display: inline-block;
        }
        
        .btn:hover {
            background: #2a2a2a;
        }
        
        .btn:active {
            transform: translateY(1px);
        }
        
        .btn-secondary {
            background: transparent;
            color: var(--color-text);
            border-color: var(--color-border);
        }
        
        .btn-secondary:hover {
            background: var(--color-bg-light);
            border-color: var(--color-text-light);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--color-accent);
            border-color: var(--color-accent);
        }
        
        .btn-outline:hover {
            background: var(--color-accent);
            color: #ffffff;
        }
        
        /* Debug section */
        .debug-minimal {
            margin-top: var(--spacing-md);
            border-top: 1px solid var(--color-border-light);
            padding-top: var(--spacing-sm);
            padding-bottom: 0;
            margin-bottom: 0;
            margin-left: calc(-1 * var(--spacing-md));
            margin-right: calc(-1 * var(--spacing-md));
            padding-left: var(--spacing-md);
            padding-right: var(--spacing-md);
        }
        
        .debug-minimal:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .debug-section {
            margin-top: var(--spacing-md);
            border-top: 1px solid var(--color-border-light);
            padding-top: var(--spacing-sm);
            margin-left: calc(-1 * var(--spacing-md));
            margin-right: calc(-1 * var(--spacing-md));
            padding-left: var(--spacing-md);
            padding-right: var(--spacing-md);
        }
        
        .debug-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--color-text-light);
            margin-bottom: var(--spacing-md);
            padding: var(--spacing-sm) 0;
            cursor: pointer;
            user-select: none;
        }
        
        .debug-header:hover {
            color: var(--color-text);
        }
        
        .debug-header-toggle {
            font-size: 12px;
            font-weight: 400;
            opacity: 0.6;
        }
        
        .debug-content {
            transition: opacity 0.2s ease;
            padding: 0;
            margin: 0;
        }
        
        .debug-content.collapsed {
            display: none;
        }
        
        .debug-item {
            display: flex;
            flex-direction: row;
            margin-bottom: var(--spacing-xs);
            padding: var(--spacing-xs) 0;
            position: relative;
        }
        
        .debug-item:last-child {
            margin-bottom: 0;
        }
        
        .debug-item-highlighted-container {
            margin-bottom: var(--spacing-md);
            margin-left: 0;
            margin-right: 0;
        }
        
        .debug-item-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-light);
            margin-bottom: var(--spacing-xs);
        }
        
        .debug-item-highlighted-box {
            background: #1a1a1a;
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            display: flex;
            align-items: flex-start;
            gap: var(--spacing-sm);
            position: relative;
        }
        
        .debug-item-highlighted-box:not(:has(.trace-content)) {
            /* Для TYPE/MESSAGE/FILE/LINE без кнопки копіювання */
            display: block;
        }
        
        .debug-item-highlighted-box .debug-value {
            font-family: var(--font-family-mono);
            font-size: 12px;
            line-height: 1.6;
            color: #ffffff;
            background: transparent;
            overflow-x: auto;
            word-break: break-word;
            white-space: pre-wrap;
            padding: 0;
            margin: 0;
            border: none;
            user-select: text;
        }
        
        .debug-item-highlighted-box:has(.trace-content) .debug-value {
            flex: 1;
        }
        
        .debug-item-highlighted-box .debug-value.type {
            color: #ff6b6b;
        }
        
        .debug-item-highlighted-box .debug-value.message {
            color: #ff6b6b;
        }
        
        .debug-item-highlighted-box .debug-value.file {
            color: #51cf66;
        }
        
        .debug-item-highlighted-box .debug-value.line {
            color: #74c0fc;
        }
        
        .debug-item-highlighted-box:has(.trace-content) {
            position: relative;
        }
        
        .debug-item-highlighted-box:has(.trace-content) .debug-copy-btn {
            position: absolute;
            top: var(--spacing-sm);
            right: var(--spacing-sm);
            flex-shrink: 0;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--radius-sm);
            padding: 4px 8px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.8);
            cursor: pointer;
            line-height: 1;
            font-family: var(--font-family);
            transition: all 0.2s ease;
            opacity: 0;
        }
        
        .debug-item-highlighted-box:has(.trace-content):hover .debug-copy-btn {
            opacity: 1;
        }
        
        .debug-item-highlighted-box:has(.trace-content) .debug-copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            color: #ffffff;
        }
        
        .debug-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-light);
            width: 90px;
            flex-shrink: 0;
            padding-right: var(--spacing-md);
            margin: 0;
            line-height: 1.8;
        }
        
        .debug-value-wrapper {
            flex: 1;
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: var(--spacing-sm);
        }
        
        .debug-item.simple {
            border-bottom: 1px solid var(--color-border-light);
            padding: var(--spacing-sm) 0;
            display: block;
            margin-left: calc(-1 * var(--spacing-md));
            margin-right: calc(-1 * var(--spacing-md));
            padding-left: var(--spacing-md);
            padding-right: var(--spacing-md);
            line-height: 1.5;
        }
        
        .debug-item.simple:first-child {
            padding-top: 0;
        }
        
        .debug-item.simple:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }
        
        .debug-item.simple .debug-label {
            display: inline-block;
            min-width: 120px;
            margin: 0;
            padding-right: var(--spacing-md);
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-light);
            vertical-align: top;
        }
        
        .debug-item.simple .debug-value {
            display: inline;
            font-family: var(--font-family-mono);
            font-size: 12px;
            line-height: 1.6;
            color: var(--color-text);
            word-break: break-word;
        }
        
        .debug-value {
            font-family: var(--font-family-mono);
            font-size: 12px;
            line-height: 1.6;
            color: var(--color-text);
            flex: 1;
            overflow-x: auto;
            word-break: break-word;
            white-space: pre-wrap;
            padding: 0;
            margin: 0;
            border: none;
            user-select: text;
        }
        
        .debug-item.highlighted .debug-value.type {
            color: #ff6b6b;
        }
        
        .debug-item.highlighted .debug-value.message {
            color: #ff6b6b;
        }
        
        .debug-item.highlighted .debug-value.file {
            color: #51cf66;
        }
        
        .debug-item.highlighted .debug-value.line {
            color: #74c0fc;
        }
        
        .debug-item.highlighted .debug-value.code {
            color: #adb5bd;
        }
        
        .debug-item.highlighted .trace-item {
            color: #e9ecef;
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }
        
        .debug-copy-btn {
            opacity: 0;
            transition: opacity 0.2s ease;
            background: transparent;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            padding: 4px 8px;
            font-size: 10px;
            color: var(--color-text-light);
            cursor: pointer;
            flex-shrink: 0;
            line-height: 1;
            font-family: var(--font-family);
        }
        
        .debug-copy-btn:hover {
            background: var(--color-primary);
            color: #ffffff;
            border-color: var(--color-primary);
        }
        
        .debug-item:hover .debug-copy-btn {
            opacity: 1;
        }
        
        .debug-copy-btn.copied {
            background: var(--color-success);
            color: #ffffff;
            border-color: var(--color-success);
        }
        
        .debug-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        
        .debug-table td {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-bottom: 1px solid var(--color-border-light);
            vertical-align: top;
        }
        
        .debug-table td:first-child {
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-light);
            width: 80px;
        }
        
        .debug-table tr:last-child td {
            border-bottom: none;
        }
        
        .trace-content {
            flex: 1;
            font-family: var(--font-family-mono);
            font-size: 11px;
            line-height: 1.6;
            color: #ffffff;
        }
        
        .trace-item {
            padding: var(--spacing-xs) 0;
            white-space: pre-wrap;
            word-break: break-all;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #e9ecef;
            user-select: text;
        }
        
        .trace-item:last-child {
            border-bottom: none;
        }
        
        .trace-number {
            color: #adb5bd;
            font-weight: 600;
        }
        
        .trace-file {
            color: #51cf66;
        }
        
        .trace-line {
            color: #74c0fc;
        }
        
        .trace-class {
            color: #ffd43b;
        }
        
        .trace-method {
            color: #ff8787;
        }
        
        .trace-function {
            color: #ff8787;
        }
        
        /* Додаткові стилі для різних типів значень */
        .debug-value.type {
            color: #dc3545;
            font-weight: 500;
        }
        
        .debug-value.message {
            color: #dc3545;
        }
        
        .debug-value.file {
            color: #28a745;
        }
        
        .debug-value.line {
            color: #007bff;
        }
        
        .debug-value.code {
            color: #6c757d;
        }
        
        /* Responsive */
        /* Планшети (641px - 1024px) */
        @media (min-width: 641px) and (max-width: 1024px) {
            body {
                padding: var(--spacing-xl) !important;
                justify-content: center !important;
            }
            
            .error-container {
                width: 100% !important;
                max-width: 800px !important;
                margin: 0 auto !important;
                border-left: 1px solid var(--color-border) !important;
                border-right: 1px solid var(--color-border) !important;
            }
            
            .error-header {
                padding: var(--spacing-sm) var(--spacing-xl) !important;
            }
            
            .error-code {
                font-size: 20px !important;
            }
            
            .error-code-separator {
                font-size: 20px !important;
            }
            
            .error-title {
                font-size: 18px !important;
            }
            
            .error-content {
                padding: var(--spacing-xl) !important;
                padding-bottom: var(--spacing-md) !important;
            }
            
            .error-message {
                font-size: 19px !important;
                line-height: 1.8 !important;
            }
            
            .debug-label {
                font-size: 13px !important;
                font-weight: 700 !important;
            }
            
            .debug-value {
                font-size: 15px !important;
            }
            
            .error-footer {
                width: 100% !important;
                max-width: 800px !important;
                margin: var(--spacing-lg) auto 0 !important;
                padding: var(--spacing-lg) !important;
                font-size: 15px !important;
            }
        }
        
        /* Мобільні пристрої та планшети (загальні стилі) */
        @media (max-width: 1024px) {
            body {
                padding: 0;
                justify-content: flex-start;
                min-height: 100vh;
            }
            
            .error-container {
                width: 100%;
                max-width: 100%;
                margin: 0;
                border-left: none;
                border-right: none;
            }
            
            .error-footer {
                width: 100%;
                max-width: 100%;
                margin: var(--spacing-sm) 0 0 0;
                padding: var(--spacing-sm) var(--spacing-md) var(--spacing-lg);
            }
        }
        
        @media (max-width: 640px) {
            body {
                padding: 0;
            }
            
            .error-header {
                padding: var(--spacing-xs) var(--spacing-sm);
                flex-wrap: wrap;
            }
            
            .error-code {
                font-size: 20px;
            }
            
            .error-title {
                font-size: 12px;
            }
            
            .error-content {
                padding: var(--spacing-md);
                padding-bottom: var(--spacing-sm);
            }
            
            .error-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .debug-table td:first-child {
                width: 100px;
                font-size: 11px;
            }
            
            .error-footer {
                padding: var(--spacing-sm) var(--spacing-sm) var(--spacing-lg);
                font-size: 11px;
            }
        }
        
        /* Print styles */
        @media print {
            body {
                background: #ffffff;
            }
            
            .error-container {
                box-shadow: none;
                border: none;
            }
            
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-header">
            <div style="display: flex; align-items: center; gap: 0;">
                <?php if ($errorCode): ?>
                    <div class="error-code"><?= htmlspecialchars($errorCode, ENT_QUOTES, 'UTF-8') ?></div>
                    <span class="error-code-separator">|</span>
                <?php endif; ?>
                <h1 class="error-title"><?= htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            
            <?php if (!empty($actions)): ?>
                <div class="error-header-actions">
                    <?php
                    // Сортуємо кнопки: спочатку головна, потім оновити, потім додаткові
                    $sortedActions = [];
                    $homeAction = null;
                    $refreshAction = null;
                    $otherActions = [];
                    
                    foreach ($actions as $action) {
                        $text = strtolower($action['text'] ?? '');
                        if (strpos($text, 'головну') !== false || strpos($text, 'home') !== false || strpos($text, 'главную') !== false || strpos($text, 'головна') !== false) {
                            $homeAction = $action;
                        } elseif (strpos($text, 'оновити') !== false || strpos($text, 'refresh') !== false || strpos($text, 'reload') !== false) {
                            $refreshAction = $action;
                        } else {
                            $otherActions[] = $action;
                        }
                    }
                    
                    // Формуємо правильний порядок
                    if ($homeAction) $sortedActions[] = $homeAction;
                    if ($refreshAction) $sortedActions[] = $refreshAction;
                    $sortedActions = array_merge($sortedActions, $otherActions);
                    ?>
                    <?php foreach ($sortedActions as $action): ?>
                        <?php
                        $href = $action['href'] ?? '#';
                        $text = $action['text'] ?? '';
                        $type = $action['type'] ?? 'primary';
                        $onclick = $action['onclick'] ?? null;
                        
                        // Визначаємо іконку: спочатку з явно вказаної, потім з розпізнавання тексту
                        $iconClass = isset($action['icon']) && !empty($action['icon']) ? $action['icon'] : '';
                        
                        // Якщо іконка не вказана явно, визначаємо залежно від тексту
                        if (empty($iconClass)) {
                            $textLower = strtolower($text);
                            
                            // Перевіряємо послідовно від найбільш специфічних до загальних
                            if (strpos($textLower, 'оновити') !== false || strpos($textLower, 'обновить') !== false || strpos($textLower, 'refresh') !== false || strpos($textLower, 'reload') !== false) {
                                $iconClass = 'fa-solid fa-rotate-right';
                            } elseif (strpos($textLower, 'увійти') !== false || strpos($textLower, 'войти') !== false || strpos($textLower, 'sign in') !== false || strpos($textLower, 'login') !== false) {
                                $iconClass = 'fa-solid fa-sign-in-alt';
                            } elseif (strpos($textLower, 'адмін') !== false || strpos($textLower, 'админ') !== false || strpos($textLower, 'admin') !== false || strpos($textLower, 'панель') !== false || strpos($textLower, 'панел') !== false) {
                                $iconClass = 'fa-solid fa-gear';
                            } elseif (strpos($textLower, 'назад') !== false || strpos($textLower, 'back') !== false) {
                                $iconClass = 'fa-solid fa-arrow-left';
                            } elseif (strpos($textLower, 'головну') !== false || strpos($textLower, 'головна') !== false || strpos($textLower, 'home') !== false || strpos($textLower, 'главную') !== false || strpos($textLower, 'главная') !== false) {
                                $iconClass = 'fa-solid fa-house';
                            } elseif (strpos($textLower, 'завантажити') !== false || strpos($textLower, 'загрузить') !== false || strpos($textLower, 'download') !== false || strpos($textLower, 'встановити') !== false || strpos($textLower, 'установить') !== false || strpos($textLower, 'install') !== false) {
                                $iconClass = 'fa-solid fa-download';
                            }
                        }
                        ?>
                        <?php
                        $buttonClass = 'error-header-btn';
                        if ($type === 'primary') {
                            $buttonClass .= ' primary';
                        } elseif (!empty($type) && $type !== 'primary') {
                            $buttonClass .= ' ' . $type;
                        }
                        ?>
                        <?php if ($onclick): ?>
                            <button class="<?= htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8') ?>" onclick="<?= htmlspecialchars($onclick, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (!empty($iconClass)): ?>
                                    <i class="<?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                                <?php else: ?>
                                    <span><?= htmlspecialchars(mb_substr($text, 0, 1), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </button>
                        <?php else: ?>
                            <?php
                            $target = $action['target'] ?? '';
                            $rel = $action['rel'] ?? '';
                            $disabled = $action['disabled'] ?? false;
                            ?>
                            <?php if ($disabled): ?>
                                <span class="<?= htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8') ?> disabled" title="<?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>" style="opacity: 0.5; cursor: not-allowed; pointer-events: none;">
                                    <?php if (!empty($iconClass)): ?>
                                        <i class="<?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                                    <?php else: ?>
                                        <span><?= htmlspecialchars(mb_substr($text, 0, 1), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>"<?= $target ? ' target="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"' : '' ?><?= $rel ? ' rel="' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                    <?php if (!empty($iconClass)): ?>
                                        <i class="<?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                                    <?php else: ?>
                                        <span><?= htmlspecialchars(mb_substr($text, 0, 1), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="error-content">
            <?php if ($errorMessage): ?>
                <div class="error-message"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            
            <?php if ($buttonAfterText !== null && !empty($buttonAfterText)): ?>
                <div style="margin-top: var(--spacing-md);">
                    <?php
                    $button = $buttonAfterText;
                    $text = $button['text'] ?? '';
                    $href = $button['href'] ?? '#';
                    $target = $button['target'] ?? '';
                    $rel = $button['rel'] ?? '';
                    $type = $button['type'] ?? 'primary';
                    $iconClass = $button['icon'] ?? '';
                    $onclick = $button['onclick'] ?? null;
                    
                    $buttonClass = 'btn';
                    if ($type === 'secondary') {
                        $buttonClass .= ' btn-secondary';
                    } elseif ($type === 'outline') {
                        $buttonClass .= ' btn-outline';
                    }
                    ?>
                    <?php if ($onclick): ?>
                        <button class="<?= htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8') ?>" onclick="<?= htmlspecialchars($onclick, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($iconClass): ?><i class="<?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?>"></i><span class="btn-icon-spacer"></span><?php endif; ?><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8') ?>"<?= $target ? ' target="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"' : '' ?><?= $rel ? ' rel="' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                            <?php if ($iconClass): ?><i class="<?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?>"></i><span class="btn-icon-spacer"></span><?php endif; ?><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($debugInfo && !empty($debugInfo)): ?>
                <?php
                $labelMap = [
                    'type' => 'TYPE',
                    'message' => 'MESSAGE',
                    'file' => 'FILE',
                    'line' => 'LINE',
                    'code' => 'CODE',
                    'php_version' => 'PHP VERSION',
                    'timestamp' => 'TIMESTAMP',
                    'request_uri' => 'REQUEST URI',
                    'request_method' => 'METHOD',
                ];
                ?>
                
                <!-- Мінімальна інформація (завжди видима) -->
                <div class="debug-minimal">
                    <?php
                    // Виводимо всі поля як простий текст у рядок
                    $allFields = ['code', 'php_version', 'timestamp', 'request_uri', 'request_method', 'type', 'message', 'file', 'line'];
                    foreach ($allFields as $key):
                        if (!isset($debugInfo[$key]) || $debugInfo[$key] === null || $debugInfo[$key] === '') continue;
                        $value = $debugInfo[$key];
                        $label = $labelMap[$key] ?? strtoupper(str_replace('_', ' ', $key));
                        $displayValue = is_array($value) || is_object($value) ? print_r($value, true) : (string)$value;
                    ?>
                        <div class="debug-item simple">
                            <span class="debug-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>:</span>
                            <span class="debug-value"><?= htmlspecialchars($displayValue, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- STACK TRACE (прихована секція) -->
                <?php if (isset($debugInfo['trace']) && !empty($debugInfo['trace'])): ?>
                    <div class="debug-section">
                        <div class="debug-header" onclick="toggleDebugSection(this)">
                            <span>STACK TRACE</span>
                            <span class="debug-header-toggle">▶</span>
                        </div>
                        
                        <div class="debug-content collapsed">
                            <div class="debug-item-highlighted-container">
                                <div class="debug-item-highlighted-box">
                                    <div class="trace-content" 
                                         data-copy-text="<?= htmlspecialchars($debugInfo['trace'], ENT_QUOTES, 'UTF-8') ?>"
                                         ondblclick="copyTraceContent(this)"
                                         title="Подвійний клік для копіювання"
                                         style="cursor: text;">
                                        <?php
                                        $traceLines = explode("\n", $debugInfo['trace']);
                                        foreach ($traceLines as $line) {
                                            if (trim($line)) {
                                                // Парсимо рядок для підсвітки
                                                if (preg_match('/^#(\d+)\s+(.+?)\s+\((\d+)\):\s+(.+)$/', $line, $matches)) {
                                                    $frameNum = $matches[1];
                                                    $filePath = $matches[2];
                                                    $lineNum = $matches[3];
                                                    $function = $matches[4];
                                                    
                                                    // Виділяємо файл та функцію
                                                    $parts = explode('->', $function);
                                                    $className = count($parts) > 1 ? $parts[0] : '';
                                                    $methodName = count($parts) > 1 ? $parts[1] : $function;
                                                    
                                                    echo '<div class="trace-item">';
                                                    echo '<span class="trace-number">#' . htmlspecialchars($frameNum, ENT_QUOTES, 'UTF-8') . '</span> ';
                                                    echo '<span class="trace-file">' . htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8') . '</span>';
                                                    echo '<span class="trace-line">(' . htmlspecialchars($lineNum, ENT_QUOTES, 'UTF-8') . ')</span>';
                                                    echo ': ';
                                                    if ($className) {
                                                        echo '<span class="trace-class">' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8') . '</span>';
                                                        echo '-><span class="trace-method">' . htmlspecialchars($methodName, ENT_QUOTES, 'UTF-8') . '</span>()';
                                                    } else {
                                                        echo '<span class="trace-function">' . htmlspecialchars($function, ENT_QUOTES, 'UTF-8') . '</span>';
                                                    }
                                                    echo '</div>';
                                                } else {
                                                    echo '<div class="trace-item">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</div>';
                                                }
                                            }
                                        }
                                        ?>
                                    </div>
                                    <button class="debug-copy-btn" onclick="copyTraceContent(this.previousElementSibling)" title="Копіювати">Копіювати</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <script>
                function toggleDebugSection(header) {
                    const content = header.nextElementSibling;
                    const toggle = header.querySelector('.debug-header-toggle');
                    if (content) {
                        content.classList.toggle('collapsed');
                        toggle.textContent = content.classList.contains('collapsed') ? '▶' : '▼';
                    }
                }
                
                function copyDebugValue(btn) {
                    const wrapper = btn.closest('.debug-value-wrapper');
                    if (!wrapper) return;
                    
                    const valueEl = wrapper.querySelector('.debug-value');
                    if (!valueEl) return;
                    
                    // Отримуємо текст для копіювання - спочатку з data-атрибута, потім textContent
                    let textToCopy = valueEl.getAttribute('data-copy-text');
                    if (!textToCopy || textToCopy.trim() === '') {
                        textToCopy = valueEl.textContent || valueEl.innerText;
                    }
                    
                    // Очищаємо текст від зайвих пробілів
                    textToCopy = textToCopy.trim();
                    
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(textToCopy).then(function() {
                            showCopySuccess(btn);
                        }).catch(function(err) {
                            console.error('Помилка копіювання:', err);
                            fallbackCopy(textToCopy, btn);
                        });
                    } else {
                        fallbackCopy(textToCopy, btn);
                    }
                }
                
                function showCopySuccess(btn) {
                    const originalText = btn.textContent;
                    btn.textContent = 'Скопійовано!';
                    btn.classList.add('copied');
                    setTimeout(function() {
                        btn.textContent = originalText;
                        btn.classList.remove('copied');
                    }, 2000);
                }
                
                function copyHighlightedValue(btn) {
                    const box = btn.closest('.debug-item-highlighted-box');
                    if (!box) return;
                    
                    const valueEl = box.querySelector('.debug-value');
                    if (!valueEl) return;
                    
                    let textToCopy = valueEl.getAttribute('data-copy-text');
                    if (!textToCopy || textToCopy.trim() === '') {
                        textToCopy = valueEl.textContent || valueEl.innerText;
                    }
                    
                    textToCopy = textToCopy.trim();
                    
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(textToCopy).then(function() {
                            showCopySuccess(btn);
                        }).catch(function(err) {
                            console.error('Помилка копіювання:', err);
                            fallbackCopy(textToCopy, btn);
                        });
                    } else {
                        fallbackCopy(textToCopy, btn);
                    }
                }
                
                function copyTraceContent(element) {
                    let textToCopy = element.getAttribute('data-copy-text');
                    if (!textToCopy || textToCopy.trim() === '') {
                        textToCopy = element.textContent || element.innerText;
                    }
                    
                    textToCopy = textToCopy.trim();
                    
                    const btn = element.parentElement.querySelector('.debug-copy-btn');
                    
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(textToCopy).then(function() {
                            showCopySuccess(btn);
                        }).catch(function(err) {
                            console.error('Помилка копіювання:', err);
                            fallbackCopy(textToCopy, btn);
                        });
                    } else {
                        fallbackCopy(textToCopy, btn);
                    }
                }
                
                function fallbackCopy(text, btn) {
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand('copy');
                        showCopySuccess(btn);
                    } catch (err) {
                        console.error('Помилка копіювання:', err);
                        alert('Не вдалося скопіювати. Спробуйте виділити текст вручну.');
                    }
                    document.body.removeChild(textarea);
                }
            </script>
        </div>
    </div>
    
    <div class="error-footer">
        Дякуємо за довіру до web студії <a href="https://flowaxy.com" target="_blank" rel="noopener noreferrer">FLOWAXY</a>
    </div>
</body>
</html>

