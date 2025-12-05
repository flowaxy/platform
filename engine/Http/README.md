# Http - Запити/відповіді

## Призначення

Директорія `Http` містить HTTP систему, включаючи обробку запитів, відповідей, middleware та роутинг.

## Поточна структура

### `engine/interface/http/` (поточне розташування)

#### Контролери (`controllers/`)
- `Request.php` - Обробка HTTP запитів
- `Response.php` - Генерація HTTP відповідей
- `AjaxHandler.php` - Обробка AJAX запитів
- `ApiController.php` - Базовий контролер API
- `ApiHandler.php` - Обробник API
- `Cookie.php` - Робота з cookies

#### Роутер (`router/`)
- `Router.php` - Основний роутер
- `RouterManager.php` - Менеджер роутерів

#### Контракти (`contracts/`)
- `AjaxHandlerInterface.php` - Інтерфейс AJAX обробника

### `engine/interface/api/` (API система)
- `RestApiController.php` - Базовий REST контролер
- `ApiRouter.php` - API роутер
- `ApiResponse.php` - API відповіді
- `middleware/` - API middleware (Auth, RateLimit, CORS)

## План міграції

### Фаза 1: Контролери
```
engine/interface/http/controllers/Request.php → engine/Http/Controllers/Request.php
engine/interface/http/controllers/Response.php → engine/Http/Controllers/Response.php
engine/interface/http/controllers/AjaxHandler.php → engine/Http/Controllers/AjaxHandler.php
engine/interface/http/controllers/ApiController.php → engine/Http/Controllers/ApiController.php
engine/interface/http/controllers/ApiHandler.php → engine/Http/Controllers/ApiHandler.php
engine/interface/http/controllers/Cookie.php → engine/Http/Controllers/Cookie.php
```

### Фаза 2: Роутер
```
engine/interface/http/router/Router.php → engine/Http/Router.php
engine/interface/http/router/RouterManager.php → engine/Http/RouterManager.php
```

### Фаза 3: API
```
engine/interface/api/RestApiController.php → engine/Http/Api/RestApiController.php
engine/interface/api/ApiRouter.php → engine/Http/Api/ApiRouter.php
engine/interface/api/ApiResponse.php → engine/Http/Api/ApiResponse.php
engine/interface/api/middleware/ → engine/Http/Api/Middleware/
```

### Фаза 4: Контракти
```
engine/interface/http/contracts/ → engine/Http/Contracts/
```

## Структура після міграції

```
engine/Http/
├── Router.php
├── RouterManager.php
├── Controllers/
│   ├── Request.php
│   ├── Response.php
│   ├── AjaxHandler.php
│   ├── ApiController.php
│   ├── ApiHandler.php
│   └── Cookie.php
├── Api/
│   ├── RestApiController.php
│   ├── ApiRouter.php
│   ├── ApiResponse.php
│   └── Middleware/
│       ├── AuthMiddleware.php
│       ├── RateLimitMiddleware.php
│       └── CorsMiddleware.php
└── Contracts/
    └── AjaxHandlerInterface.php
```

## Namespace

Всі класи мають використовувати namespace `Flowaxy\Core\Interface\Http\...`:
- `Flowaxy\Core\Interface\Http\Controllers\Request`
- `Flowaxy\Core\Interface\Http\Controllers\Response`
- `Flowaxy\Core\Interface\Http\Router`

## Функціональність

### Request
- Обробка GET/POST/FILES
- Визначення методу запиту
- Отримання заголовків
- Визначення IP адреси
- Перевірка AJAX запитів

### Response
- Генерація відповідей
- JSON відповіді
- Редиректи
- Завантаження файлів
- Встановлення security headers

### Router
- Реєстрація маршрутів
- Параметризовані маршрути
- Middleware підтримка
- Генерація URL
- Обробка 404

### API
- RESTful API
- Автоматична документація
- Middleware (Auth, Rate Limit, CORS)
- Стандартизовані відповіді

## Статус

- ✅ Структура створена
- ⏳ Міграція запланована
- 📝 Документація оновлена
