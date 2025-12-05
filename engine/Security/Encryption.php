<?php

/**
 * Клас для шифрування даних
 * Шифрування та розшифрування даних з використанням OpenSSL
 *
 * @package Engine\Classes\Security
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class Encryption
{
    private string $key;
    private string $cipher;
    private int $ivLength;

    private const KEY_LENGTHS = [
        'AES-128-CBC' => 16,
        'AES-192-CBC' => 24,
        'AES-256-CBC' => 32,
    ];

    /**
     * Конструктор
     *
     * @param string|null $key Ключ шифрування (якщо null, використовується системний)
     * @param string $cipher Алгоритм шифрування (за замовчуванням AES-256-CBC)
     */
    public function __construct(?string $key = null, string $cipher = 'AES-256-CBC')
    {
        $this->key = $key ?? $this->getDefaultKey();
        $this->cipher = $cipher;
        $this->ivLength = openssl_cipher_iv_length($cipher);

        if ($this->ivLength === false) {
            throw new Exception("Непідтримуваний алгоритм шифрування: {$cipher}");
        }

        // Нормалізуємо довжину ключа
        $requiredLength = self::KEY_LENGTHS[$cipher] ?? 32;
        if (strlen($this->key) < $requiredLength) {
            $this->key = hash('sha256', $this->key, true);
        }
    }

    /**
     * Отримання ключа за замовчуванням з конфігурації
     *
     * @return string
     */
    private function getDefaultKey(): string
    {
        if (function_exists('config_get')) {
            $key = config_get('app.encryption_key');
            if (! empty($key)) {
                return $key;
            }
        }

        $key = getenv('APP_ENCRYPTION_KEY');
        if (! empty($key)) {
            return $key;
        }

        // Для розробки - в продакшені обов'язково встановіть APP_ENCRYPTION_KEY
        return hash('sha256', 'default_encryption_key_change_in_production');
    }

    /**
     * Шифрування даних
     *
     * @param string $data Дані для шифрування
     * @return string Зашифровані дані (base64)
     * @throws Exception Якщо не вдалося зашифрувати
     */
    public function encrypt(string $data): string
    {
        $iv = random_bytes($this->ivLength);
        $encrypted = openssl_encrypt($data, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new Exception('Не вдалося зашифрувати дані');
        }

        return base64_encode($iv . $encrypted);
    }

    /**
     * Розшифрування даних
     *
     * @param string $encryptedData Зашифровані дані (base64)
     * @return string Розшифровані дані
     * @throws Exception Якщо не вдалося розшифрувати
     */
    public function decrypt(string $encryptedData): string
    {
        $data = base64_decode($encryptedData, true);

        if ($data === false || strlen($data) < $this->ivLength) {
            throw new Exception('Невірний формат зашифрованих даних');
        }

        $iv = substr($data, 0, $this->ivLength);
        $encrypted = substr($data, $this->ivLength);
        $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            throw new Exception('Не вдалося розшифрувати дані');
        }

        return $decrypted;
    }

    /**
     * Статичний метод: Швидке шифрування
     *
     * @param string $data Дані
     * @param string|null $key Ключ
     * @return string
     */
    public static function quickEncrypt(string $data, ?string $key = null): string
    {
        return (new self($key))->encrypt($data);
    }

    /**
     * Статичний метод: Швидке розшифрування
     *
     * @param string $encryptedData Зашифровані дані
     * @param string|null $key Ключ
     * @return string
     */
    public static function quickDecrypt(string $encryptedData, ?string $key = null): string
    {
        return (new self($key))->decrypt($encryptedData);
    }
}
