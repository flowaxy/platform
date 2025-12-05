<?php

/**
 * Оптимізатор автозавантаження класів
 *
 * Забезпечує оптимізацію автозавантаження через OPcache preloading та інші техніки.
 *
 * @package Flowaxy\Core\System
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\System;

final class AutoloaderOptimizer
{
    /**
     * @var bool Чи доступний OPcache
     */
    private static bool $opcacheAvailable = false;

    /**
     * @var bool Чи доступний OPcache preload
     */
    private static bool $preloadAvailable = false;

    /**
     * Ініціалізація оптимізатора
     *
     * @return void
     */
    public static function initialize(): void
    {
        self::$opcacheAvailable = function_exists('opcache_get_status') && opcache_get_status() !== false;
        self::$preloadAvailable = function_exists('opcache_compile_file') && ini_get('opcache.preload') !== '';
    }

    /**
     * Перевірка доступності OPcache
     *
     * @return bool
     */
    public static function isOpcacheAvailable(): bool
    {
        return self::$opcacheAvailable;
    }

    /**
     * Перевірка доступності OPcache preload
     *
     * @return bool
     */
    public static function isPreloadAvailable(): bool
    {
        return self::$preloadAvailable;
    }

    /**
     * Попереднє завантаження файлів через OPcache
     *
     * @param array<string> $files Список файлів для preload
     * @return array<string, bool> Результати preload (файл => успіх)
     */
    public static function preloadFiles(array $files): array
    {
        if (!self::$opcacheAvailable || !function_exists('opcache_compile_file')) {
            return [];
        }

        $results = [];
        foreach ($files as $file) {
            if (file_exists($file) && is_readable($file)) {
                $results[$file] = @opcache_compile_file($file);
            } else {
                $results[$file] = false;
            }
        }

        return $results;
    }

    /**
     * Попереднє завантаження класів з class map
     *
     * @param array<string, string> $classMap Class map (клас => файл)
     * @param int $limit Максимальна кількість файлів для preload (0 = без обмежень)
     * @return array<string, bool> Результати preload
     */
    public static function preloadClassMap(array $classMap, int $limit = 0): array
    {
        if (!self::$opcacheAvailable) {
            return [];
        }

        $files = array_values($classMap);

        if ($limit > 0 && count($files) > $limit) {
            // Пріоритизуємо критичні класи
            $priorityClasses = [
                'Flowaxy\\Core\\System\\Kernel',
                'Flowaxy\\Core\\System\\HttpKernel',
                'Flowaxy\\Core\\System\\CliKernel',
                'Flowaxy\\Core\\System\\Container',
                'Flowaxy\\Core\\System\\HookManager',
                'Flowaxy\\Core\\System\\EventDispatcher',
            ];

            $priorityFiles = [];
            $otherFiles = [];

            foreach ($classMap as $class => $file) {
                if (in_array($class, $priorityClasses, true)) {
                    $priorityFiles[] = $file;
                } else {
                    $otherFiles[] = $file;
                }
            }

            $files = array_merge($priorityFiles, array_slice($otherFiles, 0, $limit - count($priorityFiles)));
        }

        return self::preloadFiles($files);
    }

    /**
     * Отримання статистики OPcache
     *
     * @return array<string, mixed>|null
     */
    public static function getOpcacheStats(): ?array
    {
        if (!self::$opcacheAvailable || !function_exists('opcache_get_status')) {
            return null;
        }

        $status = opcache_get_status(false);
        if ($status === false) {
            return null;
        }

        return [
            'enabled' => $status['opcache_enabled'] ?? false,
            'cache_full' => $status['cache_full'] ?? false,
            'memory_usage' => $status['memory_usage'] ?? [],
            'opcache_statistics' => $status['opcache_statistics'] ?? [],
            'preload_statistics' => $status['preload_statistics'] ?? null,
        ];
    }

    /**
     * Отримання списку завантажених скриптів з OPcache
     *
     * @return array<string>|null
     */
    public static function getCachedScripts(): ?array
    {
        if (!self::$opcacheAvailable || !function_exists('opcache_get_status')) {
            return null;
        }

        $status = opcache_get_status(true);
        if ($status === false || !isset($status['scripts'])) {
            return null;
        }

        return array_keys($status['scripts']);
    }

    /**
     * Очищення OPcache
     *
     * @return bool
     */
    public static function clearOpcache(): bool
    {
        if (!self::$opcacheAvailable || !function_exists('opcache_reset')) {
            return false;
        }

        return opcache_reset();
    }

    /**
     * Отримання рекомендацій щодо оптимізації
     *
     * @return array<string, mixed>
     */
    public static function getOptimizationRecommendations(): array
    {
        $recommendations = [];

        if (!self::$opcacheAvailable) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'OPcache не увімкнено. Рекомендується увімкнути OPcache для покращення продуктивності.',
            ];
        }

        if (self::$opcacheAvailable && !self::$preloadAvailable) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'OPcache preload не налаштовано. Розгляньте можливість налаштування preload для критичних класів.',
            ];
        }

        $stats = self::getOpcacheStats();
        if ($stats !== null && ($stats['cache_full'] ?? false)) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'OPcache cache повний. Розгляньте можливість збільшення opcache.memory_consumption.',
            ];
        }

        return $recommendations;
    }
}
