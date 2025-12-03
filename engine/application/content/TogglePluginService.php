<?php

declare(strict_types=1);

final class TogglePluginService
{
    public function __construct(
        private readonly ActivatePluginService $activate,
        private readonly DeactivatePluginService $deactivate
    ) {
    }

    public function execute(string $slug, bool $enable): bool
    {
        return $enable ? $this->activate->execute($slug) : $this->deactivate->execute($slug);
    }
}
