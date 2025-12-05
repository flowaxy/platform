<?php

/**
 * Приклад використання AssetManager та AssetPipeline
 * 
 * @package Engine\Infrastructure\Assets\Examples
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/AssetManager.php';
require_once dirname(__DIR__) . '/AssetPipeline.php';

/**
 * Приклад використання AssetManager
 */
function exampleAssetManager(): void
{
    $assetManager = new AssetManager();
    
    // Додавання CSS
    $assetManager->addCss('/assets/css/style.css', 'main-style');
    $assetManager->addCss('/assets/css/theme.css', 'theme-style');
    
    // Додавання JS
    $assetManager->addJs('/assets/js/app.js', 'app-script');
    $assetManager->addJs('/assets/js/plugins.js', 'plugins-script', ['app-script'], true);
    
    // Встановлення CDN
    $assetManager->setCdnUrl('https://cdn.example.com');
    
    // Встановлення версії
    $assetManager->setVersion('1.0.0');
    
    // Рендеринг
    echo $assetManager->renderCss();
    echo $assetManager->renderJs();
}

/**
 * Приклад використання AssetPipeline
 */
function exampleAssetPipeline(): void
{
    $pipeline = new AssetPipeline('/storage/assets');
    
    // Обробка CSS файлів
    $cssFiles = [
        '/assets/css/reset.css',
        '/assets/css/base.css',
        '/assets/css/components.css',
    ];
    
    $processedCss = $pipeline->process($cssFiles, 'css');
    echo "<link rel='stylesheet' href='{$processedCss}'>";
    
    // Обробка JS файлів
    $jsFiles = [
        '/assets/js/vendor.js',
        '/assets/js/app.js',
    ];
    
    $processedJs = $pipeline->process($jsFiles, 'js');
    echo "<script src='{$processedJs}'></script>";
}

