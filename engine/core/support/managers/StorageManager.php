<?php

/**
 * Менеджер для роботи з клієнтським сховищем (LocalStorage/SessionStorage)
 * Управління даними через JavaScript API на клієнті
 *
 * @package Engine\Classes\Managers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../infrastructure/filesystem/contracts/StorageInterface.php';

class StorageManager implements StorageInterface
{
    private static ?self $instance = null;
    private string $type = 'localStorage'; // localStorage або sessionStorage
    private string $prefix = '';

    // Сховище для серверної частини (fallback, якщо JS не доступний)
    private array $serverStorage = [];

    private function __construct()
    {
        // За замовчуванням використовуємо localStorage
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
     * Встановлення типу сховища (localStorage або sessionStorage)
     *
     * @param string $type Тип (localStorage або sessionStorage)
     * @return void
     */
    public function setType(string $type): void
    {
        if (in_array($type, ['localStorage', 'sessionStorage'], true)) {
            $this->type = $type;
        }
    }

    /**
     * Отримання типу сховища
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
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
     * Отримання значення з сховища (тільки для серверної частини)
     * На клієнті значення читаються через JavaScript API
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $fullKey = $this->getFullKey($key);

        return $this->serverStorage[$fullKey] ?? $default;
    }

    /**
     * Встановлення значення в сховище
     * Генерує JavaScript код для встановлення значення на клієнті
     *
     * @param string $key Ключ
     * @param mixed $value Значення
     * @return bool
     */
    public function set(string $key, $value): bool
    {
        $fullKey = $this->getFullKey($key);
        $this->serverStorage[$fullKey] = $value;

        // Генерируем JavaScript код для установки значения на клиенте
        $jsonValue = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsCode = sprintf(
            '<script>if (typeof %s !== "undefined") { %s.setItem("%s", %s); }</script>',
            $this->type,
            $this->type,
            htmlspecialchars($fullKey, ENT_QUOTES, 'UTF-8'),
            $jsonValue
        );

        // Зберігаємо код для виводу в кінці сторінки через хук
        if (! isset($GLOBALS['_storage_manager_js'])) {
            $GLOBALS['_storage_manager_js'] = [];
            // Регистрируем хук для автоматического вывода JavaScript кода
            if (function_exists('addHook')) {
                addHook('admin_footer', function () {
                    $js = StorageManager::getInstance()->getJavaScript();
                    if (! empty($js)) {
                        echo $js;
                    }
                });
            }
        }
        $GLOBALS['_storage_manager_js'][] = $jsCode;

        return true;
    }

    /**
     * Перевірка наявності ключа в сховищі
     *
     * @param string $key Ключ
     * @return bool
     */
    public function has(string $key): bool
    {
        $fullKey = $this->getFullKey($key);

        return isset($this->serverStorage[$fullKey]);
    }

    /**
     * Видалення значення зі сховища
     *
     * @param string $key Ключ
     * @return bool
     */
    public function remove(string $key): bool
    {
        $fullKey = $this->getFullKey($key);
        unset($this->serverStorage[$fullKey]);

        // Генеруємо JavaScript код для видалення значення на клієнті
        $jsCode = sprintf(
            '<script>if (typeof %s !== "undefined") { %s.removeItem("%s"); }</script>',
            $this->type,
            $this->type,
            htmlspecialchars($fullKey, ENT_QUOTES, 'UTF-8')
        );

        if (! isset($GLOBALS['_storage_manager_js'])) {
            $GLOBALS['_storage_manager_js'] = [];
        }
        $GLOBALS['_storage_manager_js'][] = $jsCode;

        return true;
    }

    /**
     * Отримання всіх даних зі сховища
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $result = [];
        $prefixLen = $this->prefix ? strlen($this->prefix) + 1 : 0;

        foreach ($this->serverStorage as $key => $value) {
            if (! $this->prefix || str_starts_with($key, $this->prefix . '.')) {
                $resultKey = $prefixLen > 0 ? substr($key, $prefixLen) : $key;
                $result[$resultKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Очищення всіх даних зі сховища
     *
     * @return bool
     */
    public function clear(): bool
    {
        if ($this->prefix) {
            // Очищаємо тільки ключі з префіксом
            foreach ($this->serverStorage as $key => $value) {
                if (str_starts_with($key, $this->prefix . '.')) {
                    unset($this->serverStorage[$key]);
                }
            }

            // JavaScript код для очищення на клієнті
            $jsCode = sprintf(
                '<script>
                if (typeof %s !== "undefined") {
                    var keys = Object.keys(%s);
                    var prefix = "%s.";
                    keys.forEach(function(key) {
                        if (key.indexOf(prefix) === 0) {
                            %s.removeItem(key);
                        }
                    });
                }
                </script>',
                $this->type,
                $this->type,
                htmlspecialchars($this->prefix, ENT_QUOTES, 'UTF-8'),
                $this->type
            );
        } else {
            // Очищаємо все
            $this->serverStorage = [];
            $jsCode = sprintf(
                '<script>if (typeof %s !== "undefined") { %s.clear(); }</script>',
                $this->type,
                $this->type
            );
        }

        if (! isset($GLOBALS['_storage_manager_js'])) {
            $GLOBALS['_storage_manager_js'] = [];
        }
        $GLOBALS['_storage_manager_js'][] = $jsCode;

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
     * Отримання згенерованого JavaScript коду для виводу на сторінці
     *
     * @return string
     */
    public function getJavaScript(): string
    {
        if (! isset($GLOBALS['_storage_manager_js']) || empty($GLOBALS['_storage_manager_js'])) {
            return '';
        }

        $js = implode("\n", $GLOBALS['_storage_manager_js']);
        $GLOBALS['_storage_manager_js'] = [];

        return $js;
    }

    /**
     * Отримання значення як JSON
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
     * Встановлення значення як JSON
     *
     * @param string $key Ключ
     * @param mixed $value Значение
     * @return bool
     */
    public function setJson(string $key, $value): bool
    {
        return $this->set($key, $value);
    }
}
