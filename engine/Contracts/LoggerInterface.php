<?php

/**
 * Інтерфейс для системи логування
 *
 * Визначає контракт для всіх реалізацій логування в системі
 * Дозволяє замінювати реалізацію логування без зміни коду, що його використовує
 *
 * @package Flowaxy\Core\Contracts
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Contracts;

interface LoggerInterface
{
    // Рівні логування
    public const int LEVEL_DEBUG = 0;
    public const int LEVEL_INFO = 1;
    public const int LEVEL_WARNING = 2;
    public const int LEVEL_ERROR = 3;
    public const int LEVEL_CRITICAL = 4;

    /**
     * Логування повідомлення з вказаним рівнем
     *
     * @param int $level Рівень логування (константи LEVEL_*)
     * @param string $message Повідомлення для логування
     * @param array<string, mixed> $context Контекст (додаткові дані)
     * @return void
     */
    public function log(int $level, string $message, array $context = []): void;

    /**
     * Логування DEBUG повідомлення
     *
     * @param string $message Повідомлення
     * @param array<string, mixed> $context Контекст
     * @return void
     */
    public function logDebug(string $message, array $context = []): void;

    /**
     * Логування INFO повідомлення
     *
     * @param string $message Повідомлення
     * @param array<string, mixed> $context Контекст
     * @return void
     */
    public function logInfo(string $message, array $context = []): void;

    /**
     * Логування WARNING повідомлення
     *
     * @param string $message Повідомлення
     * @param array<string, mixed> $context Контекст
     * @return void
     */
    public function logWarning(string $message, array $context = []): void;

    /**
     * Логування ERROR повідомлення
     *
     * @param string $message Повідомлення
     * @param array<string, mixed> $context Контекст
     * @return void
     */
    public function logError(string $message, array $context = []): void;

    /**
     * Логування CRITICAL повідомлення
     *
     * @param string $message Повідомлення
     * @param array<string, mixed> $context Контекст
     * @return void
     */
    public function logCritical(string $message, array $context = []): void;

    /**
     * Логування винятку
     *
     * @param \Throwable $exception Виняток для логування
     * @param array<string, mixed> $context Додатковий контекст
     * @return void
     */
    public function logException(\Throwable $exception, array $context = []): void;

    /**
     * Отримання останніх записів логу
     *
     * @param int $lines Кількість рядків для повернення
     * @return array<int, string> Масив рядків логу
     */
    public function getRecentLogs(int $lines = 100): array;

    /**
     * Очищення всіх логів
     *
     * @return bool Успіх операції
     */
    public function clearLogs(): bool;

    /**
     * Отримання статистики логів
     *
     * @return array<string, int|string> Статистика (total_files, total_size, latest_file, latest_size)
     */
    public function getStats(): array;

    /**
     * Перезавантаження налаштувань логування
     *
     * @return void
     */
    public function reloadSettings(): void;

    /**
     * Отримання налаштування логування
     *
     * @param string $key Ключ налаштування
     * @param string $default Значення за замовчуванням
     * @return string Значення налаштування
     */
    public function getSetting(string $key, string $default = ''): string;

    /**
     * Встановлення налаштування логування
     *
     * @param string $key Ключ налаштування
     * @param string $value Значення
     * @return void
     */
    public function setSetting(string $key, string $value): void;
}
