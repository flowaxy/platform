<?php

/**
 * Сторінка управління користувачами
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/AdminPage.php';

class UsersPage extends AdminPage
{
    private ?AdminUserRepository $userRepository = null;
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

        $this->pageTitle = 'Користувачі - Flowaxy CMS';
        $this->templateName = 'users';

        $this->setPageHeader(
            'Користувачі',
            'Управління користувачами системи',
            'fas fa-users'
        );

        $this->setBreadcrumbs([
            ['title' => 'Головна', 'url' => UrlHelper::admin('dashboard')],
            ['title' => 'Налаштування', 'url' => UrlHelper::admin('settings')],
            ['title' => 'Користувачі'],
        ]);

        // Ініціалізуємо репозиторії
        if (class_exists('AdminUserRepository')) {
            $this->userRepository = new AdminUserRepository();
        }
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
                    case 'create_user':
                        $this->createUser();
                        break;
                    case 'update_user':
                        $this->updateUser();
                        break;
                    case 'delete_user':
                        $this->deleteUser();
                        break;
                    case 'change_password':
                        $this->changePassword();
                        break;
                }
            }
        }

        // Отримуємо всіх користувачів
        $users = $this->getUsers();
        $roles = $this->getAllRoles();

        $this->render([
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    /**
     * Отримання всіх користувачів
     */
    private function getUsers(): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            $stmt = $this->db->query('
                SELECT id, username, email, is_active, last_activity, role_ids, created_at
                FROM users
                ORDER BY username
            ');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as &$row) {
                $row['is_active'] = (bool)$row['is_active'];
                $row['role_ids'] = !empty($row['role_ids'])
                    ? json_decode($row['role_ids'], true) ?? []
                    : [];

                // Отримуємо ролі користувача
                if (!empty($row['role_ids']) && $this->roleRepository) {
                    $userRoles = [];
                    foreach ($row['role_ids'] as $roleId) {
                        $stmtRole = $this->db->prepare('SELECT name, slug FROM roles WHERE id = ?');
                        $stmtRole->execute([$roleId]);
                        $role = $stmtRole->fetch(PDO::FETCH_ASSOC);
                        if ($role) {
                            $userRoles[] = $role;
                        }
                    }
                    $row['roles'] = $userRoles;
                } else {
                    $row['roles'] = [];
                }
            }

            return $rows;
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('UsersPage: getUsers error', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('UsersPage getUsers error: ' . $e->getMessage(), ['exception' => $e]);
            }
            return [];
        }
    }

    /**
     * Отримання всіх ролей
     */
    private function getAllRoles(): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            $stmt = $this->db->query('
                SELECT id, name, slug, description
                FROM roles
                ORDER BY name
            ');
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('UsersPage: getAllRoles error', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('UsersPage getAllRoles error: ' . $e->getMessage(), ['exception' => $e]);
            }
            return [];
        }
    }

    /**
     * Створення нового користувача
     */
    private function createUser(): void
    {
        if (! $this->verifyCsrf()) {
            return;
        }

        $session = sessionManager();
        $userId = (int)($session->get('admin_user_id') ?? 0);

        $hasAccess = ($userId === 1) ||
                     (function_exists('current_user_can') && current_user_can('admin.access'));

        if (! $hasAccess) {
            $this->setMessage('У вас немає прав на створення користувачів', 'danger');
            return;
        }

        $username = SecurityHelper::sanitizeInput($_POST['username'] ?? '');
        $email = SecurityHelper::sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleIds = $_POST['roles'] ?? [];

        if (empty($username) || empty($email) || empty($password)) {
            $this->setMessage('Заповніть всі обов\'язкові поля', 'danger');
            return;
        }

        if (strlen($password) < 8) {
            $this->setMessage('Пароль має бути не менше 8 символів', 'danger');
            return;
        }

        if ($this->db === null) {
            $this->setMessage('Помилка підключення до бази даних', 'danger');
            return;
        }

        try {
            // Перевіряємо, чи username вже існує
            $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $this->setMessage('Користувач з таким логіном вже існує', 'danger');
                return;
            }

            // Перевіряємо, чи email вже існує
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $this->setMessage('Користувач з таким email вже існує', 'danger');
                return;
            }

            // Хешуємо пароль
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Підготовка role_ids
            $roleIdsJson = !empty($roleIds) && is_array($roleIds)
                ? json_encode(array_map('intval', $roleIds))
                : null;

            // Створюємо користувача
            $stmt = $this->db->prepare('
                INSERT INTO users (username, email, password, role_ids, is_active)
                VALUES (?, ?, ?, ?, 1)
            ');
            $stmt->execute([$username, $email, $passwordHash, $roleIdsJson]);

            if (function_exists('logInfo')) {
                logInfo('UsersPage: User created', ['username' => $username, 'email' => $email]);
            } else {
                logger()->logInfo('Користувача створено', ['username' => $username, 'email' => $email]);
            }
            $this->setMessage('Користувача успішно створено', 'success');
            $this->redirect('users');
        } catch (Exception $e) {
            $this->setMessage('Помилка при створенні користувача: ' . $e->getMessage(), 'danger');
            if (function_exists('logError')) {
                logError('UsersPage: createUser error', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('UsersPage createUser error: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    /**
     * Оновлення користувача
     */
    private function updateUser(): void
    {
        if (! $this->verifyCsrf()) {
            return;
        }

        $session = sessionManager();
        $userId = (int)($session->get('admin_user_id') ?? 0);

        $hasAccess = ($userId === 1) ||
                     (function_exists('current_user_can') && current_user_can('admin.access'));

        if (! $hasAccess) {
            $this->setMessage('У вас немає прав на редагування користувачів', 'danger');
            return;
        }

        $userIdToUpdate = (int)($_POST['user_id'] ?? 0);
        $email = SecurityHelper::sanitizeInput($_POST['email'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $roleIds = $_POST['roles'] ?? [];

        if (empty($userIdToUpdate) || empty($email)) {
            $this->setMessage('Невірні дані', 'danger');
            return;
        }

        // Не можна деактивувати першого користувача
        if ($userIdToUpdate === 1 && $isActive === 0) {
            $this->setMessage('Неможливо деактивувати першого користувача', 'danger');
            return;
        }

        if ($this->db === null) {
            $this->setMessage('Помилка підключення до бази даних', 'danger');
            return;
        }

        try {
            // Перевіряємо, чи email вже використовується іншим користувачем
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $stmt->execute([$email, $userIdToUpdate]);
            if ($stmt->fetch()) {
                $this->setMessage('Email вже використовується іншим користувачем', 'danger');
                return;
            }

            // Підготовка role_ids
            $roleIdsJson = !empty($roleIds) && is_array($roleIds)
                ? json_encode(array_map('intval', $roleIds))
                : null;

            // Оновлюємо користувача
            $stmt = $this->db->prepare('
                UPDATE users
                SET email = ?, is_active = ?, role_ids = ?
                WHERE id = ?
            ');
            $stmt->execute([$email, $isActive, $roleIdsJson, $userIdToUpdate]);

            if (function_exists('logInfo')) {
                logInfo('UsersPage: User updated', ['user_id' => $userIdToUpdate, 'email' => $email]);
            } else {
                logger()->logInfo('Користувача оновлено', ['user_id' => $userIdToUpdate, 'email' => $email]);
            }
            $this->setMessage('Користувача успішно оновлено', 'success');
            $this->redirect('users');
        } catch (Exception $e) {
            $this->setMessage('Помилка при оновленні користувача: ' . $e->getMessage(), 'danger');
            if (function_exists('logError')) {
                logError('UsersPage: updateUser error', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('UsersPage updateUser error: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    /**
     * Зміна пароля користувача
     */
    private function changePassword(): void
    {
        if (! $this->verifyCsrf()) {
            return;
        }

        $session = sessionManager();
        $userId = (int)($session->get('admin_user_id') ?? 0);

        $hasAccess = ($userId === 1) ||
                     (function_exists('current_user_can') && current_user_can('admin.access'));

        if (! $hasAccess) {
            $this->setMessage('У вас немає прав на зміну паролів', 'danger');
            return;
        }

        $userIdToUpdate = (int)($_POST['user_id'] ?? 0);
        $password = $_POST['password'] ?? '';

        if (empty($userIdToUpdate) || empty($password)) {
            $this->setMessage('Невірні дані', 'danger');
            return;
        }

        if (strlen($password) < 8) {
            $this->setMessage('Пароль має бути не менше 8 символів', 'danger');
            return;
        }

        if ($this->db === null) {
            $this->setMessage('Помилка підключення до бази даних', 'danger');
            return;
        }

        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$passwordHash, $userIdToUpdate]);

            $this->setMessage('Пароль успішно змінено', 'success');
            $this->redirect('users');
        } catch (Exception $e) {
            $this->setMessage('Помилка при зміні пароля: ' . $e->getMessage(), 'danger');
            if (function_exists('logError')) {
                logError('UsersPage: changePassword error', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('UsersPage changePassword error: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    /**
     * Видалення користувача
     */
    private function deleteUser(): void
    {
        if (! $this->verifyCsrf()) {
            return;
        }

        $session = sessionManager();
        $userId = (int)($session->get('admin_user_id') ?? 0);

        $hasAccess = ($userId === 1) ||
                     (function_exists('current_user_can') && current_user_can('admin.access'));

        if (! $hasAccess) {
            $this->setMessage('У вас немає прав на видалення користувачів', 'danger');
            return;
        }

        $userIdToDelete = (int)($_POST['user_id'] ?? 0);

        if (empty($userIdToDelete)) {
            $this->setMessage('Невірні дані', 'danger');
            return;
        }

        // Не можна видалити першого користувача
        if ($userIdToDelete === 1) {
            $this->setMessage('Неможливо видалити першого користувача', 'danger');
            return;
        }

        // Не можна видалити себе
        if ($userIdToDelete === $userId) {
            $this->setMessage('Ви не можете видалити себе', 'danger');
            return;
        }

        if ($this->db === null) {
            $this->setMessage('Помилка підключення до бази даних', 'danger');
            return;
        }

        try {
            $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$userIdToDelete]);

            if (function_exists('logInfo')) {
                logInfo('UsersPage: User deleted', ['user_id' => $userIdToDelete]);
            } else {
                logger()->logInfo('Користувача видалено', ['user_id' => $userIdToDelete]);
            }
            $this->setMessage('Користувача успішно видалено', 'success');
            $this->redirect('users');
        } catch (Exception $e) {
            $this->setMessage('Помилка при видаленні користувача: ' . $e->getMessage(), 'danger');
            if (function_exists('logError')) {
                logError('UsersPage: deleteUser error', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('UsersPage deleteUser error: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }
}
