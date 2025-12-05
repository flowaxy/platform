<?php

/**
 * Менеджер для роботи з сесіями
 * Централізоване управління сесіями з розширеними можливостями
 *
 * @package Engine\Classes\Managers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../../infrastructure/filesystem/contracts/StorageInterface.php';

class SessionManager implements StorageInterface
{
    private static ?self $instance = null;
    private string $prefix = '';
    private bool $initialized = false;

    private function __construct()
    {
        // Переконуємося, що сесія запущена
        if (! Session::isStarted()) {
            Session::start();
        }
        $this->initialized = true;
    }

    /**
     * Отримання екземпляра (Singleton)
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Встановлення префіксу для ключів
     *
     * @param string $prefix Префікс
     * @return void
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    /**
     * Отримання префіксу
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Формування повного ключа з префіксом
     *
     * @param string $key Ключ
     * @return string
     */
    private function getFullKey(string $key): string
    {
        return $this->prefix ? $this->prefix . '.' . $key : $key;
    }

    /**
     * Отримання значення з сесії
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return Session::get($this->getFullKey($key), $default);
    }

    /**
     * Встановлення значення в сесію
     *
     * @param string $key Ключ
     * @param mixed $value Значення
     * @return bool
     */
    public function set(string $key, $value): bool
    {
        Session::set($this->getFullKey($key), $value);

        return true;
    }

    /**
     * Перевірка наявності ключа в сесії
     *
     * @param string $key Ключ
     * @return bool
     */
    public function has(string $key): bool
    {
        return Session::has($this->getFullKey($key));
    }

    /**
     * Видалення значення з сесії
     *
     * @param string $key Ключ
     * @return bool
     */
    public function remove(string $key): bool
    {
        Session::remove($this->getFullKey($key));

        return true;
    }

    /**
     * Отримання всіх даних з сесії
     *
     * @param bool $withPrefix Включити тільки ключі з префіксом
     * @return array<string, mixed>
     */
    public function all(bool $withPrefix = true): array
    {
        $all = Session::all();

        if (! $withPrefix || ! $this->prefix) {
            return $all;
        }

        // Фільтруємо тільки ключі з префіксом
        $result = [];
        $prefixLen = strlen($this->prefix) + 1; // +1 для точки

        foreach ($all as $key => $value) {
            if (str_starts_with($key, $this->prefix . '.')) {
                $resultKey = substr($key, $prefixLen);
                $result[$resultKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Очищення всіх даних з сесії
     *
     * @param bool $withPrefix Очистити тільки ключі з префіксом
     * @return bool
     */
    public function clear(bool $withPrefix = true): bool
    {
        if (! $withPrefix || ! $this->prefix) {
            Session::clear();

            return true;
        }

        // Очищаємо тільки ключі з префіксом
        $all = Session::all();
        $prefix = $this->prefix . '.';
        $prefixLen = strlen($prefix);

        foreach ($all as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                Session::remove($key);
            }
        }

        return true;
    }

    /**
     * Отримання кількох значень за ключами
     *
     * @param array<int, string> $keys Масив ключів
     * @return array<string, mixed>
     */
    public function getMultiple(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    /**
     * Встановлення кількох значень
     *
     * @param array<string, mixed> $values Масив ключ => значення
     * @return bool
     */
    public function setMultiple(array $values): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }

        return true;
    }

    /**
     * Видалення кількох значень
     *
     * @param array<int, string> $keys Масив ключів
     * @return bool
     */
    public function removeMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->remove($key);
        }

        return true;
    }

    /**
     * Отримання Flash повідомлення (читається один раз)
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function flash(string $key, $default = null)
    {
        return Session::flash($this->getFullKey($key), $default);
    }

    /**
     * Встановлення Flash повідомлення
     *
     * @param string $key Ключ
     * @param mixed $value Значение
     * @return void
     */
    public function setFlash(string $key, $value): void
    {
        Session::setFlash($this->getFullKey($key), $value);
    }

    /**
     * Регенерація ID сесії
     *
     * @param bool $deleteOldSession Видалити стару сесію
     * @return bool
     */
    public function regenerate(bool $deleteOldSession = true): bool
    {
        return Session::regenerate($deleteOldSession);
    }

    /**
     * Отримання ID сесії
     *
     * @return string
     */
    public function getId(): string
    {
        return Session::getId();
    }

    /**
     * Знищення сесії
     *
     * @return void
     */
    public function destroy(): void
    {
        Session::destroy();
    }

    /**
     * Отримання значення як JSON (автоматичне декодування)
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function getJson(string $key, $default = null)
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
        }

        return $value;
    }

    /**
     * Встановлення значення як JSON (автоматичне кодування)
     *
     * @param string $key Ключ
     * @param mixed $value Значення (буде закодовано в JSON, якщо це рядок)
     * @return bool
     */
    public function setJson(string $key, $value): bool
    {
        // Если значение уже массив/объект, сохраняем как есть (PHP сессия автоматически сериализует)
        if (is_array($value) || is_object($value)) {
            return $this->set($key, $value);
        }

        // Если это строка, которая похожа на JSON, проверяем и сохраняем
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Это валидный JSON, сохраняем декодированным
                return $this->set($key, $decoded);
            }
        }

        return $this->set($key, $value);
    }

    /**
     * Збільшення числового значення
     *
     * @param string $key Ключ
     * @param int $increment Крок збільшення
     * @return int Нове значення
     */
    public function increment(string $key, int $increment = 1): int
    {
        $current = (int)$this->get($key, 0);
        $newValue = $current + $increment;
        $this->set($key, $newValue);

        return $newValue;
    }

    /**
     * Зменшення числового значення
     *
     * @param string $key Ключ
     * @param int $decrement Крок зменшення
     * @return int Нове значення
     */
    public function decrement(string $key, int $decrement = 1): int
    {
        return $this->increment($key, -$decrement);
    }

    /**
     * Отримання значення та видалення його з сесії (pull)
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function pull(string $key, $default = null)
    {
        $value = $this->get($key, $default);
        $this->remove($key);

        return $value;
    }
}
