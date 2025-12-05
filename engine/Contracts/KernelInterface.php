<?php

/**
 * Контракт для ядра системи Flowaxy CMS
 * Визначає життєвий цикл ядра та методи обробки запитів
 *
 * @package Engine\Core\Contracts
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/ContainerInterface.php';
require_once __DIR__ . '/ServiceProviderInterface.php';

interface KernelInterface
{
    /**
     * Ініціалізація ядра - створення контейнера та автозавантажувача
     */
    public function boot(): void;

    /**
     * Налаштування ядра - завантаження конфігурацій, змінних оточення
     */
    public function configure(): void;

    /**
     * Реєстрація сервіс-провайдерів у контейнері
     */
    public function registerProviders(): void;

    /**
     * Запуск (boot) сервіс-провайдерів після реєстрації
     */
    public function bootProviders(): void;

    /**
     * Обробка запиту та відправка відповіді
     */
    public function serve(): void;

    /**
     * Отримання контейнера залежностей
     */
    public function getContainer(): ContainerInterface;

    /**
     * Перевірка, чи ядро вже ініціалізовано
     */
    public function isBooted(): bool;
}
