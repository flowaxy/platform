<?php

/**
 * Реєстр хуків з метаданими
 *
 * Зберігає метадані про хуки: опис, версію, залежності
 *
 * @package Engine\System\Hooks
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Hooks;

// HookType вже має namespace, тому просто require_once
if (file_exists(__DIR__ . '/HookType.php')) {
    require_once __DIR__ . '/HookType.php';
}

final class HookRegistry
{
    /**
     * @var array<string, array{
     *     description: string,
     *     version: string,
     *     dependencies: array<string>,
     *     type: HookType,
     *     registered_at: int
     * }>
     */
    private array $metadata = [];

    /**
     * Реєстрація хука з метаданими
     *
     * @param string $hookName Назва хука
     * @param HookType $type Тип хука
     * @param string $description Опис хука
     * @param string $version Версія хука
     * @param array<string> $dependencies Залежності від інших хуків
     * @return void
     */
    public function register(
        string $hookName,
        HookType $type,
        string $description = '',
        string $version = '1.0.0',
        array $dependencies = []
    ): void {
        if (function_exists('logDebug')) {
            logDebug('HookRegistry::register: Registering hook with metadata', [
                'hook' => $hookName,
                'type' => $type->value,
                'version' => $version,
                'dependencies' => $dependencies,
            ]);
        }

        if (!isset($this->metadata[$hookName])) {
            $this->metadata[$hookName] = [
                'description' => $description,
                'version' => $version,
                'dependencies' => $dependencies,
                'type' => $type,
                'registered_at' => time(),
            ];

            if (function_exists('logInfo')) {
                logInfo('HookRegistry::register: Hook registered with metadata', [
                    'hook' => $hookName,
                    'type' => $type->value,
                ]);
            }
        } else {
            // Оновлюємо метадані, якщо хук вже зареєстровано
            if (function_exists('logDebug')) {
                logDebug('HookRegistry::register: Updating existing hook metadata', [
                    'hook' => $hookName,
                ]);
            }
            $this->metadata[$hookName]['description'] = $description ?: $this->metadata[$hookName]['description'];
            $this->metadata[$hookName]['version'] = $version;
            $this->metadata[$hookName]['dependencies'] = array_unique(
                array_merge($this->metadata[$hookName]['dependencies'], $dependencies)
            );
        }
    }

    /**
     * Отримання метаданих хука
     *
     * @param string $hookName Назва хука
     * @return array<string, mixed>|null Метадані або null якщо хук не знайдено
     */
    public function getMetadata(string $hookName): ?array
    {
        return $this->metadata[$hookName] ?? null;
    }

    /**
     * Отримання залежностей хука
     *
     * @param string $hookName Назва хука
     * @return array<string> Масив назв залежних хуків
     */
    public function getDependencies(string $hookName): array
    {
        return $this->metadata[$hookName]['dependencies'] ?? [];
    }

    /**
     * Перевірка наявності хука в реєстрі
     *
     * @param string $hookName Назва хука
     * @return bool
     */
    public function has(string $hookName): bool
    {
        return isset($this->metadata[$hookName]);
    }

    /**
     * Отримання всіх зареєстрованих хуків
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAll(): array
    {
        return $this->metadata;
    }

    /**
     * Отримання хуків за типом
     *
     * @param HookType $type Тип хука
     * @return array<string, array<string, mixed>>
     */
    public function getByType(HookType $type): array
    {
        return array_filter(
            $this->metadata,
            fn(array $meta) => $meta['type'] === $type
        );
    }

    /**
     * Очищення реєстру
     *
     * @return void
     */
    public function flush(): void
    {
        $this->metadata = [];
    }

    /**
     * Видалення хука з реєстру
     *
     * @param string $hookName Назва хука
     * @return void
     */
    public function remove(string $hookName): void
    {
        unset($this->metadata[$hookName]);
    }
}
