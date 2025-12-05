<?php

declare(strict_types=1);

final class DeactivatePluginService
{
    public function __construct(private readonly PluginRepositoryInterface $plugins)
    {
    }

    public function execute(string $slug): bool
    {
        if ($slug === '') {
            return false;
        }

        return $this->plugins->deactivate($slug);
    }
}
