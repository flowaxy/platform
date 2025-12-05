<?php

/**
 * Сторінка управління плагінами
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/AdminPage.php';
require_once dirname(__DIR__, 3) . '/infrastructure/filesystem/Directory.php';

use Engine\Classes\Files\Directory;

class PluginsPage extends AdminPage
{
    public function __construct()
    {
        parent::__construct();

        // Перевірка прав доступу
        if (! function_exists('current_user_can') || ! current_user_can('admin.access')) {
            Response::redirectStatic(UrlHelper::admin('dashboard'));
            exit;
        }

        $this->pageTitle = 'Керування плагінами - Flowaxy CMS';
        $this->templateName = 'plugins';

        // Реєструємо модальне вікно завантаження плагіна через компонент
        $this->registerModal('uploadPluginModal', [
            'component' => 'upload',
            'title' => 'Завантажити плагін',
            'action' => 'upload_plugin',
            'fileInputName' => 'plugin_file',
            'label' => 'ZIP архіви з плагінами',
            'accept' => '.zip',
            'multiple' => true,
            'maxSize' => 50,
            'hint' => 'Можна вибрати декілька ZIP-архівів',
        ]);

        // Реєструємо обробник завантаження плагіна
        $this->registerModalHandler('uploadPluginModal', 'upload_plugin', [$this, 'handleUploadPlugin']);

        // Створюємо кнопку через метод createButton, як на logs-view
        // Кнопка "Завантажити плагін" - якщо доступ до сторінки є, то і кнопка має бути
        // (доступ до сторінки вже перевірений вище)
        $headerButtons = $this->createButton('Завантажити плагін', 'primary', [
            'icon' => 'upload',
            'attributes' => [
                'data-bs-toggle' => 'modal',
                'data-bs-target' => '#uploadPluginModal',
                'onclick' => 'window.ModalHandler && window.ModalHandler.show("uploadPluginModal")',
            ],
        ]);

        $this->setPageHeader(
            'Керування плагінами',
            'Встановлення та налаштування плагінів',
            'fas fa-puzzle-piece',
            $headerButtons
        );

        // Додаємо хлібні крихти
        $this->setBreadcrumbs([
            ['title' => 'Головна', 'url' => UrlHelper::admin('dashboard')],
            ['title' => 'Керування плагінами'],
        ]);
    }

    public function handle()
    {
        // Обробка AJAX запитів через ModalHandler
        if ($this->isAjaxRequest()) {
            $modalId = $this->post('modal_id', '');
            $action = SecurityHelper::sanitizeInput($this->post('action', ''));

            if (! empty($modalId) && ! empty($action)) {
                $this->handleModalRequest($modalId, $action);

                return;
            }

            // Старий спосіб обробки AJAX (для зворотної сумісності)
            $this->handleAjax();

            return;
        }

        // Обробка дій
        if ($_POST) {
            $this->handleAction();
        }

        // Автоматичне виявлення нових плагінів ТІЛЬКИ за запитом користувача
        // (через параметр ?discover=1 або через POST)
        if (isset($_GET['discover']) && $_GET['discover'] == '1') {
            try {
                $discovered = pluginManager()->autoDiscoverPlugins();
                if ($discovered > 0) {
                    $this->setMessage("Обнаружено и установлено новых плагинов: {$discovered}", 'success');
                } else {
                    $this->setMessage('Новых плагинов не обнаружено', 'info');
                }
                // Перенаправляем без параметра discover
                $this->redirect('plugins');

                return;
            } catch (Exception $e) {
                if (function_exists('logError')) {
                    logError('PluginsPage: Auto-discover plugins error', ['error' => $e->getMessage(), 'exception' => $e]);
                } else {
                    logger()->logError('Auto-discover plugins error: ' . $e->getMessage(), ['exception' => $e]);
                }
                $this->setMessage('Помилка при виявленні плагінів: ' . $e->getMessage(), 'danger');
            }
        }

        // Отримання списку плагінів
        $installedPlugins = $this->getInstalledPlugins();
        $stats = $this->calculateStats($installedPlugins);

        // Рендеримо сторінку з модальним вікном
        $this->render([
            'installedPlugins' => $installedPlugins,
            'stats' => $stats,
            'uploadModalHtml' => $this->renderModal('uploadPluginModal'),
        ]);
    }

    /**
     * Обробка дій з плагінами
     */
    private function handleAction()
    {
        if (! $this->verifyCsrf()) {
            return;
        }

        $action = $_POST['action'] ?? '';
        $pluginSlug = $_POST['plugin_slug'] ?? '';

        // Перевірка прав доступу для кожного дії
        // Використовуємо загальне право admin.access
        if (! function_exists('current_user_can') || ! current_user_can('admin.access')) {
            $this->setMessage('У вас немає прав на виконання цієї дії', 'danger');
            return;
        }

        try {
            switch ($action) {
                case 'install':
                    pluginManager()->installPlugin($pluginSlug);
                    $this->setMessage('Плагін встановлено', 'success');

                    break;

                case 'activate':
                    pluginManager()->activatePlugin($pluginSlug);
                    if (function_exists('logInfo')) {
                        logInfo('PluginsPage: Plugin activated', ['plugin' => $pluginSlug]);
                    } else {
                        logger()->logInfo('Плагін активовано', ['plugin' => $pluginSlug]);
                    }
                    $this->setMessage('Плагін активовано', 'success');

                    break;

                case 'deactivate':
                    pluginManager()->deactivatePlugin($pluginSlug);
                    if (function_exists('logInfo')) {
                        logInfo('PluginsPage: Plugin deactivated', ['plugin' => $pluginSlug]);
                    } else {
                        logger()->logInfo('Плагін деактивовано', ['plugin' => $pluginSlug]);
                    }
                    $this->setMessage('Плагін деактивовано', 'success');

                    break;

                case 'uninstall':
                    // Перевіряємо, чи деактивовано плагін
                    $db = $this->db;
                    if ($db) {
                        $stmt = $db->prepare('SELECT is_active FROM plugins WHERE slug = ?');
                        $stmt->execute([$pluginSlug]);
                        $plugin = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($plugin && ! empty($plugin['is_active']) && $plugin['is_active'] == 1) {
                            $this->setMessage('Спочатку деактивуйте плагін перед видаленням', 'warning');

                            break;
                        }
                    }

                    if (pluginManager()->uninstallPlugin($pluginSlug)) {
                        if (function_exists('logInfo')) {
                            logInfo('PluginsPage: Plugin uninstalled', ['plugin' => $pluginSlug]);
                        } else {
                            logger()->logInfo('Плагін видалено', ['plugin' => $pluginSlug]);
                        }
                        $this->setMessage('Плагін видалено', 'success');
                    } else {
                        if (function_exists('logWarning')) {
                            logWarning('PluginsPage: Plugin uninstall error', ['plugin' => $pluginSlug]);
                        } else {
                            logger()->logWarning('Помилка видалення плагіна', ['plugin' => $pluginSlug]);
                        }
                        $this->setMessage('Помилка видалення плагіна. Переконайтеся, що плагін деактивований', 'danger');
                    }

                    break;
            }
        } catch (Exception $e) {
            $this->setMessage('Помилка: ' . $e->getMessage(), 'danger');
            if (function_exists('logError')) {
                logError('PluginsPage: Plugin action error', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('Plugin action error: ' . $e->getMessage(), ['exception' => $e]);
            }
        }

        // Редирект після обробки дії для запобігання повторного виконання
        $this->redirect('plugins');
        exit;
    }

    /**
     * Отримання всіх плагінів (з папки + БД)
     *
     * @return array<int, array<string, mixed>>
     */
    private function getInstalledPlugins(): array
    {
        $allPlugins = [];
        $pluginsDir = $this->getPluginsDir();

        // Отримуємо плагіни з БД
        $dbPlugins = [];

        try {
            $stmt = $this->db->query('SELECT * FROM plugins');
            $dbPlugins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $dbPlugins = array_column($dbPlugins, null, 'slug');
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('PluginsPage: DB plugins error', ['error' => $e->getMessage(), 'exception' => $e]);
            } else {
                logger()->logError('DB plugins error: ' . $e->getMessage(), ['exception' => $e]);
            }
        }

        // Скануємо папку plugins
        if (is_dir($pluginsDir)) {
            $directories = glob($pluginsDir . '*', GLOB_ONLYDIR);

            foreach ($directories as $dir) {
                $slug = basename($dir);
                $configFile = $dir . '/plugin.json';

                if (file_exists($configFile) && is_readable($configFile)) {
                    $configContent = @file_get_contents($configFile);
                    if ($configContent === false) {
                        if (function_exists('logWarning')) {
                            logWarning("PluginsPage: Cannot read plugin.json", ['plugin' => $slug]);
                        } else {
                            logger()->logWarning("Cannot read plugin.json for plugin: {$slug}");
                        }
                        continue;
                    }

                    $config = Json::decode($configContent, true);

                    if ($config && is_array($config)) {
                        // Використовуємо slug з конфігу або з імені директорії
                        $pluginSlug = $config['slug'] ?? $slug;

                        // Перевіряємо, чи встановлено плагін в БД
                        $isInstalled = isset($dbPlugins[$pluginSlug]);
                        $isActive = $isInstalled && isset($dbPlugins[$pluginSlug]['is_active']) && $dbPlugins[$pluginSlug]['is_active'];

                        // Перевіряємо наявність сторінки налаштувань
                        $hasSettings = $this->pluginHasSettings($pluginSlug, $dir);

                        $allPlugins[] = [
                            'slug' => $pluginSlug,
                            'name' => $config['name'] ?? $pluginSlug,
                            'description' => $config['description'] ?? '',
                            'version' => $config['version'] ?? '1.0.0',
                            'author' => $config['author'] ?? '',
                            'author_url' => $config['author_url'] ?? '',
                            'is_installed' => $isInstalled,
                            'is_active' => $isActive,
                            'has_settings' => $hasSettings,
                            'settings' => $isInstalled && isset($dbPlugins[$pluginSlug]) ? ($dbPlugins[$pluginSlug]['settings'] ?? null) : null,
                        ];
                    } else {
                        if (function_exists('logWarning')) {
                            logWarning("PluginsPage: Invalid JSON in plugin.json", ['plugin' => $slug]);
                        } else {
                            logger()->logWarning("Invalid JSON in plugin.json for plugin: {$slug}");
                        }
                    }
                }
            }
        }

        return $allPlugins;
    }

    /**
     * Перевірка наявності налаштувань у плагіна
     */
    private function pluginHasSettings(string $pluginSlug, string $pluginDir): bool
    {
        // Перевіряємо наявність файлу сторінки налаштувань
        $settingsFiles = [
            $pluginDir . '/admin/SettingsPage.php',
            $pluginDir . '/admin/' . ucfirst($pluginSlug) . 'SettingsPage.php',
            $pluginDir . '/SettingsPage.php',
        ];

        foreach ($settingsFiles as $file) {
            if (file_exists($file)) {
                return true;
            }
        }

        // Перевіряємо наявність файлу плагіна і шукаємо реєстрацію маршруту налаштувань
        $pluginFile = $pluginDir . '/' . $this->getPluginClassName($pluginSlug) . '.php';
        if (file_exists($pluginFile)) {
            $content = @file_get_contents($pluginFile);
            if ($content && (
                str_contains($content, '-settings') ||
                str_contains($content, 'SettingsPage') ||
                str_contains($content, 'registerAdminRoute')
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * Отримання імені класу плагіна
     */
    private function getPluginClassName(string $pluginSlug): string
    {
        $parts = explode('-', $pluginSlug);
        $className = '';
        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }

        return $className . 'Plugin';
    }

    /**
     * Розрахунок статистики
     *
     * @param array<int, array<string, mixed>> $plugins
     * @return array<string, int>
     */
    private function calculateStats(array $plugins): array
    {
        $installed = 0;
        $active = 0;
        $available = count($plugins);

        foreach ($plugins as $plugin) {
            if ($plugin['is_installed']) {
                $installed++;
            }
            if ($plugin['is_active']) {
                $active++;
            }
        }

        return [
            'total' => $available,
            'installed' => $installed,
            'active' => $active,
            'inactive' => $installed - $active,
        ];
    }

    private function getProjectRoot(): string
    {
        static $rootDir = null;
        if ($rootDir === null) {
            $rootDir = dirname(__DIR__, 4);
        }

        return $rootDir;
    }

    private function getPluginsDir(): string
    {
        static $pluginsDir = null;
        if ($pluginsDir === null) {
            $pluginsDir = rtrim($this->getProjectRoot() . '/plugins', '/\\') . DIRECTORY_SEPARATOR;
        }

        return $pluginsDir;
    }

    /**
     * Рекурсивне видалення директорії
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        return @rmdir($dir);
    }

    /**
     * Обробка AJAX запитів
     * Використовуємо ModalHandler для обробки запитів від модальних вікон
     */
    private function handleAjax(): void
    {
        $request = Request::getInstance();
        $modalId = $request->post('modal_id', '');
        $action = SecurityHelper::sanitizeInput($request->post('action', ''));

        // Якщо запит від модального вікна, обробляємо через ModalHandler
        if (! empty($modalId) && ! empty($action)) {
            $this->handleModalRequest($modalId, $action);

            return;
        }

        // Зворотна сумісність зі старими запитами
        $action = SecurityHelper::sanitizeInput($request->get('action', $request->post('action', '')));

        switch ($action) {
            case 'upload_plugin':
                $this->ajaxUploadPlugin();

                break;

            default:
                $this->sendJsonResponse(['success' => false, 'error' => 'Невідома дія'], 400);
        }
    }

    /**
     * Обробник завантаження плагіна для ModalHandler
     * Використовує логіку з ajaxUploadPlugin, але повертає масив замість відправки JSON
     *
     * @param array $data Дані запиту
     * @param array $files Файли
     * @return array Результат
     */
    /**
     * Нормалізація масиву файлів з multiple input
     * PHP створює структуру ['name' => [...], 'tmp_name' => [...]]
     * А нам потрібно [['name' => ..., 'tmp_name' => ...], ...]
     */
    private function normalizeFilesArray(array $files): array
    {
        // Якщо це один файл (не масив)
        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $normalized[] = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];
            }
        }

        return $normalized;
    }

    /**
     * Очистка slug від GitHub-суфіксів
     * logs-view-main -> logs-view
     * cache-master -> cache
     * bot-blocker-test-dev -> bot-blocker-test
     */
    private function cleanGitHubSuffix(string $slug): string
    {
        // Суфікси GitHub гілок
        $suffixes = ['-main', '-master', '-dev', '-develop', '-release', '-stable', '-latest', '-beta', '-alpha'];

        foreach ($suffixes as $suffix) {
            if (str_ends_with(strtolower($slug), $suffix)) {
                return substr($slug, 0, -strlen($suffix));
            }
        }

        return $slug;
    }

    public function handleUploadPlugin(array $data, array $files): array
    {
        if (! $this->verifyCsrf()) {
            return ['success' => false, 'error' => 'Помилка безпеки', 'reload' => false];
        }

        // Перевірка прав доступу - використовуємо загальне право admin.access
        if (! function_exists('current_user_can') || ! current_user_can('admin.access')) {
            return ['success' => false, 'error' => 'У вас немає прав на встановлення плагінів', 'reload' => false];
        }

        if (! isset($files['plugin_file'])) {
            return ['success' => false, 'error' => 'Файл не вибрано', 'reload' => false];
        }

        // Нормалізуємо масив файлів для підтримки multiple
        $normalizedFiles = $this->normalizeFilesArray($files['plugin_file']);

        if (empty($normalizedFiles)) {
            return ['success' => false, 'error' => 'Файл не вибрано', 'reload' => false];
        }

        $successCount = 0;
        $errors = [];
        $installedPlugins = [];

        // Параметр перезапису
        $overwrite = !empty($data['overwrite']);

        // Обробляємо кожен файл окремо
        foreach ($normalizedFiles as $file) {
            $result = $this->processPluginUpload($file, $overwrite);
            if ($result['success']) {
                $successCount++;
                $installedPlugins[] = $result['plugin'] ?? $file['name'];
            } else {
                $errors[] = $file['name'] . ': ' . $result['error'];
            }
        }

        // Формуємо відповідь
        if ($successCount === 0) {
            if (function_exists('logWarning')) {
                logWarning('PluginsPage: Plugin installation errors', ['errors' => $errors]);
            } else {
                logger()->logWarning('Помилка встановлення плагінів', ['errors' => $errors]);
            }
            return ['success' => false, 'error' => implode("\n", $errors), 'reload' => false];
        }

        if (function_exists('logInfo')) {
            logInfo('PluginsPage: Plugins installed', [
                'count' => $successCount,
                'plugins' => $installedPlugins,
            ]);
        } else {
            logger()->logInfo('Плагіни встановлено', [
                'count' => $successCount,
                'plugins' => $installedPlugins,
            ]);
        }

        $message = "Встановлено плагінів: {$successCount}";
        if (!empty($errors)) {
            $message .= "\nПомилки:\n" . implode("\n", $errors);
        }

        return ['success' => true, 'message' => $message, 'reload' => true];
    }

    /**
     * Обробка одного файлу плагіна
     *
     * @param array $file Файл
     * @param bool $overwrite Перезаписати існуючий плагін
     */
    private function processPluginUpload(array $file, bool $overwrite = false): array
    {
        $uploadedFile = null;
        $zip = null;

        try {
            // Завантажуємо файл через клас Upload
            $upload = new Upload();
            $upload->setAllowedExtensions(['zip'])
                   ->setAllowedMimeTypes(['application/zip', 'application/x-zip-compressed'])
                   ->setMaxFileSize(50 * 1024 * 1024)
                   ->setNamingStrategy('random')
                   ->setOverwrite(true);

            // Створюємо тимчасову директорію
            $projectRoot = $this->getProjectRoot();
            $storageDir = $projectRoot . '/storage/temp/';

            if (! is_dir($storageDir)) {
                if (! @mkdir($storageDir, 0755, true)) {
                    throw new Exception('Не вдалося створити тимчасову директорію');
                }
            }

            $upload->setUploadDir($storageDir);
            $uploadResult = $upload->upload($file);

            if (! $uploadResult['success']) {
                return ['success' => false, 'error' => $uploadResult['error'], 'reload' => false];
            }

            $uploadedFile = $uploadResult['file'];

            // Відкриваємо ZIP архів
            $zip = new Zip();
            $zip->open($uploadedFile, ZipArchive::RDONLY);

            // Перевіряємо наявність plugin.json
            $entries = $zip->getEntries();
            $hasPluginJson = false;
            $pluginJsonPath = null;
            $pluginSlug = null;

            foreach ($entries as $entryName) {
                // Нормалізуємо шлях (замінюємо зворотні слеші на прямі для Windows архівів)
                $normalizedPath = str_replace('\\', '/', $entryName);
                $normalizedPath = trim($normalizedPath, '/');

                // Пропускаємо директорії
                if (str_ends_with($normalizedPath, '/')) {
                    continue;
                }

                // Перевіряємо, чи є файл plugin.json (в будь-якій папці)
                if (basename($normalizedPath) === 'plugin.json') {
                    $hasPluginJson = true;
                    $pluginJsonPath = $entryName; // Використовуємо оригінальне ім'я для витягування

                    // Визначаємо slug зі шляху
                    $pathParts = explode('/', $normalizedPath);
                    if (count($pathParts) >= 2) {
                        // Перша частина шляху - це зазвичай назва плагіна (slug)
                        // Очищаємо від GitHub-суфіксів (main, master, dev...)
                        $pluginSlug = $this->cleanGitHubSuffix($pathParts[0]);
                    }

                    break;
                }
            }

            if (! $hasPluginJson) {
                if ($zip) {
                    $zip->close();
                }
                if ($uploadedFile && file_exists($uploadedFile)) {
                    @unlink($uploadedFile);
                }

                return ['success' => false, 'error' => 'Архів не містить plugin.json', 'reload' => false];
            }

            // Визначаємо slug
            if (! $pluginSlug) {
                $pluginJsonContent = $zip->getEntryContents($pluginJsonPath);
                if ($pluginJsonContent) {
                    $config = Json::decode($pluginJsonContent, true);
                    if ($config && isset($config['slug'])) {
                        $pluginSlug = $config['slug'];
                    }
                }
            }

            if (! $pluginSlug) {
                $pluginSlug = pathinfo($file['name'], PATHINFO_FILENAME);
                // Очищаємо від GitHub-суфіксів (main, master, dev...)
                $pluginSlug = $this->cleanGitHubSuffix($pluginSlug);
            }

            $pluginSlug = preg_replace('/[^a-z0-9\-_]/i', '', $pluginSlug);
            if (empty($pluginSlug)) {
                if ($zip) {
                    $zip->close();
                }
                if ($uploadedFile && file_exists($uploadedFile)) {
                    @unlink($uploadedFile);
                }

                return ['success' => false, 'error' => 'Неможливо визначити slug плагіна', 'reload' => false];
            }

            // Перевіряємо, чи не існує вже плагін
            $pluginsDir = $this->getPluginsDir();
            $pluginPath = $pluginsDir . $pluginSlug . '/';

            if (is_dir($pluginPath)) {
                if ($overwrite) {
                    // Видаляємо існуючу папку для оновлення
                    $this->deleteDirectory($pluginPath);
                } else {
                    if ($zip) {
                        $zip->close();
                    }
                    if ($uploadedFile && file_exists($uploadedFile)) {
                        @unlink($uploadedFile);
                    }

                    return ['success' => false, 'error' => 'Плагін з таким slug вже існує: ' . $pluginSlug, 'reload' => false];
                }
            }

            // Створюємо папку для плагіна
            if (! @mkdir($pluginPath, 0755, true)) {
                if ($zip) {
                    $zip->close();
                }
                if ($uploadedFile && file_exists($uploadedFile)) {
                    @unlink($uploadedFile);
                }

                return ['success' => false, 'error' => 'Помилка створення папки плагіна', 'reload' => false];
            }

            // Визначаємо кореневу папку в архіві
            $rootPath = null;
            if ($pluginJsonPath) {
                $rootPath = dirname($pluginJsonPath);
                if ($rootPath === '.' || $rootPath === '') {
                    $rootPath = null;
                }
            }

            // Розпаковуємо файли
            $extracted = 0;
            foreach ($entries as $entryName) {
                if (str_ends_with($entryName, '/')) {
                    continue;
                }

                if ($rootPath) {
                    if (str_starts_with($entryName, $rootPath . '/')) {
                        $relativePath = substr($entryName, strlen($rootPath) + 1);
                    } else {
                        continue;
                    }
                } else {
                    $relativePath = $entryName;
                }

                if (str_contains($relativePath, '../') || str_contains($relativePath, '..\\')) {
                    continue;
                }

                $targetPath = $pluginPath . $relativePath;
                $targetDirPath = dirname($targetPath);

                if (! is_dir($targetDirPath)) {
                    if (! @mkdir($targetDirPath, 0755, true)) {
                        continue;
                    }
                }

                try {
                    $zip->extractFile($entryName, $targetPath);
                    $extracted++;
                } catch (Exception $e) {
                    if (function_exists('logError')) {
                        logError("PluginsPage: Failed to extract file", ['entry' => $entryName, 'error' => $e->getMessage(), 'exception' => $e]);
                    } else {
                        logger()->logError("Failed to extract file {$entryName}: " . $e->getMessage(), ['exception' => $e, 'entry' => $entryName]);
                    }
                }
            }

            if ($zip) {
                $zip->close();
            }
            if ($uploadedFile && file_exists($uploadedFile)) {
                @unlink($uploadedFile);
            }

            // Автоматично встановлюємо плагін
            try {
                pluginManager()->installPlugin($pluginSlug);

                return [
                    'success' => true,
                    'plugin' => $pluginSlug,
                ];
            } catch (Exception $e) {
                if (function_exists('logError')) {
                    logError('PluginsPage: Plugin install error', ['plugin_slug' => $pluginSlug, 'error' => $e->getMessage(), 'exception' => $e]);
                } else {
                    logger()->logError('Plugin install error: ' . $e->getMessage(), ['exception' => $e, 'plugin_slug' => $pluginSlug]);
                }

                return [
                    'success' => true,
                    'plugin' => $pluginSlug,
                    'warning' => 'Завантажено, але помилка при встановленні: ' . $e->getMessage(),
                ];
            }
        } catch (Throwable $e) {
            if ($zip) {
                try {
                    $zip->close();
                } catch (Exception $ex) {
                    // Ігноруємо помилки закриття
                }
            }
            if ($uploadedFile && file_exists($uploadedFile)) {
                @unlink($uploadedFile);
            }

            if (function_exists('logError')) {
                logError('PluginsPage: Plugin upload error', ['error' => $e->getMessage(), 'exception' => $e, 'trace' => $e->getTraceAsString()]);
            } else {
                logger()->logError('Plugin upload error: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString()]);
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * AJAX завантаження плагіна з ZIP архіву
     * Використовуємо Request та File безпосередньо з engine/classes
     */
    private function ajaxUploadPlugin(): void
    {
        if (! $this->verifyCsrf()) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Помилка безпеки'], 403);
        }

        $request = Request::getInstance();
        $files = $request->files();

        if (! isset($files['plugin_file'])) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Файл не вибрано'], 400);
        }

        $uploadedFile = null;
        $zip = null;

        try {
            // Завантажуємо файл через клас Upload
            $upload = new Upload();
            $upload->setAllowedExtensions(['zip'])
                   ->setAllowedMimeTypes(['application/zip', 'application/x-zip-compressed'])
                   ->setMaxFileSize(50 * 1024 * 1024) // 50 MB
                   ->setNamingStrategy('random') // Використовуємо випадкове ім'я для уникнення конфліктів
                   ->setOverwrite(true); // Дозволяємо перезаписувати файли

            // Створюємо тимчасову директорію для завантаження
            // Використовуємо директорію всередині проекту для сумісності з різними хостингами
            $projectRoot = $this->getProjectRoot();
            $tempDir = null;
            $errors = [];

            // Клас Directory завантажується через автозавантажувач
            // Не потрібно завантажувати вручну

            // Функція для створення та перевірки директорії через клас Directory
            $createTempDir = function ($dirPath, $parentDir = null) use (&$tempDir, &$errors) {
                try {
                    // Перевіряємо, чи клас Directory завантажений (наш клас, а не вбудований PHP)
                    // Перевіряємо наявність методу create() щоб переконатися що це наш клас
                    if (! class_exists('Directory') || ! method_exists('Directory', 'create')) {
                        // Якщо не завантажений, використовуємо стандартні PHP функції
                        if ($parentDir && ! is_dir($parentDir)) {
                            if (! @mkdir($parentDir, 0755, true)) {
                                $errors[] = "Не вдалося створити батьківську директорію: {$parentDir}";

                                return false;
                            }
                        }

                        if ($parentDir && ! is_writable($parentDir)) {
                            $errors[] = "Немає прав на запис у директорію: {$parentDir}";

                            return false;
                        }

                        if (! is_dir($dirPath)) {
                            if (! @mkdir($dirPath, 0755, true)) {
                                $errors[] = "Не вдалося створити директорію: {$dirPath}";

                                return false;
                            }
                        }

                        if (! is_writable($dirPath)) {
                            $errors[] = "Немає прав на запис у директорію: {$dirPath}";

                            return false;
                        }

                        $tempDir = $dirPath;

                        return true;
                    }

                    // Використовуємо клас Directory
                    // Спочатку перевіряємо/створюємо батьківську директорію
                    if ($parentDir) {
                        $parentDirObj = new Directory($parentDir);
                        if (! $parentDirObj->exists()) {
                            try {
                                $parentDirObj->create(0755, true);
                            } catch (Exception $e) {
                                $errors[] = "Не вдалося створити батьківську директорію: {$parentDir} - " . $e->getMessage();

                                return false;
                            }
                        }

                        // Перевіряємо права на запис у батьківську директорію
                        if (! is_writable($parentDir)) {
                            $errors[] = "Немає прав на запис у директорію: {$parentDir}";

                            return false;
                        }
                    }

                    // Створюємо тимчасову директорію через клас Directory
                    $dirObj = new Directory($dirPath);
                    if (! $dirObj->exists()) {
                        try {
                            $dirObj->create(0755, true);
                        } catch (Exception $e) {
                            $errors[] = "Не вдалося створити директорію: {$dirPath} - " . $e->getMessage();

                            return false;
                        }
                    }

                    // Перевіряємо права на запис
                    if (! is_writable($dirPath)) {
                        $errors[] = "Немає прав на запис у директорію: {$dirPath}";

                        return false;
                    }

                    $tempDir = $dirPath;

                    return true;
                } catch (Exception $e) {
                    $errors[] = "Помилка при роботі з директорією {$dirPath}: " . $e->getMessage();

                    return false;
                }
            };

            // Перевіряємо існування директорії storage/temp/ (має бути створена установщиком)
            $storageParent = $projectRoot . '/storage';
            $storageDir = $storageParent . '/temp/';

            if (! is_dir($storageDir)) {
                throw new Exception('Директорія storage/temp/ не існує. Вона повинна бути створена під час установки системи. Будь ласка, запустіть установщик або створіть директорію вручну.');
            }

            if (! is_writable($storageDir)) {
                throw new Exception('Немає прав на запис у директорію storage/temp/. Перевірте права доступу.');
            }

            $tempDir = $storageDir;

            $upload->setUploadDir($tempDir);

            $request = Request::getInstance();
            $uploadResult = $upload->upload($request->files()['plugin_file']);

            if (! $uploadResult['success']) {
                $this->sendJsonResponse(['success' => false, 'error' => $uploadResult['error']], 400);
            }

            $uploadedFile = $uploadResult['file'];

            // Відкриваємо ZIP архів через клас Zip
            $zip = new Zip();
            $zip->open($uploadedFile, ZipArchive::RDONLY);

            // Перевіряємо наявність plugin.json
            $entries = $zip->getEntries();
            $hasPluginJson = false;
            $pluginJsonPath = null;
            $pluginSlug = null;

            foreach ($entries as $entryName) {
                if (basename($entryName) === 'plugin.json') {
                    $hasPluginJson = true;
                    $pluginJsonPath = $entryName;
                    // Спробуємо визначити slug зі шляху
                    $pathParts = explode('/', trim($entryName, '/'));
                    if (count($pathParts) >= 2) {
                        // Очищаємо від GitHub-суфіксів
                        $pluginSlug = $this->cleanGitHubSuffix($pathParts[0]);
                    }

                    break;
                }
            }

            if (! $hasPluginJson) {
                if ($zip) {
                    $zip->close();
                }
                if ($uploadedFile && file_exists($uploadedFile)) {
                    @unlink($uploadedFile);
                }
                $this->sendJsonResponse(['success' => false, 'error' => 'Архів не містить plugin.json'], 400);
            }

            // Якщо slug не визначено, спробуємо прочитати plugin.json
            if (! $pluginSlug) {
                $pluginJsonContent = $zip->getEntryContents($pluginJsonPath);
                if ($pluginJsonContent) {
                    $config = Json::decode($pluginJsonContent, true);
                    if ($config && isset($config['slug'])) {
                        $pluginSlug = $config['slug'];
                    }
                }
            }

            // Якщо все ще немає slug, використовуємо ім'я файлу без розширення
            if (! $pluginSlug) {
                $request = Request::getInstance();
                $files = $request->files();
                $pluginSlug = pathinfo($files['plugin_file']['name'], PATHINFO_FILENAME);
                // Очищаємо від GitHub-суфіксів
                $pluginSlug = $this->cleanGitHubSuffix($pluginSlug);
            }

            // Очищаємо slug від небезпечних символів
            $pluginSlug = preg_replace('/[^a-z0-9\-_]/i', '', $pluginSlug);
            if (empty($pluginSlug)) {
                if ($zip) {
                    $zip->close();
                }
                if ($uploadedFile && file_exists($uploadedFile)) {
                    @unlink($uploadedFile);
                }
                $this->sendJsonResponse(['success' => false, 'error' => 'Неможливо визначити slug плагіна'], 400);
            }

            // Шлях до папки плагінів
            $pluginsDir = $this->getPluginsDir();
            $pluginPath = $pluginsDir . $pluginSlug . '/';

            // Перевіряємо, чи не існує вже плагін з таким slug
            if (is_dir($pluginPath)) {
                if ($zip) {
                    $zip->close();
                }
                if ($uploadedFile && file_exists($uploadedFile)) {
                    @unlink($uploadedFile);
                }
                $this->sendJsonResponse(['success' => false, 'error' => 'Плагін з таким slug вже існує: ' . $pluginSlug], 400);
            }

            // Створюємо папку для плагіна
            if (! @mkdir($pluginPath, 0755, true)) {
                if ($zip) {
                    $zip->close();
                }
                if ($uploadedFile && file_exists($uploadedFile)) {
                    @unlink($uploadedFile);
                }
                $this->sendJsonResponse(['success' => false, 'error' => 'Помилка створення папки плагіна'], 500);
            }

            // Визначаємо кореневу папку в архіві
            $rootPath = null;
            if ($pluginJsonPath) {
                $rootPath = dirname($pluginJsonPath);
                if ($rootPath === '.' || $rootPath === '') {
                    $rootPath = null;
                }
            }

            // Розпаковуємо файли
            $extracted = 0;
            foreach ($entries as $entryName) {
                // Пропускаємо папки
                if (str_ends_with($entryName, '/')) {
                    continue;
                }

                // Визначаємо шлях для витягування
                if ($rootPath) {
                    // Якщо є коренева папка, видаляємо її з шляху
                    if (str_starts_with($entryName, $rootPath . '/')) {
                        $relativePath = substr($entryName, strlen($rootPath) + 1);
                    } else {
                        continue; // Пропускаємо файли поза кореневою папкою
                    }
                } else {
                    $relativePath = $entryName;
                }

                // Пропускаємо небезпечні шляхи
                if (str_contains($relativePath, '../') || str_contains($relativePath, '..\\')) {
                    continue;
                }

                $targetPath = $pluginPath . $relativePath;
                $targetDirPath = dirname($targetPath);

                // Створюємо папки якщо потрібно
                if (! is_dir($targetDirPath)) {
                    if (! @mkdir($targetDirPath, 0755, true)) {
                        if (function_exists('logError')) {
                            logError("PluginsPage: Failed to create directory", ['path' => $targetDirPath]);
                        } else {
                            logger()->logError("Failed to create directory: {$targetDirPath}", ['path' => $targetDirPath]);
                        }
                        continue;
                    }
                }

                // Витягуємо файл
                try {
                    $zip->extractFile($entryName, $targetPath);
                    $extracted++;
                } catch (Exception $e) {
                    if (function_exists('logError')) {
                        logError("PluginsPage: Failed to extract file", ['entry' => $entryName, 'error' => $e->getMessage(), 'exception' => $e]);
                    } else {
                        logger()->logError("Failed to extract file {$entryName}: " . $e->getMessage(), ['exception' => $e, 'entry' => $entryName]);
                    }
                    // Продовжуємо з наступним файлом
                }
            }

            if ($zip) {
                $zip->close();
            }
            if ($uploadedFile && file_exists($uploadedFile)) {
                @unlink($uploadedFile);
            }

            // Автоматично встановлюємо плагін
            try {
                pluginManager()->installPlugin($pluginSlug);
                $this->sendJsonResponse([
                    'success' => true,
                    'message' => 'Плагін успішно завантажено та встановлено',
                    'plugin_slug' => $pluginSlug,
                    'extracted_files' => $extracted,
                ], 200);
            } catch (Exception $e) {
                logger()->logError('Plugin install error: ' . $e->getMessage(), ['exception' => $e]);
                $this->sendJsonResponse([
                    'success' => true,
                    'message' => 'Плагін завантажено, але помилка при встановленні: ' . $e->getMessage(),
                    'plugin_slug' => $pluginSlug,
                    'extracted_files' => $extracted,
                ], 200);
            }
        } catch (Throwable $e) {
            // Очищаємо ресурси при помилці
            if ($zip) {
                try {
                    $zip->close();
                } catch (Exception $ex) {
                    // Ігноруємо помилки закриття
                }
            }
            if ($uploadedFile && file_exists($uploadedFile)) {
                @unlink($uploadedFile);
            }

            if (function_exists('logError')) {
                logError('PluginsPage: Plugin upload error', ['error' => $e->getMessage(), 'exception' => $e, 'trace' => $e->getTraceAsString()]);
            } else {
                logger()->logError('Plugin upload error: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString()]);
            }
            $this->sendJsonResponse(['success' => false, 'error' => 'Помилка: ' . $e->getMessage()], 500);
        }
    }
}
