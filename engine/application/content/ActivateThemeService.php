<?php

declare(strict_types=1);

final class ActivateThemeService
{
    public function __construct(private readonly ThemeRepositoryInterface $themes)
    {
    }

    public function execute(string $slug): bool
    {
        if ($slug === '') {
            return false;
        }

        return $this->themes->activate($slug);
    }
}
