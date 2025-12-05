<?php

/**
 * Контракт сервіс-провайдера для реєстрації залежностей.
 *
 * @package Flowaxy\Core\Contracts
 */

declare(strict_types=1);

namespace Flowaxy\Core\Contracts;

// Завантажуємо ContainerInterface перед використанням
if (!interface_exists('Flowaxy\Core\Contracts\ContainerInterface')) {
    require_once __DIR__ . '/ContainerInterface.php';
}

interface ServiceProviderInterface
{
    /**
     * Реєстрація біндів/одиночок в контейнері.
     */
    public function register(ContainerInterface $container): void;

    /**
     * Опціональна ініціалізація після реєстрації.
     */
    public function boot(ContainerInterface $container): void;
}

interface ModuleServiceProviderInterface extends ServiceProviderInterface
{
}
