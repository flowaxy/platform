<?php

/**
 * Базовий контролер REST API
 * 
 * @package Engine\Interface\API
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/ApiResponse.php';

abstract class RestApiController
{
    /**
     * Отримання списку ресурсів
     */
    public function index(): ApiResponse
    {
        return ApiResponse::error('Method not implemented', 501);
    }

    /**
     * Отримання одного ресурсу
     */
    public function show(array $params): ApiResponse
    {
        return ApiResponse::error('Method not implemented', 501);
    }

    /**
     * Створення ресурсу
     */
    public function store(array $params): ApiResponse
    {
        return ApiResponse::error('Method not implemented', 501);
    }

    /**
     * Оновлення ресурсу
     */
    public function update(array $params): ApiResponse
    {
        return ApiResponse::error('Method not implemented', 501);
    }

    /**
     * Видалення ресурсу
     */
    public function destroy(array $params): ApiResponse
    {
        return ApiResponse::error('Method not implemented', 501);
    }

    /**
     * Отримання даних запиту
     */
    protected function getRequestData(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        return is_array($data) ? $data : [];
    }

    /**
     * Валідація даних
     */
    protected function validate(array $data, array $rules): array
    {
        if (class_exists('Security')) {
            return Security::validate($data, $rules);
        }

        return [];
    }
}

