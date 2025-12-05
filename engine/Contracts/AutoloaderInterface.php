<?php

/**
 * Інтерфейс автозавантажувача класів
 *
 * @package Flowaxy\Core\Contracts
 */

declare(strict_types=1);

namespace Flowaxy\Core\Contracts;

interface AutoloaderInterface
{
    /**
     * Реєструє автозавантажувач у SPL.
     *
     * @param bool $prepend Додавати на початок черги.
     */
    public function register(bool $prepend = false): void;

    /**
     * Відв'язує автозавантажувач.
     */
    public function unregister(): void;

    /**
     * Передає екземпляр логера.
     */
    public function setLogger(?LoggerInterface $logger): void;

    /**
     * Вмикає або вимикає логування відсутніх класів/файлів.
     */
    public function enableMissingClassLogging(bool $enabled = true): void;

    /**
     * Додає статичну мапу класів.
     *
     * @param array<string,string> $classMap Ключ — назва класу, значення — абсолютний шлях до файлу.
     */
    public function addClassMap(array $classMap): void;

    /**
     * Додає директорію у пошук.
     */
    public function addDirectory(string $directory): void;

    /**
     * Додає одразу декілька директорій.
     *
     * @param string[] $directories
     */
    public function addDirectories(array $directories): void;

    /**
     * Завантажує клас вручну.
     *
     * @return bool true, якщо файл знайдено та підключено.
     */
    public function loadClass(string $className): bool;

    /**
     * Повертає статистику роботи автозавантажувача.
     *
     * @return array<string,int>
     */
    public function getStats(): array;
}
