<?php

/**
 * Сторінка управління ролями та правами
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/AdminPage.php';

class RolesPage extends AdminPage
{
    private ?AdminRoleRepository $roleRepository = null;

    public function __construct()
    {
        parent::__construct();

        // Перевірка прав доступу
        $session = sessionManager();
        $userId = (int)($session->get('admin_user_id') ?? 0);

        $hasAccess = ($userId === 1) ||
                     (function_exists('current_user_can') && current_user_can('admin.access'));

        if (! $hasAccess) {
            Response::redirectStatic(UrlHelper::admin('dashboard'));
            exit;
        }

        $this->pageTitle = 'Ролі та права - Flowaxy CMS';
        $this->templateName = 'roles';

        $this->setPageHeader(
            'Ролі та права',
            'Управління ролями та правами доступу',
            'fas fa-user-shield'
        );
        
        $this->setBreadcrumbs([
            ['title' => 'Головна', 'url' => UrlHelper::admin('dashboard')],
            ['title' => 'Налаштування', 'url' => UrlHelper::admin('settings')],
            ['title' => 'Ролі та права'],
        ]);

        // Ініціалізуємо репозиторій
        if (class_exists('AdminRoleRepository')) {
            $this->roleRepository = new AdminRoleRepository();
        }
    }

    public function handle(): void
    {
        // Обробка POST запитів
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action'])) {
                switch ($_POST['action']) {
                    case 'create_role':
                        $this->createRole();
                        break;
                    case 'update_role':
                        $this->updateRole();
                        break;
                    case 'delete_role':
                        $this->deleteRole();
                        break;
                }
            }
        }

        // Отримуємо всі ролі
        $roles = $this->getRoles();
        $permissions = $this->getAllPermissions();

        $this->render([
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Отримання всіх ролей
     */
    private function getRoles(): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            $stmt = $this->db->query('
                SELECT r.id, r.name, r.slug, r.description, r.is_system, r.permissions
                FROM roles r
                ORDER BY r.name
            ');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as &$row) {
                // Отримуємо права з JSON стовпця
                $permissionIds = [];
                if (!empty($row['permissions'])) {
                    $permissionIds = json_decode($row['permissions'], true) ?: [];
                }
                
                // Конвертуємо ID прав в slug
                if (!empty($permissionIds)) {
                    $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
                    $permStmt = $this->db->prepare("SELECT slug FROM permissions WHERE id IN ($placeholders)");
                    $permStmt->execute($permissionIds);
                    $row['permissions'] = $permStmt->fetchAll(PDO::FETCH_COLUMN);
                } else {
                    $row['permissions'] = [];
                }
                $row['is_system'] = (bool)$row['is_system'];
            }

            return $rows;
        } catch (Exception $e) {
            logger()->logError('RolesPage getRoles error: ' . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }

    /**
     * Отримання всіх доступних прав
     */
    private function getAllPermissions(): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            $stmt = $this->db->query('
                SELECT id, slug, name, description, category
                FROM permissions
                ORDER BY category, name
            ');
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            logger()->logError('RolesPage getAllPermissions error: ' . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }

    /**
     * Створення нової ролі
     */
    private function createRole(): void
    {
        if (! $this->verifyCsrf()) {
            return;
        }

        $session = sessionManager();
        $userId = (int)($session->get('admin_user_id') ?? 0);

        $hasAccess = ($userId === 1) ||
                     (function_exists('current_user_can') && current_user_can('admin.access'));

        if (! $hasAccess) {
            $this->setMessage('У вас немає прав на створення ролей', 'danger');
            return;
        }

        $name = SecurityHelper::sanitizeInput($_POST['name'] ?? '');
        $slug = SecurityHelper::sanitizeInput($_POST['slug'] ?? '');
        $description = SecurityHelper::sanitizeInput($_POST['description'] ?? '');
        $permissions = $_POST['permissions'] ?? [];

        if (empty($name) || empty($slug)) {
            $this->setMessage('Назва та slug обов\'язкові поля', 'danger');
            return;
        }

        // Генеруємо slug, якщо не вказано
        if (empty($slug)) {
            $slug = $this->generateSlug($name);
        }

        if ($this->db === null) {
            $this->setMessage('Помилка підключення до бази даних', 'danger');
            return;
        }

        try {
            $this->db->beginTransaction();

            // Перевіряємо, чи slug вже існує
            $stmt = $this->db->prepare('SELECT id FROM roles WHERE slug = ?');
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                $this->db->rollBack();
                $this->setMessage('Роль з таким slug вже існує', 'danger');
                return;
            }

            // Створюємо роль
            $stmt = $this->db->prepare('
                INSERT INTO roles (name, slug, description, is_system)
                VALUES (?, ?, ?, 0)
            ');
            $stmt->execute([$name, $slug, $description]);
            $roleId = (int)$this->db->lastInsertId();

            // Додаємо права
            if (!empty($permissions) && is_array($permissions)) {
                $placeholders = implode(',', array_fill(0, count($permissions), '?'));
                $stmt = $this->db->prepare("SELECT id FROM permissions WHERE slug IN ($placeholders)");
                $stmt->execute(array_map('SecurityHelper::sanitizeInput', $permissions));
                $permissionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $permissionsJson = json_encode($permissionIds);
                $stmt = $this->db->prepare('UPDATE roles SET permissions = ? WHERE id = ?');
                $stmt->execute([$permissionsJson, $roleId]);
            }

            $this->db->commit();
            logger()->logInfo('Роль створено', ['role_id' => $roleId, 'role_name' => $name]);
            $this->setMessage('Роль успішно створена', 'success');
            $this->redirect('roles');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->setMessage('Помилка при створенні ролі: ' . $e->getMessage(), 'danger');
            logger()->logError('RolesPage createRole error: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Оновлення ролі
     */
    private function updateRole(): void
    {
        if (! $this->verifyCsrf()) {
            return;
        }

        $session = sessionManager();
        $userId = (int)($session->get('admin_user_id') ?? 0);

        $hasAccess = ($userId === 1) ||
                     (function_exists('current_user_can') && current_user_can('admin.access'));

        if (! $hasAccess) {
            $this->setMessage('У вас немає прав на редагування ролей', 'danger');
            return;
        }

        $roleId = (int)($_POST['role_id'] ?? 0);
        $name = SecurityHelper::sanitizeInput($_POST['name'] ?? '');
        $description = SecurityHelper::sanitizeInput($_POST['description'] ?? '');
        $permissions = $_POST['permissions'] ?? [];

        if (empty($roleId) || empty($name)) {
            $this->setMessage('Невірні дані', 'danger');
            return;
        }

        if ($this->db === null) {
            $this->setMessage('Помилка підключення до бази даних', 'danger');
            return;
        }

        try {
            $this->db->beginTransaction();

            // Перевіряємо, чи це системна роль
            $stmt = $this->db->prepare('SELECT is_system FROM roles WHERE id = ?');
            $stmt->execute([$roleId]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$role) {
                $this->db->rollBack();
                $this->setMessage('Роль не знайдена', 'danger');
                return;
            }

            if ((bool)$role['is_system']) {
                $this->db->rollBack();
                $this->setMessage('Неможливо редагувати системну роль', 'danger');
                return;
            }

            // Оновлюємо роль
            $stmt = $this->db->prepare('
                UPDATE roles 
                SET name = ?, description = ?
                WHERE id = ?
            ');
            $stmt->execute([$name, $description, $roleId]);

            // Оновлюємо права
            if (!empty($permissions) && is_array($permissions)) {
                $placeholders = implode(',', array_fill(0, count($permissions), '?'));
                $stmt = $this->db->prepare("SELECT id FROM permissions WHERE slug IN ($placeholders)");
                $stmt->execute(array_map('SecurityHelper::sanitizeInput', $permissions));
                $permissionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $permissionsJson = json_encode($permissionIds);
                $stmt = $this->db->prepare('UPDATE roles SET permissions = ? WHERE id = ?');
                $stmt->execute([$permissionsJson, $roleId]);
            } else {
                // Якщо немає прав, встановлюємо порожній масив
                $stmt = $this->db->prepare('UPDATE roles SET permissions = ? WHERE id = ?');
                $stmt->execute([json_encode([]), $roleId]);
            }

            $this->db->commit();
            logger()->logInfo('Роль оновлено', ['role_id' => $roleId, 'role_name' => $name]);
            $this->setMessage('Роль успішно оновлена', 'success');
            $this->redirect('roles');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->setMessage('Помилка при оновленні ролі: ' . $e->getMessage(), 'danger');
            logger()->logError('RolesPage updateRole error: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Видалення ролі
     */
    private function deleteRole(): void
    {
        if (! $this->verifyCsrf()) {
            return;
        }

        $session = sessionManager();
        $userId = (int)($session->get('admin_user_id') ?? 0);

        $hasAccess = ($userId === 1) ||
                     (function_exists('current_user_can') && current_user_can('admin.access'));

        if (! $hasAccess) {
            $this->setMessage('У вас немає прав на видалення ролей', 'danger');
            return;
        }

        $roleId = (int)($_POST['role_id'] ?? 0);

        if (empty($roleId)) {
            $this->setMessage('Невірні дані', 'danger');
            return;
        }

        if ($this->db === null) {
            $this->setMessage('Помилка підключення до бази даних', 'danger');
            return;
        }

        try {
            $this->db->beginTransaction();

            // Перевіряємо, чи це системна роль
            $stmt = $this->db->prepare('SELECT is_system FROM roles WHERE id = ?');
            $stmt->execute([$roleId]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$role) {
                $this->db->rollBack();
                $this->setMessage('Роль не знайдена', 'danger');
                return;
            }

            if ((bool)$role['is_system']) {
                $this->db->rollBack();
                $this->setMessage('Неможливо видалити системну роль', 'danger');
                return;
            }

            // Очищаємо права ролі (встановлюємо порожній масив)
            $stmt = $this->db->prepare('UPDATE roles SET permissions = ? WHERE id = ?');
            $stmt->execute([json_encode([]), $roleId]);

            // Видаляємо роль
            $stmt = $this->db->prepare('DELETE FROM roles WHERE id = ?');
            $stmt->execute([$roleId]);

            $this->db->commit();
            logger()->logInfo('Роль видалено', ['role_id' => $roleId]);
            $this->setMessage('Роль успішно видалена', 'success');
            $this->redirect('roles');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->setMessage('Помилка при видаленні ролі: ' . $e->getMessage(), 'danger');
            logger()->logError('RolesPage deleteRole error: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Генерація slug з назви
     */
    private function generateSlug(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }
}
