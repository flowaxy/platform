<?php

/**
 * Базовий контейнер залежностей ядра.
 *
 * @package Flowaxy\Core\System
 */

declare(strict_types=1);

namespace Flowaxy\Core\System;

require_once __DIR__ . '/../../Contracts/ContainerInterface.php';
require_once __DIR__ . '/ServiceTags.php';

use Flowaxy\Core\Contracts\ContainerInterface;

final class Container implements ContainerInterface
{
    /**
     * @var array<string,array{concrete:callable|string|null,shared:bool,lazy:bool}>
     */
    private array $bindings = [];

    /**
     * @var array<string,object>
     */
    private array $instances = [];

    /**
     * @var array<int,string>
     */
    private array $resolutionStack = [];

    /**
     * Система тегів для сервісів
     */
    private ServiceTags $tags;

    /**
     * @var array<string,string> Аліаси сервісів (alias => abstract)
     */
    private array $aliases = [];

    /**
     * Конструктор контейнера
     */
    public function __construct()
    {
        $this->tags = new ServiceTags();
    }

    /**
     * Реєстрація бінда в контейнері
     *
     * @param string $abstract Абстрактний клас або інтерфейс
     * @param callable|string|null $concrete Конкретна реалізація або замикання
     * @param bool $lazy Відкладене завантаження
     */
    public function bind(string $abstract, callable|string|null $concrete = null, bool $lazy = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        if (function_exists('logDebug')) {
            logDebug('Container::bind: Binding service', [
                'abstract' => $abstract,
                'lazy' => $lazy,
            ]);
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => false,
            'lazy' => $lazy,
        ];

        unset($this->instances[$abstract]);
    }

    /**
     * Реєстрація singleton-бінда (один екземпляр на весь життєвий цикл)
     *
     * @param string $abstract Абстрактний клас або інтерфейс
     * @param callable|string|null $concrete Конкретна реалізація або замикання
     * @param bool $lazy Відкладене завантаження
     */
    public function singleton(string $abstract, callable|string|null $concrete = null, bool $lazy = true): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        if (function_exists('logDebug')) {
            logDebug('Container::singleton: Registering singleton service', [
                'abstract' => $abstract,
                'lazy' => $lazy,
            ]);
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => true,
            'lazy' => $lazy,
        ];

