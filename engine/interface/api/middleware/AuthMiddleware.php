<?php

/**
 * Middleware для аутентифікації API
 * 
 * @package Engine\Interface\API\Middleware
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../ApiResponse.php';

final class AuthMiddleware
{
    /**
     * Перевірка аутентифікації через токен
     * 
     * @return callable
     */
    public static function token(): callable
    {
        return function () {
            $token = self::getTokenFromRequest();

            if (empty($token)) {
                ApiResponse::error('Unauthorized', 401)->send();
                return;
            }

            if (!self::validateToken($token)) {
                ApiResponse::error('Invalid token', 401)->send();
                return;
            }

            // Токен валідний, продовжуємо
            return null;
        };
    }

    /**
     * Отримання токена з запиту
     */
    private static function getTokenFromRequest(): string
    {
        // Перевіряємо заголовок Authorization
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Перевіряємо параметр запиту
        return $_GET['token'] ?? $_POST['token'] ?? '';
    }

    /**
     * Валідація токена
     */
    private static function validateToken(string $token): bool
    {
        // Спрощена реалізація - в реальності потрібна перевірка в БД
        if (function_exists('sessionManager')) {
            $session = sessionManager();
            $storedToken = $session->get('api_token');
            
            return $storedToken === $token;
        }

        return false;
    }
}

