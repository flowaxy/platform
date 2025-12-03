<?php

/**
 * Базовий клас для системних модулів
 *
 * @package Engine
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

abstract class BaseModule
{
    protected ?PDO $db = null;
    /**
     * @var array<string, static>
     */
    protected static array $instances = [];
    private static bool $initializing = false;

    /**
     * Конструктор
     * Важливо: БД завантажується ліниво, щоб уникнути циклічних залежностей
     */
    protected function __construct()
    {
        // НЕ завантажуємо БД в конструкторі, щоб уникнути циклічних залежностей
        // БД буде завантажена пізніше через getDB() метод
        $this->db = null;
        $this->init();
    }

    /**
     * Ліниве отримання підключення до БД
     *
     * @return PDO|null
     */
    protected function getDB(): ?PDO
    {
        if ($this->db === null && ! self::$initializing) {
            // У середовищах, де ядро повністю не завантажене (наприклад, інсталер),
            // клас DatabaseHelper може бути недоступним. В такому випадку просто
            // повертаємо null без фатальної помилки.
            if (! class_exists('DatabaseHelper')) {
                return null;
            }

            try {
                self::$initializing = true;
                $this->db = DatabaseHelper::getConnection(false); // Не показуємо помилку, щоб уникнути рекурсії
            } catch (Exception $e) {
                // Ігноруємо помилки БД в конструкторі модулів
                logger()->logError('BaseModule: Failed to get DB connection', ['error' => $e->getMessage()]);
            } finally {
                self::$initializing = false;
            }
        }

        return $this->db;
    }

    /**
     * Ініціалізація модуля (перевизначається в дочірніх класах)
     */
    protected function init(): void
    {
        // Перевизначається в дочірніх класах
    }

    /**
     * Отримання екземпляра модуля (Singleton)
     *
     * @return static
     */
    public static function getInstance()
    {
        $className = static::class;
        if (! isset(self::$instances[$className])) {
            self::$instances[$className] = new static();
        }

        return self::$instances[$className];
    }

    /**
     * Реєстрація хуків модуля
     * Викликається автоматично при завантаженні модуля
     */
    public function registerHooks(): void
    {
        // Перевизначається в дочірніх класах
    }

    /**
     * Отримання інформації про модуль
     *
     * @return array<string, mixed>
     */
    abstract public function getInfo(): array;

    /**
     * Отримання API методів модуля
     *
     * @return array<string, mixed> Масив з описом доступних методів
     */
    public function getApiMethods(): array
    {
        return [];
    }
}
