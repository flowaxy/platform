<?php

declare(strict_types=1);

require_once __DIR__ . '/../contracts/ContainerInterface.php';

final class ServiceConfig
{
    public static function load(?string $baseFile = null, ?string $overrideFile = null): array
    {
        $base = self::loadFile($baseFile);
        $override = self::loadFile($overrideFile);

        return array_replace_recursive($base, $override);
    }

    public static function register(ContainerInterface $container, array $config): void
    {
        self::registerGroup($container, $config['singletons'] ?? [], true);
        self::registerGroup($container, $config['bindings'] ?? [], false);
    }

    private static function loadFile(?string $file): array
    {
        if ($file === null || ! is_file($file)) {
            return [];
        }

        $config = require $file;

        return is_array($config) ? $config : [];
    }

    private static function registerGroup(ContainerInterface $container, array $definitions, bool $shared): void
    {
        foreach ($definitions as $abstract => $definition) {
            $normalized = self::normalizeDefinition($definition, (string)$abstract);
            $class = $normalized['class'];
            $arguments = $normalized['arguments'] ?? [];
            $method = $shared ? 'singleton' : 'bind';

            $shouldBindDirectly = $arguments === [] || (string)$abstract === $class;

            if ($shouldBindDirectly) {
                $container->$method($abstract, $class);

                continue;
            }

            $container->$method(
                $abstract,
                static function (ContainerInterface $container) use ($class, $arguments) {
                    return $container->make($class, $arguments);
                }
            );
        }
    }

    private static function normalizeDefinition(mixed $definition, string $abstract): array
    {
        if (is_string($definition)) {
            return ['class' => $definition];
        }

        if (is_array($definition) && isset($definition['class']) && is_string($definition['class'])) {
            $normalized = ['class' => $definition['class']];
            if (isset($definition['arguments']) && is_array($definition['arguments'])) {
                $normalized['arguments'] = $definition['arguments'];
            }

            return $normalized;
        }

        throw new \InvalidArgumentException(sprintf(
            'Service definition for "%s" має бути рядком або масивом з ключем class',
            $abstract
        ));
    }
}
