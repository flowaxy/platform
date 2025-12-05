<?php

/**
 * Базовий клас ядра системи Flowaxy CMS
 * Реалізує спільну логіку для HttpKernel та CliKernel
 *
 * @package Flowaxy\Core\System
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\System;

require_once __DIR__ . '/../../Contracts/KernelInterface.php';
require_once __DIR__ . '/../../Contracts/ContainerInterface.php';
require_once __DIR__ . '/../../Contracts/ServiceProviderInterface.php';
require_once __DIR__ . '/ClassAutoloader.php';
require_once __DIR__ . '/ClassMapGenerator.php';
require_once __DIR__ . '/ServiceConfig.php';
require_once __DIR__ . '/ModuleManager.php';
require_once __DIR__ . '/EnvironmentLoader.php';

use Flowaxy\Core\Contracts\ContainerInterface;
use Flowaxy\Core\Contracts\KernelInterface;
use Flowaxy\Core\Contracts\ServiceProviderInterface;

abstract class Kernel implements KernelInterface
{
    protected ContainerInterface $container;
    protected ClassAutoloader $autoloader;
    protected bool $booted = false;
    protected string $rootDir;
    protected array $serviceProviders = [];
    protected ?EnvironmentLoader $environmentLoader = null;

    /**
     * @param string $rootDir Корінь директорії engine/
     */
    public function __construct(string $rootDir)
    {
        $this->rootDir = rtrim($rootDir, '/\\');
    }

    /**
     * Ініціалізація ядра
     */
    public function boot(): void
    {
        if ($this->booted) {
            if (function_exists('logDebug')) {
                logDebug('Kernel::boot: Kernel already booted');
            }
            return;
        }

        if (function_exists('logDebug')) {
            logDebug('Kernel::boot: Starting kernel boot', ['root_dir' => $this->rootDir]);
        }

        $this->createAutoloader();
        $this->createContainer();

        $this->booted = true;

        if (function_exists('logInfo')) {
            logInfo('Kernel::boot: Kernel booted successfully', ['root_dir' => $this->rootDir]);
        }
    }

    /**
     * Налаштування ядра
     */
    public function configure(): void
    {
        if (! $this->isBooted()) {
            $this->boot();
        }

        if (function_exists('logDebug')) {
            logDebug('Kernel::configure: Configuring kernel');
        }

        $this->loadEnvironmentConfig();
        $this->loadServiceConfig();
        $this->loadGlobalFunctions();

        if (function_exists('logInfo')) {
            logInfo('Kernel::configure: Kernel configured successfully');
        }
    }

    /**
     * Реєстрація сервіс-провайдерів
     * Оптимізовано: lazy loading провайдерів
     */
    public function registerProviders(): void
    {
        if (! $this->isBooted()) {
            $this->boot();
        }

        if (function_exists('logDebug')) {
            logDebug('Kernel::registerProviders: Starting provider registration');
        }

        $this->serviceProviders = $this->getServiceProviders();

        $registeredCount = 0;
        foreach ($this->serviceProviders as $providerClass) {
            if (! class_exists($providerClass)) {
                if (function_exists('logWarning')) {
                    logWarning('Kernel::registerProviders: Provider class not found', ['provider' => $providerClass]);
                }
                continue;
            }

            try {
                // Lazy loading: створюємо провайдер тільки коли потрібно
                /** @var ServiceProviderInterface $provider */
                $provider = new $providerClass();
                $provider->register($this->container);
                $registeredCount++;

                if (function_exists('logDebug')) {
                    logDebug('Kernel::registerProviders: Provider registered', ['provider' => $providerClass]);
                }
            } catch (\Exception $e) {
                if (function_exists('logError')) {
                    logError('Kernel::registerProviders: Failed to register provider', [
                        'provider' => $providerClass,
                        'error' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                }
            }
        }

        if (function_exists('logInfo')) {
            logInfo('Kernel::registerProviders: Providers registered', [
                'total' => count($this->serviceProviders),
                'registered' => $registeredCount,
            ]);
        }
    }

    /**
     * Запуск сервіс-провайдерів
     * Оптимізовано: кешування екземплярів провайдерів
     */
    public function bootProviders(): void
    {
        $providerInstances = [];

        foreach ($this->serviceProviders as $providerClass) {
            if (! class_exists($providerClass)) {
                continue;
            }

            // Кешуємо екземпляри провайдерів для уникнення повторного створення
            if (!isset($providerInstances[$providerClass])) {
                /** @var ServiceProviderInterface $provider */
                $providerInstances[$providerClass] = new $providerClass();
            }

            $providerInstances[$providerClass]->boot($this->container);
        }

        // Запускаємо ModuleManager після провайдерів
        if ($this->container->has(ModuleManager::class)) {
            $moduleManager = $this->container->make(ModuleManager::class);
            if (method_exists($moduleManager, 'boot')) {
                $moduleManager->boot();
            }
        }
    }

    /**
     * Отримання контейнера
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Перевірка, чи ядро ініціалізовано
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Створення автозавантажувача
     */
    protected function createAutoloader(): void
    {
        // Якщо автозавантажувач вже створено (наприклад, в app.php), використовуємо його
        if (isset($GLOBALS['engineAutoloader']) && $GLOBALS['engineAutoloader'] instanceof ClassAutoloader) {
            $this->autoloader = $GLOBALS['engineAutoloader'];

            return;
        }

        $this->autoloader = new ClassAutoloader($this->rootDir);
        $this->autoloader->enableMissingClassLogging(true);

        $this->registerClassMap();
        $this->registerDirectories();

        $this->autoloader->register();
        $GLOBALS['engineAutoloader'] = $this->autoloader;
    }

    /**
     * Створення контейнера залежностей
     * Оптимізовано: використання правильного namespace
     */
    protected function createContainer(): void
    {
        // Якщо контейнер вже створено (наприклад, в app.php), використовуємо його
        if (isset($GLOBALS['engineContainer']) && $GLOBALS['engineContainer'] instanceof ContainerInterface) {
            $this->container = $GLOBALS['engineContainer'];

            return;
        }

        // Використовуємо повний namespace для Container
        $containerClass = 'Flowaxy\Core\System\Container';
        if (class_exists($containerClass)) {
            $this->container = new $containerClass();
        } else {
            // Fallback для зворотної сумісності
            $this->container = new Container();
        }

        $GLOBALS['engineContainer'] = $this->container;
    }

    /**
     * Завантаження environment конфігурації
     */
    protected function loadEnvironmentConfig(): void
    {
        $projectRoot = dirname($this->rootDir);
        $this->environmentLoader = new EnvironmentLoader($projectRoot);

        // Завантажуємо конфігурацію
        $envConfig = $this->environmentLoader->load();

        // Зареєструємо EnvironmentLoader в контейнері для доступу
        $this->container->singleton(EnvironmentLoader::class, fn() => $this->environmentLoader);

        // Зберігаємо environment в контейнері (використовуємо singleton для примітивних типів)
        $environment = $this->environmentLoader->getEnvironment();
        $this->container->singleton('environment', fn() => $environment);
        $this->container->singleton('env.config', fn() => $envConfig);
    }

    /**
     * Завантаження конфігурації сервісів
     */
    protected function loadServiceConfig(): void
    {
        $servicesConfig = ServiceConfig::load(
            $this->rootDir . '/core/config/services.php',
            null // overrides зберігатимуться не в storage/config у вигляді PHP
        );
        ServiceConfig::register($this->container, $servicesConfig);
    }

    /**
     * Завантаження глобальних функцій
     */
    protected function loadGlobalFunctions(): void
    {
        $functionsFile = $this->rootDir . '/Support/functions.php';
        if (file_exists($functionsFile)) {
            require_once $functionsFile;
        }

        // role-functions.php завантажується всередині functions.php

        if (function_exists('loadDatabaseConfig')) {
            loadDatabaseConfig();
        }
    }

    /**
     * Отримання списку сервіс-провайдерів для реєстрації
     * Потрібно перевизначити в нащадках
     *
     * @return array<string> Масив класів провайдерів
     */
    abstract protected function getServiceProviders(): array;

    /**
     * Реєстрація class map в автозавантажувачі
     * Оптимізовано: спроба завантажити згенерований class map
     */
    protected function registerClassMap(): void
    {
        // Спочатку намагаємося завантажити згенерований class map
        $classMapFile = dirname($this->rootDir) . '/storage/cache/classmap.php';
        if (file_exists($classMapFile)) {
            $generatedMap = require $classMapFile;
            if (is_array($generatedMap)) {
                $this->autoloader->addClassMap($generatedMap);
            }
        }

        // Додаємо додатковий class map з getClassMap() (для класів, які не в згенерованому map)
        $additionalClassMap = $this->getClassMap();
        if (!empty($additionalClassMap)) {
            $this->autoloader->addClassMap($additionalClassMap);
        }
    }

    /**
     * Реєстрація директорій в автозавантажувачі
     */
    protected function registerDirectories(): void
    {
        $directories = $this->getDirectories();
        $this->autoloader->addDirectories($directories);
    }

    /**
     * Отримання class map для автозавантажувача
     * Можна перевизначити в нащадках
     *
     * @return array<string, string>
     */
    protected function getClassMap(): array
    {
        return [];
    }

    /**
     * Отримання списку директорій для автозавантажувача
     * Можна перевизначити в нащадках
     *
     * @return array<string>
     */
    protected function getDirectories(): array
    {
        return [];
    }
}
