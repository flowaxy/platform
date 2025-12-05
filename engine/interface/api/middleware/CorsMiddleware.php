<?php

/**
 * Middleware для CORS підтримки
 * 
 * @package Engine\Interface\API\Middleware
 * @version 1.0.0
 */

declare(strict_types=1);

final class CorsMiddleware
{
    private array $allowedOrigins = ['*'];
    private array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
    private array $allowedHeaders = ['Content-Type', 'Authorization'];
    private bool $allowCredentials = false;

    /**
     * Створення middleware з налаштуваннями
     * 
     * @param array<string>|string $origins Дозволені джерела
     * @param array<string> $methods Дозволені методи
     * @param array<string> $headers Дозволені заголовки
     * @param bool $credentials Дозволити credentials
     * @return callable
     */
    public static function create(
        array|string $origins = '*',
        array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization'],
        bool $credentials = false
    ): callable {
        return function () use ($origins, $methods, $headers, $credentials) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

            // Обробка preflight запиту
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                header('Access-Control-Allow-Origin: ' . ($origins === '*' || in_array($origin, (array)$origins) ? ($origins === '*' ? '*' : $origin) : ''));
                header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
                header('Access-Control-Allow-Headers: ' . implode(', ', $headers));
                
                if ($credentials) {
                    header('Access-Control-Allow-Credentials: true');
                }
                
                http_response_code(200);
                exit;
            }

            // Встановлюємо CORS заголовки для звичайних запитів
            if ($origins === '*' || in_array($origin, (array)$origins)) {
                header('Access-Control-Allow-Origin: ' . ($origins === '*' ? '*' : $origin));
                header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
                header('Access-Control-Allow-Headers: ' . implode(', ', $headers));
                
                if ($credentials) {
                    header('Access-Control-Allow-Credentials: true');
                }
            }

            return null;
        };
    }
}

