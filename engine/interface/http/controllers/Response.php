<?php

/**
 * Клас для роботи з HTTP відповідями
 * Відправка відповідей, редіректів, JSON та інших типів відповідей
 *
 * @package Engine\Classes\Http
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private ?string $content = null;

    /**
     * Встановлення статус коду
     *
     * @param int $code Код статусу
     * @return self
     */
    public function status(int $code): self
    {
        $this->statusCode = $code;

        return $this;
    }

    /**
     * Встановлення заголовка
     *
     * @param string $name Ім'я заголовка
     * @param string $value Значення
     * @return self
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Встановлення вмісту
     *
     * @param string $content Вміст
     * @return self
     */
    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Відправка відповіді
     *
     * @return void
     */
    public function send(): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->content !== null) {
            echo $this->content;
        }
    }

    /**
     * Відправка JSON відповіді
     *
     * @param mixed $data Дані
     * @param int $statusCode Код статусу
     * @return void
     */
    public function json($data, int $statusCode = 200): void
    {
        // Очищаємо буфер виводу перед відправкою JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Вимікаємо вивід помилок на екран (але логуємо їх)
        $oldErrorReporting = error_reporting(E_ALL);
        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');

        // Переконуємося, що заголовки ще не відправлені
        if (headers_sent($file, $line)) {
            error_log("Response::json() викликано після відправки заголовків у {$file}:{$line}");
            // Намагаємося відправити JSON через JavaScript, якщо можливо
            echo '<script>if(typeof console !== "undefined") console.error("JSON response failed: headers already sent");</script>';
            exit;
        }

        $this->status($statusCode)->header('Content-Type', 'application/json; charset=UTF-8');

        try {
            $jsonContent = Json::stringify($data);
            $this->content($jsonContent);
            $this->send();
        } catch (Exception $e) {
            error_log('Помилка кодування JSON відповіді: ' . $e->getMessage());
            // Відправляємо помилку у JSON форматі
            $this->status(500)->header('Content-Type', 'application/json; charset=UTF-8');
            $this->content(json_encode([
                'success' => false,
                'error' => 'Помилка формування JSON відповіді: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
            $this->send();
        }

        // Відновлюємо налаштування
        error_reporting($oldErrorReporting);
        ini_set('display_errors', $oldDisplayErrors);

        exit;
    }

    /**
     * Редірект
     *
     * @param string $url URL для редіректу
     * @param int $statusCode Код статусу (301 або 302)
     * @return void
     */
    public function redirect(string $url, int $statusCode = 302): void
    {
        if (headers_sent()) {
            // Використовуємо Security клас для екранування
            echo '<script>window.location.href="' . Security::clean($url) . '";</script>';

            return;
        }

        http_response_code($statusCode);
        header("Location: {$url}");
        exit;
    }

    /**
     * Відправка файла для завантаження
     *
     * @param string $filePath Шлях до файла
     * @param string|null $fileName Ім'я файла (якщо null, береться зі шляху)
     * @return void
     */
    public function download(string $filePath, ?string $fileName = null): void
    {
        if (! file_exists($filePath)) {
            $this->status(404)->send();

            return;
        }

        $fileName = $fileName ?? basename($filePath);
        $mimeType = MimeType::get($filePath);
        $fileSize = filesize($filePath);

        $this->header('Content-Type', $mimeType)
             ->header('Content-Disposition', 'attachment; filename="' . Security::sanitizeFilename($fileName) . '"')
             ->header('Content-Length', (string)$fileSize)
             ->send();

        readfile($filePath);
        exit;
    }

    /**
     * Статичний метод: Встановлення заголовка
     *
     * @param string $name Ім'я заголовка
     * @param string $value Значення
     * @return void
     */
    public static function setHeader(string $name, string $value): void
    {
        if (! headers_sent()) {
            header("{$name}: {$value}");
        }
    }

    /**
     * Статичний метод: Швидка JSON відповідь
     *
     * @param mixed $data Дані
     * @param int $statusCode Код статусу
     * @return void
     */
    public static function jsonResponse($data, int $statusCode = 200): void
    {
        (new self())->json($data, $statusCode);
    }

    /**
     * Статичний метод: Швидкий редірект
     * Використовується через функцію redirectTo() з init.php
     *
     * @param string $url URL
     * @param int $statusCode Код статусу
     * @return void
     */
    public static function redirectStatic(string $url, int $statusCode = 302): void
    {
        (new self())->redirect($url, $statusCode);
    }

    /**
     * Встановлення security headers для захисту від атак
     *
     * @param array $options Налаштування security headers
     * @return self
     */
    /**
     * @param array<string, mixed> $options
     * @return self
     */
    public function securityHeaders(array $options = []): self
    {
        $defaults = [
            'csp' => "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.gstatic.com data:;",
            'x_frame_options' => 'SAMEORIGIN',
            'x_content_type_options' => 'nosniff',
            'x_xss_protection' => '1; mode=block',
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'strict_transport_security' => 'max-age=31536000; includeSubDomains',
            'permissions_policy' => 'geolocation=(), microphone=(), camera=()',
        ];

        $options = array_merge($defaults, $options);

        // Content-Security-Policy
        if (! empty($options['csp'])) {
            $this->header('Content-Security-Policy', $options['csp']);
        }

        // X-Frame-Options (захист від clickjacking)
        if (! empty($options['x_frame_options'])) {
            $this->header('X-Frame-Options', $options['x_frame_options']);
        }

        // X-Content-Type-Options (захист від MIME sniffing)
        if (! empty($options['x_content_type_options'])) {
            $this->header('X-Content-Type-Options', $options['x_content_type_options']);
        }

        // X-XSS-Protection (захист від XSS)
        if (! empty($options['x_xss_protection'])) {
            $this->header('X-XSS-Protection', $options['x_xss_protection']);
        }

        // Referrer-Policy (контроль передачі referrer)
        if (! empty($options['referrer_policy'])) {
            $this->header('Referrer-Policy', $options['referrer_policy']);
        }

        // Strict-Transport-Security (HSTS) - тільки для HTTPS
        $isHttps = false;
        if (class_exists('UrlHelper')) {
            $isHttps = UrlHelper::isHttps();
        } elseif (function_exists('detectProtocol')) {
            $isHttps = (detectProtocol() === 'https://');
        } else {
            $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        }

        if (! empty($options['strict_transport_security']) && $isHttps) {
            $this->header('Strict-Transport-Security', $options['strict_transport_security']);
        }

        // Permissions-Policy (контроль доступу до браузерних API)
        if (! empty($options['permissions_policy'])) {
            $this->header('Permissions-Policy', $options['permissions_policy']);
        }

        return $this;
    }

    /**
     * Статичний метод: Встановлення security headers
     *
     * @param array $options Налаштування security headers
     * @return void
     */
    /**
     * @param array<string, mixed> $options
     * @return void
     */
    public static function setSecurityHeaders(array $options = []): void
    {
        if (headers_sent()) {
            return;
        }

        $response = new self();
        $response->securityHeaders($options);
        $response->send();
    }
}
