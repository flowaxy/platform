<?php

/**
 * Універсальний роутер для адмінки та фронтенду
 * Підтримує автоматичну завантаження маршрутів, API для плагінів, middleware та параметри
 *
 * @package Engine\Classes\Http
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class Router
{
    private array $routes = [];
    private array $routesByMethod = [];
    private array $middlewares = [];
    private ?string $basePath = null;
    private ?string $defaultRoute = null;
    private bool $autoLoadEnabled = true;
    private bool $routesLoaded = false;

    /**
     * Конструктор
     *
     * @param string|null $basePath Базовий шлях (наприклад '/admin' або '/')
     * @param string|null $defaultRoute Маршрут за замовчуванням
     */
    public function __construct(?string $basePath = null, ?string $defaultRoute = null)
    {
        $this->basePath = $basePath;
        $this->defaultRoute = $defaultRoute;
    }

    /**
     * Додавання маршруту
     * Підтримує два формати:
     * 1. add('GET', $path, $handler, $options) - новий формат
     * 2. add($path, $handler) - старий формат (для зворотної сумісності)
     *
     * @param string|array $methods HTTP методи або шлях (для зворотної сумісності)
     * @param string|callable $path Шлях маршруту або обробник
     * @param string|callable|null $handler Обробник або опції
     * @param array $options Додаткові опції
     * @return self
     */
    public function add($methods, $path = null, $handler = null, array $options = []): self
    {
        // Перевірка на старий формат: add($path, $handler)
        // Якщо другий параметр - це callable або string (назва класу), а $methods - це шлях (не HTTP метод)
        if ($path !== null &&
            (is_callable($path) || is_string($path)) &&
            is_string($methods) &&
            ! in_array(strtoupper($methods), ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'])) {
            // Старий формат: add($path, $handler)
            $options = is_array($handler) ? $handler : [];
            $handler = $path;
            $path = $methods;
            $methods = ['GET'];
        }

        $methods = is_array($methods) ? $methods : [$methods];
        $methods = array_map('strtoupper', $methods);

        // Переконуємося, що $path та $handler встановлені
        if ($path === null || $handler === null) {
            throw new InvalidArgumentException('Router::add() requires path and handler parameters');
        }

        $route = [
            'methods' => $methods,
            'path' => trim($path, '/'), // Зберігаємо оригінальний шлях без слешів для точного порівняння
            'normalizedPath' => $this->normalizePath($path), // Нормалізований шлях для паттерну
            'handler' => $handler,
            'middleware' => $options['middleware'] ?? [],
            'name' => $options['name'] ?? null,
            'params' => $this->extractParams($path),
            'pattern' => $this->pathToPattern($path),
        ];

        $this->routes[] = $route;
        foreach ($methods as $method) {
            $this->routesByMethod[$method][] = $route;
        }

        return $this;
    }

    /**
     * Додавання GET маршруту
     */
    public function get(string $path, $handler, array $options = []): self
    {
        return $this->add('GET', $path, $handler, $options);
    }

    /**
     * Додавання POST маршруту
     */
    public function post(string $path, $handler, array $options = []): self
    {
        return $this->add('POST', $path, $handler, $options);
    }

    /**
     * Додавання PUT маршруту
     */
    public function put(string $path, $handler, array $options = []): self
    {
        return $this->add('PUT', $path, $handler, $options);
    }

    /**
     * Додавання DELETE маршруту
     */
    public function delete(string $path, $handler, array $options = []): self
    {
        return $this->add('DELETE', $path, $handler, $options);
    }

    /**
     * Додавання маршруту для будь-якого методу
     */
    public function any(string $path, $handler, array $options = []): self
    {
        return $this->add(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'], $path, $handler, $options);
    }

    /**
     * Групування маршрутів з префіксом та middleware
     */
    public function group(string $prefix, callable $callback, array $options = []): self
    {
        $oldPrefix = $this->basePath;
        $oldMiddlewares = $this->middlewares;

        $this->basePath = $this->normalizePath(($oldPrefix ?? '') . '/' . $prefix);
        if (! empty($options['middleware'])) {
            $this->middlewares = array_merge($this->middlewares, (array)$options['middleware']);
        }

        $callback($this);

        $this->basePath = $oldPrefix;
        $this->middlewares = $oldMiddlewares;

        return $this;
    }

    /**
     * Реєстрація middleware
     */
    public function middleware(string $name, callable $handler): self
    {
        $this->middlewares[$name] = $handler;

        return $this;
    }

    /**
     * Автоматична завантаження маршрутів з модулів, плагінів та теми
     */
    public function autoLoad(): void
    {
        if (! $this->autoLoadEnabled || $this->routesLoaded) {
            return;
        }

        // Завантаження маршрутів з модулів
        $this->loadModuleRoutes();

        // Завантаження маршрутів з теми (перед хуком, щоб тема могла перезаписати дефолтні маршрути)
        $this->loadThemeRoutes();

        // Завантаження маршрутів з плагінів через хук (останнім, щоб плагіни могли перезаписати маршрути теми)
        hook_dispatch('register_routes', $this);
        $this->routesLoaded = true;
    }

    /**
     * Вимкнути автоматичне завантаження (наприклад, при тестуванні)
     */
    public function disableAutoLoad(): self
    {
        $this->autoLoadEnabled = false;

        return $this;
    }

    /**
     * Примусово позначити, що маршрути вже завантажені
     */
    public function markRoutesLoaded(): self
    {
        $this->routesLoaded = true;

        return $this;
    }

    /**
     * Завантаження маршрутів з модулів
     * Модулі реєструють свої маршрути через хук register_routes
     */
    private function loadModuleRoutes(): void
    {
        // Модулі реєструють свої маршрути через хук register_routes
    }

    /**
     * Завантаження маршрутів з теми
     */
    private function loadThemeRoutes(): void
    {
        if (! function_exists('themeManager')) {
            return;
        }

        $themeManager = themeManager();
        $activeTheme = $themeManager->getActiveTheme();

        if (empty($activeTheme['slug'] ?? null)) {
            return;
        }

        $themePath = $themeManager->getThemePath($activeTheme['slug']);
        if (empty($themePath)) {
            return;
        }

        $routesFile = $themePath . 'routes.php';
        if (file_exists($routesFile) && is_readable($routesFile)) {
            $router = $this;
            require_once $routesFile;
        }
    }

    /**
     * Нормалізація URI (видалення query string, базового шляху, index.php тощо)
     */
    private static function normalizeUriStatic(string $uri, ?string $basePath = null): string
    {
        // Видаляємо query string
        if (str_contains($uri, '?')) {
            $uri = strstr($uri, '?', true) ?: $uri;
        }

        // Видаляємо базовий шлях, якщо встановлено
        if ($basePath !== null && $basePath !== '/') {
            $basePathTrimmed = rtrim($basePath, '/');
            if (str_starts_with($uri, $basePathTrimmed)) {
                $uri = substr($uri, strlen($basePathTrimmed));
                $uri = '/' . ltrim($uri, '/');
                if ($uri === '/') {
                    $uri = '';
                }
            }
        } elseif ($basePath === '/') {
            $uri = ltrim($uri, '/');
        }

        // Видаляємо index.php
        $uri = preg_replace('#^/?index\.php(/.*)?$#', '$1', $uri);
        $uri = ltrim($uri, '/');

        // Видаляємо розширення .php
        if (preg_match('/^(.+)\.php$/', $uri, $matches)) {
            $uri = $matches[1];
        }

        return trim($uri, '/');
    }

    /**
     * Нормалізація URI (використовує поточний basePath)
     */
    private function normalizeUri(string $uri, ?string $basePath = null): string
    {
        return self::normalizeUriStatic($uri, $basePath ?? $this->basePath);
    }

    /**
     * Отримання поточного URI
     */
    private function getCurrentUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return $this->normalizeUri($uri);
    }

    /**
     * Нормалізація шляху
     * Увага: НЕ додає basePath до path для паттерну, це робиться в getCurrentUri()
     */
    private function normalizePath(string $path): string
    {
        // Просто нормалізуємо шлях без додавання базового шляху
        // Базовий шлях видаляється з URI в getCurrentUri(), а не додається до паттерну
        $path = trim($path, '/');

        // Для порожнього шляху повертаємо порожній рядок, для інших - шлях зі слешем
        return empty($path) ? '' : '/' . $path;
    }

    /**
     * Витягування параметрів з шляху
     */
    private function extractParams(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        return $matches[1] ?? [];
    }

    /**
     * Перетворення шляху на regex pattern
     */
    private function pathToPattern(string $path): string
    {
        // Нормалізуємо шлях для pattern
        $normalizedPath = $this->normalizePath($path);

        // Якщо шлях порожній, він має відповідати кореневому маршруту
        if (empty($normalizedPath) || $normalizedPath === '/') {
            return '/^\/?$/';
        }

        // Прибираємо початковий слеш для паттерну, оскільки він буде доданий в uriPath
        $normalizedPath = ltrim($normalizedPath, '/');

        // Якщо після обробки шлях порожній, повертаємо кореневий паттерн
        if (empty($normalizedPath)) {
            return '/^\/?$/';
        }

        // Розбиваємо шлях на частини
        $parts = explode('/', $normalizedPath);
        $patternParts = [];

        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }

            // Перевіряємо, чи це параметр
            if (preg_match('/^\{([^}]+)\}$/', $part, $matches)) {
                $paramName = trim($matches[1]);
                // Перевіряємо, що ім'я параметра валідне (тільки букви, цифри, підкреслення)
                if (! empty($paramName) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $paramName)) {
                    // Використовуємо іменовану групу, але перевіряємо, що ім'я безпечне
                    $patternParts[] = '(?P<' . $paramName . '>[^\/]+)';
                } else {
                    // Якщо параметр невалідний, екрануємо як звичайний текст
                    $patternParts[] = preg_quote($part, '/');
                }
            } else {
                // Звичайна частина шляху - екрануємо
                $patternParts[] = preg_quote($part, '/');
            }
        }

        // Об'єднуємо частини (використовуємо екранований слеш)
        $pattern = implode('\/', $patternParts);

        // Перевіряємо, що паттерн не порожній
        if (empty($pattern)) {
            return '/^\/?$/';
        }

        // Формуємо фінальний паттерн
        $finalPattern = '/^\/' . $pattern . '$/';

        return $finalPattern;
    }

    /**
     * Перевірка, чи існує маршрут для порожнього шляху
     */
    private function hasEmptyRoute(array $routes): bool
    {
        foreach ($routes as $route) {
            if (empty($route['path'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Пошук відповідного маршруту
     */
    private function findRoute(string $uri, array $routes): ?array
    {
        foreach ($routes as $route) {
            $params = [];
            $uriPath = empty($uri) ? '/' : '/' . $uri;

            // Точне співпадіння (найшвидше)
            if ($route['path'] === $uri) {
                return ['route' => $route, 'params' => $params];
            }

            // Regex pattern для маршрутів з параметрами
            if (! empty($route['params'])) {
                $pattern = $route['pattern'] ?? '';
                // Перевіряємо, що паттерн не порожній і валідний
                if (empty($pattern) || $pattern === '/^\/$/' || strlen($pattern) < 4) {
                    continue;
                }

                // Перевіряємо валідність паттерну (має починатися і закінчуватися на /)
                if (! str_starts_with($pattern, '/') || ! str_ends_with($pattern, '/')) {
                    continue;
                }

                // Валідація паттерну: перевіряємо базову структуру
                // Паттерн повинен починатися з /^ і закінчуватися на $/
                if (! preg_match('/^\/\^.*\$\/$/', $pattern)) {
                    continue;
                }

                // Перевіряємо валідність паттерну через тестовий preg_match
                // Використовуємо try-catch для перехоплення помилок
                $isValid = true;
                $prevErrorReporting = error_reporting(E_ALL);
                $prevDisplayErrors = ini_get('display_errors');
                ini_set('display_errors', '0');

                // Тестуємо паттерн на тестовому рядку
                $testResult = @preg_match($pattern, '/test');
                $testError = preg_last_error();

                // Відновлюємо налаштування
                error_reporting($prevErrorReporting);
                ini_set('display_errors', $prevDisplayErrors);

                // Якщо паттерн невалідний, пропускаємо
                if ($testError !== PREG_NO_ERROR) {
                    continue;
                }

                $matches = [];

                // Виконуємо preg_match з обробкою помилок
                $result = @preg_match($pattern, $uriPath, $matches);
                $pregError = preg_last_error();

                // Якщо виникла помилка regex, пропускаємо цей маршрут
                if ($pregError !== PREG_NO_ERROR || $result === false) {
                    continue;
                }

                if ($result === 1) {
                    foreach ($route['params'] as $param) {
                        if (isset($matches[$param])) {
                            $params[$param] = $matches[$param];
                        }
                    }

                    return ['route' => $route, 'params' => $params];
                }
            }
        }

        return null;
    }

    /**
     * Виконання знайденого маршруту
     */
    private function executeRoute(array $route, array $params): bool
    {
        if (! $this->runMiddlewares($route['middleware'], $params)) {
            return false;
        }

        return $this->executeHandler($route['handler'], $params);
    }

    /**
     * Обробка запиту
     */
    public function dispatch(): bool
    {
        $this->autoLoad();

        $uri = $this->getCurrentUri();
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $methodRoutes = $this->routesByMethod[$method] ?? [];

        // DEBUG: логуємо кожен запит
        if (function_exists('logDebug')) {
            logDebug('Router: Request routing', [
                'method' => $method,
                'uri' => $uri,
            ]);
        } else {
            logger()->logDebug('Маршрутизація запиту', [
                'method' => $method,
                'uri' => $uri,
            ]);
        }

        // Якщо шлях порожній і немає маршруту для порожнього шляху, використовуємо маршрут за замовчуванням
        if (empty($uri) && ! $this->hasEmptyRoute($methodRoutes) && $this->defaultRoute !== null) {
            $uri = $this->defaultRoute;
        }

        // Пошук відповідного маршруту
        $found = $this->findRoute($uri, $methodRoutes);
        if ($found !== null) {
            return $this->executeRoute($found['route'], $found['params']);
        }

        // Маршрут не знайдено
        $this->show404();

        return false;
    }

    /**
     * Виконання middleware
     */
    private function runMiddlewares(array $middlewareNames, array $params): bool
    {
        foreach ($middlewareNames as $middlewareName) {
            if (isset($this->middlewares[$middlewareName])) {
                $result = call_user_func($this->middlewares[$middlewareName], $params);
                if ($result === false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Виконання обробника маршруту
     */
    private function executeHandler($handler, array $params): bool
    {
        if (is_callable($handler)) {
            return call_user_func($handler, $params) !== false;
        }

        if (is_string($handler) && class_exists($handler)) {
            $page = new $handler();
            if (method_exists($page, 'handle')) {
                // Передаємо параметри як властивості об'єкту або через метод
                if (method_exists($page, 'setParams')) {
                    $page->setParams($params);
                }
                $page->handle();

                return true;
            }
        }

        return false;
    }

    /**
     * Отримання поточного маршруту (статичний метод для зворотної сумісності)
     * Використовується в header.php та sidebar.php
     */
    public static function getCurrentPage(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $normalized = self::normalizeUriStatic($uri, '/admin');

        // Для адмінки порожній шлях = dashboard
        if (empty($normalized) && str_starts_with($uri, '/admin')) {
            return 'dashboard';
        }

        return $normalized;
    }

    /**
     * Генерація URL для маршруту за іменем
     */
    public function url(string $routeName, array $params = []): ?string
    {
        foreach ($this->routes as $route) {
            if ($route['name'] === $routeName) {
                // Використовуємо normalizedPath для генерації URL, щоб зберегти структуру з параметрами
                $url = isset($route['normalizedPath']) ? trim($route['normalizedPath'], '/') : $route['path'];
                foreach ($params as $key => $value) {
                    $url = str_replace('{' . $key . '}', $value, $url);
                }

                return $url;
            }
        }

        return null;
    }

    /**
     * Відображення сторінки 404
     */
    private function show404(): void
    {
        (new Response())->status(404)->send();

        // Перевіряємо, чи є кастомна сторінка 404 у темі
        if (function_exists('themeManager')) {
            $themeManager = themeManager();
            $themePath = $themeManager->getThemePath();
            if (! empty($themePath)) {
                $error404File = $themePath . '404.php';
                if (file_exists($error404File) && is_readable($error404File)) {
                    include $error404File;

                    return;
                }
            }
        }

        // Стандартна сторінка 404
        $this->renderDefault404();
    }

    /**
     * Рендеринг стандартної сторінки 404
     */
    private function renderDefault404(): void
    {
        // Використовуємо універсальний обробник помилок
        if (function_exists('showHttpError')) {
            showHttpError(404);
            return;
        }

        // Пряме підключення шаблону
        $templateFile = __DIR__ . '/../../errors/errors.php';
        if (file_exists($templateFile)) {
            $httpCode = 404;
            require $templateFile;
            return;
        }

        // Fallback якщо шаблон не знайдено
        echo '<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Сторінка не знайдена</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f7fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            text-align: center;
        }
        .container { max-width: 600px; padding: 40px 20px; }
        h1 { font-size: 120px; color: #667eea; margin-bottom: 20px; }
        h2 { font-size: 28px; color: #2d3748; margin-bottom: 16px; }
        p { font-size: 18px; color: #718096; margin-bottom: 32px; }
        a {
            display: inline-block;
            padding: 12px 32px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s;
        }
        a:hover { background: #5568d3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <h2>Сторінка не знайдена</h2>
        <p>Запитувана сторінка не існує</p>
        <a href="/">На головну</a>
    </div>
</body>
</html>';
    }

    /**
     * API для плагінів: Додавання маршруту
     */
    public function addRoute($methods, string $path, $handler, array $options = []): self
    {
        return $this->add($methods, $path, $handler, $options);
    }

    /**
     * API для плагінів: Отримання всіх маршрутів
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * API для плагінів: Додавання групи маршрутів
     */
    public function addGroup(string $prefix, callable $callback, array $options = []): self
    {
        return $this->group($prefix, $callback, $options);
    }

    /**
     * API для плагінів: Додавання middleware
     */
    public function addMiddleware(string $name, callable $handler): self
    {
        return $this->middleware($name, $handler);
    }
}

// Примітка: Функція adminUrl видалена - використовуйте UrlHelper::admin()
