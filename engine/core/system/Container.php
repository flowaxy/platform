<?php

/**
 * Базовий контейнер залежностей ядра.
 *
 * @package Engine\System
 */

declare(strict_types=1);

require_once __DIR__ . '/../contracts/ContainerInterface.php';

final class Container implements ContainerInterface
{
    /**
     * @var array<string,array{concrete:callable|string|null,shared:bool}>
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
     * Реєстрація бінда в контейнері
     *
     * @param string $abstract Абстрактний клас або інтерфейс
     * @param callable|string|null $concrete Конкретна реалізація або замикання
     */
    public function bind(string $abstract, callable|string|null $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => false,
        ];

        unset($this->instances[$abstract]);
    }

    /**
     * Реєстрація singleton-бінда (один екземпляр на весь життєвий цикл)
     *
     * @param string $abstract Абстрактний клас або інтерфейс
     * @param callable|string|null $concrete Конкретна реалізація або замикання
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => true,
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
        return isset($this->instances[$abstract]) || isset($this->bindings[$abstract]);
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
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $binding = $this->bindings[$abstract] ?? [
            'concrete' => $abstract,
            'shared' => false,
        ];

        $object = $this->build($abstract, $binding['concrete'], $parameters);

        if ($binding['shared']) {
            $this->instances[$abstract] = $object;
        }

        return $object;
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
    }

    /**
     * @param callable|string|null $concrete
     */
    private function build(string $abstract, callable|string|null $concrete, array $parameters)
    {
        if (in_array($abstract, $this->resolutionStack, true)) {
            throw new RuntimeException('Circular dependency detected while resolving ' . $abstract);
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

                throw new RuntimeException("Class {$className} не знайдено при резолві {$abstract}");
            }

            $reflection = new ReflectionClass($className);

            if (! $reflection->isInstantiable()) {
                array_pop($this->resolutionStack);

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
