# Events - Система подій

## Призначення

Директорія `Events` містить систему подій (events) та слухачів (listeners), яка дозволяє компонентам системи спілкуватися між собою через події.

## Поточна структура

### `engine/core/system/` (поточне розташування)

#### Основні класи
- `EventDispatcher.php` - Диспетчер подій

#### Події (`events/`)
- `Event.php` - Базовий клас події
- `EventListener.php` - Базовий клас слухача
- `EventSubscriber.php` - Інтерфейс підписника подій
- `examples/` - Приклади використання
  - `UserRegisteredEvent.php` - Приклад події
  - `SendWelcomeEmailListener.php` - Приклад слухача

## План міграції

### Фаза 1: Диспетчер
```
engine/core/system/EventDispatcher.php → engine/Events/EventDispatcher.php
```

### Фаза 2: Базові класи
```
engine/core/system/events/Event.php → engine/Events/Event.php
engine/core/system/events/EventListener.php → engine/Events/EventListener.php
engine/core/system/events/EventSubscriber.php → engine/Events/EventSubscriber.php
```

### Фаза 3: Приклади
```
engine/core/system/events/examples/ → engine/Events/Examples/
```

## Структура після міграції

```
engine/Events/
├── EventDispatcher.php
├── Event.php
├── EventListener.php
├── EventSubscriber.php
└── Examples/
    ├── UserRegisteredEvent.php
    └── SendWelcomeEmailListener.php
```

## Namespace

Всі класи мають використовувати namespace `Flowaxy\Core\System\Events\...`:
- `Flowaxy\Core\System\EventDispatcher`
- `Flowaxy\Core\System\Events\Event`
- `Flowaxy\Core\System\Events\EventListener`

## Функціональність

### EventDispatcher
- Реєстрація слухачів подій
- Диспетчеризація подій
- Підтримка пріоритетів
- Підтримка підписників (subscribers)
- Асинхронна обробка подій

### Event
- Базовий клас для всіх подій
- Payload (дані події)
- Контроль поширення (propagation)
- Можливість скасування

### EventListener
- Базовий клас для слухачів
- Обробка подій
- Пріоритети

### EventSubscriber
- Інтерфейс для класів, що підписуються на множинні події
- Автоматична реєстрація

## Приклади використання

```php
// Створення події
class UserRegisteredEvent extends Event
{
    public function __construct(public readonly User $user)
    {
        parent::__construct(['user' => $user]);
    }
}

// Створення слухача
class SendWelcomeEmailListener extends EventListener
{
    public function handle(UserRegisteredEvent $event): void
    {
        // Відправка email
    }
}

// Реєстрація слухача
$dispatcher->addListener(UserRegisteredEvent::class, [SendWelcomeEmailListener::class, 'handle'], 10);

// Диспетчеризація події
$dispatcher->dispatch(new UserRegisteredEvent($user));
```

## Інтеграція з HookManager

EventDispatcher інтегрований з HookManager для автоматичної диспетчеризації подій при виклику хуків.

## Статус

- ✅ Структура створена
- ⏳ Міграція запланована
- 📝 Документація оновлена
