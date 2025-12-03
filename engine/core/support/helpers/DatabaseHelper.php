<?php

/**
 * Хелпер для роботи з базою даних
 * Обгортка над Database класом
 *
 * @package Engine\Classes\Helpers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../infrastructure/persistence/DatabaseInterface.php';

class DatabaseHelper
{
    /**
     * Отримання підключення до БД
     *
     * @param bool $showError Показувати сторінку помилки
     * @return PDO|null
     */
    public static function getConnection(bool $showError = true): ?PDO
    {
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

        // Перевіряємо, що конфігурація БД доступна
        if (empty($dbHost) || empty($dbName)) {
            if ($showError && php_sapi_name() !== 'cli') {
                // Не показуємо помилку БД, якщо конфігурація не встановлена (це нормально для встановлювача)
                // Просто повертаємо null
            }

            return null;
        }

        // Встановлюємо GLOBALS для Database класу (якщо ще не встановлені)
        if (! isset($GLOBALS['_INSTALLER_DB_HOST'])) {
            $GLOBALS['_INSTALLER_DB_HOST'] = $dbHost;
            $GLOBALS['_INSTALLER_DB_NAME'] = $dbName;
            $GLOBALS['_INSTALLER_DB_USER'] = $dbUser;
            $GLOBALS['_INSTALLER_DB_PASS'] = $dbPass;
            $GLOBALS['_INSTALLER_DB_CHARSET'] = $dbCharset;
        }

        try {
            if (! class_exists('Database')) {
                // Якщо Database ще не завантажено, намагаємося завантажити з нового розташування
                $dbFile = __DIR__ . '/../../../infrastructure/persistence/Database.php';
                if (file_exists($dbFile)) {
                    require_once $dbFile;
                }
            }

            return Database::getInstance()->getConnection();
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('Помилка підключення до бази даних', ['error' => $e->getMessage()]);
            }

            if ($showError && php_sapi_name() !== 'cli') {
                $errorDetails = [
                    'host' => $dbHost,
                    'database' => $dbName,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ];

                if (function_exists('showDatabaseError')) {
                    showDatabaseError($errorDetails);
                }
                exit;
            }

            return null;
        }
    }

    /**
     * Перевірка доступності БД
     *
     * @param bool $showError Показувати сторінку помилки при недоступності
     * @return bool
     */
    public static function isAvailable(bool $showError = false): bool
    {
        // Перевіряємо, що константи БД визначені та не порожні
        if (! defined('DB_HOST') || empty(DB_HOST) || ! defined('DB_NAME') || empty(DB_NAME)) {
            return false;
        }

        try {
            if (! class_exists('Database')) {
                // Якщо Database ще не завантажено, намагаємося завантажити з нового розташування
                $dbFile = __DIR__ . '/../../../infrastructure/persistence/Database.php';
                if (file_exists($dbFile)) {
                    require_once $dbFile;
                }
            }
            $isAvailable = Database::getInstance()->isAvailable();

            if (! $isAvailable && $showError && php_sapi_name() !== 'cli') {
                if (function_exists('showDatabaseError')) {
                    showDatabaseError([
                        'host' => DB_HOST,
                        'database' => DB_NAME,
                        'error' => 'База даних недоступна',
                    ]);
                }
                exit;
            }

            return $isAvailable;
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('Перевірка доступності бази даних не вдалася', ['error' => $e->getMessage()]);
            } else {
                logger()->logError('isDatabaseAvailable помилка: ' . $e->getMessage(), ['exception' => $e]);
            }

            if ($showError && php_sapi_name() !== 'cli') {
                if (function_exists('showDatabaseError')) {
                    showDatabaseError([
                        'host' => DB_HOST,
                        'database' => DB_NAME,
                        'error' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ]);
                }
                exit;
            }

            return false;
        }
    }

    /**
     * Отримання екземпляра Database
     *
     * @return DatabaseInterface
     */
    public static function getInstance(): DatabaseInterface
    {
        return Database::getInstance();
    }

    /**
     * Перевірка існування таблиці в базі даних
     *
     * @param string $tableName Ім'я таблиці
     * @return bool
     */
    public static function tableExists(string $tableName): bool
    {
        try {
            if (! class_exists('Database')) {
                // Якщо Database ще не завантажено, намагаємося завантажити з нового розташування
                $dbFile = __DIR__ . '/../../../infrastructure/persistence/Database.php';
                if (file_exists($dbFile)) {
                    require_once $dbFile;
                }
            }

            $db = Database::getInstance();
            $connection = $db->getConnection();

            $stmt = $connection->prepare('
                SELECT COUNT(*) as count 
                FROM information_schema.tables 
                WHERE table_schema = ? 
                AND table_name = ?
            ');
            $stmt->execute([DB_NAME, $tableName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return isset($result['count']) && (int)$result['count'] > 0;
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->logError('Перевірка існування таблиці не вдалася', [
                    'table' => $tableName,
                    'error' => $e->getMessage(),
                ]);
            }

            return false;
        }
    }

    /**
     * Перевірка існування всіх вказаних таблиць
     *
     * @param array $tables Масив імен таблиць
     * @return array Масив з результатами перевірки ['exists' => array, 'missing' => array]
     */
    /**
     * @param array<int, string> $tables
     * @return array{exists: array<int, string>, missing: array<int, string>}
     */
    public static function checkTables(array $tables): array
    {
        $exists = [];
        $missing = [];

        foreach ($tables as $table) {
            if (self::tableExists($table)) {
                $exists[] = $table;
            } else {
                $missing[] = $table;
            }
        }

        return [
            'exists' => $exists,
            'missing' => $missing,
        ];
    }
}
