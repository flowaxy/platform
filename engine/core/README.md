# Core - Базові класи, ядро ООП, інтерфейси, контракти

## Призначення

Директорія `Core` містить базові класи ядра Flowaxy CMS, інтерфейси та контракти, які є фундаментом всієї системи.

## Поточна структура

### `engine/core/` (поточне розташування)

#### `core/bootstrap/`
- `app.php` - Точка входу для завантаження ядра
- `router.php` - Ініціалізація роутера
- `api-routes.php` - API маршрути
- `roles-init.php` - Ініціалізація ролей

#### `core/config/`
- `feature-flags.php` - Прапорці функцій
- `modules.php` - Конфігурація модулів
- `services.php` - Конфігурація сервісів

#### `core/contracts/` (інтерфейси)
- `AutoloaderInterface.php` - Інтерфейс автозавантажувача
- `ComponentRegistryInterface.php` - Інтерфейс реєстру компонентів
- `ContainerInterface.php` - Інтерфейс DI контейнера
- `FeatureFlagManagerInterface.php` - Інтерфейс менеджера прапорців
- `HookManagerInterface.php` - Інтерфейс менеджера хуків
- `HookRegistryInterface.php` - Інтерфейс реєстру хуків
- `KernelInterface.php` - Інтерфейс ядра
- `LoggerInterface.php` - Інтерфейс логера
- `ServiceProviderInterface.php` - Інтерфейс провайдера сервісів

#### `core/providers/`
- `AuthServiceProvider.php` - Провайдер автентифікації
- `CoreServiceProvider.php` - Основний провайдер
- `PluginModuleServiceProvider.php` - Провайдер модулів плагінів
- `ThemeServiceProvider.php` - Провайдер тем

#### `core/system/` (базові класи ядра)
- `Kernel.php` - Базовий клас ядра
- `HttpKernel.php` - HTTP ядро
- `CliKernel.php` - CLI ядро
- `Container.php` - DI контейнер
- `ClassAutoloader.php` - Автозавантажувач класів
- `EventDispatcher.php` - Диспетчер подій
- `HookManager.php` - Менеджер хуків
- `ModuleManager.php` - Менеджер модулів
- `QueueManager.php` - Менеджер черг
- `TaskScheduler.php` - Планувальник завдань
- `ServiceTags.php` - Теги сервісів
- `ComponentRegistry.php` - Реєстр компонентів
- `FeatureFlagManager.php` - Менеджер прапорців
- `EnvironmentLoader.php` - Завантажувач середовища
- `MigrationRunner.php` - Виконавець міграцій
- `TestService.php` - Сервіс тестування

#### `core/system/commands/` (CLI команди)
- `CodeCheckCommand.php` - Перевірка коду
- `CodeAnalyzeCommand.php` - Аналіз коду
- `IsolationCheckCommand.php` - Перевірка ізоляції
- `PerformanceTestCommand.php` - Тести продуктивності
- `MakeCommand.php` - Базовий клас генерації
- `MakeControllerCommand.php` - Генерація контролера
- `MakeModelCommand.php` - Генерація моделі
- `MakePluginCommand.php` - Генерація плагіна

#### `core/system/events/` (система подій)
- `Event.php` - Базовий клас події
- `EventListener.php` - Базовий клас слухача
- `EventSubscriber.php` - Інтерфейс підписника
- `examples/` - Приклади використання

#### `core/system/hooks/` (система хуків)
- `Action.php` - Клас для action хуків
- `Filter.php` - Клас для filter хуків
- `HookManager.php` - Менеджер хуків (буде мігровано в Hooks/)
- `HookListener.php` - Слухач хука
- `HookRegistry.php` - Реєстр хуків
- `HookPerformanceMonitor.php` - Моніторинг продуктивності
- `HookMiddleware.php` - Middleware для хуків
- `HookType.php` - Типи хуків (enum)

#### `core/system/queue/` (система черг)
- `Job.php` - Базовий клас завдання
- `QueueWorker.php` - Воркер черги
- `QueueDriverInterface.php` - Інтерфейс драйвера
- `drivers/` - Драйвери (Database, File, Redis)
- `examples/` - Приклади

#### `core/system/tasks/` (система завдань)
- `ScheduledTask.php` - Заплановане завдання
- `TaskRunner.php` - Виконавець завдань
- `examples/` - Приклади

#### `core/support/` (підтримка)
- `base/` - Базові класи (BasePlugin, BaseModule)
- `containers/` - Контейнери (PluginContainer, ThemeContainer)
- `helpers/` - Помічники (SecurityHelper, UrlHelper, DatabaseHelper)
- `managers/` - Менеджери (PluginManager, ThemeManager)
- `facades/` - Фасади
- `isolation/` - Ізоляція (PluginIsolation)
- `validators/` - Валідатори
- `functions.php` - Глобальні функції
- `error-handler.php` - Обробник помилок

## План міграції

### Фаза 1: Контракти
```
engine/core/contracts/ → engine/Core/Contracts/
```

### Фаза 2: Системні класи
```
engine/core/system/Kernel.php → engine/Core/System/Kernel.php
engine/core/system/HttpKernel.php → engine/Core/System/HttpKernel.php
engine/core/system/CliKernel.php → engine/Core/System/CliKernel.php
engine/core/system/Container.php → engine/Core/System/Container.php
engine/core/system/ClassAutoloader.php → engine/Core/System/ClassAutoloader.php
engine/core/system/ModuleManager.php → engine/Core/System/ModuleManager.php
```

### Фаза 3: Підтримка
```
engine/core/support/base/ → engine/Core/Base/
engine/core/support/helpers/ → engine/Core/Helpers/
engine/core/support/managers/ → engine/Core/Managers/
```

### Фаза 4: Конфігурація та провайдери
```
engine/core/config/ → engine/Core/Config/
engine/core/providers/ → engine/Core/Providers/
engine/core/bootstrap/ → engine/Core/Bootstrap/
```

## Namespace

Всі класи мають використовувати namespace `Flowaxy\Core\...`:
- `Flowaxy\Core\System\Kernel`
- `Flowaxy\Core\Contracts\ContainerInterface`
- `Flowaxy\Core\Base\BasePlugin`
- `Flowaxy\Core\Helpers\SecurityHelper`

## Статус

- ✅ Структура створена
- ⏳ Міграція запланована
- 📝 Документація оновлена
