<?php

declare(strict_types=1);

final class UninstallPluginService
{
    public function __construct(private readonly PluginRepositoryInterface $plugins)
    {
    }

    public function execute(string $slug, ?callable $beforeRemove = null): bool
    {
        if ($slug === '') {
            return false;
        }

        if ($beforeRemove !== null) {
            $beforeRemove($slug);
        }

        return $this->plugins->uninstall($slug);
    }
}
