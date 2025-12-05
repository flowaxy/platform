# Cache - Система кешування

## Призначення

Директорія `Cache` містить систему багаторівневого кешування з підтримкою різних драйверів (Memory, File, Database).

## Поточна структура

### `engine/infrastructure/cache/` (поточне розташування)

#### Основні класи
- `Cache.php` - Основний клас кешування
- `MultiLevelCache.php` - Багаторівневий кеш
- `CacheDriverInterface.php` - Інтерфейс драйвера кешу
- `CacheMetadata.php` - Метадані кешу
- `CacheStrategy.php` - Стратегії кешування
- `CacheBatch.php` - Batch операції
- `CachePrefetcher.php` - Попереднє завантаження
- `CacheWarmer.php` - Нагрівання кешу
- `CacheWarmerInterface.php` - Інтерфейс warmer'а
- `PluginCacheManager.php` - Менеджер кешу плагінів

#### Драйвери (`drivers/`)
- `MemoryCacheDriver.php` - In-memory кеш (найшвидший)
- `FileCacheDriver.php` - Файловий кеш (персистентний)
- `DatabaseCacheDriver.php` - Кеш в БД (спільний)

#### Warmers (`warmers/`)
- `ConfigCacheWarmer.php` - Нагрівання конфігурації
- `RoutesCacheWarmer.php` - Нагрівання маршрутів

## План міграції

### Фаза 1: Основні класи
```
engine/infrastructure/cache/Cache.php → engine/Cache/Cache.php
engine/infrastructure/cache/MultiLevelCache.php → engine/Cache/MultiLevelCache.php
engine/infrastructure/cache/CacheDriverInterface.php → engine/Cache/CacheDriverInterface.php
engine/infrastructure/cache/CacheMetadata.php → engine/Cache/CacheMetadata.php
engine/infrastructure/cache/CacheStrategy.php → engine/Cache/CacheStrategy.php
engine/infrastructure/cache/CacheBatch.php → engine/Cache/CacheBatch.php
engine/infrastructure/cache/CachePrefetcher.php → engine/Cache/CachePrefetcher.php
```

### Фаза 2: Драйвери
```
engine/infrastructure/cache/drivers/ → engine/Cache/Drivers/
```

### Фаза 3: Warmers
```
engine/infrastructure/cache/warmers/ → engine/Cache/Warmers/
engine/infrastructure/cache/CacheWarmer.php → engine/Cache/CacheWarmer.php
engine/infrastructure/cache/CacheWarmerInterface.php → engine/Cache/CacheWarmerInterface.php
```

### Фаза 4: Спеціалізовані менеджери
```
engine/infrastructure/cache/PluginCacheManager.php → engine/Cache/PluginCacheManager.php
```

## Структура після міграції

```
engine/Cache/
├── Cache.php
├── MultiLevelCache.php
├── CacheDriverInterface.php
├── CacheMetadata.php
├── CacheStrategy.php
├── CacheBatch.php
├── CachePrefetcher.php
├── CacheWarmer.php
├── CacheWarmerInterface.php
├── PluginCacheManager.php
├── Drivers/
│   ├── MemoryCacheDriver.php
│   ├── FileCacheDriver.php
│   └── DatabaseCacheDriver.php
└── Warmers/
    ├── ConfigCacheWarmer.php
    └── RoutesCacheWarmer.php
```

## Namespace

Всі класи мають використовувати namespace `Flowaxy\Core\Infrastructure\Cache\...`:
- `Flowaxy\Core\Infrastructure\Cache\Cache`
- `Flowaxy\Core\Infrastructure\Cache\MultiLevelCache`
- `Flowaxy\Core\Infrastructure\Cache\Drivers\MemoryCacheDriver`

## Функціональність

### Multi-Level Cache
- **Memory** (L1) - Найшвидший, тільки для поточного запиту
- **File** (L2) - Персистентний, для одного сервера
- **Database** (L3) - Спільний для всіх серверів

### Стратегії кешування
- TTL (Time To Live)
- Tag-based invalidation
- Compression для великих об'єктів
- Batch операції

### Cache Warmer
- Попереднє нагрівання кешу
- Автоматичне нагрівання після очищення
- Реєстрація власних warmer'ів

## Статус

- ✅ Структура створена
- ⏳ Міграція запланована
- 📝 Документація оновлена
