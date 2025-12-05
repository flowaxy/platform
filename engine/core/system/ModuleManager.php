<?php

/**
 * Менеджер модулів з підтримкою lazy loading та кешування
 *
 * @package Flowaxy\Core\System
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\System;

require_once __DIR__ . '/../../Contracts/ComponentRegistryInterface.php';
require_once __DIR__ . '/../../Contracts/ContainerInterface.php';
require_once __DIR__ . '/../../Contracts/ServiceProviderInterface.php';

use Flowaxy\Core\Contracts\ComponentRegistryInterface;
use Flowaxy\Core\Contracts\ContainerInterface;
use Flowaxy\Core\Contracts\ServiceProviderInterface;

final class ModuleManager
{
    /**
     * @var array<string,string>
     */
    private array $providers = [];

    /**
     * @var array<string,array<string,mixed>>
     */
    private array $moduleConfigs = [];

    /**
     * @var ModuleServiceProviderInterface[]
     */
    private array $instances = [];

    private bool $booted = false;

    /**
     * Lazy завантажувач модулів
     */
    private ?LazyModuleLoader $lazyLoader = null;

    /**
     * Кеш модулів
     */
    private ?ModuleCache $moduleCache = null;

    /**
     * Чи використовувати lazy loading
     */
    private bool $useLazyLoading = true;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ComponentRegistryInterface $registry
    ) {
        // Ініціалізуємо lazy loader та кеш
        $this->lazyLoader = new LazyModuleLoader();

        // Спробуємо отримати Cache з контейнера або створити новий
        $cache = null;
        if ($container->has('Flowaxy\Core\Infrastructure\Cache\Cache')) {
            $cache = $container->make('Flowaxy\Core\Infrastructure\Cache\Cache');
        } elseif (class_exists('Flowaxy\Core\Infrastructure\Cache\Cache')) {
            $cache = \Flowaxy\Core\Infrastructure\Cache\Cache::getInstance();
        }

        if ($cache !== null) {
            $this->moduleCache = new ModuleCache($cache);
        }

        $this->loadConfiguredModules();
    }

    public function register(string $moduleName, string $providerClass, array $config = []): void
    {
        $this->providers[$moduleName] = $providerClass;
        $this->moduleConfigs[$moduleName] = array_merge([
            'provider' => $providerClass,
            'dependencies' => [],
            'enabled' => true,
            'priority' => 10,
        ], $config);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Валідація залежностей перед завантаженням
        $this->validateDependencies();

        // Сортування модулів за пріоритетом та залежностями
        // Спробуємо отримати з кешу
        $sortedModules = null;
        if ($this->moduleCache !== null) {
            $sortedModules = $this->moduleCache->getSortedModules();
        }

        if ($sortedModules === null) {
            $sortedModules = $this->sortModulesByDependencies();

            // Зберігаємо в кеш
            if ($this->moduleCache !== null) {
                $this->moduleCache->setSortedModules($sortedModules);
            }
        }

        foreach ($sortedModules as $moduleName) {
            if (!isset($this->moduleConfigs[$moduleName]['enabled']) || !$this->moduleConfigs[$moduleName]['enabled']) {
                continue;
            }

            // Використовуємо lazy loading, якщо увімкнено
            if ($this->useLazyLoading && $this->lazyLoader !== null) {
                // Реєструємо loader для модуля
                $providerClass = $this->providers[$moduleName];
                $this->lazyLoader->registerLoader($moduleName, function () use ($providerClass) {
                    return $this->container->make($providerClass);
                });

                // Завантажуємо модуль
                $provider = $this->lazyLoader->load($moduleName);
            } else {
                // Старий спосіб завантаження
                if (class_exists('ModuleLoader') && method_exists('ModuleLoader', 'loadModule')) {
                    ModuleLoader::loadModule($moduleName);
                }

                $providerClass = $this->providers[$moduleName];
                $provider = $this->container->make($providerClass);
            }

            if ($provider instanceof ServiceProviderInterface) {
                $provider->register($this->container);
                $this->instances[] = $provider;
            }
        }

        foreach ($this->instances as $provider) {
            $provider->boot($this->container);
        }

        $this->booted = true;
    }

    /**
     * Валідація залежностей між модулями
     *
     * @return void
     * @throws RuntimeException
     */
    private function validateDependencies(): void
    {
        foreach ($this->moduleConfigs as $moduleName => $config) {
            $dependencies = $config['dependencies'] ?? [];

            foreach ($dependencies as $dependency) {
                if (!isset($this->providers[$dependency])) {
                    throw new RuntimeException("Module '{$moduleName}' requires '{$dependency}' but it is not registered");
                }
            }
        }
    }

    /**
     * Сортування модулів за залежностями (топологічне сортування)
     *
     * @return array<string>
     */
    private function sortModulesByDependencies(): array
    {
        $sorted = [];
        $visited = [];
        $visiting = [];

        foreach (array_keys($this->providers) as $module) {
            if (!isset($visited[$module])) {
                $this->visitModule($module, $visited, $visiting, $sorted);
            }
        }

        return $sorted;
    }

    /**
     * Відвідування модуля для топологічного сортування
     *
     * @param string $module Назва модуля
     * @param array<string,bool> $visited Відвідані модулі
     * @param array<string,bool> $visiting Модулі в процесі відвідування
     * @param array<string> $sorted Відсортований список
     * @return void
     * @throws RuntimeException
     */
    private function visitModule(string $module, array &$visited, array &$visiting, array &$sorted): void
    {
        if (isset($visiting[$module])) {
            throw new RuntimeException("Circular dependency detected involving module '{$module}'");
        }

        if (isset($visited[$module])) {
            return;
        }

        $visiting[$module] = true;
        $dependencies = $this->moduleConfigs[$module]['dependencies'] ?? [];

        foreach ($dependencies as $dependency) {
            $this->visitModule($dependency, $visited, $visiting, $sorted);
        }

        unset($visiting[$module]);
        $visited[$module] = true;
        $sorted[] = $module;
    }

    /**
     * @return array<string,string>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    private function loadConfiguredModules(): void
    {
        // Спробуємо завантажити з кешу
        if ($this->moduleCache !== null) {
            $cachedConfigs = $this->moduleCache->getAllConfigs();
            if (!empty($cachedConfigs)) {
                foreach ($cachedConfigs as $moduleName => $moduleConfig) {
                    $providerClass = $moduleConfig['provider'] ?? null;
                    if ($providerClass) {
                        $this->providers[$moduleName] = $providerClass;
                        $this->moduleConfigs[$moduleName] = $moduleConfig;
                    }
                }
                return; // Використовуємо кешовані конфігурації
            }
        }

        $configDir = dirname(__DIR__) . '/config';

        // Спробуємо завантажити з різних форматів
        $configs = [
            $configDir . '/modules.php',
            $configDir . '/modules.json',
            $configDir . '/modules.yaml',
            $configDir . '/modules.yml',
        ];

        $allConfigs = [];

        foreach ($configs as $configPath) {
            if (!file_exists($configPath)) {
                continue;
            }

            $modules = $this->loadConfigFile($configPath);
            if ($modules === null) {
                continue;
            }

            foreach ($modules as $moduleName => $moduleConfig) {
                if (is_string($moduleConfig)) {
                    // Старий формат: 'ModuleName' => 'ProviderClass'
                    $this->register($moduleName, $moduleConfig);
                    $allConfigs[$moduleName] = [
                        'provider' => $moduleConfig,
                        'dependencies' => [],
                        'enabled' => true,
                        'priority' => 10,
                    ];
                } elseif (is_array($moduleConfig)) {
                    // Новий формат з конфігурацією
                    $providerClass = $moduleConfig['provider'] ?? $moduleConfig['class'] ?? null;
                    if ($providerClass) {
                        $this->register($moduleName, $providerClass, $moduleConfig);
                        $allConfigs[$moduleName] = $moduleConfig;
                    }
                }
            }

            // Зберігаємо в кеш
            if ($this->moduleCache !== null && !empty($allConfigs)) {
                $this->moduleCache->setAllConfigs($allConfigs);
            }

            break; // Використовуємо перший знайдений файл
        }
    }

    /**
     * Завантаження конфігураційного файлу
     *
     * @param string $path Шлях до файлу
     * @return array<string,mixed>|null
     */
    private function loadConfigFile(string $path): ?array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'php' => require $path,
            'json' => json_decode(file_get_contents($path), true),
            'yaml', 'yml' => $this->loadYaml($path),
            default => null,
        };
    }

    /**
     * Завантаження YAML файлу
     *
     * @param string $path Шлях до файлу
     * @return array<string,mixed>|null
     */
    private function loadYaml(string $path): ?array
    {
        if (!function_exists('yaml_parse_file')) {
            // Якщо YAML розширення недоступне, спробуємо простий парсер
            return $this->parseSimpleYaml(file_get_contents($path));
        }

        return yaml_parse_file($path) ?: null;
    }

    /**
     * Простий парсер YAML (базова реалізація)
     *
     * @param string $content Вміст файлу
     * @return array<string,mixed>|null
     */
    private function parseSimpleYaml(string $content): ?array
    {
        // Спрощена реалізація - в реальності потрібна більш повна підтримка YAML
        // Тут просто повертаємо null, щоб використовувати PHP/JSON формати
        return null;
    }

    /**
     * Отримання конфігурації модуля
     *
     * @param string $moduleName Назва модуля
     * @return array<string,mixed>|null
     */
    public function getModuleConfig(string $moduleName): ?array
    {
        return $this->moduleConfigs[$moduleName] ?? null;
    }
}
