<?php

/**
 * Базовий клас ядра системи Flowaxy CMS
 * Реалізує спільну логіку для HttpKernel та CliKernel
 *
 * @package Engine\Core\System
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../contracts/KernelInterface.php';
require_once __DIR__ . '/../contracts/ContainerInterface.php';
require_once __DIR__ . '/../contracts/ServiceProviderInterface.php';
require_once __DIR__ . '/ClassAutoloader.php';
require_once __DIR__ . '/Container.php';
require_once __DIR__ . '/ServiceConfig.php';
require_once __DIR__ . '/ModuleManager.php';
require_once __DIR__ . '/EnvironmentLoader.php';

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
            return;
        }

        $this->createAutoloader();
        $this->createContainer();

        $this->booted = true;
    }

    /**
     * Налаштування ядра
     */
    public function configure(): void
    {
        if (! $this->isBooted()) {
            $this->boot();
        }

        $this->loadEnvironmentConfig();
        $this->loadServiceConfig();
        $this->loadGlobalFunctions();
    }

    /**
     * Реєстрація сервіс-провайдерів
     */
    public function registerProviders(): void
    {
        if (! $this->isBooted()) {
            $this->boot();
        }

        $this->serviceProviders = $this->getServiceProviders();

        foreach ($this->serviceProviders as $providerClass) {
            if (! class_exists($providerClass)) {
                continue;
            }

            /** @var ServiceProviderInterface $provider */
            $provider = new $providerClass();
            $provider->register($this->container);
        }
    }

    /**
     * Запуск сервіс-провайдерів
     */
    public function bootProviders(): void
    {
        foreach ($this->serviceProviders as $providerClass) {
            if (! class_exists($providerClass)) {
                continue;
            }

            /** @var ServiceProviderInterface $provider */
            $provider = new $providerClass();
            $provider->boot($this->container);
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
     */
    protected function createContainer(): void
    {
        // Якщо контейнер вже створено (наприклад, в app.php), використовуємо його
        if (isset($GLOBALS['engineContainer']) && $GLOBALS['engineContainer'] instanceof ContainerInterface) {
            $this->container = $GLOBALS['engineContainer'];

            return;
        }

        $this->container = new Container();
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
        $functionsFile = $this->rootDir . '/core/support/functions.php';
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
     */
    protected function registerClassMap(): void
    {
        $classMap = $this->getClassMap();
        $this->autoloader->addClassMap($classMap);
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
