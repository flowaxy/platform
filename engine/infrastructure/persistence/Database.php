<?php

/**
 * Покращений клас для роботи з базою даних
 * Інтегрований з модулем Logger для відстеження повільних запитів та помилок
 *
 * @package Engine\Classes
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/contracts/LoggerInterface.php';
require_once __DIR__ . '/DatabaseInterface.php';

class Database implements DatabaseInterface
{
    private static ?self $instance = null;
    private static bool $connectionLogged = false; // Статичний прапорець для запобігання подвійного логування
    private static bool $isConnecting = false; // Статичний прапорець для запобігання одночасного підключення
    private ?PDO $connection = null;
    private bool $isConnected = false;
    private int $connectionAttempts = 0;
    private int $maxConnectionAttempts = 3;
    private int $connectionTimeout = 3;
    private int $hostCheckTimeout = 1;

    // Статистика запитів
    private array $queryList = [];
    private array $queryErrors = [];
    private int $queryCount = 0;
    private float $totalQueryTime = 0.0;
    private float $slowQueryThreshold = 1.0;

    // Екземпляр Logger
    private ?LoggerInterface $logger = null;

    /**
     * Конструктор (приватний для Singleton)
     * Важливо: НЕ ініціалізуємо Logger в конструкторі, щоб уникнути циклічних залежностей
     */
    private function __construct()
    {
        // НЕ ініціалізуємо logger тут, щоб уникнути рекурсії:
        // Database::__construct() -> initLogger() -> logger() -> Logger::getInstance() -> BaseModule::__construct() -> getDB() -> Database::getInstance()
        // Logger буде завантажено ліниво через getLogger() метод

        // Завантажуємо параметри з налаштувань, якщо доступні
        $this->loadConfigParams();
    }

    /**
     * Завантаження параметрів конфігурації з налаштувань
     *
     * @return void
     */
    private function loadConfigParams(): void
    {
        if (class_exists('SystemConfig')) {
            $systemConfig = SystemConfig::getInstance();
            $this->maxConnectionAttempts = $systemConfig->getDbMaxAttempts();
            $this->connectionTimeout = $systemConfig->getDbConnectionTimeout();
            $this->hostCheckTimeout = $systemConfig->getDbHostCheckTimeout();
            $this->slowQueryThreshold = $systemConfig->getDbSlowQueryThreshold();
        }
    }

    /**
     * Ліниве отримання Logger
     * Викликається тільки коли потрібно логувати, після того як Database вже створено
     *
     * @return LoggerInterface|null
     */
    private function getLogger(): ?LoggerInterface
    {
        if ($this->logger === null) {
            try {
                // Намагаємося отримати Logger через функцію (повертає LoggerInterface)
                /** @var LoggerInterface $loggerFromFunction */
                $loggerFromFunction = logger();
                $this->logger = $loggerFromFunction;
                if ($this->logger === null && class_exists('Logger') && method_exists('Logger', 'getInstance')) {
                    // Logger реалізує LoggerInterface, тому можемо безпечно використовувати
                    /** @var LoggerInterface $loggerInstance */
                    $loggerInstance = Logger::getInstance();
                    $this->logger = $loggerInstance;
                }

                // Поріг повільних запитів вже завантажено з SystemConfig в конструкторі
                // Якщо SystemConfig недоступний, використовуємо Logger як fallback
                if ($this->slowQueryThreshold === 1.0 && $this->logger && method_exists($this->logger, 'getSetting')) {
                    $threshold = (float)$this->logger->getSetting('slow_query_threshold', '1.0');
                    $this->slowQueryThreshold = $threshold;
                }
            } catch (Exception $e) {
                // Logger недоступний, продовжуємо без нього
            }
        }

        return $this->logger;
    }

    /**
     * Отримання екземпляра класу (Singleton)
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Отримання підключення до бази даних
     *
     * @return PDO
     * @throws Exception
     */
    public function getConnection(): PDO
    {
        if (! $this->isConnected || $this->connection === null) {
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * Швидка перевірка доступності хоста
     *
     * @param string $host Хост
     * @param int $port Порт (за замовчуванням 3306 для MySQL)
     * @return bool
     */
    private function checkHostAvailability(string $host, int $port = 3306): bool
    {
        // Розбираємо хост:port якщо вказано
        if (str_contains($host, ':')) {
            [$host, $port] = explode(':', $host, 2);
            $port = (int)$port;
        }

        // Намагаємося швидко перевірити доступність хоста
        try {
            // Використовуємо stream_socket_client з коротким таймаутом
            $context = stream_context_create([
                'socket' => [
                    'connect_timeout' => $this->hostCheckTimeout,
                ],
            ]);

            $socket = @stream_socket_client(
                "tcp://{$host}:{$port}",
                $errno,
                $errstr,
                $this->hostCheckTimeout,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if ($socket !== false) {
                fclose($socket);

                return true;
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Підключення до бази даних
     *
     * @return void
     * @throws Exception
     */
    private function connect(): void
    {
        // Якщо вже підключено, не робимо нічого
        if ($this->isConnected && $this->connection !== null) {
            return;
        }

        // Якщо вже підключаємося в межах одного процесу (через Singleton)
        // Просто повертаємося - підключення завершиться незабаром
        // НЕ блокуємо процес очікуванням - це критично для продуктивності при високому навантаженні
        if (self::$isConnecting) {
            // В межах одного процесу (Singleton) підключення вже відбувається
            // Наступний виклик getConnection() отримає готове підключення
            return;
        }

        // Встановлюємо прапорець підключення ПЕРЕД початком операцій
        // Це запобігає одночасним викликам connect() в межах одного процесу
        self::$isConnecting = true;
        // Спрощена логіка: пріоритет GLOBALS над константами
        // Якщо GLOBALS встановлені (інсталлер), використовуємо їх
        // Інакше використовуємо константи
        if (isset($GLOBALS['_INSTALLER_DB_HOST']) && ! empty($GLOBALS['_INSTALLER_DB_HOST'])) {
            // Інсталлер: використовуємо GLOBALS
            $dbHost = $GLOBALS['_INSTALLER_DB_HOST'];
            $dbName = $GLOBALS['_INSTALLER_DB_NAME'] ?? '';
            $dbUser = $GLOBALS['_INSTALLER_DB_USER'] ?? 'root';
            $dbPass = $GLOBALS['_INSTALLER_DB_PASS'] ?? '';
            $dbCharset = $GLOBALS['_INSTALLER_DB_CHARSET'] ?? 'utf8mb4';
        } else {
            // Звичайна робота: використовуємо константи
            $dbHost = defined('DB_HOST') ? DB_HOST : '';
            $dbName = defined('DB_NAME') ? DB_NAME : '';
            $dbUser = defined('DB_USER') ? DB_USER : 'root';
            $dbPass = defined('DB_PASS') ? DB_PASS : '';
            $dbCharset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
        }

        // Перевіряємо, що конфігурація БД доступна
        if (empty($dbHost) || empty($dbName)) {
            $this->isConnected = false;
            $this->connection = null;

            throw new Exception('Конфігурація бази даних не встановлена');
        }

        if ($this->connectionAttempts >= $this->maxConnectionAttempts) {
            $error = 'Перевищено максимальну кількість спроб підключення до бази даних';
            $this->logError('Помилка підключення до бази даних', ['error' => $error, 'attempts' => $this->connectionAttempts]);

            throw new Exception($error);
        }

        $this->connectionAttempts++;
        $timeStart = $this->getRealTime();

        // Швидка перевірка доступності хоста перед підключенням PDO
        $host = $dbHost;
        $port = 3306;

        // Розбираємо хост:port якщо вказано
        if (str_contains($host, ':')) {
            [$host, $port] = explode(':', $host, 2);
            $port = (int)$port;
        }

        // Перевіряємо доступність хоста
        if (! $this->checkHostAvailability($host, $port)) {
            $connectionTime = $this->getRealTime() - $timeStart;
            $error = "Сервер бази даних недоступний (хост: {$host}, порт: {$port})";

            $errorContext = [
                'error' => $error,
                'host' => $host,
                'port' => $port,
                'attempt' => $this->connectionAttempts,
                'connection_time' => round($connectionTime, 4),
            ];

            $this->logError('Хост бази даних недоступний', $errorContext);

            throw new Exception($error);
        }

        try {
            // Використовуємо змінні замість констант (для підтримки встановлювача)
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $host,
                $port,
                $dbName,
                $dbCharset
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $dbCharset . ' COLLATE utf8mb4_unicode_ci',
                PDO::ATTR_TIMEOUT => $this->connectionTimeout,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ];

            // Встановлюємо короткий таймаут через ini_set для socket
            $oldTimeout = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', (string)$this->connectionTimeout);

            try {
                $this->connection = new PDO($dsn, $dbUser, $dbPass, $options);
            } finally {
                // Відновлюємо попередній таймаут
                ini_set('default_socket_timeout', $oldTimeout);
            }
            $this->isConnected = true;
            $this->connectionAttempts = 0;

            $connectionTime = $this->getRealTime() - $timeStart;

            // Оптимізація MySQL налаштувань
            $this->connection->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            $this->connection->exec("SET SESSION time_zone = '+00:00'");
            $this->connection->exec('SET SESSION sql_auto_is_null = 0');

            // Скидаємо прапорець підключення
            self::$isConnecting = false;

            // Логуємо підключення тільки один раз (використовуємо статичний прапорець)
            if (! self::$connectionLogged) {
                self::$connectionLogged = true; // Встановлюємо одразу, щоб запобігти подвійному логуванню
                $this->logQuery('Підключення до MySQL сервера', [], $connectionTime, false);

                // Отримуємо версію MySQL (для внутрішнього використання)
                $version = $this->connection->query('SELECT VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
            }

        } catch (PDOException $e) {
            // Скидаємо прапорець підключення в разі помилки
            self::$isConnecting = false;

            $this->isConnected = false;
            $this->connection = null;
            $connectionTime = $this->getRealTime() - $timeStart;

            // Визначаємо причину помилки
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();

            // Якщо таймаут або хост недоступний, не повторюємо спроби
            $isTimeout = str_contains($errorMessage, 'timeout')
                      || str_contains($errorMessage, 'timed out')
                      || str_contains($errorMessage, 'Connection refused')
                      || $errorCode == 2002 // SQLSTATE[HY000] [2002] Connection refused
                      || $errorCode == 2003; // SQLSTATE[HY000] [2003] Can't connect to MySQL server

            $errorContext = [
                'error' => $errorMessage,
                'code' => $errorCode,
                'attempt' => $this->connectionAttempts,
                'connection_time' => round($connectionTime, 4),
                'host' => $host,
                'port' => $port,
                'database' => $dbName,
            ];

            $this->logError('Помилка підключення до бази даних', $errorContext);

            // Якщо таймаут або хост недоступний, або перевищено кількість спроб - викидаємо виняток одразу
            if ($isTimeout || $this->connectionAttempts >= $this->maxConnectionAttempts) {
                $finalError = $isTimeout
                    ? "Сервер бази даних недоступний (таймаут підключення: {$host}:{$port})"
                    : 'Помилка підключення до бази даних після ' . $this->maxConnectionAttempts . ' спроб: ' . $errorMessage;

                throw new Exception($finalError);
            }

            // Повторна спроба підключення тільки якщо не таймаут
            // Зменшуємо затримку для швидкої відповіді
            usleep(100000); // 0.1 секунди замість 1 секунди
            $this->connect();
        }
    }

    /**
     * Виконання підготовленого запиту
     *
     * @param string $query SQL запит
     * @param array<string|int, mixed> $params Параметри для підготовленого запиту
     * @param bool $logQuery Логувати запит
     * @return PDOStatement
     * @throws PDOException
     */
    /**
     * Виконання SQL-запиту
     *
     * @param string $query SQL-запит
     * @param array<string|int, mixed> $params Параметри для підготовленого запиту
     * @param bool $logQuery Чи логувати запит
     * @return PDOStatement
     */
    public function query(string $query, array $params = [], bool $logQuery = true): PDOStatement
    {
        $timeStart = $this->getRealTime();

        try {
            if (! $this->isConnected || $this->connection === null) {
                $this->connect();
            }

            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);

            $executionTime = $this->getRealTime() - $timeStart;
            $this->totalQueryTime += $executionTime;
            $this->queryCount++;

            if ($logQuery) {
                $this->logQuery($query, $params, $executionTime);

                // Перевірка на повільний запит
                if ($executionTime >= $this->slowQueryThreshold) {
                    $this->logSlowQuery($query, $params, $executionTime);
                }
            }

            return $stmt;

        } catch (PDOException $e) {
            $executionTime = $this->getRealTime() - $timeStart;

            $errorContext = [
                'query' => $query,
                'params' => $params,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'execution_time' => round($executionTime, 4),
            ];

            // Викликаємо хук для логування помилок БД
            if ($this->getSetting('log_db_errors', '1') === '1') {
                hook_dispatch('db_error', $errorContext);
            }

            $this->logError('Помилка запиту до бази даних', $errorContext);
            $this->queryErrors[] = $errorContext;

            throw $e;
        }
    }

    /**
     * Отримання одного рядка
     *
     * @param string $query SQL запит
     * @param array<string|int, mixed> $params Параметри
     * @return array<string, mixed>|false
     */
    public function getRow(string $query, array $params = []): array|false
    {
        $stmt = $this->query($query, $params);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Отримання всіх рядків
     *
     * @param string $query SQL запит
     * @param array<string|int, mixed> $params Параметри
     * @return array<int, array<string, mixed>>
     */
    public function getAll(string $query, array $params = []): array
    {
        $stmt = $this->query($query, $params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Отримання одного значення (першого стовпця першого рядка)
     *
     * @param string $query SQL запит
     * @param array $params Параметри
     * @return mixed
     */
    public function getValue(string $query, array $params = []): mixed
    {
        $stmt = $this->query($query, $params);

        return $stmt->fetchColumn();
    }

    /**
     * Вставка запису та отримання ID
     *
     * @param string $query SQL запит INSERT
     * @param array<string|int, mixed> $params Параметри
     * @return int|false ID вставленого запису або false при помилці
     */
    public function insert(string $query, array $params = []): int|false
    {
        try {
            $stmt = $this->query($query, $params);

            return (int)$this->connection->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Оновлення/видалення записів
     *
     * @param string $query SQL запит UPDATE/DELETE
     * @param array<string|int, mixed> $params Параметри
     * @return int Кількість залучених рядків
     */
    public function execute(string $query, array $params = []): int
    {
        $stmt = $this->query($query, $params);

        return $stmt->rowCount();
    }

    /**
     * Виконання транзакції
     *
     * @param callable $callback Функція для виконання в транзакції
     * @return mixed
     * @throws Exception
     */
    public function transaction(callable $callback)
    {
        if ($this->connection === null) {
            throw new Exception('Немає підключення до бази даних');
        }

        $timeStart = $this->getRealTime();

        try {
            $this->connection->beginTransaction();
            $result = $callback($this->connection);
            $this->connection->commit();

            $executionTime = $this->getRealTime() - $timeStart;
            $this->logInfo('Транзакцію зафіксовано', ['execution_time' => round($executionTime, 4)]);

            return $result;
        } catch (Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            $executionTime = $this->getRealTime() - $timeStart;
            $this->logError('Транзакцію відкочено', [
                'error' => $e->getMessage(),
                'execution_time' => round($executionTime, 4),
            ]);

            throw $e;
        }
    }

    /**
     * Безпечне екранування рядка
     *
     * @param string $string
     * @return string
     */
    public function escape(string $string): string
    {
        if ($this->connection === null) {
            return addslashes($string);
        }

        return substr($this->connection->quote($string), 1, -1);
    }

    /**
     * Логування запиту
     *
     * @param string $query SQL запит
     * @param array<string|int, mixed> $params Параметри
     * @param float $executionTime Час виконання
     * @param bool $isUserQuery Чи це користувацький запит
     * @return void
     */
    private function logQuery(string $query, array $params, float $executionTime, bool $isUserQuery = true): void
    {
        // Логуємо системні запити тільки якщо увімкнено
        if (! $isUserQuery && $this->getSetting('log_db_queries', '0') !== '1') {
            return;
        }

        $queryInfo = [
            'query' => $this->normalizeQuery($query),
            'params' => $params,
            'time' => round($executionTime, 4),
            'num' => $this->queryCount,
        ];

        $this->queryList[] = $queryInfo;

        // Обмежуємо розмір списку запитів (оптимізація пам'яті)
        if (count($this->queryList) > 100) {
            array_shift($this->queryList);
        }

        // Логуємо через Logger, якщо увімкнено опцію логування запитів
        if ($this->getSetting('log_db_queries', '0') === '1') {
            // Викликаємо хук для логування
            hook_dispatch('db_query', $queryInfo);
            $this->logDebug('Запит до бази даних', $queryInfo);
        }
    }

    /**
     * Логування повільного запиту
     *
     * @param string $query SQL запит
     * @param array<string|int, mixed> $params Параметри
     * @param float $executionTime Час виконання
     * @return void
     */
    private function logSlowQuery(string $query, array $params, float $executionTime): void
    {
        $slowQueryInfo = [
            'query' => $this->normalizeQuery($query),
            'params' => $params,
            'execution_time' => round($executionTime, 4),
            'threshold' => $this->slowQueryThreshold,
        ];

        // Викликаємо хук для логування повільних запитів
        if ($this->getSetting('log_slow_queries', '1') === '1') {
            hook_dispatch('db_slow_query', $slowQueryInfo);
        }

        $this->logWarning('Виявлено повільний запит до бази даних', $slowQueryInfo);
    }

    /**
     * Нормалізація SQL запиту (видалення зайвих пробілів)
     *
     * @param string $query SQL запит
     * @return string
     */
    private function normalizeQuery(string $query): string
    {
        return preg_replace('/\s+/', ' ', trim($query));
    }

    /**
     * Отримання реального часу (мікросекунди)
     *
     * @return float
     */
    private function getRealTime(): float
    {
        [$seconds, $microSeconds] = explode(' ', microtime());

        return ((float)$seconds + (float)$microSeconds);
    }

    /**
     * Отримання налаштування
     *
     * @param string $key Ключ налаштування
     * @param string $default Значення за замовчуванням
     * @return string
     */
    private function getSetting(string $key, string $default = ''): string
    {
        // Використовуємо налаштування Logger, якщо доступні
        $logger = $this->getLogger();
        if ($logger && method_exists($logger, 'getSetting')) {
            $loggerKey = str_starts_with($key, 'logger_') ? substr($key, 7) : $key;
            $value = $logger->getSetting($loggerKey, $default);

            return $value !== null ? (string)$value : $default;
        }

        // Fallback на SettingsManager
        if (class_exists('SettingsManager')) {
            $settingKey = 'logger_' . $key;

            return settingsManager()->get($settingKey, $default);
        }

        return $default;
    }

    /**
     * Логирование через Logger
     */
    private function logError(string $message, array $context = []): void
    {
        $logger = $this->getLogger();
        if ($logger && method_exists($logger, 'logError')) {
            $logger->logError($message, $context);
        }
    }

    private function logWarning(string $message, array $context = []): void
    {
        $logger = $this->getLogger();
        if ($logger && method_exists($logger, 'logWarning')) {
            $logger->logWarning($message, $context);
        }
    }

    private function logInfo(string $message, array $context = []): void
    {
        $logger = $this->getLogger();
        if ($logger && method_exists($logger, 'logInfo')) {
            $logger->logInfo($message, $context);
        }
    }

    private function logDebug(string $message, array $context = []): void
    {
        $logger = $this->getLogger();
        if ($logger && method_exists($logger, 'logDebug')) {
            $logger->logDebug($message, $context);
        }
    }

    /**
     * Перевірка доступності БД
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        // Перевіряємо, що константи БД визначені та не порожні
        if (! defined('DB_HOST') || empty(DB_HOST) || ! defined('DB_NAME') || empty(DB_NAME)) {
            return false;
        }

        try {
            if (! $this->isConnected || $this->connection === null) {
                $this->connect();
            }

            // Перевіряємо підключення простим запитом
            if ($this->connection === null) {
                return false;
            }

            $stmt = $this->connection->query('SELECT 1');

            return $stmt !== false;
        } catch (Exception $e) {
            $this->isConnected = false;
            $this->connection = null;
            // Не логуємо помилку, якщо конфігурація не встановлена (це нормально для встановлювача)
            if (defined('DB_HOST') && ! empty(DB_HOST)) {
                $this->logError('Перевірка доступності бази даних не вдалася', ['error' => $e->getMessage()]);
            }

            return false;
        }
    }

    /**
     * Перевірка існування бази даних
     *
     * @return bool
     */
    public function databaseExists(): bool
    {
        try {
            // Спрощена логіка: пріоритет GLOBALS над константами
            if (isset($GLOBALS['_INSTALLER_DB_HOST']) && ! empty($GLOBALS['_INSTALLER_DB_HOST'])) {
                // Інсталлер: використовуємо GLOBALS
                $dbHost = $GLOBALS['_INSTALLER_DB_HOST'];
                $dbName = $GLOBALS['_INSTALLER_DB_NAME'] ?? '';
                $dbUser = $GLOBALS['_INSTALLER_DB_USER'] ?? 'root';
                $dbPass = $GLOBALS['_INSTALLER_DB_PASS'] ?? '';
                $dbCharset = $GLOBALS['_INSTALLER_DB_CHARSET'] ?? 'utf8mb4';
            } else {
                // Звичайна робота: використовуємо константи
                $dbHost = defined('DB_HOST') ? DB_HOST : '';
                $dbName = defined('DB_NAME') ? DB_NAME : '';
                $dbUser = defined('DB_USER') ? DB_USER : 'root';
                $dbPass = defined('DB_PASS') ? DB_PASS : '';
                $dbCharset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
            }

            if (empty($dbHost)) {
                return false;
            }

            // Підключаємося без вказання бази даних
            $host = $dbHost;
            $port = 3306;

            if (str_contains($host, ':')) {
                [$host, $port] = explode(':', $host, 2);
                $port = (int)$port;
            }

            $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $dbCharset);
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2,
            ];

            $tempConnection = new PDO($dsn, $dbUser, $dbPass, $options);

            $stmt = $tempConnection->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
            $stmt->execute([$dbName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Перевірка активного з'єднання
     *
     * @return bool
     */
    public function ping(): bool
    {
        try {
            if ($this->connection === null) {
                return false;
            }

            $this->connection->query('SELECT 1');

            return true;
        } catch (PDOException $e) {
            $this->isConnected = false;
            $this->connection = null;

            return false;
        }
    }

    /**
     * Закриття з'єднання
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->connection = null;
        $this->isConnected = false;
        $this->connectionAttempts = 0;
    }

    /**
     * Отримання статистики
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $stats = [
            'connected' => $this->isConnected,
            'query_count' => $this->queryCount,
            'total_query_time' => round($this->totalQueryTime, 4),
            'average_query_time' => $this->queryCount > 0 ? round($this->totalQueryTime / $this->queryCount, 4) : 0,
            'slow_queries' => 0,
            'error_count' => count($this->queryErrors),
            'query_list_size' => count($this->queryList),
        ];

        // Підрахунок повільних запитів
        foreach ($this->queryList as $query) {
            if (isset($query['time']) && $query['time'] >= $this->slowQueryThreshold) {
                $stats['slow_queries']++;
            }
        }

        // Статистика MySQL
        if ($this->connection !== null) {
            try {
                $stmt = $this->connection->query("SHOW STATUS WHERE Variable_name IN ('Threads_connected', 'Threads_running', 'Uptime', 'Questions', 'Slow_queries')");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $key = strtolower($row['Variable_name']);
                    $stats['mysql_' . $key] = (int)($row['Value'] ?? 0);
                }
            } catch (PDOException $e) {
                // Ігноруємо помилки отримання статистики
            }
        }

        return $stats;
    }

    /**
     * Отримання списку запитів
     *
     * @return array<int, array<string, mixed>>
     */
    public function getQueryList(): array
    {
        return $this->queryList;
    }

    /**
     * Отримання списку помилок
     *
     * @return array<int, array<string, mixed>>
     */
    public function getQueryErrors(): array
    {
        return $this->queryErrors;
    }

    /**
     * Встановлення порогу повільних запитів
     *
     * @param float $seconds Секунди
     * @return void
     */
    public function setSlowQueryThreshold(float $seconds): void
    {
        $this->slowQueryThreshold = $seconds;

        // Зберігаємо в налаштування Logger, якщо доступний
        $logger = $this->getLogger();
        if ($logger && method_exists($logger, 'setSetting')) {
            $logger->setSetting('slow_query_threshold', (string)$seconds);
        }
    }

    /**
     * Очищення статистики
     *
     * @return void
     */
    public function clearStats(): void
    {
        $this->queryList = [];
        $this->queryErrors = [];
        $this->queryCount = 0;
        $this->totalQueryTime = 0.0;
    }

    // Запобігання клонуванню
    private function __clone()
    {
    }

    // Запобігання десеріалізації
    public function __wakeup(): void
    {
        throw new Exception('Неможливо десеріалізувати singleton');
    }
}
