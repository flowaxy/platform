# Support - Утиліти та помічники

## Призначення

Директорія `Support` містить утиліти, помічники, фасади та інші допоміжні класи для спрощення роботи з системою.

## Поточна структура

### `engine/core/support/` (поточне розташування)

#### Базові класи (`base/`)
- `BasePlugin.php` - Базовий клас для плагінів
- `BaseModule.php` - Базовий клас для модулів

#### Контейнери (`containers/`)
- `PluginContainer.php` - Ізольований контейнер для плагінів
- `PluginContainerFactory.php` - Фабрика контейнерів плагінів
- `ThemeContainer.php` - Ізольований контейнер для тем
- `ThemeContainerFactory.php` - Фабрика контейнерів тем

#### Помічники (`helpers/`)
- `SecurityHelper.php` - Помічник для безпеки
- `UrlHelper.php` - Помічник для URL
- `DatabaseHelper.php` - Помічник для БД

#### Менеджери (`managers/`)
- `PluginManager.php` - Менеджер плагінів
- `PluginLoader.php` - Завантажувач плагінів
- `ThemeManager.php` - Менеджер тем
- `ThemeLoader.php` - Завантажувач тем
- `SettingsManager.php` - Менеджер налаштувань
- `SessionManager.php` - Менеджер сесій
- `CookieManager.php` - Менеджер cookies
- `RoleManager.php` - Менеджер ролей
- `TimezoneManager.php` - Менеджер часових поясів
- `StorageManager.php` - Менеджер сховища

#### Фасади (`facades/`)
- `Hooks.php` - Фасад для хуків
- `Log.php` - Фасад для логування
- `Cache.php` - Фасад для кешу
- `Database.php` - Фасад для БД
- `Config.php` - Фасад для конфігурації
- `Request.php` - Фасад для запитів
- `Response.php` - Фасад для відповідей
- `Router.php` - Фасад для роутера
- `Session.php` - Фасад для сесій
- `Cookie.php` - Фасад для cookies
- `Security.php` - Фасад для безпеки
- `Hash.php` - Фасад для хешування
- `Encryption.php` - Фасад для шифрування

#### Ізоляція (`isolation/`)
- `PluginIsolation.php` - Система ізоляції плагінів
- `IsolationMiddleware.php` - Middleware для ізоляції

#### Валідатори (`validators/`)
- `PluginStructureValidator.php` - Валідатор структури плагінів
- `ThemeStructureValidator.php` - Валідатор структури тем
- `PluginCompatibilityValidator.php` - Валідатор сумісності плагінів

#### Теми (`theme/`)
- `Theme.php` - API для тем
- `ThemeRenderer.php` - Рендерер тем

#### Інші
- `functions.php` - Глобальні функції
- `error-handler.php` - Обробник помилок
- `Timezone.php` - Робота з часовими поясами

## План міграції

### Фаза 1: Базові класи
```
engine/core/support/base/ → engine/Support/Base/
```

### Фаза 2: Контейнери
```
engine/core/support/containers/ → engine/Support/Containers/
```

### Фаза 3: Помічники
```
engine/core/support/helpers/ → engine/Support/Helpers/
```

### Фаза 4: Менеджери
```
engine/core/support/managers/ → engine/Support/Managers/
```

### Фаза 5: Фасади
```
engine/core/support/facades/ → engine/Support/Facades/
```

### Фаза 6: Ізоляція
```
engine/core/support/isolation/ → engine/Support/Isolation/
```

### Фаза 7: Валідатори
```
engine/core/support/validators/ → engine/Support/Validators/
```

### Фаза 8: Теми
```
engine/core/support/theme/ → engine/Support/Theme/
```

## Структура після міграції

```
engine/Support/
├── Base/
│   ├── BasePlugin.php
│   └── BaseModule.php
├── Containers/
│   ├── PluginContainer.php
│   ├── PluginContainerFactory.php
│   ├── ThemeContainer.php
│   └── ThemeContainerFactory.php
├── Helpers/
│   ├── SecurityHelper.php
│   ├── UrlHelper.php
│   └── DatabaseHelper.php
├── Managers/
│   ├── PluginManager.php
│   ├── PluginLoader.php
│   ├── ThemeManager.php
│   ├── ThemeLoader.php
│   ├── SettingsManager.php
│   ├── SessionManager.php
│   ├── CookieManager.php
│   ├── RoleManager.php
│   ├── TimezoneManager.php
│   └── StorageManager.php
├── Facades/
│   ├── Hooks.php
│   ├── Log.php
│   ├── Cache.php
│   ├── Database.php
│   ├── Config.php
│   ├── Request.php
│   ├── Response.php
│   ├── Router.php
│   ├── Session.php
│   ├── Cookie.php
│   ├── Security.php
│   ├── Hash.php
│   └── Encryption.php
├── Isolation/
│   ├── PluginIsolation.php
│   └── IsolationMiddleware.php
├── Validators/
│   ├── PluginStructureValidator.php
│   ├── ThemeStructureValidator.php
│   └── PluginCompatibilityValidator.php
├── Theme/
│   ├── Theme.php
│   └── ThemeRenderer.php
├── functions.php
├── error-handler.php
└── Timezone.php
```

## Namespace

Всі класи мають використовувати namespace `Flowaxy\Core\Support\...`:
- `Flowaxy\Core\Support\Base\BasePlugin`
- `Flowaxy\Core\Support\Helpers\SecurityHelper`
- `Flowaxy\Core\Support\Managers\PluginManager`

## Функціональність

### BasePlugin
- Базовий клас для всіх плагінів
- Життєвий цикл (install, activate, deactivate, uninstall)
- Реєстрація хуків та маршрутів
- Інтеграція з ізольованим контейнером

### Containers
- Ізольовані контейнери для плагінів та тем
- Забезпечення ізоляції від ядра та інших компонентів
- Фабрики для створення контейнерів

### Helpers
- Спрощення роботи з основними функціями системи
- Безпечні обгортки над класами

### Managers
- Управління різними компонентами системи
- Завантаження та ініціалізація
- Кешування та оптимізація

### Facades
- Статичний доступ до сервісів
- Спрощення використання

### Isolation
- Забезпечення ізоляції плагінів
- Блокування доступу до ядра
- Middleware для перевірки

### Validators
- Валідація структури плагінів/тем
- Перевірка сумісності
- Створення стандартної структури

## Статус

- ✅ Структура створена
- ⏳ Міграція запланована
- 📝 Документація оновлена
