<?php

declare(strict_types=1);

final class FakePluginRepository implements PluginRepositoryInterface
{
    /** @var array<string,Plugin> */
    public array $plugins = [];

    /** @var array<string,array<string,mixed>> */
    public array $settings = [];

    /**
     * @return array<int, Plugin>
     */
    public function all(): array
    {
        return array_values($this->plugins);
    }

    public function find(string $slug): ?Plugin
    {
        return $this->plugins[$slug] ?? null;
    }

    public function install(Plugin $plugin): bool
    {
        $this->plugins[$plugin->slug] = $plugin;

        return true;
    }

    public function uninstall(string $slug): bool
    {
        unset($this->plugins[$slug], $this->settings[$slug]);

        return true;
    }

    public function activate(string $slug): bool
    {
        if (! isset($this->plugins[$slug])) {
            return false;
        }

        $plugin = $this->plugins[$slug];
        $this->plugins[$slug] = new Plugin(
            $plugin->slug,
            $plugin->name,
            $plugin->version,
            true,
            $plugin->description,
            $plugin->author,
            $plugin->meta
        );

        return true;
    }

    public function deactivate(string $slug): bool
    {
        if (! isset($this->plugins[$slug])) {
            return false;
        }

        $plugin = $this->plugins[$slug];
        $this->plugins[$slug] = new Plugin(
            $plugin->slug,
            $plugin->name,
            $plugin->version,
            false,
            $plugin->description,
            $plugin->author,
            $plugin->meta
        );

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(string $slug): array
    {
        return $this->settings[$slug] ?? [];
    }

    public function setSetting(string $slug, string $key, mixed $value): bool
    {
        $this->settings[$slug][$key] = $value;

        return true;
    }
}
