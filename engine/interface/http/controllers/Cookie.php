<?php

/**
 * Клас для роботи з cookies
 * Управління cookies з додатковою безпекою
 *
 * @package Engine\Classes\Http
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class Cookie
{
    /**
     * Встановлення cookie
     *
     * @param string $name Ім'я cookie
     * @param string $value Значення
     * @param int $expire Час закінчення (Unix timestamp, 0 = до закриття браузера)
     * @param string $path Шлях
     * @param string|null $domain Домен
     * @param bool $secure Тільки через HTTPS
     * @param bool $httponly Тільки через HTTP (не доступно через JavaScript)
     * @param string $samesite SameSite атрибут (Strict, Lax, None)
     * @return bool
     */
    public static function set(
        string $name,
        string $value,
        int $expire = 0,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httponly = true,
        string $samesite = 'Lax'
    ): bool {
        if (headers_sent()) {
            return false;
        }

        $domain = $domain ?? $_SERVER['HTTP_HOST'] ?? '';

        // Перевіряємо реальне HTTPS з'єднання
        $realHttps = (
            (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
            (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );

        // Якщо secure=true, але реальне з'єднання HTTP - вимикаємо secure для сумісності з Edge
        if ($secure && ! $realHttps) {
            $secure = false;
        }

        // Якщо SameSite=None, але secure=false - змінюємо на Lax (Edge вимагає Secure для None)
        if ($samesite === 'None' && ! $secure) {
            $samesite = 'Lax';
        }

        return setcookie($name, $value, [
            'expires' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);
    }

    /**
     * Отримання cookie
     *
     * @param string $name Ім'я cookie
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    public static function get(string $name, $default = null)
    {
        return $_COOKIE[$name] ?? $default;
    }

    /**
     * Перевірка наявності cookie
     *
     * @param string $name Ім'я cookie
     * @return bool
     */
    public static function has(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * Видалення cookie
     *
     * @param string $name Ім'я cookie
     * @param string $path Шлях
     * @param string|null $domain Домен
     * @return bool
     */
    public static function delete(string $name, string $path = '/', ?string $domain = null): bool
    {
        if (! self::has($name)) {
            return true;
        }

        return self::set($name, '', time() - 3600, $path, $domain);
    }

    /**
     * Встановлення постійної cookie (на рік)
     *
     * @param string $name Ім'я cookie
     * @param string $value Значення
     * @param int $days Кількість днів
     * @return bool
     */
    public static function forever(string $name, string $value, int $days = 365): bool
    {
        return self::set($name, $value, time() + ($days * 86400));
    }

    /**
     * Встановлення зашифрованої cookie
     *
     * @param string $name Ім'я cookie
     * @param string $value Значення
     * @param int $expire Час закінчення
     * @param string|null $key Ключ шифрування
     * @return bool
     */
    public static function encrypted(string $name, string $value, int $expire = 0, ?string $key = null): bool
    {
        try {
            $encrypted = Encryption::quickEncrypt($value, $key);

            return self::set($name, $encrypted, $expire);
        } catch (Exception $e) {
            error_log('Cookie encryption error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Отримання розшифрованої cookie
     *
     * @param string $name Ім'я cookie
     * @param mixed $default Значення за замовчуванням
     * @param string|null $key Ключ шифрування
     * @return mixed
     */
    public static function decrypted(string $name, $default = null, ?string $key = null)
    {
        $encrypted = self::get($name);

        if ($encrypted === null) {
            return $default;
        }

        try {
            return Encryption::quickDecrypt($encrypted, $key);
        } catch (Exception $e) {
            error_log('Cookie decryption error: ' . $e->getMessage());

            return $default;
        }
    }
}
