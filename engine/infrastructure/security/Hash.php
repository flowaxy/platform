<?php

/**
 * Клас для хешування даних
 * Хешування паролів, створення токенів та перевірочних кодів
 *
 * @package Engine\Classes\Security
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class Hash
{
    /**
     * Хешування пароля
     *
     * @param string $password Пароль
     * @param int|null $algorithm Алгоритм (PASSWORD_DEFAULT, PASSWORD_BCRYPT, PASSWORD_ARGON2ID)
     * @param array|null $options Опції для алгоритму
     * @return string Хеш пароля
     */
    public static function make(string $password, ?int $algorithm = null, ?array $options = null): string
    {
        return $options !== null
            ? password_hash($password, $algorithm ?? PASSWORD_DEFAULT, $options)
            : password_hash($password, $algorithm ?? PASSWORD_DEFAULT);
    }

    /**
     * Перевірка пароля
     *
     * @param string $password Пароль
     * @param string $hash Хеш для перевірки
     * @return bool
     */
    public static function check(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Перевірка необхідності перехешування
     *
     * @param string $hash Хеш для перевірки
     * @param int|null $algorithm Алгоритм
     * @param array|null $options Опції
     * @return bool
     */
    public static function needsRehash(string $hash, ?int $algorithm = null, ?array $options = null): bool
    {
        return $options !== null
            ? password_needs_rehash($hash, $algorithm ?? PASSWORD_DEFAULT, $options)
            : password_needs_rehash($hash, $algorithm ?? PASSWORD_DEFAULT);
    }

    /**
     * Створення випадкового токена
     *
     * @param int $length Довжина токена в байтах (за замовчуванням 32)
     * @return string Токен в hex форматі
     */
    public static function token(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Створення випадкового рядка
     *
     * @param int $length Довжина рядка
     * @param string $charset Набір символів
     * @return string
     */
    public static function randomString(int $length = 32, string $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
    {
        $charsetLength = strlen($charset);
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $charset[random_int(0, $charsetLength - 1)];
        }

        return $result;
    }

    /**
     * Створення MD5 хеша
     *
     * @param string $data Дані для хешування
     * @param bool $rawOutput Повертати сирі бінарні дані
     * @return string
     */
    public static function md5(string $data, bool $rawOutput = false): string
    {
        return md5($data, $rawOutput);
    }

    /**
     * Створення SHA1 хеша
     *
     * @param string $data Дані для хешування
     * @param bool $rawOutput Повертати сирі бінарні дані
     * @return string
     */
    public static function sha1(string $data, bool $rawOutput = false): string
    {
        return sha1($data, $rawOutput);
    }

    /**
     * Створення SHA256 хеша
     *
     * @param string $data Дані для хешування
     * @param bool $rawOutput Повертати сирі бінарні дані
     * @return string
     */
    public static function sha256(string $data, bool $rawOutput = false): string
    {
        return hash('sha256', $data, $rawOutput);
    }

    /**
     * Створення SHA512 хеша
     *
     * @param string $data Дані для хешування
     * @param bool $rawOutput Повертати сирі бінарні дані
     * @return string
     */
    public static function sha512(string $data, bool $rawOutput = false): string
    {
        return hash('sha512', $data, $rawOutput);
    }

    /**
     * Створення HMAC підпису
     *
     * @param string $data Дані
     * @param string $key Секретний ключ
     * @param string $algorithm Алгоритм (sha256, sha512 і т.д.)
     * @param bool $rawOutput Повертати сирі бінарні дані
     * @return string
     */
    public static function hmac(string $data, string $key, string $algorithm = 'sha256', bool $rawOutput = false): string
    {
        return hash_hmac($algorithm, $data, $key, $rawOutput);
    }

    /**
     * Створення перевірочного коду (наприклад, для email підтвердження)
     *
     * @param int $length Довжина коду
     * @param bool $numeric Тільки цифри
     * @return string
     */
    public static function code(int $length = 6, bool $numeric = true): string
    {
        return $numeric
            ? self::randomString($length, '0123456789')
            : self::randomString($length, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
    }

    /**
     * Порівняння хешів з захистом від timing attack
     *
     * @param string $knownString Відомий рядок
     * @param string $userString Користувацький рядок
     * @return bool
     */
    public static function equals(string $knownString, string $userString): bool
    {
        return hash_equals($knownString, $userString);
    }
}
