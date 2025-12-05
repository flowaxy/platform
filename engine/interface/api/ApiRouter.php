<?php

/**
 * Роутер для API
 * 
 * @package Engine\Interface\API
 * @version 1.0.0
 */

declare(strict_types=1);

final class ApiRouter
{
    private array $routes = [];
    private string $prefix = '/api';
    private array $middleware = [];

    /**
     * Додавання GET маршруту
     */
    public function get(string $path, callable $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * Додавання POST маршруту
     */
    public function post(string $path, callable $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * Додавання PUT маршруту
     */
    public function put(string $path, callable $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Додавання DELETE маршруту
     */
    public function delete(string $path, callable $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Додавання маршруту
     */
    private function addRoute(string $method, string $path, callable $handler): self
    {
        $fullPath = $this->prefix . $path;
        
        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }

        $this->routes[$method][$fullPath] = $handler;
        
        return $this;
    }

    /**
     * Встановлення префіксу
     */
    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * Додавання middleware
     */
    public function middleware(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Обробка запиту
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        // Застосовуємо middleware
        foreach ($this->middleware as $middleware) {
            $result = $middleware();
            if ($result !== null) {
                return;
            }
        }

        // Шукаємо маршрут
        if (!isset($this->routes[$method])) {
            ApiResponse::error('Method not allowed', 405)->send();
            return;
        }

        foreach ($this->routes[$method] as $routePath => $handler) {
            if ($this->matchRoute($routePath, $path, $params)) {
                $this->callHandler($handler, $params);
                return;
            }
        }

        ApiResponse::error('Not found', 404)->send();
    }

    /**
     * Перевірка відповідності маршруту
     */
    private function matchRoute(string $routePath, string $requestPath, array &$params): bool
    {
        $routePattern = preg_replace('/\{(\w+)\}/', '([^/]+)', $routePath);
        $routePattern = '#^' . $routePattern . '$#';

        if (preg_match($routePattern, $requestPath, $matches)) {
            array_shift($matches);
            preg_match_all('/\{(\w+)\}/', $routePath, $paramNames);
            
            $params = [];
            foreach ($paramNames[1] as $index => $name) {
                $params[$name] = $matches[$index] ?? null;
            }

            return true;
        }

        return false;
    }

    /**
     * Виклик обробника
     */
    private function callHandler(callable $handler, array $params): void
    {
        try {
            $result = $handler($params);
            
            if ($result instanceof ApiResponse) {
                $result->send();
            } elseif (is_array($result) || is_object($result)) {
                ApiResponse::success($result)->send();
            } else {
                ApiResponse::success(['result' => $result])->send();
            }
        } catch (Throwable $e) {
            ApiResponse::error($e->getMessage(), 500)->send();
        }
    }
}

