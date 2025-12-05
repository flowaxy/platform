<?php

/**
 * Клас ресурсу (CSS/JS)
 * 
 * @package Engine\Infrastructure\Assets
 * @version 1.0.0
 */

declare(strict_types=1);

final class Asset
{
    private string $path;
    private string $type;
    private ?string $version = null;
    private array $attributes = [];

    public function __construct(string $path, string $type = 'auto')
    {
        $this->path = $path;
        $this->type = $type === 'auto' ? $this->detectType($path) : $type;
    }

    /**
     * Встановлення версії
     */
    public function version(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    /**
     * Додавання атрибутів
     */
    public function attributes(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    /**
     * Отримання URL ресурсу
     */
    public function url(): string
    {
        $url = $this->path;
        
        if ($this->version) {
            $separator = strpos($url, '?') !== false ? '&' : '?';
            $url .= $separator . 'v=' . $this->version;
        }

        return $url;
    }

    /**
     * Рендеринг HTML тегу
     */
    public function render(): string
    {
        $url = htmlspecialchars($this->url(), ENT_QUOTES, 'UTF-8');
        $attrs = $this->buildAttributes();

        if ($this->type === 'css') {
            return "<link rel=\"stylesheet\" href=\"{$url}\"{$attrs}>";
        } elseif ($this->type === 'js') {
            return "<script src=\"{$url}\"{$attrs}></script>";
        }

        return '';
    }

    /**
     * Визначення типу ресурсу
     */
    private function detectType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        return match ($extension) {
            'css' => 'css',
            'js' => 'js',
            default => 'unknown',
        };
    }

    /**
     * Побудова атрибутів
     */
    private function buildAttributes(): string
    {
        if (empty($this->attributes)) {
            return '';
        }

        $parts = [];
        foreach ($this->attributes as $key => $value) {
            $key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $parts[] = "{$key}=\"{$value}\"";
        }

        return ' ' . implode(' ', $parts);
    }
}

