# Console - CLI команди

## Призначення

Директорія `Console` містить CLI систему, включаючи CliKernel та всі CLI команди для управління системою через командний рядок.

## Поточна структура

### `engine/core/system/` (поточне розташування)

#### Основні класи
- `CliKernel.php` - CLI ядро для обробки команд

#### Команди (`commands/`)
- `CodeCheckCommand.php` - Перевірка коду (syntax, linting)
- `CodeAnalyzeCommand.php` - Аналіз коду (складність, метрики)
- `IsolationCheckCommand.php` - Перевірка ізоляції плагінів/тем
- `PerformanceTestCommand.php` - Тести продуктивності
- `MakeCommand.php` - Базовий клас для генерації
- `MakeControllerCommand.php` - Генерація контролера
- `MakeModelCommand.php` - Генерація моделі
- `MakePluginCommand.php` - Генерація плагіна

#### Додаткові команди (в CliKernel)
- `cache:clear` - Очищення кешу
- `plugin:list` - Список плагінів
- `theme:list` - Список тем
- `hooks:list` - Список хуків
- `test` - Запуск тестів
- `classmap` - Генерація class map
- `doctor` - Діагностика системи

## План міграції

### Фаза 1: Ядро
```
engine/core/system/CliKernel.php → engine/Console/CliKernel.php
```

### Фаза 2: Команди
```
engine/core/system/commands/ → engine/Console/Commands/
```

### Фаза 3: Додаткові команди
Створення окремих класів для команд, які зараз в CliKernel:
- `CacheClearCommand.php`
- `PluginListCommand.php`
- `ThemeListCommand.php`
- `HooksListCommand.php`
- `TestCommand.php`
- `ClassMapCommand.php`
- `DoctorCommand.php`

## Структура після міграції

```
engine/Console/
├── CliKernel.php
└── Commands/
    ├── CodeCheckCommand.php
    ├── CodeAnalyzeCommand.php
    ├── IsolationCheckCommand.php
    ├── PerformanceTestCommand.php
    ├── MakeCommand.php
    ├── MakeControllerCommand.php
    ├── MakeModelCommand.php
    ├── MakePluginCommand.php
    ├── CacheClearCommand.php
    ├── PluginListCommand.php
    ├── ThemeListCommand.php
    ├── HooksListCommand.php
    ├── TestCommand.php
    ├── ClassMapCommand.php
    └── DoctorCommand.php
```

## Namespace

Всі класи мають використовувати namespace `Flowaxy\Core\System\...`:
- `Flowaxy\Core\System\CliKernel`
- `Flowaxy\Core\System\Commands\CodeCheckCommand`

## Функціональність

### CliKernel
- Обробка CLI запитів
- Парсинг аргументів
- Виконання команд
- Виведення допомоги

### Команди перевірки
- **code:check** - Перевірка синтаксису PHP, linting
- **code:analyze** - Аналіз складності коду, метрики
- **isolation:check** - Перевірка ізоляції плагінів/тем
- **performance:test** - Тести продуктивності системи

### Команди генерації
- **make:controller** - Генерація контролера
- **make:model** - Генерація моделі
- **make:plugin** - Генерація структури плагіна

### Команди управління
- **cache:clear** - Очищення кешу
- **plugin:list** - Список плагінів
- **theme:list** - Список тем
- **hooks:list** - Список зареєстрованих хуків

### Команди тестування
- **test** - Запуск тестів (unit, integration, functional, performance)

### Команди оптимізації
- **classmap** - Генерація class map для автозавантаження

### Команди діагностики
- **doctor** - Діагностика системи (перевірка конфігурації, БД, тощо)

## Приклади використання

```bash
# Перевірка коду
php flowaxy code:check

# Аналіз коду
php flowaxy code:analyze

# Генерація контролера
php flowaxy make:controller UserController

# Очищення кешу
php flowaxy cache:clear

# Запуск тестів
php flowaxy test

# Діагностика
php flowaxy doctor
```

## Статус

- ✅ Структура створена
- ⏳ Міграція запланована
- 📝 Документація оновлена
