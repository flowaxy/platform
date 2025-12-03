<?php

/**
 * Клас для безпеки
 * Захист від XSS, CSRF, SQL ін'єкцій та інших атак
 *
 * @package Engine\Classes\Security
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class Security
{
    private const IP_HEADERS = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR',
    ];

    /**
     * Очищення даних від XSS
     *
     * @param mixed $data Дані для очищення
     * @param bool $strict Строгий режим (видаляти HTML теги)
     * @return mixed
     */
    public static function clean($data, bool $strict = false)
    {
        if (is_array($data)) {
            return array_map(fn ($item) => self::clean($item, $strict), $data);
        }

        if (is_string($data)) {
            return $strict ? strip_tags($data) : htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $data;
    }

    /**
     * Генерація CSRF токена
     *
     * @return string
     */
    public static function csrfToken(): string
    {
        $session = sessionManager();

        if (! $session->has('csrf_token')) {
            $session->set('csrf_token', Hash::token(32));
        }

        return $session->get('csrf_token');
    }

    /**
     * Перевірка CSRF токена
     *
     * @param string|null $token Токен для перевірки (якщо null, береться з POST)
     * @return bool
     */
    public static function verifyCsrfToken(?string $token = null): bool
    {
        // Переконуємося, що сесія запущена
        if (! Session::isStarted()) {
            Session::start();
        }

        $session = sessionManager();
        $sessionToken = $session->get('csrf_token');

        if (empty($sessionToken)) {
            error_log('Security::verifyCsrfToken: Session token is empty');

            return false;
        }

        $token = $token ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

        if (empty($token)) {
            error_log('Security::verifyCsrfToken: Token from request is empty');

            return false;
        }

        $result = Hash::equals($sessionToken, $token);
        if (! $result) {
            error_log('Security::verifyCsrfToken: Tokens do not match');
            error_log('Security::verifyCsrfToken: Session token: ' . substr($sessionToken, 0, 20) . '...');
            error_log('Security::verifyCsrfToken: Request token: ' . substr($token, 0, 20) . '...');
        }

        return $result;
    }

    /**
     * Генерація CSRF токена для форми
     *
     * @return string HTML input з токеном
     */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Санітизація рядка для SQL (використовуйте підготовлені запити!)
     *
     * @param string $string Рядок для санітизації
     * @return string
     * @deprecated Використовуйте підготовлені запити замість цього
     */
    public static function sql(string $string): string
    {
        return str_replace(
            ['\\', "\n", "\r", "\x00", "\x1a", "'", '"'],
            ['\\\\', '\\n', '\\r', '\\0', '\\Z', "\\'", '\\"'],
            $string
        );
    }

    /**
     * Валідація email
     *
     * @param string $email Email для перевірки
     * @return bool
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Валідація URL
     *
     * @param string $url URL для перевірки
     * @return bool
     */
    public static function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Валідація IP адреси
     *
     * @param string $ip IP адреса
     * @param bool $ipv6 Дозволяти IPv6
     * @return bool
     */
    public static function isValidIp(string $ip, bool $ipv6 = true): bool
    {
        $flags = $ipv6 ? FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 : FILTER_FLAG_IPV4;

        return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
    }

    /**
     * Отримання IP адреси клієнта
     *
     * @return string
     */
    public static function getClientIp(): string
    {
        foreach (self::IP_HEADERS as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }

            $ip = $_SERVER[$key];

            if (str_contains($ip, ',')) {
                $ip = trim(explode(',', $ip)[0]);
            }

            if (self::isValidIp($ip)) {
                return $ip;
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Перевірка, чи є запит AJAX
     *
     * @return bool
     */
    public static function isAjax(): bool
    {
        return ! empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Генерація безпечного випадкового імені файла
     *
     * @param string $filename Оригінальне ім'я файла
     * @return string
     */
    public static function sanitizeFilename(string $filename): string
    {
        $filename = basename($filename);

        // Транскрипция русских букв в латиницу
        $filename = self::transliterate($filename);

        // Очищаем имя от небезопасных символов
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        if (strlen($filename) > 255) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $filename = mb_substr($name, 0, 255 - mb_strlen($ext) - 1) . '.' . $ext;
        }

        return $filename;
    }

    /**
     * Транскрипция русских букв в латиницу
     *
     * @param string $text Текст для транскрипции
     * @return string
     */
    public static function transliterate(string $text): string
    {
        $translitMap = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
            'ґ' => 'g', 'Ґ' => 'G', 'і' => 'i', 'І' => 'I', 'ї' => 'yi', 'Ї' => 'Yi',
            'є' => 'ye', 'Є' => 'Ye',
        ];

        return strtr($text, $translitMap);
    }

    /**
     * Захист від брутфорсу (обмеження спроб)
     *
     * @param string $key Ключ для відстеження (наприклад, IP або email)
     * @param int $maxAttempts Максимальна кількість спроб
     * @param int $lockoutTime Час блокування в секундах
     * @return bool True якщо досягнуто ліміт
     */
    public static function isRateLimited(string $key, int $maxAttempts = 5, int $lockoutTime = 900): bool
    {
        $session = sessionManager();

        $attemptsKey = 'rate_limit_' . md5($key);
        $attempts = $session->get($attemptsKey, []);
        $now = time();

        // Фільтруємо застарілі спроби
        $attempts = array_filter($attempts, fn ($timestamp) => ($now - $timestamp) < $lockoutTime);

        if (count($attempts) >= $maxAttempts) {
            return true;
        }

        $attempts[] = $now;
        $session->set($attemptsKey, array_values($attempts));

        return false;
    }

    /**
     * Скидання лічильника спроб
     *
     * @param string $key Ключ
     * @return void
     */
    public static function resetRateLimit(string $key): void
    {
        $session = sessionManager();
        $session->remove('rate_limit_' . md5($key));
    }
}
