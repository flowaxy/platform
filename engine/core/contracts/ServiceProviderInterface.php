<?php

/**
 * Контракт сервіс-провайдера для реєстрації залежностей.
 *
 * @package Engine\Interfaces
 */

declare(strict_types=1);

require_once __DIR__ . '/ContainerInterface.php';

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
