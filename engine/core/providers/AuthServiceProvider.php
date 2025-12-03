<?php

declare(strict_types=1);

require_once __DIR__ . '/../system/ServiceProvider.php';
require_once __DIR__ . '/../../domain/content/AdminUserRepositoryInterface.php';
require_once __DIR__ . '/../../infrastructure/persistence/AdminUserRepository.php';
require_once __DIR__ . '/../../domain/content/AdminRole.php';
require_once __DIR__ . '/../../domain/content/AdminRoleRepositoryInterface.php';
require_once __DIR__ . '/../../infrastructure/persistence/AdminRoleRepository.php';
require_once __DIR__ . '/../../application/security/AuthenticateAdminUserService.php';
require_once __DIR__ . '/../../application/security/AdminAuthorizationService.php';
require_once __DIR__ . '/../../application/security/LogoutAdminUserService.php';
require_once __DIR__ . '/../../application/security/AuthenticationResult.php';

final class AuthServiceProvider extends ServiceProvider
{
    protected function registerBindings(): void
    {
        if (! $this->container->has(AdminUserRepositoryInterface::class)) {
            $this->container->singleton(AdminUserRepositoryInterface::class, static fn () => new AdminUserRepository());
        }

        if (! $this->container->has(AdminRoleRepositoryInterface::class)) {
            $this->container->singleton(AdminRoleRepositoryInterface::class, static fn () => new AdminRoleRepository());
        }

        if (! $this->container->has(AuthenticateAdminUserService::class)) {
            $this->container->singleton(AuthenticateAdminUserService::class, fn () => new AuthenticateAdminUserService(
                $this->container->make(AdminUserRepositoryInterface::class)
            ));
        }

        if (! $this->container->has(AdminAuthorizationService::class)) {
            $this->container->singleton(AdminAuthorizationService::class, fn () => new AdminAuthorizationService(
                $this->container->make(AdminRoleRepositoryInterface::class)
            ));
        }

        if (! $this->container->has(LogoutAdminUserService::class)) {
            $this->container->singleton(LogoutAdminUserService::class, fn () => new LogoutAdminUserService(
                $this->container->make(AdminUserRepositoryInterface::class)
            ));
        }
    }
}
