<?php

/**
 * Middleware для перевірки ізоляції плагінів
 *
 * Перевіряє всі виклики з плагінів та блокує недозволені операції.
 *
 * @package Flowaxy\Core\Support\Isolation
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Support\Isolation;

use Flowaxy\Core\System\Hooks\HookMiddlewareInterface;

final class IsolationMiddleware implements HookMiddlewareInterface
{
    /**
     * @var string|null Slug поточного плагіна
     */
    private ?string $currentPluginSlug = null;

    /**
     * Встановлення поточного плагіна
     *
     * @param string $pluginSlug
     * @return void
     */
    public function setCurrentPlugin(string $pluginSlug): void
    {
        $this->currentPluginSlug = $pluginSlug;
    }

    /**
     * Обробка хука з перевіркою ізоляції
     *
     * @param string $hookName Назва хука
     * @param array<int, mixed> $payload Дані хука
     * @return array<int, mixed> Оброблені дані
     */
    public function handle(string $hookName, array $payload): array
    {
        if ($this->currentPluginSlug === null) {
            return $payload;
        }

        // Перевіряємо, чи не намагається плагін передати небезпечні дані
        $payload = $this->sanitizePayload($payload);

        return $payload;
    }

    /**
     * Очищення payload від небезпечних даних
     *
     * @param array<int, mixed> $payload
     * @return array<int, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $item) {
            // Перевіряємо, чи не передаються шляхи до ядра/тем
            if (is_string($item)) {
                if (PluginIsolation::isEnginePath($item) || PluginIsolation::isThemePath($item)) {
                    // Блокуємо передачу шляхів до ядра/тем
                    continue;
                }
            }

            // Рекурсивно обробляємо масиви
            if (is_array($item)) {
                $item = $this->sanitizePayload($item);
            }

            $sanitized[] = $item;
        }

        return $sanitized;
    }

    /**
     * Отримання пріоритету middleware
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 100; // Високий пріоритет для безпеки
    }
}
