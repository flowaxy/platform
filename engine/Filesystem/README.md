# Filesystem - Робота з файлами

## Призначення

Директорія `Filesystem` містить систему безпечної роботи з файлами та директоріями, включаючи підтримку різних форматів файлів.

## Поточна структура

### `engine/infrastructure/filesystem/` (поточне розташування)

#### Основні класи
- `File.php` - Робота з файлами (CRUD, метадані)
- `Directory.php` - Робота з директоріями (CRUD, сканування)
- `Upload.php` - Безпечне завантаження файлів
- `Image.php` - Робота з зображеннями
- `MimeType.php` - Визначення MIME типів
- `PluginFilesystem.php` - Файлова система для плагінів

#### Формати файлів
- `Json.php` - Робота з JSON
- `Xml.php` - Робота з XML
- `Yaml.php` - Робота з YAML
- `Ini.php` - Робота з INI
- `Csv.php` - Робота з CSV
- `Zip.php` - Робота з ZIP архівами

#### Контракти (`contracts/`)
- `FileInterface.php` - Інтерфейс файлу
- `StorageInterface.php` - Інтерфейс сховища
- `StructuredFileInterface.php` - Інтерфейс структурованого файлу

## План міграції

### Фаза 1: Основні класи
```
engine/infrastructure/filesystem/File.php → engine/Filesystem/File.php
engine/infrastructure/filesystem/Directory.php → engine/Filesystem/Directory.php
engine/infrastructure/filesystem/Upload.php → engine/Filesystem/Upload.php
engine/infrastructure/filesystem/Image.php → engine/Filesystem/Image.php
engine/infrastructure/filesystem/MimeType.php → engine/Filesystem/MimeType.php
engine/infrastructure/filesystem/PluginFilesystem.php → engine/Filesystem/PluginFilesystem.php
```

### Фаза 2: Формати файлів
```
engine/infrastructure/filesystem/Json.php → engine/Filesystem/Formats/Json.php
engine/infrastructure/filesystem/Xml.php → engine/Filesystem/Formats/Xml.php
engine/infrastructure/filesystem/Yaml.php → engine/Filesystem/Formats/Yaml.php
engine/infrastructure/filesystem/Ini.php → engine/Filesystem/Formats/Ini.php
engine/infrastructure/filesystem/Csv.php → engine/Filesystem/Formats/Csv.php
engine/infrastructure/filesystem/Zip.php → engine/Filesystem/Formats/Zip.php
```

### Фаза 3: Контракти
```
engine/infrastructure/filesystem/contracts/ → engine/Filesystem/Contracts/
```

## Структура після міграції

```
engine/Filesystem/
├── File.php
├── Directory.php
├── Upload.php
├── Image.php
├── MimeType.php
├── PluginFilesystem.php
├── Contracts/
│   ├── FileInterface.php
│   ├── StorageInterface.php
│   └── StructuredFileInterface.php
└── Formats/
    ├── Json.php
    ├── Xml.php
    ├── Yaml.php
    ├── Ini.php
    ├── Csv.php
    └── Zip.php
```

## Namespace

Всі класи мають використовувати namespace `Flowaxy\Core\Infrastructure\Filesystem\...`:
- `Flowaxy\Core\Infrastructure\Filesystem\File`
- `Flowaxy\Core\Infrastructure\Filesystem\Directory`
- `Flowaxy\Core\Infrastructure\Filesystem\Formats\Json`

## Функціональність

### File
- CRUD операції
- Метадані (розмір, дата створення/зміни)
- Безпечна робота з шляхами (захист від path traversal)
- Нормалізація шляхів

### Directory
- CRUD операції
- Рекурсивне сканування
- Фільтрація файлів
- Перевірка прав доступу

### Upload
- Валідація типів файлів
- Валідація розміру
- Генерація безпечних імен
- Захист від завантаження небезпечних файлів
- Підтримка множинних завантажень

### Image
- Обробка зображень
- Зміна розміру
- Конвертація форматів
- Оптимізація

## Статус

- ✅ Структура створена
- ⏳ Міграція запланована
- 📝 Документація оновлена