        unset($this->instances[$abstract]);
    }

    /**
     * Реєстрація factory для створення нового екземпляра при кожному виклику
     *
     * @param string $abstract Абстрактний клас або інтерфейс
     * @param callable $factory Фабрика для створення екземплярів
     * @return void
     */
    public function factory(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $factory,
            'shared' => false,
            'lazy' => false,
        ];

        unset($this->instances[$abstract]);
    }

    /**
     * Реєстрація готового екземпляра в контейнері
     *
     * @param string $abstract Абстрактний клас або інтерфейс
     * @param object $instance Готовий екземпляр об'єкта
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Перевірка наявності бінда в контейнері
     *
     * @param string $abstract Абстрактний клас або інтерфейс
     * @return bool True, якщо бінд існує
     */
    public function has(string $abstract): bool
    {
        // Перевіряємо аліаси
        $resolved = $this->resolveAlias($abstract);

        return isset($this->instances[$resolved]) || isset($this->bindings[$resolved]);
    }

    /**
     * Отримання екземпляра з контейнера
     *
     * @param string $abstract Абстрактний клас або інтерфейс
     * @param array<string, mixed> $parameters Додаткові параметри для конструктора
     * @return object
     */
    public function make(string $abstract, array $parameters = [])
    {
        // Резолвимо аліас до оригінальної назви
        $resolved = $this->resolveAlias($abstract);

        if (function_exists('logDebug')) {
            logDebug('Container::make: Resolving service', [
                'abstract' => $abstract,
                'resolved' => $resolved,
            ]);
        }

        // Якщо є готовий екземпляр (singleton), повертаємо його
        if (isset($this->instances[$resolved])) {
            if (function_exists('logDebug')) {
                logDebug('Container::make: Returning existing singleton instance', ['abstract' => $resolved]);
            }
            return $this->instances[$resolved];
        }

        // Отримуємо біндінг або створюємо дефолтний
        $binding = $this->bindings[$resolved] ?? [
            'concrete' => $resolved,
            'shared' => false,
            'lazy' => false,
        ];

        // Якщо це lazy binding і це Closure, обгортаємо його
        if ($binding['lazy'] && $binding['concrete'] instanceof Closure) {
            if (function_exists('logDebug')) {
                logDebug('Container::make: Creating lazy proxy', ['abstract' => $resolved]);
            }
            return $this->createLazyProxy($resolved, $binding);
        }

        try {
            // Звичайне створення об'єкта (може бути factory через callable)
            $object = $this->build($resolved, $binding['concrete'], $parameters);

            // Якщо це singleton (shared), зберігаємо екземпляр
            if ($binding['shared']) {
                $this->instances[$resolved] = $object;
                if (function_exists('logDebug')) {
                    logDebug('Container::make: Stored as singleton instance', ['abstract' => $resolved]);
                }
            }

            if (function_exists('logDebug')) {
                logDebug('Container::make: Service resolved successfully', [
                    'abstract' => $resolved,
                    'class' => get_class($object),
                ]);
            }

            return $object;
        } catch (\Exception $e) {
            if (function_exists('logError')) {
                logError('Container::make: Failed to resolve service', [
                    'abstract' => $resolved,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
            throw $e;
        }
    }

    /**
     * Створення lazy proxy для відкладеного завантаження
     *
     * @param string $abstract Назва сервісу
     * @param array{concrete:callable|string|null,shared:bool,lazy:bool} $binding Біндінг
     * @return object Lazy proxy об'єкт
     */
    private function createLazyProxy(string $abstract, array $binding): object
    {
        // Створюємо Closure, який буде викликатися при першому доступі
        $factory = function () use ($abstract, $binding) {
            static $instance = null;

            if ($instance === null) {
                $instance = $this->build($abstract, $binding['concrete'], []);

                if ($binding['shared']) {
                    $this->instances[$abstract] = $instance;
                }
            }

            return $instance;
        };

        // Створюємо простий proxy об'єкт
        return new class($factory) {
            private $factory;
            private $instance = null;

            public function __construct(callable $factory)
            {
                $this->factory = $factory;
            }

            public function __call(string $method, array $args)
            {
                if ($this->instance === null) {
                    $this->instance = ($this->factory)();
                }

                return $this->instance->$method(...$args);
            }

            public function __get(string $property)
            {
                if ($this->instance === null) {
                    $this->instance = ($this->factory)();
                }

                return $this->instance->$property;
            }

            public function __set(string $property, $value): void
            {
                if ($this->instance === null) {
                    $this->instance = ($this->factory)();
                }

                $this->instance->$property = $value;
            }
        };
    }

    /**
     * Виклик callable з автоматичною інжекцією залежностей
     *
     * @param callable $callback Функція або метод для виклику
     * @param array<string, mixed> $parameters Додаткові параметри
     * @return mixed Результат виклику
     */
    public function call(callable $callback, array $parameters = [])
    {
        $reflection = is_array($callback)
            ? new ReflectionMethod($callback[0], $callback[1])
            : new ReflectionFunction(Closure::fromCallable($callback));

        $dependencies = $this->resolveParameters($reflection->getParameters(), $parameters);

        return $callback(...$dependencies);
    }

    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->resolutionStack = [];
        $this->tags->flush();
        $this->aliases = [];
    }

    /**
     * Додавання тегу до сервісу
     *
     * @param string $abstract Назва сервісу
     * @param string|array<string> $tags Тег або масив тегів
     * @return void
     */
    public function tag(string $abstract, string|array $tags): void
    {
        $this->tags->tag($abstract, $tags);
    }

    /**
     * Отримання всіх сервісів з вказаним тегом
     *
     * @param string $tag Тег
     * @return array<object> Масив екземплярів сервісів
     */
    public function getTagged(string $tag): array
    {
        $serviceNames = $this->tags->getTagged($tag);
        $services = [];

        foreach ($serviceNames as $serviceName) {
            if ($this->has($serviceName)) {
                $services[] = $this->make($serviceName);
            }
        }

        return $services;
    }

    /**
     * Отримання системи тегів
     *
     * @return ServiceTags
     */
    public function getTags(): ServiceTags
    {
        return $this->tags;
    }

    /**
     * Створення аліасу для сервісу
     *
     * @param string $abstract Абстрактний клас або інтерфейс
     * @param string $alias Аліас
     * @return void
     */
    public function alias(string $abstract, string $alias): void
    {
        if ($alias === $abstract) {
            throw new InvalidArgumentException('Аліас не може бути таким самим як назва сервісу');
        }

        // Перевіряємо на циклічні залежності
        $this->checkAliasCircularDependency($abstract, $alias);

        $this->aliases[$alias] = $abstract;
    }

    /**
     * Перевірка циклічних залежностей в аліасах
     *
     * @param string $abstract Абстрактний клас
     * @param string $alias Аліас
     * @return void
     * @throws RuntimeException
     */
    private function checkAliasCircularDependency(string $abstract, string $alias): void
    {
        $visited = [];
        $current = $alias;

        while (isset($this->aliases[$current])) {
            if (in_array($current, $visited, true)) {
                throw new RuntimeException("Циклічна залежність виявлена в аліасах: {$alias} -> {$abstract}");
            }

            $visited[] = $current;
            $current = $this->aliases[$current];

            if ($current === $abstract) {
                throw new RuntimeException("Циклічна залежність виявлена в аліасах: {$alias} -> {$abstract}");
            }
        }
    }

    /**
     * Отримання оригінальної назви сервісу через аліас
     *
     * @param string $alias Аліас
     * @return string Оригінальна назва сервісу
     */
    private function resolveAlias(string $alias): string
    {
        $resolved = $alias;
        $visited = [];

        while (isset($this->aliases[$resolved])) {
            if (in_array($resolved, $visited, true)) {
                throw new RuntimeException("Циклічна залежність в аліасах: {$alias}");
            }

            $visited[] = $resolved;
            $resolved = $this->aliases[$resolved];
        }

        return $resolved;
    }

    /**
     * @param callable|string|null $concrete
     */
    private function build(string $abstract, callable|string|null $concrete, array $parameters)
    {
        if (in_array($abstract, $this->resolutionStack, true)) {
            $circularPath = implode(' -> ', array_merge($this->resolutionStack, [$abstract]));
            if (function_exists('logError')) {
                logError('Container::build: Circular dependency detected', [
                    'abstract' => $abstract,
                    'circular_path' => $circularPath,
                ]);
            }
            throw new RuntimeException('Circular dependency detected while resolving ' . $abstract . '. Path: ' . $circularPath);
        }

        if (function_exists('logDebug')) {
            logDebug('Container::build: Building service', [
                'abstract' => $abstract,
                'concrete' => is_string($concrete) ? $concrete : (is_object($concrete) ? get_class($concrete) : 'callable'),
            ]);
        }

        $this->resolutionStack[] = $abstract;

        try {
            if ($concrete instanceof Closure || (is_string($concrete) && ! class_exists($concrete))) {
                /** @var callable $callable */
                $callable = $concrete instanceof Closure ? $concrete : $this->wrapCallable($concrete);
                $object = $callable($this, $parameters);
                array_pop($this->resolutionStack);

                return $object;
            }

            if (is_object($concrete) && ! $concrete instanceof Closure) {
                array_pop($this->resolutionStack);

                return $concrete;
            }

            $className = is_string($concrete) ? $concrete : $abstract;

            if (! class_exists($className)) {
                array_pop($this->resolutionStack);

                if (function_exists('logError')) {
                    logError('Container::build: Class not found', [
                        'abstract' => $abstract,
                        'className' => $className,
                    ]);
                }

                throw new RuntimeException("Class {$className} не знайдено при резолві {$abstract}");
            }

            $reflection = new ReflectionClass($className);

            if (! $reflection->isInstantiable()) {
                array_pop($this->resolutionStack);

                if (function_exists('logWarning')) {
                    logWarning('Container::build: Class is not instantiable', [
                        'abstract' => $abstract,
                        'className' => $className,
                    ]);
                }

                throw new RuntimeException("Клас {$className} неможливо інстанціювати");
            }

            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                array_pop($this->resolutionStack);

                return new $className();
            }

            $dependencies = $this->resolveParameters($constructor->getParameters(), $parameters);
            $instance = $reflection->newInstanceArgs($dependencies);
            array_pop($this->resolutionStack);

            return $instance;
        } catch (Throwable $e) {
            array_pop($this->resolutionStack);

            throw $e;
        }
    }

    /**
     * @param ReflectionParameter[] $parameters
     */
    private function resolveParameters(array $parameters, array $overrides): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $overrides)) {
                $dependencies[] = $overrides[$name];

                continue;
            }

            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();

                continue;
            }

            if ($parameter->allowsNull()) {
                $dependencies[] = null;

                continue;
            }

            throw new RuntimeException(sprintf(
                'Неможливо вирішити параметр $%s',
                $name
            ));
        }

        return $dependencies;
    }

    /**
     * @return Closure(mixed...$args):mixed
     */
    private function wrapCallable(string $concrete): Closure
    {
        return function (ContainerInterface $container, array $parameters = []) use ($concrete) {
            if (function_exists($concrete)) {
                return $container->call($concrete, $parameters);
            }

            if (strpos($concrete, '::') !== false) {
                return $container->call(explode('::', $concrete, 2), $parameters);
            }

            return $container->make($concrete, $parameters);
        };
    }
}
