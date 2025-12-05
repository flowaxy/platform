# Services - Сервіс-класи

## Призначення

Директорія `Services` містить сервіс-класи (application services), які реалізують бізнес-логіку та координують роботу між різними компонентами системи.

## Поточна структура

### `engine/application/` (поточне розташування)

#### Контент (`content/`)
- `ActivatePluginService.php` - Активація плагіна
- `DeactivatePluginService.php` - Деактивація плагіна
- `InstallPluginService.php` - Встановлення плагіна
- `UninstallPluginService.php` - Видалення плагіна
- `TogglePluginService.php` - Перемикання стану плагіна
- `PluginLifecycleService.php` - Управління життєвим циклом плагіна
- `ActivateThemeService.php` - Активація теми
- `UpdateThemeSettingsService.php` - Оновлення налаштувань теми

#### Безпека (`security/`)
- `AdminAuthorizationService.php` - Авторизація адміна
- `AuthenticateAdminUserService.php` - Аутентифікація користувача
- `AuthenticationResult.php` - Результат аутентифікації
- `LogoutAdminUserService.php` - Вихід з системи

## План міграції

### Фаза 1: Контент
```
engine/application/content/ → engine/Services/Content/
```

### Фаза 2: Безпека
```
engine/application/security/ → engine/Services/Security/
```

## Структура після міграції

```
engine/Services/
├── Content/
│   ├── ActivatePluginService.php
│   ├── DeactivatePluginService.php
│   ├── InstallPluginService.php
│   ├── UninstallPluginService.php
│   ├── TogglePluginService.php
│   ├── PluginLifecycleService.php
│   ├── ActivateThemeService.php
│   └── UpdateThemeSettingsService.php
└── Security/
    ├── AdminAuthorizationService.php
    ├── AuthenticateAdminUserService.php
    ├── AuthenticationResult.php
    └── LogoutAdminUserService.php
```

## Namespace

Всі класи мають використовувати namespace `Flowaxy\Core\Application\...`:
- `Flowaxy\Core\Application\Content\ActivatePluginService`
- `Flowaxy\Core\Application\Security\AuthenticateAdminUserService`

## Функціональність

### Content Services
- **ActivatePluginService** - Активація плагіна з перевіркою залежностей
- **DeactivatePluginService** - Деактивація плагіна
- **InstallPluginService** - Встановлення плагіна (створення таблиць, тощо)
- **UninstallPluginService** - Видалення плагіна (очищення даних)
- **TogglePluginService** - Перемикання стану плагіна
- **PluginLifecycleService** - Координація життєвого циклу плагіна
- **ActivateThemeService** - Активація теми
- **UpdateThemeSettingsService** - Оновлення налаштувань теми

### Security Services
- **AdminAuthorizationService** - Перевірка прав доступу адміна
- **AuthenticateAdminUserService** - Аутентифікація користувача
- **AuthenticationResult** - Результат аутентифікації (успіх/помилка)
- **LogoutAdminUserService** - Вихід з системи (очищення сесії)

## Принципи

Сервіси дотримуються принципів:
- **Single Responsibility** - Кожен сервіс відповідає за одну операцію
- **Dependency Injection** - Залежності передаються через конструктор
- **Transaction Management** - Управління транзакціями БД
- **Error Handling** - Обробка помилок та винятків

## Статус

- ✅ Структура створена
- ⏳ Міграція запланована
- 📝 Документація оновлена
