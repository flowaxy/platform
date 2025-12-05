# Models - Моделі даних

## Призначення

Директорія `Models` містить моделі даних (domain models), які представляють бізнес-логіку та структуру даних системи.

## Поточна структура

### `engine/domain/content/` (поточне розташування)

#### Моделі
- `AdminUser.php` - Модель користувача адмінки
- `AdminRole.php` - Модель ролі адмінки
- `Plugin.php` - Модель плагіна
- `Theme.php` - Модель теми

#### Інтерфейси репозиторіїв
- `AdminUserRepositoryInterface.php` - Інтерфейс репозиторію користувачів
- `AdminRoleRepositoryInterface.php` - Інтерфейс репозиторію ролей
- `PluginRepositoryInterface.php` - Інтерфейс репозиторію плагінів
- `ThemeRepositoryInterface.php` - Інтерфейс репозиторію тем
- `ThemeSettingsRepositoryInterface.php` - Інтерфейс репозиторію налаштувань тем

#### Додаткові інтерфейси
- `PluginLifecycleInterface.php` - Інтерфейс життєвого циклу плагіна
- `PluginFilesystemInterface.php` - Інтерфейс файлової системи плагіна
- `PluginCacheInterface.php` - Інтерфейс кешу плагіна

## План міграції

### Фаза 1: Моделі
```
engine/domain/content/AdminUser.php → engine/Models/AdminUser.php
engine/domain/content/AdminRole.php → engine/Models/AdminRole.php
engine/domain/content/Plugin.php → engine/Models/Plugin.php
engine/domain/content/Theme.php → engine/Models/Theme.php
```

### Фаза 2: Інтерфейси репозиторіїв
```
engine/domain/content/AdminUserRepositoryInterface.php → engine/Models/Repositories/AdminUserRepositoryInterface.php
engine/domain/content/AdminRoleRepositoryInterface.php → engine/Models/Repositories/AdminRoleRepositoryInterface.php
engine/domain/content/PluginRepositoryInterface.php → engine/Models/Repositories/PluginRepositoryInterface.php
engine/domain/content/ThemeRepositoryInterface.php → engine/Models/Repositories/ThemeRepositoryInterface.php
engine/domain/content/ThemeSettingsRepositoryInterface.php → engine/Models/Repositories/ThemeSettingsRepositoryInterface.php
```

### Фаза 3: Додаткові інтерфейси
```
engine/domain/content/PluginLifecycleInterface.php → engine/Models/PluginLifecycleInterface.php
engine/domain/content/PluginFilesystemInterface.php → engine/Models/PluginFilesystemInterface.php
engine/domain/content/PluginCacheInterface.php → engine/Models/PluginCacheInterface.php
```

## Структура після міграції

```
engine/Models/
├── AdminUser.php
├── AdminRole.php
├── Plugin.php
├── Theme.php
├── PluginLifecycleInterface.php
├── PluginFilesystemInterface.php
├── PluginCacheInterface.php
└── Repositories/
    ├── AdminUserRepositoryInterface.php
    ├── AdminRoleRepositoryInterface.php
    ├── PluginRepositoryInterface.php
    ├── ThemeRepositoryInterface.php
    └── ThemeSettingsRepositoryInterface.php
```

## Namespace

Всі класи мають використовувати namespace `Flowaxy\Core\Domain\Content\...`:
- `Flowaxy\Core\Domain\Content\AdminUser`
- `Flowaxy\Core\Domain\Content\Plugin`
- `Flowaxy\Core\Domain\Content\Repositories\PluginRepositoryInterface`

## Функціональність

### AdminUser
- Модель користувача адмінки
- Властивості: id, username, email, password, isActive, sessionToken, lastActivity
- Методи для роботи з користувачем

### AdminRole
- Модель ролі адмінки
- Властивості: id, name, permissions
- Методи для роботи з ролями

### Plugin
- Модель плагіна
- Властивості: slug, name, version, description, isActive, dependencies
- Методи для роботи з плагіном

### Theme
- Модель теми
- Властивості: slug, name, version, description, isActive
- Методи для роботи з темою

### Repository Interfaces
- Визначення контрактів для репозиторіїв
- CRUD операції
- Пошук та фільтрація

## Статус

- ✅ Структура створена
- ⏳ Міграція запланована
- 📝 Документація оновлена
