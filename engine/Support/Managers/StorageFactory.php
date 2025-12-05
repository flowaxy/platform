<?php

/**
 * Фабрика для створення менеджерів сховища
 * Уніфікований доступ до різних типів сховища
 *
 * @package Engine\Classes\Managers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../../infrastructure/filesystem/contracts/StorageInterface.php';

class StorageFactory
{
    public const TYPE_COOKIE = 'cookie';
    public const TYPE_SESSION = 'session';
    public const TYPE_LOCAL_STORAGE = 'localStorage';
    public const TYPE_SESSION_STORAGE = 'sessionStorage';

    /**
     * Отримання менеджера сховища за типом
     *
     * @param string $type Тип сховища (cookie, session, localStorage, sessionStorage)
     * @param string $prefix Префікс для ключів (опціонально)
     * @return StorageInterface|null
     */
    public static function get(string $type, string $prefix = ''): ?StorageInterface
    {
        switch ($type) {
            case self::TYPE_COOKIE:
                $manager = CookieManager::getInstance();
                if ($prefix) {
                    $manager->setDefaultOptions(['path' => $prefix]);
                }

                return $manager;

            case self::TYPE_SESSION:
                $manager = SessionManager::getInstance();
                if ($prefix) {
                    $manager->setPrefix($prefix);
                }

                return $manager;

            case self::TYPE_LOCAL_STORAGE:
                $manager = StorageManager::getInstance();
                $manager->setType('localStorage');
                if ($prefix) {
                    $manager->setPrefix($prefix);
                }

                return $manager;

            case self::TYPE_SESSION_STORAGE:
                $manager = StorageManager::getInstance();
                $manager->setType('sessionStorage');
                if ($prefix) {
                    $manager->setPrefix($prefix);
                }

                return $manager;

            default:
                return null;
        }
    }

    /**
     * Отримання менеджера cookies
     *
     * @param string $prefix Префікс для ключів (опціонально)
     * @return CookieManager
     */
    public static function cookie(string $prefix = ''): CookieManager
    {
        return self::get(self::TYPE_COOKIE, $prefix) ?? CookieManager::getInstance();
    }

    /**
     * Отримання менеджера сесій
     *
     * @param string $prefix Префікс для ключів (опціонально)
     * @return SessionManager
     */
    public static function session(string $prefix = ''): SessionManager
    {
        $manager = SessionManager::getInstance();
        if ($prefix) {
            $manager->setPrefix($prefix);
        }

        return $manager;
    }

    /**
     * Отримання менеджера localStorage
     *
     * @param string $prefix Префікс для ключів (опціонально)
     * @return StorageManager
     */
    public static function localStorage(string $prefix = ''): StorageManager
    {
        $manager = StorageManager::getInstance();
        $manager->setType('localStorage');
        if ($prefix) {
            $manager->setPrefix($prefix);
        }

        return $manager;
    }

    /**
     * Получение менеджера sessionStorage
     *
     * @param string $prefix Префикс для ключей (опционально)
     * @return StorageManager
     */
    public static function sessionStorage(string $prefix = ''): StorageManager
    {
        $manager = StorageManager::getInstance();
        $manager->setType('sessionStorage');
        if ($prefix) {
            $manager->setPrefix($prefix);
        }

        return $manager;
    }
}
