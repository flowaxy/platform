<?php

/**
 * Базовий клас для сторінок адмінки
 * Спрощена архітектура без MVC
 */

declare(strict_types=1);

// Підключаємо хелпер для компонентів
require_once __DIR__ . '/ComponentHelper.php';

// Класи з engine/classes завантажуються автоматично через autoloader в engine/core/bootstrap/app.php
// Використовуємо їх безпосередньо: Request, AjaxHandler, Validator, File, Directory, Response тощо

class AdminPage
{
    protected ?PDO $db = null;
    protected string $message = '';
    protected string $messageType = '';
    protected string $pageTitle = 'Flowaxy CMS';
    protected string $pageHeaderTitle = '';
    protected string $pageHeaderDescription = '';
    protected string $pageHeaderIcon = '';
    protected string $pageHeaderButtons = '';
    protected array $pageBreadcrumbs = [];
    protected string $templateName = '';
    protected array $additionalCSS = [];
    protected array $additionalJS = [];
    protected string $additionalInlineCSS = '';

    // Прапорці для автоматичного редиректу після POST
    private bool $postProcessed = false;
    private bool $autoRedirectEnabled = true;
    private bool $redirectPerformed = false;
    private string $currentPage = '';

    public function __construct()
    {
        // Для AJAX запитів очищаємо буфер виводу одразу
        if (AjaxHandler::isAjax()) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            ini_set('display_errors', '0');
        }

        // Перевірка авторизації (SecurityHelper::requireAdmin() перевіряє через БД)
        SecurityHelper::requireAdmin();

        // Підключення до БД з обробкою помилок
        try {
            $this->db = DatabaseHelper::getConnection(true);
            if ($this->db === null) {
                // Якщо підключення не вдалося, DatabaseHelper::getConnection() вже показав сторінку помилки
                // Для AJAX повертаємо JSON помилку
                if (AjaxHandler::isAjax()) {
                    Response::jsonResponse(['success' => false, 'error' => 'Помилка підключення до бази даних'], 500);
                }
                exit;
            }
        } catch (Exception $e) {
            // Для AJAX повертаємо JSON помилку
            if (AjaxHandler::isAjax()) {
                Response::jsonResponse([
                    'success' => false,
                    'error' => 'Помилка підключення до бази даних: ' . $e->getMessage(),
                ], 500);
                exit;
            }

            // Показуємо сторінку помилки для звичайних запитів
            if (function_exists('showDatabaseError')) {
                showDatabaseError([
                    'host' => DB_HOST ?? 'unknown',
                    'database' => DB_NAME ?? 'unknown',
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]);
            } else {
                logger()->logError('Database connection error in AdminPage: ' . $e->getMessage(), [
                    'exception' => $e,
                    'host' => DB_HOST ?? 'unknown',
                    'database' => DB_NAME ?? 'unknown',
                ]);
                http_response_code(500);
                echo 'Database connection error: ' . Security::clean($e->getMessage());
                exit;
            }
        }

        // Оновлюємо час останньої активності користувача (кожні 10 хвилин)
        // Викликаємо після підключення до БД
        $this->updateUserActivity();

        // Гарантуємо, що перший користувач має доступ до адмінки
        $this->ensureFirstUserHasAdminAccess();

        // Перевірка прав доступу до адмін-панелі
        if (function_exists('current_user_can')) {
            $session = sessionManager();
            $currentUserId = (int)$session->get('admin_user_id');
            if (! current_user_can('admin.access')) {
                // Якщо це перший користувач - намагаємося повторно синхронізувати роль та перевірити ще раз
                if ($currentUserId === 1) {
                    $this->ensureFirstUserHasAdminAccess();
                    if (! current_user_can('admin.access')) {
                        logger()->logWarning('Access denied to admin panel', ['user_id' => $currentUserId]);
                        http_response_code(403);
                        if (AjaxHandler::isAjax()) {
                            header('Content-Type: application/json; charset=UTF-8');
                            echo json_encode(['success' => false, 'error' => 'Доступ заборонено. У вас немає прав для доступу до адміністративної панелі.'], JSON_UNESCAPED_UNICODE);
                        } else {
                            echo 'Доступ заборонено. У вас немає прав для доступу до адміністративної панелі.';
                        }
                        exit;
                    }
                } else {
                    logger()->logWarning('Access denied to admin panel', ['user_id' => $currentUserId]);
                    http_response_code(403);
                    if (AjaxHandler::isAjax()) {
                        header('Content-Type: application/json; charset=UTF-8');
                        echo json_encode(['success' => false, 'error' => 'Доступ заборонено. У вас немає прав для доступу до адміністративної панелі.'], JSON_UNESCAPED_UNICODE);
                    } else {
                        echo 'Доступ заборонено. У вас немає прав для доступу до адміністративної панелі.';
                    }
                    exit;
                }
            }
        }

