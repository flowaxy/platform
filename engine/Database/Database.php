<?php

/**
 * Покращений клас для роботи з базою даних
 * Інтегрований з модулем Logger для відстеження повільних запитів та помилок
 *
 * @package Flowaxy\Core\Infrastructure\Persistence
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Infrastructure\Persistence;

// LoggerInterface завантажується через autoloader
require_once __DIR__ . '/DatabaseInterface.php';
require_once __DIR__ . '/QueryBuilder.php';
require_once __DIR__ . '/ConnectionPool.php';

class Database implements DatabaseInterface
{
    private static ?self $instance = null;
    private static bool $connectionLogged = false;
    private static bool $isConnecting = false;
    private ?\PDO $connection = null;
    private bool $isConnected = false;
    private ?ConnectionPool $connectionPool = null;
    private bool $useConnectionPool = false;
    private int $connectionAttempts = 0;
    private int $maxConnectionAttempts = 3;
    private int $connectionTimeout = 3;
    private int $hostCheckTimeout = 1;

    private array $queryList = [];
    private array $queryErrors = [];
    private int $queryCount = 0;
    private float $totalQueryTime = 0.0;
    private float $slowQueryThreshold = 1.0;

    private ?object $logger = null;

    private function __construct()
    {
        $this->loadConfigParams();
    }

    private function loadConfigParams(): void
    {
        if (class_exists('SystemConfig')) {
            $systemConfig = \SystemConfig::getInstance();
            $this->maxConnectionAttempts = $systemConfig->getDbMaxAttempts();
            $this->connectionTimeout = $systemConfig->getDbConnectionTimeout();
            $this->hostCheckTimeout = $systemConfig->getDbHostCheckTimeout();
            $this->slowQueryThreshold = $systemConfig->getDbSlowQueryThreshold();
        }
    }

    private function getLogger(): ?object
    {
        if ($this->logger === null) {
            try {
                if (function_exists('logger')) {
                    $loggerFromFunction = logger();
                    $this->logger = $loggerFromFunction;
                }
                if ($this->logger === null && class_exists('Logger') && method_exists('Logger', 'getInstance')) {
                    $loggerInstance = \Logger::getInstance();
                    $this->logger = $loggerInstance;
                }
                if ($this->slowQueryThreshold === 1.0 && $this->logger && method_exists($this->logger, 'getSetting')) {
                    $threshold = (float)$this->logger->getSetting('slow_query_threshold', '1.0');
                    $this->slowQueryThreshold = $threshold;
                }
            } catch (\Exception $e) {
            }
        }

        return $this->logger;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): \PDO
    {
        if ($this->useConnectionPool && $this->connectionPool !== null) {
            return $this->connectionPool->get();
        }

        if (! $this->isConnected || $this->connection === null) {
            $this->connect();
        }

        return $this->connection;
    }

    public function setConnectionPool(ConnectionPool $pool): void
    {
        $this->connectionPool = $pool;
        $this->useConnectionPool = true;
    }

    public function releaseConnection(\PDO $connection): void
    {
        if ($this->useConnectionPool && $this->connectionPool !== null) {
            $this->connectionPool->release($connection);
        }
    }

    private function checkHostAvailability(string $host, int $port = 3306): bool
    {
        if (str_contains($host, ':')) {
            [$host, $port] = explode(':', $host, 2);
            $port = (int)$port;
        }

        try {
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
        } catch (\Exception $e) {
            return false;
        }
    }

    private function connect(): void
    {
        if ($this->isConnected && $this->connection !== null) {
            return;
        }

        if (self::$isConnecting) {
            return;
        }

        self::$isConnecting = true;

        if (isset($GLOBALS['_INSTALLER_DB_HOST']) && ! empty($GLOBALS['_INSTALLER_DB_HOST'])) {
            $dbHost = $GLOBALS['_INSTALLER_DB_HOST'];
            $dbName = $GLOBALS['_INSTALLER_DB_NAME'] ?? '';
            $dbUser = $GLOBALS['_INSTALLER_DB_USER'] ?? 'root';
            $dbPass = $GLOBALS['_INSTALLER_DB_PASS'] ?? '';
            $dbCharset = $GLOBALS['_INSTALLER_DB_CHARSET'] ?? 'utf8mb4';
        } else {
            $dbHost = defined('DB_HOST') ? DB_HOST : '';
            $dbName = defined('DB_NAME') ? DB_NAME : '';
            $dbUser = defined('DB_USER') ? DB_USER : 'root';
            $dbPass = defined('DB_PASS') ? DB_PASS : '';
            $dbCharset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
        }

        if (empty($dbHost) || empty($dbName)) {
            $this->isConnected = false;
            $this->connection = null;
            throw new \Exception('Конфігурація бази даних не встановлена');
        }

        if ($this->connectionAttempts >= $this->maxConnectionAttempts) {
            $error = 'Перевищено максимальну кількість спроб підключення до бази даних';
            $this->logError('Помилка підключення до бази даних', ['error' => $error, 'attempts' => $this->connectionAttempts]);
            throw new \Exception($error);
        }

        $this->connectionAttempts++;
        $timeStart = $this->getRealTime();

        $host = $dbHost;
        $port = 3306;

        if (str_contains($host, ':')) {
            [$host, $port] = explode(':', $host, 2);
            $port = (int)$port;
        }

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
            throw new \Exception($error);
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $host,
                $port,
                $dbName,
                $dbCharset
            );

            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_PERSISTENT => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $dbCharset . ' COLLATE utf8mb4_unicode_ci',
                \PDO::ATTR_TIMEOUT => $this->connectionTimeout,
                \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
            ];

            $oldTimeout = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', (string)$this->connectionTimeout);

            try {
                if ($this->useConnectionPool && $this->connectionPool === null) {
                    $this->connectionPool = new ConnectionPool(
                        $dsn,
                        $dbUser,
                        $dbPass,
                        $options,
                        10
                    );
                    $this->connection = $this->connectionPool->get();
                } else {
                    $this->connection = new \PDO($dsn, $dbUser, $dbPass, $options);
                }
            } finally {
                ini_set('default_socket_timeout', $oldTimeout);
            }
            $this->isConnected = true;
            $this->connectionAttempts = 0;

            $connectionTime = $this->getRealTime() - $timeStart;

            $this->connection->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            $this->connection->exec("SET SESSION time_zone = '+00:00'");
            $this->connection->exec('SET SESSION sql_auto_is_null = 0');

            self::$isConnecting = false;

            if (! self::$connectionLogged) {
                self::$connectionLogged = true;
                $this->logQuery('Підключення до MySQL сервера', [], $connectionTime, false);
                // Додаємо DEBUG логування для підключення
                $this->logDebug('Підключення до бази даних успішне', [
                    'host' => $host,
                    'port' => $port,
                    'database' => $dbName,
                    'connection_time' => round($connectionTime, 4),
                ]);
            }

        } catch (\PDOException $e) {
            self::$isConnecting = false;
            $this->isConnected = false;
            $this->connection = null;
            $connectionTime = $this->getRealTime() - $timeStart;

            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();

            $isTimeout = str_contains($errorMessage, 'timeout')
                      || str_contains($errorMessage, 'timed out')
                      || str_contains($errorMessage, 'Connection refused')
                      || $errorCode == 2002
                      || $errorCode == 2003;

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

            if ($isTimeout || $this->connectionAttempts >= $this->maxConnectionAttempts) {
                $finalError = $isTimeout
                    ? "Сервер бази даних недоступний (таймаут підключення: {$host}:{$port})"
                    : 'Помилка підключення до бази даних після ' . $this->maxConnectionAttempts . ' спроб: ' . $errorMessage;
                throw new \Exception($finalError);
            }

            usleep(100000);
            $this->connect();
        }
    }

    public function query(string $query, array $params = [], bool $logQuery = true): \PDOStatement
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

                // Логуємо SQL запит через logSql() якщо налаштовано
                if (function_exists('logSql')) {
                    logSql($query, $params, $executionTime);
                } else {
                    $logger = $this->getLogger();
                    if ($logger && method_exists($logger, 'logSql')) {
                        $logger->logSql($query, $params, $executionTime);
                    }
                }

                // Логуємо успішний запит як INFO
                $this->logInfo('SQL запит виконано успішно', [
                    'query' => $this->normalizeQuery($query),
                    'execution_time' => round($executionTime, 4),
                    'query_count' => $this->queryCount,
                ]);

                if ($executionTime >= $this->slowQueryThreshold) {
                    $this->logSlowQuery($query, $params, $executionTime);
                }
            }

            return $stmt;

        } catch (\PDOException $e) {
            $executionTime = $this->getRealTime() - $timeStart;

            $errorContext = [
                'query' => $query,
                'params' => $params,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'execution_time' => round($executionTime, 4),
            ];

            if ($this->getSetting('log_db_errors', '1') === '1') {
                if (function_exists('hook_dispatch')) {
                    hook_dispatch('db_error', $errorContext);
                }
            }

            // Логуємо помилку БД через logDbError()
            if (function_exists('logDbError')) {
                logDbError('Помилка запиту до бази даних: ' . $e->getMessage(), $errorContext);
            } else {
                $logger = $this->getLogger();
                if ($logger && method_exists($logger, 'logDbError')) {
                    $logger->logDbError('Помилка запиту до бази даних: ' . $e->getMessage(), $errorContext);
                } else {
                    $this->logError('Помилка запиту до бази даних', $errorContext);
                }
            }

            $this->queryErrors[] = $errorContext;

            throw $e;
        }
    }

    public function getRow(string $query, array $params = []): array|false
    {
        $stmt = $this->query($query, $params);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function getAll(string $query, array $params = []): array
    {
        $stmt = $this->query($query, $params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getValue(string $query, array $params = []): mixed
    {
        $stmt = $this->query($query, $params);
        return $stmt->fetchColumn();
    }

    public function insert(string $query, array $params = []): int|false
    {
        try {
            $stmt = $this->query($query, $params);
            $this->invalidateTableCache($query);
            return (int)$this->connection->lastInsertId();
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function execute(string $query, array $params = []): int
    {
        $stmt = $this->query($query, $params);
        $this->invalidateTableCache($query);
        return $stmt->rowCount();
    }

    public function cachedQuery(string $query, array $params = [], int $ttl = 3600): array
    {
        $cacheKey = 'db_query_' . md5($query . serialize($params));

        if (function_exists('cache')) {
            $cache = \cache();
            $cached = $cache->get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        $results = $this->getAll($query, $params);

        if (function_exists('cache')) {
            $cache = \cache();
            $cache->set($cacheKey, $results, $ttl);
        }

        return $results;
    }

    private function invalidateCache(string $table): void
    {
        if (function_exists('cache')) {
            $cache = \cache();
            $cache->delete($table . '_query_cache');
        }
    }

    private function invalidateTableCache(string $query): void
    {
        if (preg_match('/(?:FROM|INTO|UPDATE|DELETE\s+FROM)\s+`?(\w+)`?/i', $query, $matches)) {
            $table = $matches[1];
            $this->invalidateCache($table);
        }
    }

    public function transaction(callable $callback)
    {
        if ($this->connection === null) {
            throw new \Exception('Немає підключення до бази даних');
        }

        $timeStart = $this->getRealTime();

        try {
            $this->logDebug('Початок транзакції');
            $this->connection->beginTransaction();
            $result = $callback($this->connection);
            $this->connection->commit();

            $executionTime = $this->getRealTime() - $timeStart;
            $this->logInfo('Транзакцію зафіксовано', ['execution_time' => round($executionTime, 4)]);
            $this->logDebug('Транзакцію завершено успішно', ['execution_time' => round($executionTime, 4)]);

            return $result;
        } catch (\Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            $executionTime = $this->getRealTime() - $timeStart;
            $this->logError('Транзакцію відкочено', [
                'error' => $e->getMessage(),
                'execution_time' => round($executionTime, 4),
            ]);
            $this->logDebug('Транзакцію відкочено через помилку', [
                'error' => $e->getMessage(),
                'execution_time' => round($executionTime, 4),
            ]);

            throw $e;
        }
    }

    public function escape(string $string): string
    {
        if ($this->connection === null) {
            return addslashes($string);
        }

        return substr($this->connection->quote($string), 1, -1);
    }

    private function logQuery(string $query, array $params, float $executionTime, bool $isUserQuery = true): void
    {
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

        if (count($this->queryList) > 100) {
            array_shift($this->queryList);
        }

        if ($this->getSetting('log_db_queries', '0') === '1') {
            if (function_exists('hook_dispatch')) {
                hook_dispatch('db_query', $queryInfo);
            }
            $this->logDebug('Запит до бази даних', $queryInfo);
        }
    }

    private function logSlowQuery(string $query, array $params, float $executionTime): void
    {
        $slowQueryInfo = [
            'query' => $this->normalizeQuery($query),
            'params' => $params,
            'execution_time' => round($executionTime, 4),
            'threshold' => $this->slowQueryThreshold,
            'type' => 'slow_query',
        ];

        if ($this->getSetting('log_slow_queries', '1') === '1') {
            if (function_exists('hook_dispatch')) {
                hook_dispatch('db_slow_query', $slowQueryInfo);
            }
        }

        // Використовуємо logger()->logWarning() з правильним контекстом
        $logger = $this->getLogger();
        if ($logger && method_exists($logger, 'logWarning')) {
            $logger->logWarning('Виявлено повільний запит до бази даних', $slowQueryInfo);
        } else {
            $this->logWarning('Виявлено повільний запит до бази даних', $slowQueryInfo);
        }
    }

    private function normalizeQuery(string $query): string
    {
        return preg_replace('/\s+/', ' ', trim($query));
    }

    private function getRealTime(): float
    {
        [$seconds, $microSeconds] = explode(' ', microtime());
        return ((float)$seconds + (float)$microSeconds);
    }

    private function getSetting(string $key, string $default = ''): string
    {
        $logger = $this->getLogger();
        if ($logger && method_exists($logger, 'getSetting')) {
            $loggerKey = str_starts_with($key, 'logger_') ? substr($key, 7) : $key;
            $value = $logger->getSetting($loggerKey, $default);
            return $value !== null ? (string)$value : $default;
        }

        if (class_exists('SettingsManager')) {
            $settingKey = 'logger_' . $key;
            return settingsManager()->get($settingKey, $default);
        }

        return $default;
    }

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

    public function isAvailable(): bool
    {
        if (! defined('DB_HOST') || empty(DB_HOST) || ! defined('DB_NAME') || empty(DB_NAME)) {
            return false;
        }

        try {
            if (! $this->isConnected || $this->connection === null) {
                $this->connect();
            }

            if ($this->connection === null) {
                return false;
            }

            $stmt = $this->connection->query('SELECT 1');
            return $stmt !== false;
        } catch (\Exception $e) {
            $this->isConnected = false;
            $this->connection = null;
            if (defined('DB_HOST') && ! empty(DB_HOST)) {
                $this->logError('Перевірка доступності бази даних не вдалася', ['error' => $e->getMessage()]);
            }
            return false;
        }
    }

    public function databaseExists(): bool
    {
        try {
            if (isset($GLOBALS['_INSTALLER_DB_HOST']) && ! empty($GLOBALS['_INSTALLER_DB_HOST'])) {
                $dbHost = $GLOBALS['_INSTALLER_DB_HOST'];
                $dbName = $GLOBALS['_INSTALLER_DB_NAME'] ?? '';
                $dbUser = $GLOBALS['_INSTALLER_DB_USER'] ?? 'root';
                $dbPass = $GLOBALS['_INSTALLER_DB_PASS'] ?? '';
                $dbCharset = $GLOBALS['_INSTALLER_DB_CHARSET'] ?? 'utf8mb4';
            } else {
                $dbHost = defined('DB_HOST') ? DB_HOST : '';
                $dbName = defined('DB_NAME') ? DB_NAME : '';
                $dbUser = defined('DB_USER') ? DB_USER : 'root';
                $dbPass = defined('DB_PASS') ? DB_PASS : '';
                $dbCharset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
            }

            if (empty($dbHost)) {
                return false;
            }

            $host = $dbHost;
            $port = 3306;

            if (str_contains($host, ':')) {
                [$host, $port] = explode(':', $host, 2);
                $port = (int)$port;
            }

            $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $dbCharset);
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 2,
            ];

            $tempConnection = new \PDO($dsn, $dbUser, $dbPass, $options);

            $stmt = $tempConnection->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
            $stmt->execute([$dbName]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function ping(): bool
    {
        try {
            if ($this->connection === null) {
                return false;
            }

            $this->connection->query('SELECT 1');
            return true;
        } catch (\PDOException $e) {
            $this->isConnected = false;
            $this->connection = null;
            return false;
        }
    }

    public function disconnect(): void
    {
        $this->connection = null;
        $this->isConnected = false;
        $this->connectionAttempts = 0;
    }

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

        foreach ($this->queryList as $query) {
            if (isset($query['time']) && $query['time'] >= $this->slowQueryThreshold) {
                $stats['slow_queries']++;
            }
        }

        if ($this->connection !== null) {
            try {
                $stmt = $this->connection->query("SHOW STATUS WHERE Variable_name IN ('Threads_connected', 'Threads_running', 'Uptime', 'Questions', 'Slow_queries')");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $key = strtolower($row['Variable_name']);
                    $stats['mysql_' . $key] = (int)($row['Value'] ?? 0);
                }
            } catch (\PDOException $e) {
            }
        }

        return $stats;
    }

    public function getQueryList(): array
    {
        return $this->queryList;
    }

    public function getQueryErrors(): array
    {
        return $this->queryErrors;
    }

    public function setSlowQueryThreshold(float $seconds): void
    {
        $this->slowQueryThreshold = $seconds;

        $logger = $this->getLogger();
        if ($logger && method_exists($logger, 'setSetting')) {
            $logger->setSetting('slow_query_threshold', (string)$seconds);
        }
    }

    public function clearStats(): void
    {
        $this->queryList = [];
        $this->queryErrors = [];
        $this->queryCount = 0;
        $this->totalQueryTime = 0.0;
    }

    public function queryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this);
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new \Exception('Неможливо десеріалізувати singleton');
    }
}
