<?php

/**
 * Базовий сервіс-провайдер.
 *
 * @package Engine\System
 */

declare(strict_types=1);

require_once __DIR__ . '/../../Contracts/ServiceProviderInterface.php';
require_once __DIR__ . '/../../Contracts/ContainerInterface.php';

use Flowaxy\Core\Contracts\ServiceProviderInterface;
use Flowaxy\Core\Contracts\ContainerInterface;

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
