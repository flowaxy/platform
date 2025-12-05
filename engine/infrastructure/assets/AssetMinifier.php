<?php

/**
 * Мініфікатор ресурсів
 * 
 * @package Engine\Infrastructure\Assets
 * @version 1.0.0
 */

declare(strict_types=1);

final class AssetMinifier
{
    /**
     * Мініфікація CSS
     */
    public function minifyCss(string $css): string
    {
        // Видаляємо коментарі
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Видаляємо зайві пробіли
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Видаляємо пробіли навколо спеціальних символів
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
        
        // Видаляємо пробіли на початку та в кінці
        $css = trim($css);
        
        return $css;
    }

    /**
     * Мініфікація JavaScript
     */
    public function minifyJs(string $js): string
    {
        // Видаляємо однорядкові коментарі
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // Видаляємо багаторядкові коментарі
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // Видаляємо зайві пробіли та переноси рядків
        $js = preg_replace('/\s+/', ' ', $js);
        
        // Видаляємо пробіли навколо операторів
        $js = preg_replace('/\s*([=+\-*\/\(\)\{\}\[\]\;\,])\s*/', '$1', $js);
        
        // Видаляємо пробіли на початку та в кінці
        $js = trim($js);
        
        return $js;
    }

    /**
     * Автоматична мініфікація за типом файлу
     */
    public function minify(string $content, string $type): string
    {
        return match ($type) {
            'css' => $this->minifyCss($content),
            'js' => $this->minifyJs($content),
            default => $content,
        };
    }
}

