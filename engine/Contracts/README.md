# Contracts - Інтерфейси та контракти

## Призначення

Директорія `Contracts` містить інтерфейси та контракти, які визначають контракти між різними компонентами системи.

## Поточна структура

### `engine/core/contracts/` (поточне розташування)

#### Основні інтерфейси
- `AutoloaderInterface.php` - Інтерфейс автозавантажувача
- `ComponentRegistryInterface.php` - Інтерфейс реєстру компонентів
- `ContainerInterface.php` - Інтерфейс DI контейнера
- `FeatureFlagManagerInterface.php` - Інтерфейс менеджера прапорців
- `HookManagerInterface.php` - Інтерфейс менеджера хуків
- `HookRegistryInterface.php` - Інтерфейс реєстру хуків
- `KernelInterface.php` - Інтерфейс ядра
- `LoggerInterface.php` - Інтерфейс логера
- `ServiceProviderInterface.php` - Інтерфейс провайдера сервісів

### `engine/infrastructure/filesystem/contracts/` (інтерфейси файлової системи)
- `FileInterface.php` - Інтерфейс файлу
- `StorageInterface.php` - Інтерфейс сховища
- `StructuredFileInterface.php` - Інтерфейс структурованого файлу

### `engine/interface/http/contracts/` (інтерфейси HTTP)
- `AjaxHandlerInterface.php` - Інтерфейс AJAX обробника

## План міграції

### Фаза 1: Основні інтерфейси
```
engine/core/contracts/ → engine/Contracts/
```

### Фаза 2: Файлова система
```
engine/infrastructure/filesystem/contracts/ → engine/Contracts/Filesystem/
```

### Фаза 3: HTTP
```
engine/interface/http/contracts/ → engine/Contracts/Http/
```

### Фаза 4: Додаткові інтерфейси
```
engine/infrastructure/cache/CacheDriverInterface.php → engine/Contracts/Cache/CacheDriverInterface.php
engine/core/system/queue/QueueDriverInterface.php → engine/Contracts/Queue/QueueDriverInterface.php
engine/infrastructure/persistence/DatabaseInterface.php → engine/Contracts/Database/DatabaseInterface.php
```

## Структура після міграції

```
engine/Contracts/
├── AutoloaderInterface.php
├── ComponentRegistryInterface.php
├── ContainerInterface.php
├── FeatureFlagManagerInterface.php
├── HookManagerInterface.php
├── HookRegistryInterface.php
├── KernelInterface.php
├── LoggerInterface.php
├── ServiceProviderInterface.php
├── Filesystem/
│   ├── FileInterface.php
│   ├── StorageInterface.php
│   └── StructuredFileInterface.php
├── Http/
│   └── AjaxHandlerInterface.php
├── Cache/
│   └── CacheDriverInterface.php
├── Queue/
│   └── QueueDriverInterface.php
└── Database/
    └── DatabaseInterface.php
```

## Namespace

Всі інтерфейси мають використовувати namespace `Flowaxy\Core\Contracts\...`:
- `Flowaxy\Core\Contracts\ContainerInterface`
- `Flowaxy\Core\Contracts\HookManagerInterface`
- `Flowaxy\Core\Contracts\Filesystem\FileInterface`

## Функціональність

### Основні інтерфейси
- **ContainerInterface** - Контракт DI контейнера
- **HookManagerInterface** - Контракт менеджера хуків
- **LoggerInterface** - Контракт логера
- **KernelInterface** - Контракт ядра

### Спеціалізовані інтерфейси
- **FileInterface** - Контракт для роботи з файлами
- **CacheDriverInterface** - Контракт драйвера кешу
- **QueueDriverInterface** - Контракт драйвера черги
- **DatabaseInterface** - Контракт бази даних

## Принципи

Інтерфейси дотримуються принципів:
- **Interface Segregation** - Інтерфейси розділені за призначенням
- **Dependency Inversion** - Залежності від абстракцій, а не від конкретних реалізацій
- **Contract First** - Спочатку визначаються контракти, потім реалізації

## Статус

- ✅ Структура створена
- ⏳ Міграція запланована
- 📝 Документація оновлена