        // Завантажуємо flash повідомлення з сесії (якщо є)
        $session = sessionManager();
        $flashMessage = $session->flash('admin_message');
        $flashMessageType = $session->flash('admin_message_type');
        if ($flashMessage) {
            $this->message = $flashMessage;
            $this->messageType = $flashMessageType ?: 'info';
        }

        // Визначаємо поточну сторінку для редиректу
        $request = Request::getInstance();
        // Намагаємося визначити з query параметра 'page'
        $this->currentPage = $request->query('page', '');
        if (empty($this->currentPage)) {
            // Намагаємося визначити з URL (остання частина шляху після /admin/)
            $path = $request->path();
            $path = trim($path, '/');
            $parts = explode('/', $path);
            // Шукаємо частину після 'admin'
            $adminIndex = array_search('admin', $parts);
            if ($adminIndex !== false && isset($parts[$adminIndex + 1])) {
                $this->currentPage = $parts[$adminIndex + 1];
            } elseif (! empty($parts)) {
                // Якщо 'admin' не знайдено, беремо останню частину
                $this->currentPage = end($parts);
            }
        }

        // Якщо все ще порожньо, використовуємо 'dashboard' за замовчуванням
        if (empty($this->currentPage)) {
            $this->currentPage = 'dashboard';
        }
    }

    /**
     * Встановлення повідомлення
     * При POST-запиті автоматично зберігає в flash для передачі через редирект
     */
    protected function setMessage($message, $type = 'info')
    {
        $this->message = $message;
        $this->messageType = $type;

        // Якщо це POST-запит, зберігаємо повідомлення в flash для передачі через редирект
        if (Request::getMethod() === 'POST' && ! AjaxHandler::isAjax()) {
            $session = sessionManager();
            $session->setFlash('admin_message', $message);
            $session->setFlash('admin_message_type', $type);
            $this->postProcessed = true;
        }
    }

    /**
     * Оновлення часу останньої активності користувача
     * Викликається при кожному запиті, але оновлює БД тільки якщо пройшло більше 10 хвилин
     *
     * @return void
     */
    private function updateUserActivity(): void
    {
        $session = sessionManager();
        $userId = (int)$session->get('admin_user_id');

        if ($userId <= 0) {
            return;
        }

        try {
            // Використовуємо вже підключену БД
            if ($this->db === null) {
                return;
            }

            // Отримуємо поточний час останньої активності
            $stmt = $this->db->prepare('SELECT last_activity FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $shouldUpdate = false;

                if (empty($user['last_activity'])) {
                    // Якщо last_activity відсутня - оновлюємо
                    $shouldUpdate = true;
                } else {
                    // Перевіряємо, чи пройшло більше 10 хвилин (600 секунд)
                    $lastActivity = strtotime($user['last_activity']);
                    $currentTime = time();
                    $timeDiff = $currentTime - $lastActivity;

                    if ($timeDiff >= 600) {
                        $shouldUpdate = true;
                    }
                }

                if ($shouldUpdate) {
                    $now = date('Y-m-d H:i:s');
                    $stmt = $this->db->prepare('UPDATE users SET last_activity = ? WHERE id = ?');
                    $stmt->execute([$now, $userId]);
                }
            }
        } catch (Exception $e) {
            // Ігноруємо помилки оновлення активності
            if (class_exists('Logger')) {
                Logger::getInstance()->logWarning('Error updating user activity', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Вимкнути автоматичний редирект після POST
     * Використовуйте цей метод, якщо потрібно обробити POST без редиректу
     */
    protected function preventAutoRedirect(): void
    {
        $this->autoRedirectEnabled = false;
    }

    /**
     * Відзначити, що редирект був виконаний вручну
     */
    protected function markRedirectPerformed(): void
    {
        $this->redirectPerformed = true;
    }

    /**
     * Встановлення заголовка сторінки
     */
    protected function setPageHeader($title, $description = '', $icon = '', $buttons = '')
    {
        $this->pageHeaderTitle = $title;
        $this->pageHeaderDescription = $description;
        $this->pageHeaderIcon = $icon;
        $this->pageHeaderButtons = $buttons;
    }

    /**
     * Встановлення хлібних крошок
     * 
     * @param array $breadcrumbs Масив крошок: [['title' => '...', 'url' => '...'], ...]
     */
    protected function setBreadcrumbs(array $breadcrumbs)
    {
        $this->pageBreadcrumbs = $breadcrumbs;
    }

    /**
     * Перевірка CSRF токена
     */
    protected function verifyCsrf(): bool
    {
        $token = $this->request()->post('csrf_token', '');
        if (! SecurityHelper::verifyCsrfToken($token)) {
            $this->setMessage('Помилка безпеки', 'danger');

            return false;
        }

        return true;
    }

    /**
     * Отримання даних для шаблону
     */
    protected function getTemplateData()
    {
        return [
            'message' => $this->message,
            'messageType' => $this->messageType,
            'pageTitle' => $this->pageTitle,
            'pageHeaderTitle' => $this->pageHeaderTitle,
            'pageHeaderDescription' => $this->pageHeaderDescription,
            'pageHeaderIcon' => $this->pageHeaderIcon,
            'pageHeaderButtons' => $this->pageHeaderButtons,
            'pageBreadcrumbs' => $this->pageBreadcrumbs,
            'templateName' => $this->templateName,
            'additionalCSS' => $this->additionalCSS,
            'additionalJS' => $this->additionalJS,
            'additionalInlineCSS' => $this->additionalInlineCSS,
        ];
    }

    /**
     * Отримання шляху до шаблону (може бути перевизначено в дочірніх класах)
     */
    protected function getTemplatePath()
    {
        return __DIR__ . '/../templates/';
    }

    /**
     * Рендеринг сторінки
     */
    protected function render($data = [])
    {
        $templateData = array_merge($this->getTemplateData(), $data);

        // Перевіряємо, чи є кастомний шлях до шаблону
        $customTemplatePath = $this->getTemplatePath();
        $defaultTemplatePath = __DIR__ . '/../templates/';

        // Нормалізуємо шляхи для порівняння (замінюємо зворотні слеші на прямі)
        $customPathNormalized = str_replace('\\', '/', rtrim($customTemplatePath, '/\\')) . '/';
        $defaultPathNormalized = str_replace('\\', '/', rtrim($defaultTemplatePath, '/\\')) . '/';

        if ($customPathNormalized !== $defaultPathNormalized) {
            // Використовуємо кастомний шаблон з плагіна
            $this->renderCustomTemplate($customTemplatePath, $templateData);
        } else {
            // Використовуємо стандартний шаблон
            ob_start();
            renderTemplate($this->templateName, $templateData);
            $output = ob_get_clean();
            if ($output !== false) {
                echo ltrim($output, "\xEF\xBB\xBF\r\n\t ");
            }
        }
    }

    /**
     * Рендеринг кастомного шаблону (для плагінів)
     */
    protected function renderCustomTemplate($templatePath, $data)
    {
        // Витягуємо змінні з даних
        extract($data);

        $templateFile = $templatePath . $this->templateName . '.php';

        if (! file_exists($templateFile)) {
            logger()->logError('Template not found in AdminPage::render', ['template' => $templateFile]);
            throw new RuntimeException('Template not found: ' . $templateFile);
        }

        // Зберігаємо шлях до кастомного шаблону для використання в layout
        $customTemplateFile = $templateFile;

        // Використовуємо стандартний layout адмінки (тепер підтримує кастомні шаблони)
        ob_start();
        include __DIR__ . '/../layouts/base.php';
        $output = ob_get_clean();
        if ($output !== false) {
            echo ltrim($output, "\xEF\xBB\xBF\r\n\t ");
        }
    }

    /**
     * Обробка запиту (перевизначається в дочірніх класах)
     */
    public function handle()
    {
        // Перевизначається в дочірніх класах
    }

    /**
     * Допоміжний метод для рендерингу компонента alert
     */
    protected function renderAlert($message, $type = 'info', $dismissible = true, $icon = null)
    {
        $alertPath = __DIR__ . '/../components/alert.php';
        if (file_exists($alertPath)) {
            include $alertPath;
        }
    }

    /**
     * Допоміжний метод для рендерингу компонента button
     */
    protected function renderButton($text, $type = 'primary', $options = [])
    {
        $buttonPath = __DIR__ . '/../components/button.php';
        if (file_exists($buttonPath)) {
            $url = $options['url'] ?? null;
            $icon = $options['icon'] ?? null;
            $attributes = $options['attributes'] ?? [];
            $submit = $options['submit'] ?? false;
            include $buttonPath;
        }
    }

    /**
     * Допоміжний метод для рендерингу компонента empty-state
     */
    protected function renderEmptyState($icon, $title, $message, $actions = '', $classes = [])
    {
        $emptyStatePath = __DIR__ . '/../components/empty-state.php';
        if (file_exists($emptyStatePath)) {
            include $emptyStatePath;
        }
    }

    /**
     * Допоміжний метод для отримання HTML компонента через ob_start/ob_get_clean
     */
    protected function getComponent($componentName, $data = [])
    {
        $componentPath = __DIR__ . '/../components/' . $componentName . '.php';
        if (! file_exists($componentPath)) {
            return '';
        }

        // Витягуємо змінні з даних
        extract($data);

        ob_start();
        include $componentPath;

        return ob_get_clean();
    }

    /**
     * Створення кнопки через компонент та повернення HTML
     *
     * @param string $text Текст кнопки
     * @param string $type Тип кнопки (primary, secondary, success, danger, warning, info, outline-primary, тощо)
     * @param array $options Опції: url, icon, attributes, submit
     * @return string HTML кнопки
     */
    protected function createButton($text, $type = 'primary', $options = [])
    {
        return $this->getComponent('button', array_merge([
            'text' => $text,
            'type' => $type,
        ], $options));
    }

    /**
     * Створення групи кнопок для header
     *
     * @param array $buttons Масив кнопок: [['text' => '...', 'type' => '...', 'options' => [...]], ...]
     * @param string $wrapperClass CSS клас для обгортки (за замовчуванням 'd-flex gap-2')
     * @return string HTML групи кнопок
     */
    protected function createButtonGroup($buttons, $wrapperClass = 'd-flex gap-2')
    {
        $buttonsHtml = '';
        foreach ($buttons as $button) {
            $text = $button['text'] ?? '';
            $type = $button['type'] ?? 'primary';
            $options = $button['options'] ?? [];
            $buttonsHtml .= $this->createButton($text, $type, $options);
        }

        return '<div class="' . htmlspecialchars($wrapperClass) . '">' . $buttonsHtml . '</div>';
    }

    /**
     * Отримання екземпляра Request (використовує клас безпосередньо з engine/classes)
     *
     * @return Request
     */
    protected function request(): Request
    {
        return Request::getInstance();
    }

    /**
     * Перевірка на AJAX запит
     *
     * @return bool
     */
    protected function isAjaxRequest(): bool
    {
        return AjaxHandler::isAjax();
    }

    /**
     * Отримання даних з запиту
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return mixed
     */
    protected function input(string $key, $default = null)
    {
        return Request::input($key, $default);
    }

    /**
     * Отримання POST даних
     */
    protected function post(string $key, $default = null)
    {
        return $this->request()->post($key, $default);
    }

    /**
     * Отримання GET даних
     */
    protected function query(string $key, $default = null)
    {
        return $this->request()->query($key, $default);
    }

    /**
     * Отримання методу запиту
     */
    protected function method(): string
    {
        return Request::getMethod();
    }

    /**
     * Перевірка методу запиту
     */
    protected function isMethod(string $method): bool
    {
        return $this->request()->isMethod($method);
    }

    /**
     * Відправка JSON відповіді (для AJAX)
     * Використовує Response::jsonResponse безпосередньо з engine/classes
     *
     * @param array $data Дані для відправки
     * @param int $statusCode HTTP статус код
     */
    protected function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        // Очищаємо буфер виводу перед відправкою JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Переконуємося, що заголовки ще не відправлені
        if (headers_sent($file, $line)) {
            logger()->logWarning("sendJsonResponse() викликано після відправки заголовків в {$file}:{$line}", ['file' => $file, 'line' => $line]);
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'error' => 'Headers already sent'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        Response::jsonResponse($data, $statusCode);
        // exit викликається всередині Response::jsonResponse(), але для надійності викликаємо ще раз
        exit;
    }

    /**
     * Редирект на сторінку
     *
     * @param string $page Сторінка для редиректу (якщо порожньо, використовується поточна)
     * @param array $params Параметри GET
     */
    protected function redirect(string $page = '', array $params = []): void
    {
        if (empty($page)) {
            $page = $this->currentPage;
        }

        $url = UrlHelper::admin($page);
        if (! empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $this->markRedirectPerformed();
        Response::redirectStatic($url);
        exit;
    }

    /**
     * Перевірка необхідності автоматичного редиректу
     */
    private function shouldAutoRedirect(): bool
    {
        // Не редиректимо, якщо:
        // 1. Це не POST-запит
        // 2. Це AJAX-запит
        // 3. Автоматичний редирект вимкнено
        // 4. Редирект вже був виконаний
        // 5. POST не був оброблений (немає повідомлень)

        if (Request::getMethod() !== 'POST') {
            return false;
        }

        if (AjaxHandler::isAjax()) {
            return false;
        }

        if (! $this->autoRedirectEnabled) {
            return false;
        }

        if ($this->redirectPerformed) {
            return false;
        }

        if (! $this->postProcessed && empty($this->message)) {
            return false;
        }

        return true;
    }

    /**
     * Деструктор для автоматичного редиректу після POST
     */
    public function __destruct()
    {
        // Автоматичний редирект після POST-запиту
        if ($this->shouldAutoRedirect()) {
            $this->redirect();
        }
    }

    /**
     * Гарантує, що перший користувач має роль розробника та доступ до адмінки
     */
    private function ensureFirstUserHasAdminAccess(): void
    {
        $session = sessionManager();
        $userId = (int)$session->get('admin_user_id');
        if ($userId !== 1 || ! $this->db) {
            return;
        }

        try {
            $this->ensureRolesAndPermissionsExist();

            // Перевіряємо, чи є роль developer у користувача через role_ids
            $stmt = $this->db->prepare('SELECT role_ids FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $hasDeveloperRole = false;
            if ($user && ! empty($user['role_ids'])) {
                $roleIds = json_decode($user['role_ids'], true) ?: [];
                // Перевіряємо, чи є роль developer
                $stmt = $this->db->prepare("SELECT id FROM roles WHERE slug = 'developer' LIMIT 1");
                $stmt->execute();
                $devRole = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($devRole && in_array((int)$devRole['id'], $roleIds)) {
                    $hasDeveloperRole = true;
                }
            }

            if (! $hasDeveloperRole) {
                $stmt = $this->db->prepare("SELECT id FROM roles WHERE slug = 'developer' LIMIT 1");
                $stmt->execute();
                $role = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($role) {
                    $roleId = (int)$role['id'];
                    // Отримуємо поточні role_ids
                    $roleIds = [];
                    if ($user && ! empty($user['role_ids'])) {
                        $roleIds = json_decode($user['role_ids'], true) ?: [];
                    }
                    // Додаємо роль developer
                    if (! in_array($roleId, $roleIds)) {
                        $roleIds[] = $roleId;
                        $stmt = $this->db->prepare('UPDATE users SET role_ids = ? WHERE id = ?');
                        $stmt->execute([json_encode($roleIds), $userId]);
                    }
                }
            }

            if (class_exists('RoleManager')) {
                RoleManager::getInstance()->clearUserCache($userId);
            }
        } catch (Exception $e) {
            logger()->logError('AdminPage ensureFirstUserHasAdminAccess error: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Створює базові ролі та дозволи, якщо вони відсутні
     */
    private function ensureRolesAndPermissionsExist(): void
    {
        if (! $this->db) {
            return;
        }

        try {
            $stmt = $this->db->query('SELECT COUNT(*) FROM roles');
            $rolesCount = (int)$stmt->fetchColumn();

            if ($rolesCount === 0) {
                $roles = [
                    ['Разработчик', 'developer', 'Повний доступ до всіх функцій системи. Роль не може бути видалена.', 1],
                    ['Користувач', 'user', 'Базові права користувача', 1],
                    ['Гость', 'guest', 'Базова роль для неавторизованих користувачів', 1],
                ];

                foreach ($roles as $roleData) {
                    $stmt = $this->db->prepare('INSERT IGNORE INTO roles (name, slug, description, is_system) VALUES (?, ?, ?, ?)');
                    $stmt->execute($roleData);
                }
            }

            $stmt = $this->db->query('SELECT COUNT(*) FROM permissions');
            $permissionsCount = (int)$stmt->fetchColumn();

            if ($permissionsCount === 0) {
                $permissions = [
                    ['Доступ до адмін-панелі', 'admin.access', 'Доступ до адміністративної панелі', 'admin'],
                ];

                foreach ($permissions as $permissionData) {
                    $stmt = $this->db->prepare('INSERT IGNORE INTO permissions (name, slug, description, category) VALUES (?, ?, ?, ?)');
                    $stmt->execute($permissionData);
                }
            }

            $stmt = $this->db->prepare("SELECT id FROM roles WHERE slug = 'developer' LIMIT 1");
            $stmt->execute();
            $developerRole = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($developerRole) {
                $roleId = (int)$developerRole['id'];
                $permissionIds = $this->db->query('SELECT id FROM permissions')->fetchAll(PDO::FETCH_COLUMN);
                $permissionsJson = json_encode($permissionIds);
                $stmt = $this->db->prepare('UPDATE roles SET permissions = ? WHERE id = ?');
                $stmt->execute([$permissionsJson, $roleId]);
            }

            // Призначаємо базові дозволи ролі user
            $stmt = $this->db->prepare("SELECT id FROM roles WHERE slug = 'user' LIMIT 1");
            $stmt->execute();
            $userRole = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($userRole) {
                $roleId = (int)$userRole['id'];
                // Дозволи для ролі user видалені (дозволи кабінету - це плагін)
                $permissionSlugs = [];

                // Перевіряємо, що масив не порожній перед виконанням запиту
                if (! empty($permissionSlugs)) {
                    $placeholders = implode(',', array_fill(0, count($permissionSlugs), '?'));
                    $permStmt = $this->db->prepare("SELECT id FROM permissions WHERE slug IN ($placeholders)");
                    $permStmt->execute($permissionSlugs);
                    $permissionIds = $permStmt->fetchAll(PDO::FETCH_COLUMN);
                    $permissionsJson = json_encode($permissionIds);
                    $stmt = $this->db->prepare('UPDATE roles SET permissions = ? WHERE id = ?');
                    $stmt->execute([$permissionsJson, $roleId]);
                }
            }
        } catch (Exception $e) {
            logger()->logError('AdminPage ensureRolesAndPermissionsExist error: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Отримати екземпляр ModalHandler
     *
     * @return ModalHandler
     */
    protected function modalHandler(): ModalHandler
    {
        $handler = ModalHandler::getInstance();
        $handler->setContext('admin');

        return $handler;
    }

    /**
     * Реєстрація модального вікна
     *
     * @param string $id ID модального вікна
     * @param array $config Конфігурація
     * @return self
     */
    protected function registerModal(string $id, array $config = []): self
    {
        $this->modalHandler()->register($id, $config);

        return $this;
    }

    /**
     * Реєстрація обробника для модального вікна
     *
     * @param string $modalId ID модального вікна
     * @param string $action Дія
     * @param callable $handler Обробник
     * @return self
     */
    protected function registerModalHandler(string $modalId, string $action, callable $handler): self
    {
        $this->modalHandler()->registerHandler($modalId, $action, $handler);

        return $this;
    }

    /**
     * Обробка AJAX запиту від модального вікна
     *
     * @param string $modalId ID модального вікна
     * @param string $action Дія
     * @return void
     */
    protected function handleModalRequest(string $modalId, string $action): void
    {
        $request = Request::getInstance();

        // Отримуємо всі дані з POST та GET
        $data = $request->all();

        // Прибираємо службові поля
        unset($data['modal_id'], $data['action'], $data['csrf_token']);

        $files = $request->files();

        $result = $this->modalHandler()->handle($modalId, $action, $data, $files);
        // Завжди повертаємо 200 для AJAX - помилки в JSON body
        $this->sendJsonResponse($result, 200);
    }

    /**
     * Рендеринг модального окна
     *
     * @param string $id ID модального окна
     * @param array $options Дополнительные опции
     * @return string HTML
     */
    protected function renderModal(string $id, array $options = []): string
    {
        return $this->modalHandler()->render($id, $options);
    }
}
