# Security - Безпека, захист, токени

## Призначення

Директорія `Security` містить систему безпеки, включаючи захист від XSS, CSRF, SQL injection, rate limiting та інші механізми безпеки.

## Поточна структура

### `engine/infrastructure/security/` (поточне розташування)

#### Основні класи
- `Security.php` - Основний клас безпеки (XSS, CSRF, валідація)
- `SecurityHeaders.php` - Генерація security headers
- `CSPGenerator.php` - Генерація Content Security Policy
- `RateLimiter.php` - Обмеження частоти запитів
- `RateLimitStrategy.php` - Стратегії rate limiting
- `Hash.php` - Хешування паролів та даних
- `Encryption.php` - Шифрування даних
- `Session.php` - Безпечна робота з сесіями

## План міграції

### Фаза 1: Основні класи
```
engine/infrastructure/security/Security.php → engine/Security/Security.php
engine/infrastructure/security/SecurityHeaders.php → engine/Security/SecurityHeaders.php
engine/infrastructure/security/CSPGenerator.php → engine/Security/CSPGenerator.php
engine/infrastructure/security/RateLimiter.php → engine/Security/RateLimiter.php
engine/infrastructure/security/RateLimitStrategy.php → engine/Security/RateLimitStrategy.php
engine/infrastructure/security/Hash.php → engine/Security/Hash.php
engine/infrastructure/security/Encryption.php → engine/Security/Encryption.php
engine/infrastructure/security/Session.php → engine/Security/Session.php
```

## Структура після міграції

```
engine/Security/
├── Security.php
├── SecurityHeaders.php
├── CSPGenerator.php
├── RateLimiter.php
├── RateLimitStrategy.php
├── Hash.php
├── Encryption.php
└── Session.php
```

## Namespace

Всі класи мають використовувати namespace `Flowaxy\Core\Infrastructure\Security\...`:
- `Flowaxy\Core\Infrastructure\Security\Security`
- `Flowaxy\Core\Infrastructure\Security\SecurityHeaders`
- `Flowaxy\Core\Infrastructure\Security\RateLimiter`

## Функціональність

### Security
- **XSS Protection**: Очищення HTML, escaping
- **CSRF Protection**: Генерація та перевірка токенів
- **Input Sanitization**: Санітизація вводу за типами
- **Validation**: Валідація даних за правилами
- **IP Detection**: Визначення реального IP клієнта

### SecurityHeaders
- Content-Security-Policy (CSP)
- X-Frame-Options
- X-Content-Type-Options
- Strict-Transport-Security (HSTS)
- Referrer-Policy
- Permissions-Policy

### RateLimiter
- Обмеження по IP
- Обмеження по користувачу
- Обмеження по маршруту
- Гнучкі стратегії

### Hash
- Хешування паролів (bcrypt, argon2)
- Хешування даних (SHA-256, SHA-512)
- Перевірка хешів

### Encryption
- Шифрування даних (AES-256)
- Розшифрування
- Генерація ключів

### Session
- Безпечна робота з сесіями
- Регенерація ID сесії
- Захист від session fixation

## Статус

- ✅ Структура створена
- ⏳ Міграція запланована
- 📝 Документація оновлена
