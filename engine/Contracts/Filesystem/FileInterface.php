<?php

/**
 * Інтерфейс для роботи з файлами
 *
 * Визначає контракт для всіх реалізацій роботи з файлами
 * Дозволяє замінювати реалізацію роботи з файлами без зміни коду, що його використовує
 *
 * @package Engine\Interfaces
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

interface FileInterface
{
    /**
     * Встановлення шляху до файлу
     *
     * @param string $filePath Шлях до файлу
     * @return self
     */
    public function setPath(string $filePath): self;

    /**
     * Отримання шляху до файлу
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Перевірка існування файлу
     *
     * @return bool
     */
    public function exists(): bool;

    /**
     * Читання вмісту файлу
     *
     * @return string
     * @throws Exception Якщо файл не існує або не може бути прочитаний
     */
    public function read(): string;

    /**
     * Запис вмісту в файл
     *
     * @param string $content Вміст для запису
     * @param bool $append Додавати в кінець файлу (false = перезаписати)
     * @return bool
     * @throws Exception Якщо не вдалося записати файл
     */
    public function write(string $content, bool $append = false): bool;

    /**
     * Копіювання файлу
     *
     * @param string $destinationPath Шлях призначення
     * @return bool
     * @throws Exception Якщо не вдалося скопіювати
     */
    public function copy(string $destinationPath): bool;

    /**
     * Переміщення/перейменування файлу
     *
     * @param string $destinationPath Шлях призначення
     * @return bool
     * @throws Exception Якщо не вдалося перемістити
     */
    public function move(string $destinationPath): bool;

    /**
     * Видалення файлу
     *
     * @return bool
     * @throws Exception Якщо не вдалося видалити
     */
    public function delete(): bool;

    /**
     * Отримання розміру файлу
     *
     * @return int Розмір в байтах
     */
    public function getSize(): int;

    /**
     * Отримання MIME типу файлу
     *
     * @return string|false
     */
    public function getMimeType();

    /**
     * Отримання часу останньої зміни
     *
     * @return int|false Unix timestamp
     */
    public function getMTime();

    /**
     * Отримання розширення файлу
     *
     * @return string
     */
    public function getExtension(): string;

    /**
     * Отримання імені файлу з розширенням
     *
     * @return string
     */
    public function getBasename(): string;

    /**
     * Отримання імені файлу без шляху та розширення
     *
     * @return string
     */
    public function getFilename(): string;

    /**
     * Перевірка доступності файлу для читання
     *
     * @return bool
     */
    public function isReadable(): bool;

    /**
     * Перевірка доступності файлу для запису
     *
     * @return bool
     */
    public function isWritable(): bool;
}
