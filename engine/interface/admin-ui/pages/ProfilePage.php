<?php

/**
 * Сторінка профілю користувача
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/AdminPage.php';

class ProfilePage extends AdminPage
{
    public function __construct()
    {
        parent::__construct();

        // Профіль доступний всім авторизованим користувачам без додаткових перевірок
        // (базова перевірка авторизації вже виконана в AdminPage::__construct)

        $this->pageTitle = 'Профіль користувача - Flowaxy CMS';
        $this->templateName = 'profile';

        $this->setPageHeader(
            'Профіль користувача',
            'Зміна логіну, email та пароля',
            'fas fa-user'
        );

        // Додаємо хлібні крихти
        $this->setBreadcrumbs([
            ['title' => 'Головна', 'url' => UrlHelper::admin('dashboard')],
            ['title' => 'Профіль користувача'],
        ]);
    }

    public function handle()
    {
        // Обробка збереження
        if ($_POST && isset($_POST['save_profile'])) {
            $this->saveProfile();
        }

        // Отримання даних користувача
        $user = $this->getCurrentUser();

        // Рендеримо сторінку
        $this->render([
            'user' => $user,
        ]);
    }

    /**
     * Отримання поточного користувача
     *
     * @return array<string, mixed>|null
     */
    private function getCurrentUser(): ?array
    {
        // Використовуємо SessionManager
        $session = sessionManager();
        $userId = $session->get('admin_user_id');

        if (! $userId) {
            $this->setMessage('Користувач не знайдено', 'danger');

            return null;
        }

        try {
            $stmt = $this->db->prepare('SELECT id, username, email FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (! $user) {
                $this->setMessage('Користувач не знайдено', 'danger');

                return null;
            }

            return $user;
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('ProfilePage: Error getting user', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('Error getting user: ' . $e->getMessage(), ['exception' => $e]);
            }
            $this->setMessage('Помилка завантаження даних користувача', 'danger');

            return null;
        }
    }

    /**
     * Збереження профілю
     */
    private function saveProfile()
    {
        if (! $this->verifyCsrf()) {
            return;
        }

        // Використовуємо SessionManager
        $session = sessionManager();
        $userId = $session->get('admin_user_id');
        if (! $userId) {
            $this->setMessage('Користувач не знайдено', 'danger');

            return;
        }

        $username = SecurityHelper::sanitizeInput($_POST['username'] ?? '');
        $email = SecurityHelper::sanitizeInput($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Валідація (використовуємо Validator безпосередньо з engine/classes)
        if (! Validator::validateString($username, 3, 50)) {
            $this->setMessage('Логін має містити від 3 до 50 символів', 'danger');

            return;
        }

        if (! empty($email) && ! Validator::validateEmail($email)) {
            $this->setMessage('Невірний формат email', 'danger');

            return;
        }

        // Перевірка унікальності username
        try {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $stmt->execute([$username, $userId]);
            if ($stmt->fetch()) {
                $this->setMessage('Користувач з таким логіном вже існує', 'danger');

                return;
            }
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('ProfilePage: Error checking username', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('Error checking username: ' . $e->getMessage(), ['exception' => $e]);
            }
            $this->setMessage('Помилка перевірки логіну', 'danger');

            return;
        }

        // Якщо змінюється пароль, перевіряємо старий
        if (! empty($newPassword)) {
            if (empty($currentPassword)) {
                $this->setMessage('Введіть поточний пароль для зміни', 'danger');

                return;
            }

            // Перевіряємо поточний пароль
            try {
                $stmt = $this->db->prepare('SELECT password FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (! $user || ! password_verify($currentPassword, $user['password'])) {
                    $this->setMessage('Невірний поточний пароль', 'danger');

                    return;
                }
            } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('ProfilePage: Error verifying password', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('Error verifying password: ' . $e->getMessage(), ['exception' => $e]);
            }
                $this->setMessage('Помилка перевірки пароля', 'danger');

                return;
            }

            // Перевіряємо довжину нового пароля
            if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                $this->setMessage('Пароль повинен містити мінімум ' . PASSWORD_MIN_LENGTH . ' символів', 'danger');

                return;
            }

            // Перевіряємо збіг паролів
            if ($newPassword !== $confirmPassword) {
                $this->setMessage('Нові паролі не співпадають', 'danger');

                return;
            }
        }

        // Зберігаємо зміни
        try {
            $this->db->beginTransaction();

            if (! empty($newPassword)) {
                // Обновляем username, email и password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $this->db->prepare('UPDATE users SET username = ?, email = ?, password = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$username, $email, $hashedPassword, $userId]);

                // Оновлюємо сесію (використовуємо SessionManager)
                $session = sessionManager();
                $session->set('admin_username', $username);
            } else {
                // Оновлюємо тільки username і email
                $stmt = $this->db->prepare('UPDATE users SET username = ?, email = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$username, $email, $userId]);

                // Оновлюємо сесію (використовуємо SessionManager)
                $session = sessionManager();
                $session->set('admin_username', $username);
            }

            $this->db->commit();
            if (function_exists('logInfo')) {
                logInfo('ProfilePage: Profile updated', ['user_id' => $userId, 'username' => $username]);
            } else {
                logger()->logInfo('Профіль оновлено', ['user_id' => $userId, 'username' => $username]);
            }
            $this->setMessage('Профіль успішно оновлено', 'success');

            // Редирект после сохранения для предотвращения повторного выполнения
            $this->redirect('profile');
            exit;
        } catch (Exception $e) {
            $this->db->rollBack();
            if (function_exists('logError')) {
                logError('ProfilePage: Error saving profile', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('Error saving profile: ' . $e->getMessage(), ['exception' => $e]);
            }
            $this->setMessage('Помилка при збереженні профілю', 'danger');
        }
    }
}
