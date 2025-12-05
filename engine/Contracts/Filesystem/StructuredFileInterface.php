<?php

/**
 * Інтерфейс для роботи зі структурованими файлами
 *
 * Визначає контракт для роботи з файлами, що містять структуровані дані
 * (JSON, INI, XML, YAML тощо)
 * Розширює FileInterface, додаючи методи для роботи з даними
 *
 * @package Engine\Interfaces
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

interface StructuredFileInterface extends FileInterface
{
    /**
     * Встановлення шляху до файлу
     *
     * @param string $filePath Шлях до файлу
     * @return self
     * @throws Exception Якщо файл існує, але недоступний для читання
     */
    public function setFile(string $filePath): self;

    /**
     * Завантаження даних з файлу
     *
     * @return self
     * @throws Exception Якщо файл не існує або не може бути прочитаний
     */
    public function load(): self;

    /**
     * Отримання даних з файлу
     *
     * @return mixed
     */
    public function getData();

    /**
     * Встановлення даних для запису в файл
     *
     * @param mixed $data Дані
     * @return self
     */
    public function setData($data): self;

    /**
     * Збереження даних в файл
     *
     * @return bool
     * @throws Exception Якщо не вдалося зберегти
     */
    public function save(): bool;

    /**
     * Перевірка наявності завантажених даних
     *
     * @return bool
     */
    public function hasData(): bool;

    /**
     * Отримання значення за ключем
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Встановлення значення за ключем
     *
     * @param string $key Ключ
     * @param mixed $value Значення
     * @return self
     */
    public function set(string $key, $value): self;

    /**
     * Перевірка наявності ключа
     *
     * @param string $key Ключ
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Видалення значення за ключем
     *
     * @param string $key Ключ
     * @return self
     */
    public function remove(string $key): self;

    /**
     * Очищення всіх даних
     *
     * @return self
     */
    public function clear(): self;
}
