<?php

declare(strict_types=1);

final class ActivatePluginService
{
    public function __construct(private readonly PluginRepositoryInterface $plugins)
    {
    }

    public function execute(string $slug): bool
    {
        if ($slug === '') {
            return false;
        }

        return $this->plugins->activate($slug);
    }
}
