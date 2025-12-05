<?php

/**
 * Менеджер для роботи з cookies
 * Централізоване управління cookies з розширеними можливостями
 *
 * @package Engine\Classes\Managers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../../infrastructure/filesystem/contracts/StorageInterface.php';

class CookieManager implements StorageInterface
{
    private static ?self $instance = null;
    /**
     * @var array<string, mixed>
     */
    private array $defaultOptions = [
        'expire' => 0,          // 0 = до закриття браузера
        'path' => '/',
        'domain' => null,
        'secure' => false,      // Автоматично визначається
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    private function __construct()
    {
        // Визначаємо secure автоматично на основі протоколу з налаштувань
        if (class_exists('UrlHelper')) {
            $this->defaultOptions['secure'] = UrlHelper::isHttps();
        } elseif (function_exists('detectProtocol')) {
            $protocol = detectProtocol();
            $this->defaultOptions['secure'] = ($protocol === 'https://');
        } else {
            // Fallback на автоматичне визначення
            $this->defaultOptions['secure'] = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        }
        $this->defaultOptions['domain'] = $_SERVER['HTTP_HOST'] ?? null;
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
     * Отримання значення з cookie
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return Cookie::get($key, $default);
    }

    /**
     * Встановлення значення в cookie
     *
     * @param string $key Ключ
     * @param mixed $value Значення
     * @param array<string, mixed> $options Додаткові опції
     * @return bool
     */
    public function set(string $key, $value, array $options = []): bool
    {
        $options = array_merge($this->defaultOptions, $options);

        // Перевіряємо реальне HTTPS з'єднання для корекції secure
        $realHttps = (
            (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
            (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );

        // Якщо secure=true, але реальне з'єднання HTTP - вимикаємо secure для сумісності з Edge
        if ($options['secure'] && ! $realHttps) {
            $options['secure'] = false;
        }

        // Якщо SameSite=None, але secure=false - змінюємо на Lax (Edge вимагає Secure для None)
        if ($options['samesite'] === 'None' && ! $options['secure']) {
            $options['samesite'] = 'Lax';
        }

        // Підтримка масиву/об'єкта (JSON)
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        // Підтримка часу в днях
        $expire = $options['expire'];
        if (is_int($expire) && $expire > 0) {
            $expire = time() + $expire;
        } elseif (is_string($expire) && str_starts_with($expire, '+')) {
            // Підтримка формату "+30 days"
            $expire = strtotime($expire);
        }

        return Cookie::set(
            $key,
            (string)$value,
            $expire,
            $options['path'],
            $options['domain'],
            $options['secure'],
            $options['httponly'],
            $options['samesite']
        );
    }

    /**
     * Перевірка наявності ключа в cookie
     *
     * @param string $key Ключ
     * @return bool
     */
    public function has(string $key): bool
    {
        return Cookie::has($key);
    }

    /**
     * Видалення значення з cookie
     *
     * @param string $key Ключ
     * @param array<string, mixed> $options Додаткові опції
     * @return bool
     */
    public function remove(string $key, array $options = []): bool
    {
        $options = array_merge($this->defaultOptions, $options);

        return Cookie::delete($key, $options['path'], $options['domain']);
    }

    /**
     * Отримання всіх даних з cookie
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $_COOKIE;
    }

    /**
     * Очищення всіх даних з cookie (не рекомендується)
     *
     * @return bool
     */
    public function clear(): bool
    {
        $result = true;
        foreach ($_COOKIE as $key => $value) {
            if (! $this->remove($key)) {
                $result = false;
            }
        }

        return $result;
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
        $result = true;
        foreach ($values as $key => $value) {
            if (! $this->set($key, $value)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Встановлення кількох значень з опціями
     *
     * @param array<string, mixed> $values Масив ключ => значення
     * @param array<string, mixed> $options Додаткові опції
     * @return bool
     */
    public function setMultipleWithOptions(array $values, array $options = []): bool
    {
        $result = true;
        foreach ($values as $key => $value) {
            if (! $this->set($key, $value, $options)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Видалення кількох значень
     *
     * @param array<int, string> $keys Масив ключів
     * @param array<string, mixed> $options Додаткові опції
     * @return bool
     */
    public function removeMultiple(array $keys, array $options = []): bool
    {
        $result = true;
        foreach ($keys as $key) {
            if (! $this->remove($key, $options)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Встановлення постійної cookie (на рік)
     *
     * @param string $key Ключ
     * @param mixed $value Значення
     * @param int $days Кількість днів
     * @return bool
     */
    public function forever(string $key, $value, int $days = 365): bool
    {
        return $this->set($key, $value, ['expire' => time() + ($days * 86400)]);
    }

    /**
     * Встановлення тимчасової cookie (до закриття браузера)
     *
     * @param string $key Ключ
     * @param mixed $value Значення
     * @return bool
     */
    public function temporary(string $key, $value): bool
    {
        return $this->set($key, $value, ['expire' => 0]);
    }

    /**
     * Встановлення зашифрованої cookie
     *
     * @param string $key Ключ
     * @param mixed $value Значення
     * @param int $expire Час закінчення
     * @param string|null $encryptionKey Ключ шифрування
     * @return bool
     */
    public function encrypted(string $key, $value, int $expire = 0, ?string $encryptionKey = null): bool
    {
        return Cookie::encrypted($key, (string)$value, $expire, $encryptionKey);
    }

    /**
     * Отримання розшифрованої cookie
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @param string|null $encryptionKey Ключ шифрування
     * @return mixed
     */
    public function decrypted(string $key, $default = null, ?string $encryptionKey = null)
    {
        return Cookie::decrypted($key, $default, $encryptionKey);
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

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    /**
     * Встановлення значення як JSON (автоматичне кодування)
     *
     * @param string $key Ключ
     * @param mixed $value Значення (буде закодовано в JSON)
     * @param array $options Додаткові опції
     * @return bool
     */
    /**
     * @param string $key
     * @param mixed $value
     * @param array<string, mixed> $options
     * @return bool
     */
    public function setJson(string $key, $value, array $options = []): bool
    {
        $jsonValue = json_encode($value, JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return $this->set($key, $jsonValue, $options);
    }

    /**
     * Встановлення налаштувань за замовчуванням
     *
     * @param array $options Опції
     * @return void
     */
    /**
     * @param array<string, mixed> $options
     * @return void
     */
    public function setDefaultOptions(array $options): void
    {
        $this->defaultOptions = array_merge($this->defaultOptions, $options);
    }

    /**
     * Отримання налаштувань за замовчуванням
     *
     * @return array<string, mixed>
     */
    public function getDefaultOptions(): array
    {
        return $this->defaultOptions;
    }
}
