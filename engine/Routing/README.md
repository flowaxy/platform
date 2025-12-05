# Routing - Маршрутизація

## Призначення

Директорія `Routing` містить систему маршрутизації HTTP запитів, включаючи роутер, менеджер роутерів та підтримку middleware.

## Поточна структура

### `engine/interface/http/router/` (поточне розташування)

#### Основні класи
- `Router.php` - Основний роутер для HTTP запитів
- `RouterManager.php` - Менеджер роутерів

### `engine/interface/api/` (API роутинг)
- `ApiRouter.php` - Роутер для API запитів

## План міграції

### Фаза 1: HTTP роутинг
```
engine/interface/http/router/Router.php → engine/Routing/Router.php
engine/interface/http/router/RouterManager.php → engine/Routing/RouterManager.php
```

### Фаза 2: API роутинг
```
engine/interface/api/ApiRouter.php → engine/Routing/ApiRouter.php
```

### Фаза 3: Middleware
```
engine/interface/http/middleware/ → engine/Routing/Middleware/
```

## Структура після міграції

```
engine/Routing/
├── Router.php
├── RouterManager.php
├── ApiRouter.php
└── Middleware/
    ├── AuthMiddleware.php
    ├── RateLimitMiddleware.php
    └── CorsMiddleware.php
```

## Namespace

Всі класи мають використовувати namespace `Flowaxy\Core\Interface\Http\Router\...`:
- `Flowaxy\Core\Interface\Http\Router\Router`
- `Flowaxy\Core\Interface\Http\Router\RouterManager`

## Функціональність

### Router
- Реєстрація маршрутів (GET, POST, PUT, DELETE, PATCH)
- Параметризовані маршрути (`/user/{id}`)
- Підтримка middleware
- Групування маршрутів
- Префікси маршрутів
- Генерація URL
- Обробка 404 помилок
- Автоматичне завантаження маршрутів з плагінів

### RouterManager
- Управління множинними роутерами
- Реєстрація роутерів
- Отримання роутера за ім'ям

### ApiRouter
- RESTful API маршрутизація
- Автоматична документація
- Версіонування API
- Middleware для API

## Приклади використання

```php
// Реєстрація маршруту
$router->add('GET', '/user/{id}', [UserController::class, 'show']);

// Групування маршрутів
$router->group('/admin', function($router) {
    $router->add('GET', '/dashboard', [DashboardController::class, 'index']);
});

// Middleware
$router->add('POST', '/api/user', [UserController::class, 'create'])
    ->middleware([AuthMiddleware::class, RateLimitMiddleware::class]);
```

## Статус

- ✅ Структура створена
- ⏳ Міграція запланована
- 📝 Документація оновлена
