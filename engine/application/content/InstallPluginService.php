<?php

declare(strict_types=1);

final class InstallPluginService
{
    public function __construct(private readonly PluginRepositoryInterface $plugins)
    {
    }

    /**
     * @param string $slug
     * @param array<string, mixed> $config
     * @param callable|null $afterInstall
     * @return bool
     */
    public function execute(string $slug, array $config, ?callable $afterInstall = null): bool
    {
        if ($slug === '') {
            return false;
        }

        $plugin = new Plugin(
            slug: $slug,
            name: $config['name'] ?? ucfirst($slug),
            version: $config['version'] ?? '1.0.0',
            active: false,
            description: $config['description'] ?? '',
            author: $config['author'] ?? '',
            meta: $config
        );

        $result = $this->plugins->install($plugin);

        if ($result && $afterInstall !== null) {
            $afterInstall($plugin);
        }

        return $result;
    }
}
