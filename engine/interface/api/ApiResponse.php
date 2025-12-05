<?php

/**
 * Клас відповіді API
 * 
 * @package Engine\Interface\API
 * @version 1.0.0
 */

declare(strict_types=1);

final class ApiResponse
{
    private int $statusCode = 200;
    private array $data = [];
    private array $headers = [];
    private string $message = '';

    /**
     * Створення успішної відповіді
     */
    public static function success(mixed $data = null, string $message = '', int $statusCode = 200): self
    {
        $response = new self();
        $response->statusCode = $statusCode;
        $response->data = $data !== null ? (is_array($data) ? $data : ['data' => $data]) : [];
        $response->message = $message;
        
        return $response;
    }

    /**
     * Створення помилкової відповіді
     */
    public static function error(string $message, int $statusCode = 400, array $errors = []): self
    {
        $response = new self();
        $response->statusCode = $statusCode;
        $response->message = $message;
        $response->data = ['errors' => $errors];
        
        return $response;
    }

    /**
     * Встановлення статус коду
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Додавання даних
     */
    public function setData(mixed $data): self
    {
        $this->data = is_array($data) ? $data : ['data' => $data];
        return $this;
    }

    /**
     * Додавання заголовка
     */
    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Відправка відповіді
     */
    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Встановлюємо JSON заголовок за замовчуванням
        if (!isset($this->headers['Content-Type'])) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $response = [
            'success' => $this->statusCode < 400,
            'message' => $this->message,
            'data' => $this->data,
        ];

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Отримання JSON рядка
     */
    public function toJson(): string
    {
        $response = [
            'success' => $this->statusCode < 400,
            'message' => $this->message,
            'data' => $this->data,
        ];

        return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

