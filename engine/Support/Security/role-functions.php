<?php

/**
 * Глобальні функції для роботи з ролями та правами
 *
 * @package Engine\Support\Security
 */

declare(strict_types=1);

/**
 * Отримання сервісу авторизації адміністраторів
 */
if (! function_exists('adminAuthorizationService')) {
    function adminAuthorizationService(): ?AdminAuthorizationService
    {
        static $resolving = false;
        static $instance = null;

        if ($resolving) {
            // Захист від циклічної залежності
            return null;
        }

        if ($instance !== null) {
            return $instance;
        }

        if (! class_exists('AdminAuthorizationService')) {
            return null;
        }

        try {
            $resolving = true;

            if (function_exists('container')) {
                $container = container();
                // Перевіряємо, чи контейнер вже намагається створити AdminAuthorizationService
                if ($container->has(AdminAuthorizationService::class)) {
                    $result = $container->make(AdminAuthorizationService::class);
                    // Якщо контейнер повернув Closure, викликаємо його
                    if ($result instanceof \Closure) {
                        $instance = $result();
                    } else {
                        $instance = $result;
                    }
                } else {
                    // Якщо немає в контейнері, створюємо напряму
                    $roleRepoResult = $container->has(AdminRoleRepositoryInterface::class)
                        ? $container->make(AdminRoleRepositoryInterface::class)
                        : null;

                    // Обробляємо Closure для roleRepo
                    $roleRepo = null;
                    if ($roleRepoResult instanceof \Closure) {
                        $roleRepo = $roleRepoResult();
                    } elseif ($roleRepoResult !== null) {
                        $roleRepo = $roleRepoResult;
                    } else {
                        $roleRepo = new AdminRoleRepository();
                    }
                    $instance = new AdminAuthorizationService($roleRepo);
                }
            } else {
                $instance = new AdminAuthorizationService(new AdminRoleRepository());
            }

            $resolving = false;

            return $instance;
        } catch (Throwable $e) {
            $resolving = false;
            // Не логуємо помилки циклічної залежності, щоб не засмічувати логи
            if (strpos($e->getMessage(), 'Circular dependency') === false) {
                if (function_exists('logError')) {
                    logError('adminAuthorizationService error', ['error' => $e->getMessage(), 'exception' => $e]);
                } else {
                    error_log('adminAuthorizationService error: ' . $e->getMessage());
                }
            }

            return null;
        }
    }
}

/**
 * Отримання екземпляра RoleManager через фасад
 */
if (! function_exists('roleManager')) {
    function roleManager(): RoleManager
    {
        if (class_exists('Role')) {
            return Role::manager();
        }

        return RoleManager::getInstance();
    }
}

/**
 * Перевірка дозволу у користувача
 */
if (! function_exists('user_can')) {
    function user_can(int $userId, string $permission): bool
    {
        $service = adminAuthorizationService();
        if ($service) {
            return $service->userHasPermission($userId, $permission);
        }

        return roleManager()->hasPermission($userId, $permission);
    }
}

/**
 * Перевірка ролі у користувача
 */
if (! function_exists('user_has_role')) {
    function user_has_role(int $userId, string $roleSlug): bool
    {
        $service = adminAuthorizationService();
        if ($service) {
            return $service->userHasRole($userId, $roleSlug);
        }

        return roleManager()->hasRole($userId, $roleSlug);
    }
}

/**
 * Перевірка дозволу у поточного користувача (для адмінки)
 */
if (! function_exists('current_user_can')) {
    function current_user_can(string $permission): bool
    {
        if (! class_exists('Session')) {
            return false;
        }

        $session = sessionManager();
        $userId = $session->get('admin_user_id');
        if (! $userId) {
            return false;
        }

        return user_can((int)$userId, $permission);
    }
}

/**
 * Отримання ID поточного авторизованого користувача (для публічної частини)
 */
if (! function_exists('auth_get_current_user_id')) {
    function auth_get_current_user_id(): ?int
    {
        if (! class_exists('Session')) {
            return null;
        }

        $session = sessionManager();
        $userId = $session->get('user_id');
        if (! $userId) {
            return null;
        }

        return (int)$userId;
    }
}

/**
 * Перевірка дозволу у поточного авторизованого користувача (для публічної частини)
 */
if (! function_exists('auth_user_can')) {
    function auth_user_can(string $permission): bool
    {
        $userId = auth_get_current_user_id();
        if (! $userId) {
            return false;
        }

        return user_can($userId, $permission);
    }
}

/**
 * Отримання всіх дозволів користувача
 */
if (! function_exists('user_permissions')) {
    function user_permissions(int $userId): array
    {
        $service = adminAuthorizationService();
        if ($service) {
            return $service->getUserPermissions($userId);
        }

        return roleManager()->getUserPermissions($userId);
    }
}

/**
 * Отримання всіх ролей користувача
 */
if (! function_exists('user_roles')) {
    function user_roles(int $userId): array
    {
        $service = adminAuthorizationService();
        if ($service) {
            return $service->getUserRoles($userId);
        }

        return roleManager()->getUserRoles($userId);
    }
}

/**
 * Призначення ролі користувачу
 */
if (! function_exists('assign_role')) {
    function assign_role(int $userId, int $roleId): bool
    {
        $service = adminAuthorizationService();
        if ($service) {
            return $service->assignRole($userId, $roleId);
        }

        return roleManager()->assignRole($userId, $roleId);
    }
}

/**
 * Видалення ролі у користувача
 */
if (! function_exists('remove_role')) {
    function remove_role(int $userId, int $roleId): bool
    {
        $service = adminAuthorizationService();
        if ($service) {
            return $service->removeRole($userId, $roleId);
        }

        return roleManager()->removeRole($userId, $roleId);
    }
}
