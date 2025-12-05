<?php

/**
 * Генератор security headers
 * 
 * Автоматичне додавання security headers до HTTP відповіді
 * 
 * @package Engine\Infrastructure\Security
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/CSPGenerator.php';

final class SecurityHeaders
{
    private bool $enabled = true;
    private CSPGenerator $cspGenerator;
    private array $headers = [];

    public function __construct()
    {
        $this->cspGenerator = new CSPGenerator();
        $this->setDefaultHeaders();
    }

    /**
     * Встановлення стандартних заголовків
     * 
     * @return void
     */
    private function setDefaultHeaders(): void
    {
        // X-Content-Type-Options
        $this->headers['X-Content-Type-Options'] = 'nosniff';

        // X-Frame-Options
        $this->headers['X-Frame-Options'] = 'DENY';

        // X-XSS-Protection
        $this->headers['X-XSS-Protection'] = '1; mode=block';

        // Referrer-Policy
        $this->headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';

        // Permissions-Policy
        $this->headers['Permissions-Policy'] = 'geolocation=(), microphone=(), camera=()';

        // Strict-Transport-Security (HSTS) - тільки для HTTPS
        if ($this->isHttps()) {
            $this->headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
    }

    /**
     * Встановлення CSP політики
     * 
     * @param CSPGenerator|callable $csp CSP генератор або callback для налаштування
     * @return self
     */
    public function setCSP(CSPGenerator|callable $csp): self
    {
        if (is_callable($csp)) {
            $csp($this->cspGenerator);
        } else {
            $this->cspGenerator = $csp;
        }

        $cspHeader = $this->cspGenerator->generate();
        if (!empty($cspHeader)) {
            $this->headers['Content-Security-Policy'] = $cspHeader;
        }

        return $this;
    }

    /**
     * Додавання кастомного заголовка
     * 
     * @param string $name Назва заголовка
     * @param string $value Значення
     * @return self
     */
    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Видалення заголовка
     * 
     * @param string $name Назва заголовка
     * @return self
     */
    public function removeHeader(string $name): self
    {
        unset($this->headers[$name]);
        return $this;
    }

    /**
     * Відправка всіх заголовків
     * 
     * @return void
     */
    public function send(): void
    {
        if (!$this->enabled) {
            return;
        }

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}", false);
        }
    }

    /**
     * Отримання всіх заголовків
     * 
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Увімкнення/вимкнення заголовків
     * 
     * @param bool $enabled
     * @return self
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Перевірка, чи запит через HTTPS
     * 
     * @return bool
     */
    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    }
}

