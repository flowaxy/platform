<?php

/**
 * Ініціалізація системи ролей
 * Перевіряє наявність таблиць та створює їх за потреби
 *
 * @package Engine\Core\Bootstrap
 */

declare(strict_types=1);

if (! function_exists('initializeRolesSystem')) {
    function initializeRolesSystem(): void
    {
        try {
            $db = DatabaseHelper::getConnection();
            if (! $db) {
                return;
            }

            // Перевіряємо наявність таблиці roles
            $stmt = $db->query("SHOW TABLES LIKE 'roles'");
            if ($stmt === false) {
                return;
            }
            $rolesTableExists = $stmt->rowCount() > 0;

            // Якщо таблиці немає, створюємо систему ролей
            if (! $rolesTableExists) {
                $sqlFile = __DIR__ . '/../../db/roles_permissions.sql';
                if (file_exists($sqlFile)) {
                    $sql = file_get_contents($sqlFile);
                    if (! empty($sql)) {
                        // Виконуємо SQL частинами
                        $statements = array_filter(
                            array_map('trim', explode(';', $sql)),
                            fn ($stmt) => ! empty($stmt) && ! preg_match('/^--/', $stmt) && ! preg_match('/^\/\*/', $stmt)
                        );

                        foreach ($statements as $statement) {
                            // Пропускаємо коментарі та порожні рядки
                            $statement = preg_replace('/--.*$/m', '', $statement ?? '') ?? '';
                            $statement = preg_replace('/\/\*.*?\*\//s', '', $statement ?? '') ?? '';
                            $statement = trim($statement ?? '');

                            if (! empty($statement)) {
                                try {
                                    $db->exec($statement);
                                } catch (Exception $e) {
                                    // Ігноруємо помилки типу "таблиця вже існує"
                                    if (! str_contains($e->getMessage(), 'already exists') &&
                                        ! str_contains($e->getMessage(), 'Duplicate')) {
                                        error_log('Roles system init SQL error: ' . $e->getMessage());
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Roles system initialization error: ' . $e->getMessage());
        }
    }
}
