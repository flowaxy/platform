<?php

/**
 * Менеджер ресурсів (CSS/JS)
 * 
 * Управління CSS/JS ресурсами з версіонуванням та підтримкою CDN
 * 
 * @package Engine\Infrastructure\Assets
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/Asset.php';

final class AssetManager
{
    /**
     * @var array<string, Asset>
     */
    private array $css = [];

    /**
     * @var array<string, Asset>
     */
    private array $js = [];

    private ?string $cdnUrl = null;
    private string $version = '1.0.0';

    /**
     * Додавання CSS ресурсу
     */
    public function addCss(string $path, ?string $key = null): Asset
    {
        $asset = new Asset($path, 'css');
        $key = $key ?? $path;
        $this->css[$key] = $asset;
        
        return $asset;
    }

    /**
     * Додавання JS ресурсу
     */
    public function addJs(string $path, ?string $key = null): Asset
    {
        $asset = new Asset($path, 'js');
        $key = $key ?? $path;
        $this->js[$key] = $asset;
        
        return $asset;
    }

    /**
     * Видалення CSS ресурсу
     */
    public function removeCss(string $key): void
    {
        unset($this->css[$key]);
    }

    /**
     * Видалення JS ресурсу
     */
    public function removeJs(string $key): void
    {
        unset($this->js[$key]);
    }

    /**
     * Рендеринг всіх CSS ресурсів
     */
    public function renderCss(): string
    {
        $html = [];
        foreach ($this->css as $asset) {
            $html[] = $asset->render();
        }
        return implode("\n", $html);
    }

    /**
     * Рендеринг всіх JS ресурсів
     */
    public function renderJs(): string
    {
        $html = [];
        foreach ($this->js as $asset) {
            $html[] = $asset->render();
        }
        return implode("\n", $html);
    }

    /**
     * Встановлення CDN URL
     */
    public function setCdnUrl(string $url): self
    {
        $this->cdnUrl = rtrim($url, '/');
        return $this;
    }

    /**
     * Встановлення версії
     */
    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    /**
     * Очищення всіх ресурсів
     */
    public function clear(): void
    {
        $this->css = [];
        $this->js = [];
    }
}

