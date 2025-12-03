<?php

/**
 * Базовий сервіс-провайдер.
 *
 * @package Engine\System
 */

declare(strict_types=1);

require_once __DIR__ . '/../contracts/ServiceProviderInterface.php';

abstract class ServiceProvider implements ServiceProviderInterface
{
    protected ContainerInterface $container;

    final public function register(ContainerInterface $container): void
    {
        $this->container = $container;
        $this->registerBindings();
    }

    public function boot(ContainerInterface $container): void
    {
        // За замовчуванням нічого не робимо
    }

    /**
     * Метод для реєстрації біндів у нащадках.
     */
    abstract protected function registerBindings(): void;
}
