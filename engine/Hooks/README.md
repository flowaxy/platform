# Hooks - Система хуків та фільтрів

## Призначення

Директорія `Hooks` містить систему хуків (hooks) та фільтрів (filters), яка дозволяє плагінам та темам розширювати функціональність ядра без модифікації коду ядра.

## Поточна структура

### `engine/core/system/` (поточне розташування)

#### Основні класи
- `HookManager.php` - Менеджер хуків та фільтрів

#### Хуки (`hooks/`)
- `Action.php` - Клас для action хуків (WordPress-style)
- `Filter.php` - Клас для filter хуків (WordPress-style)
- `HookListener.php` - Слухач хука
- `HookType.php` - Типи хуків (enum: Action, Filter)
- `HookRegistry.php` - Реєстр хуків з метаданими
- `HookPerformanceMonitor.php` - Моніторинг продуктивності хуків
- `HookMiddleware.php` - Middleware для хуків
- `HookMiddlewareInterface.php` - Інтерфейс middleware
- `HookDefinition.php` - Визначення хука

## План міграції

### Фаза 1: Менеджер
```
engine/core/system/HookManager.php → engine/Hooks/HookManager.php
```

### Фаза 2: Базові класи
```
engine/core/system/hooks/Action.php → engine/Hooks/Action.php
engine/core/system/hooks/Filter.php → engine/Hooks/Filter.php
engine/core/system/hooks/HookListener.php → engine/Hooks/HookListener.php
engine/core/system/hooks/HookType.php → engine/Hooks/HookType.php
engine/core/system/hooks/HookDefinition.php → engine/Hooks/HookDefinition.php
```

### Фаза 3: Розширені можливості
```
engine/core/system/hooks/HookRegistry.php → engine/Hooks/HookRegistry.php
engine/core/system/hooks/HookPerformanceMonitor.php → engine/Hooks/HookPerformanceMonitor.php
engine/core/system/hooks/HookMiddleware.php → engine/Hooks/HookMiddleware.php
engine/core/system/hooks/HookMiddlewareInterface.php → engine/Hooks/HookMiddlewareInterface.php
```

## Структура після міграції

```
engine/Hooks/
├── HookManager.php
├── Action.php
├── Filter.php
├── HookListener.php
├── HookType.php
├── HookDefinition.php
├── HookRegistry.php
├── HookPerformanceMonitor.php
├── HookMiddleware.php
└── HookMiddlewareInterface.php
```

## Namespace

Всі класи мають використовувати namespace `Flowaxy\Core\System\Hooks\...`:
- `Flowaxy\Core\System\HookManager`
- `Flowaxy\Core\System\Hooks\Action`
- `Flowaxy\Core\System\Hooks\Filter`

## Функціональність

### HookManager
- Реєстрація action хуків
- Реєстрація filter хуків
- Виконання хуків з пріоритетами
- Підтримка ізоляції (core, plugin, theme)
- Middleware підтримка
- Моніторинг продуктивності
- Реєстрація метаданих

### Action
WordPress-style API для action хуків:
```php
Action::add('init', function() {
    // Код виконання
}, 10);

Action::do('init');
```

### Filter
WordPress-style API для filter хуків:
```php
Filter::add('the_title', function($title) {
    return strtoupper($title);
}, 10);

$title = Filter::apply('the_title', 'Hello World');
```

### HookRegistry
- Зберігання метаданих хуків
- Опис хуків
- Версіонування
- Залежності

### HookPerformanceMonitor
- Відстеження часу виконання
- Збір статистики
- Виявлення повільних хуків

### HookMiddleware
- Обробка payload перед виконанням
- Обробка результату після виконання
- Підтримка ізоляції

## Ізоляція

Система хуків підтримує ізоляцію:
- **Core hooks** - Хуки з ядра
- **Plugin hooks** - Хуки з плагінів (виконуються в ізольованому контейнері)
- **Theme hooks** - Хуки з тем (виконуються в ізольованому контейнері)

## Інтеграція з EventDispatcher

HookManager інтегрований з EventDispatcher для автоматичної диспетчеризації подій при виклику хуків.

## Статус

- ✅ Структура створена
- ⏳ Міграція запланована
- 📝 Документація оновлена
