# Database - ORM/QueryBuilder

## Призначення

Директорія `Database` містить систему роботи з базою даних, включаючи ORM, QueryBuilder, Connection Pool та репозиторії.

## Поточна структура

### `engine/infrastructure/persistence/` (поточне розташування)

#### Основні класи
- `Database.php` - Основний клас для роботи з БД
- `DatabaseInterface.php` - Інтерфейс бази даних
- `QueryBuilder.php` - Fluent query builder
- `ConnectionPool.php` - Пул з'єднань для оптимізації

#### Репозиторії
- `AdminUserRepository.php` - Репозиторій користувачів адмінки
- `AdminRoleRepository.php` - Репозиторій ролей адмінки
- `PluginRepository.php` - Репозиторій плагінів
- `ThemeRepository.php` - Репозиторій тем
- `ThemeSettingsRepository.php` - Репозиторій налаштувань тем

#### Оптимізація
- `IndexManager.php` - Менеджер індексів
- `QueryOptimizer.php` - Оптимізатор запитів

## План міграції

### Фаза 1: Основні класи
```
engine/infrastructure/persistence/Database.php → engine/Database/Database.php
engine/infrastructure/persistence/DatabaseInterface.php → engine/Database/DatabaseInterface.php
engine/infrastructure/persistence/QueryBuilder.php → engine/Database/QueryBuilder.php
engine/infrastructure/persistence/ConnectionPool.php → engine/Database/ConnectionPool.php
```

### Фаза 2: Репозиторії
```
engine/infrastructure/persistence/AdminUserRepository.php → engine/Database/Repositories/AdminUserRepository.php
engine/infrastructure/persistence/AdminRoleRepository.php → engine/Database/Repositories/AdminRoleRepository.php
engine/infrastructure/persistence/PluginRepository.php → engine/Database/Repositories/PluginRepository.php
engine/infrastructure/persistence/ThemeRepository.php → engine/Database/Repositories/ThemeRepository.php
engine/infrastructure/persistence/ThemeSettingsRepository.php → engine/Database/Repositories/ThemeSettingsRepository.php
```

### Фаза 3: Оптимізація
```
engine/infrastructure/persistence/IndexManager.php → engine/Database/IndexManager.php
engine/infrastructure/persistence/QueryOptimizer.php → engine/Database/QueryOptimizer.php
```

### Фаза 4: Міграції
```
engine/core/system/MigrationRunner.php → engine/Database/MigrationRunner.php
```

## Структура після міграції

```
engine/Database/
├── Database.php
├── DatabaseInterface.php
├── QueryBuilder.php
├── ConnectionPool.php
├── IndexManager.php
├── QueryOptimizer.php
├── MigrationRunner.php
└── Repositories/
    ├── AdminUserRepository.php
    ├── AdminRoleRepository.php
    ├── PluginRepository.php
    ├── ThemeRepository.php
    └── ThemeSettingsRepository.php
```

## Namespace

Всі класи мають використовувати namespace `Flowaxy\Core\Database\...`:
- `Flowaxy\Core\Database\Database`
- `Flowaxy\Core\Database\QueryBuilder`
- `Flowaxy\Core\Database\Repositories\AdminUserRepository`

## Функціональність

### Database
- Підключення до БД (PDO)
- Підготовлені запити
- Транзакції
- Логування запитів
- Кешування результатів
- Connection pooling

### QueryBuilder
- Fluent interface
- Підтримка всіх SQL операцій
- Параметризовані запити
- Оптимізація запитів

### ConnectionPool
- Перевикористання з'єднань
- Обмеження кількості з'єднань
- Автоматичне закриття неактивних з'єднань

## Статус

- ✅ Структура створена
- ⏳ Міграція запланована
- 📝 Документація оновлена
