<?php

/**
 * Інтерфейс для системи Feature Flags
 *
 * Визначає контракт для управління прапорцями функцій в системі
 *
 * @package Engine\Core\Contracts
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

interface FeatureFlagManagerInterface
{
    /**
     * Перевірка, чи увімкнено feature flag
     *
     * @param string $flagName Назва прапорця
     * @param array<string, mixed> $context Контекст для перевірки (наприклад, ['user_id' => 1])
     * @return bool
     */
    public function isEnabled(string $flagName, array $context = []): bool;

    /**
     * Перевірка, чи вимкнено feature flag
     *
     * @param string $flagName Назва прапорця
     * @param array<string, mixed> $context Контекст для перевірки
     * @return bool
     */
    public function isDisabled(string $flagName, array $context = []): bool;

    /**
     * Отримання значення feature flag з можливістю варіантів
     *
     * @param string $flagName Назва прапорця
     * @param mixed $default Значення за замовчуванням
     * @param array<string, mixed> $context Контекст для перевірки
     * @return mixed
     */
    public function get(string $flagName, mixed $default = false, array $context = []): mixed;

    /**
     * Встановлення значення feature flag
     *
     * @param string $flagName Назва прапорця
     * @param mixed $value Значення (bool або варіант для A/B тестування)
     * @return void
     */
    public function set(string $flagName, mixed $value): void;

    /**
     * Отримання всіх feature flags
     *
     * @return array<string, mixed> Масив всіх прапорців
     */
    public function all(): array;

    /**
     * Очищення кешу feature flags
     *
     * @return void
     */
    public function clearCache(): void;

    /**
     * Перезавантаження feature flags з джерела
     *
     * @return void
     */
    public function reload(): void;
}
