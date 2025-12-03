<?php

/**
 * Сторінка керування темами
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/AdminPage.php';

class ThemesPage extends AdminPage
{
    public function __construct()
    {
        parent::__construct();

        // Перевірка прав доступу
        if (! function_exists('current_user_can') || ! current_user_can('admin.access')) {
            Response::redirectStatic(UrlHelper::admin('dashboard'));
            exit;
        }

        $this->pageTitle = 'Теми - Flowaxy CMS';
        $this->templateName = 'themes';

        // Реєструємо модальне вікно завантаження теми через ModalHandler
        // Реєструємо модальне вікно завантаження теми через компонент
        $this->registerModal('uploadThemeModal', [
            'component' => 'upload',
            'title' => 'Завантажити тему',
            'action' => 'upload_theme',
            'fileInputName' => 'theme_file',
            'label' => 'ZIP архіви з темами',
            'accept' => '.zip',
            'multiple' => true,
            'maxSize' => 50,
            'hint' => 'Можна вибрати декілька ZIP-архівів',
        ]);

        // Реєструємо обробник завантаження теми
        $this->registerModalHandler('uploadThemeModal', 'upload_theme', [$this, 'handleUploadTheme']);

        // Використовуємо допоміжні методи для створення кнопок
        // Кнопка "Завантажити тему" тільки якщо є право на встановлення
        $headerButtons = '';
        if (function_exists('current_user_can') && current_user_can('admin.access')) {
            $headerButtons = $this->createButtonGroup([
                [
                    'text' => 'Завантажити тему',
                    'type' => 'primary',
                    'options' => [
                        'icon' => 'upload',
                        'attributes' => [
                            'data-bs-toggle' => 'modal',
                            'data-bs-target' => '#uploadThemeModal',
                            'onclick' => 'window.ModalHandler && window.ModalHandler.show("uploadThemeModal")',
                        ],
                    ],
                ],
            ]);
        }

        $this->setPageHeader(
            'Теми',
            'Керування темами дизайну сайту',
            'fas fa-palette',
            $headerButtons
        );
        
        // Додаємо хлібні крихти
        $this->setBreadcrumbs([
            ['title' => 'Головна', 'url' => UrlHelper::admin('dashboard')],
            ['title' => 'Теми'],
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

        // Обробка активації теми
        if ($_POST && isset($_POST['activate_theme'])) {
            $this->activateTheme();
        }

        // Обробка деактивації теми
        if ($_POST && isset($_POST['action']) && $_POST['action'] === 'deactivate_theme') {
            $this->deactivateTheme();
        }

        // Обробка видалення теми
        if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_theme') {
            $this->deleteTheme();
        }

        // Очищаємо кеш тем для отримання актуальної інформації про активність
        themeManager()->clearThemeCache();

        // Отримання всіх тем
        $themes = themeManager()->getAllThemes();
        $activeTheme = themeManager()->getActiveTheme();

        // Перевіряємо підтримку кастомізації та навігації для кожної теми
        $themesWithCustomization = [];
        $themesWithNavigation = [];
        $themesWithSettings = [];
        $themesFeatures = [];
        foreach ($themes as $theme) {
            $themePath = themeManager()->getThemePath($theme['slug']);

            // Використовуємо supports_customization з theme.json, якщо є
            if (isset($theme['supports_customization'])) {
                $themesWithCustomization[$theme['slug']] = (bool)$theme['supports_customization'];
            } else {
                // Fallback: перевіряємо наявність customizer.php
                $themesWithCustomization[$theme['slug']] = file_exists($themePath . 'customizer.php');
            }

            // Перевіряємо підтримку навігації
            if (isset($theme['supports_navigation'])) {
                $themesWithNavigation[$theme['slug']] = (bool)$theme['supports_navigation'];
            } else {
                // Fallback: перевіряємо через ThemeManager
                $themesWithNavigation[$theme['slug']] = themeManager()->supportsNavigation($theme['slug']);
            }

            // Перевіряємо наявність налаштувань теми
            $themesWithSettings[$theme['slug']] = $this->themeHasSettings($theme['slug'], $themePath);

            // Перевіряємо підтримку функцій теми
            $themesFeatures[$theme['slug']] = $this->getThemeFeatures($theme, $themePath);
        }

        // Рендеримо сторінку
        $this->render([
            'themes' => $themes,
            'activeTheme' => $activeTheme,
            'themesWithCustomization' => $themesWithCustomization,
            'themesWithNavigation' => $themesWithNavigation,
            'themesWithSettings' => $themesWithSettings,
            'themesFeatures' => $themesFeatures,
            'uploadModalHtml' => $this->renderModal('uploadThemeModal'),
        ]);
    }

    /**
     * Активація теми
     */
    private function activateTheme()
    {
        if (! $this->verifyCsrf()) {
            return;
        }

        // Перевірка прав доступу
        if (! function_exists('current_user_can') || ! current_user_can('admin.access')) {
            $this->setMessage('У вас немає прав на активацію тем', 'danger');

            return;
        }

        $themeSlug = $_POST['theme_slug'] ?? '';

        if (empty($themeSlug)) {
            $this->setMessage('Тему не вибрано', 'danger');

            return;
        }

        // Активируем тему
        $result = themeManager()->activateTheme($themeSlug);

        if ($result) {
            // Очищаємо всі кеші після успішної активації
            themeManager()->clearThemeCache($themeSlug);

            // Очищаємо кеш меню адмінки (всі варіанти)
            $cachePatterns = [
                'admin_menu_items_0',
                'admin_menu_items_1',
                'admin_menu_items_0_0',
                'admin_menu_items_0_1',
                'admin_menu_items_1_0',
                'admin_menu_items_1_1',
            ];
            foreach ($cachePatterns as $pattern) {
                cache_forget($pattern);
            }

            // Додатково очищаємо кеш активної теми
            cache_forget('active_theme_slug');
            cache_forget('active_theme');

            logger()->logInfo('Тему активовано', ['theme' => $themeSlug]);
            $this->setMessage('Тему успішно активовано', 'success');
            $this->redirect('themes');
            exit;
        } else {
            $this->setMessage('Помилка при активації теми. Перевірте логи системи.', 'danger');
        }
    }

    /**
     * Обробка AJAX запитів
     */
    private function handleAjax()
    {
        // Використовуємо Request безпосередньо з engine/classes
        $request = Request::getInstance();
        $action = SecurityHelper::sanitizeInput($request->get('action', $request->post('action', '')));

        switch ($action) {
            case 'activate_theme':
                $this->ajaxActivateTheme();

                break;

            case 'check_compilation':
                $this->ajaxCheckCompilation();

                break;

            case 'upload_theme':
                $this->ajaxUploadTheme();

                break;

            default:
                $this->sendJsonResponse(['success' => false, 'error' => 'Невідома дія'], 400);
        }
    }

    /**
     * AJAX активація теми з компіляцією SCSS
     */
    private function ajaxActivateTheme()
    {
        if (! $this->verifyCsrf()) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Помилка безпеки'], 403);

            return;
        }

        // Перевірка прав доступу
        if (! function_exists('current_user_can') || ! current_user_can('admin.access')) {
            $this->sendJsonResponse(['success' => false, 'error' => 'У вас немає прав на активацію тем'], 403);

            return;
        }

        $request = Request::getInstance();
        $themeSlug = SecurityHelper::sanitizeInput($request->post('theme_slug', ''));

        if (empty($themeSlug)) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Тему не вибрано'], 400);

            return;
        }

        // Перевіряємо, чи підтримує тема SCSS
        $hasScssSupport = themeManager()->hasScssSupport($themeSlug);

        // Компілюємо SCSS перед активацією, якщо тема підтримує SCSS
        if ($hasScssSupport) {
            $compileResult = themeManager()->compileScss($themeSlug, true);
            if (! $compileResult) {
                // Попереджаємо, але не блокуємо активацію
                logger()->logWarning("ThemeManager: SCSS compilation failed for theme: {$themeSlug}");
            }
        }

        // Активируем тему
        $result = themeManager()->activateTheme($themeSlug);

        if ($result) {
            // Очищаємо всі кеші після успішної активації
            themeManager()->clearThemeCache($themeSlug);

            // Очищаємо кеш меню адмінки (всі варіанти)
            $cachePatterns = [
                'admin_menu_items_0',
                'admin_menu_items_1',
                'admin_menu_items_0_0',
                'admin_menu_items_0_1',
                'admin_menu_items_1_0',
                'admin_menu_items_1_1',
            ];
            foreach ($cachePatterns as $pattern) {
                cache_forget($pattern);
            }

            // Додатково очищаємо кеш активної теми
            cache_forget('active_theme_slug');
            cache_forget('active_theme');

            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Тему успішно активовано',
                'has_scss' => $hasScssSupport,
                'compiled' => $hasScssSupport ? $compileResult : null,
            ], 200);

            return;
        } else {
            $this->sendJsonResponse([
                'success' => false,
                'error' => 'Помилка при активації теми. Перевірте логи системи.',
            ], 500);

            return;
        }
    }

    /**
     * AJAX перевірка статусу компіляції
     */
    private function ajaxCheckCompilation()
    {
        $request = Request::getInstance();
        $themeSlug = SecurityHelper::sanitizeInput($request->query('theme_slug', ''));

        if (empty($themeSlug)) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Тему не вказано'], 400);
        }

        $hasScssSupport = themeManager()->hasScssSupport($themeSlug);
        $themePath = themeManager()->getThemePath($themeSlug);
        $cssFile = new File($themePath . 'assets/css/style.css');
        $cssExists = $cssFile->exists();

        $this->sendJsonResponse([
            'success' => true,
            'has_scss' => $hasScssSupport,
            'css_exists' => $cssExists,
            'css_file' => $cssExists ? 'assets/css/style.css' : null,
        ], 200);
    }

    /**
     * Обробник завантаження теми для ModalHandler
     * Використовує логіку з ajaxUploadTheme, але повертає масив замість відправки JSON
     *
     * @param array $data Дані запиту
     * @param array $files Файли
     * @return array Результат
     */
    /**
     * Нормалізація масиву файлів з multiple input
     */
    private function normalizeFilesArray(array $files): array
    {
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
     * theme-starter-main -> theme-starter
     * starter-master -> starter
     */
    private function cleanGitHubSuffix(string $slug): string
    {
        $suffixes = ['-main', '-master', '-dev', '-develop', '-release', '-stable', '-latest', '-beta', '-alpha'];
        
        foreach ($suffixes as $suffix) {
            if (str_ends_with(strtolower($slug), $suffix)) {
                return substr($slug, 0, -strlen($suffix));
            }
        }
        
        return $slug;
    }

    public function handleUploadTheme(array $data, array $files): array
    {
        if (! $this->verifyCsrf()) {
            return ['success' => false, 'error' => 'Помилка безпеки', 'reload' => false];
        }

        // Перевірка прав доступу
        if (! function_exists('current_user_can') || ! current_user_can('admin.access')) {
            return ['success' => false, 'error' => 'У вас немає прав на встановлення тем', 'reload' => false];
        }

        if (! isset($files['theme_file'])) {
            return ['success' => false, 'error' => 'Файл не вибрано', 'reload' => false];
        }

        // Нормалізуємо масив файлів
        $normalizedFiles = $this->normalizeFilesArray($files['theme_file']);
        
        if (empty($normalizedFiles)) {
            return ['success' => false, 'error' => 'Файл не вибрано', 'reload' => false];
        }
        
        $successCount = 0;
        $errors = [];
        $installedThemes = [];
        
        // Параметр перезапису
        $overwrite = !empty($data['overwrite']);
        
        foreach ($normalizedFiles as $file) {
            $result = $this->processThemeUpload($file, $overwrite);
            if ($result['success']) {
                $successCount++;
                $installedThemes[] = $result['theme'] ?? $file['name'];
            } else {
                $errors[] = $file['name'] . ': ' . $result['error'];
            }
        }
        
        // Очищаємо кеш тем
        themeManager()->clearThemeCache();
        
        if ($successCount === 0) {
            logger()->logWarning('Помилка встановлення тем', ['errors' => $errors]);
            return ['success' => false, 'error' => implode("\n", $errors), 'reload' => false];
        }
        
        logger()->logInfo('Теми встановлено', [
            'count' => $successCount,
            'themes' => $installedThemes,
        ]);
        
        $message = "Встановлено тем: {$successCount}";
        if (!empty($errors)) {
            $message .= "\nПомилки:\n" . implode("\n", $errors);
        }
        
        return ['success' => true, 'message' => $message, 'reload' => true];
    }
    
    /**
     * Обробка одного файлу теми
     * 
     * @param array $file Файл
     * @param bool $overwrite Перезаписати існуючу тему
     * @return array
     */
    private function processThemeUpload(array $file, bool $overwrite = false): array
    {
        $uploadedFile = null;
        $zip = null;

        try {
            $upload = new Upload();
            $upload->setAllowedExtensions(['zip'])
                   ->setAllowedMimeTypes(['application/zip', 'application/x-zip-compressed'])
                   ->setMaxFileSize(50 * 1024 * 1024)
                   ->setNamingStrategy('random')
                   ->setOverwrite(true);

            $projectRoot = dirname(__DIR__, 4);
            $storageDir = $projectRoot . '/storage/temp/';

            if (! is_dir($storageDir)) {
                return ['success' => false, 'error' => 'Директорія storage/temp/ не існує'];
            }

            if (! is_writable($storageDir)) {
                return ['success' => false, 'error' => 'Немає прав на запис у storage/temp/'];
            }

            $upload->setUploadDir($storageDir);
            $uploadResult = $upload->upload($file);

            if (! $uploadResult['success']) {
                return ['success' => false, 'error' => $uploadResult['error'], 'reload' => false];
            }

            $uploadedFile = $uploadResult['file'];

            // Відкриваємо ZIP
            $zip = new Zip();
            $zip->open($uploadedFile, ZipArchive::RDONLY);

            // Перевіряємо наявність theme.json
            $entries = $zip->getEntries();
            $hasThemeJson = false;
            $themeJsonPath = null;
            $themeSlug = null;

            foreach ($entries as $entryName) {
                // Нормалізуємо шлях (замінюємо зворотні слеші на прямі для Windows архівів)
                $normalizedPath = str_replace('\\', '/', $entryName);
                $normalizedPath = trim($normalizedPath, '/');

                // Пропускаємо директорії
                if (str_ends_with($normalizedPath, '/')) {
                    continue;
                }

                // Перевіряємо, чи є файл theme.json (в будь-якій папці)
                if (basename($normalizedPath) === 'theme.json') {
                    $hasThemeJson = true;
                    $themeJsonPath = $entryName; // Використовуємо оригінальне ім'я для витягування

                    // Визначаємо slug зі шляху
                    $pathParts = explode('/', $normalizedPath);
                    if (count($pathParts) >= 2) {
                        // Перша частина шляху - це зазвичай назва теми (slug)
                        // Очищаємо від GitHub-суфіксів (main, master, dev...)
                        $themeSlug = $this->cleanGitHubSuffix($pathParts[0]);
                    }

                    break;
                }
            }

            if (! $hasThemeJson) {
                if ($zip) {
                    $zip->close();
                }
                if ($uploadedFile && file_exists($uploadedFile)) {
                    @unlink($uploadedFile);
                }

                return ['success' => false, 'error' => 'Архів не містить theme.json', 'reload' => false];
            }

            // Визначаємо slug
            if (! $themeSlug) {
                $themeJsonContent = $zip->getEntryContents($themeJsonPath);
                if ($themeJsonContent) {
                    $config = Json::decode($themeJsonContent, true);
                    if ($config && isset($config['slug'])) {
                        $themeSlug = $config['slug'];
                    }
                }
            }

            if (! $themeSlug) {
                $themeSlug = pathinfo($file['name'], PATHINFO_FILENAME);
                // Очищаємо від GitHub-суфіксів
                $themeSlug = $this->cleanGitHubSuffix($themeSlug);
            }

            $themeSlug = preg_replace('/[^a-z0-9\-_]/i', '', $themeSlug);
            if (empty($themeSlug)) {
                if ($zip) {
                    $zip->close();
                }
                if ($uploadedFile && file_exists($uploadedFile)) {
                    @unlink($uploadedFile);
                }

                return ['success' => false, 'error' => 'Неможливо визначити slug теми', 'reload' => false];
            }

            // Путь к папке тем
            $themesDir = dirname(__DIR__, 4) . '/themes/';
            $themePath = $themesDir . $themeSlug . '/';

            // Перевіряємо, чи не існує вже тема
            if (is_dir($themePath)) {
                if ($overwrite) {
                    // Видаляємо існуючу папку для оновлення
                    $this->deleteDirectory($themePath);
                } else {
                    if ($zip) {
                        $zip->close();
                    }
                    if ($uploadedFile && file_exists($uploadedFile)) {
                        @unlink($uploadedFile);
                    }

                    return ['success' => false, 'error' => 'Тема з таким slug вже існує: ' . $themeSlug, 'reload' => false];
                }
            }

            // Створюємо папку для теми
            if (! @mkdir($themePath, 0755, true)) {
                if ($zip) {
                    $zip->close();
                }
                if ($uploadedFile && file_exists($uploadedFile)) {
                    @unlink($uploadedFile);
                }

                return ['success' => false, 'error' => 'Помилка створення папки теми', 'reload' => false];
            }

            // Визначаємо кореневу папку в архіві
            $rootPath = null;
            if ($themeJsonPath) {
                $rootPath = dirname($themeJsonPath);
                if ($rootPath === '.' || $rootPath === '') {
                    $rootPath = null;
                }
            }

            // Распаковываем файлы
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

                $targetPath = $themePath . $relativePath;
                $targetDirPath = dirname($targetPath);

                if (! is_dir($targetDirPath)) {
                    if (! @mkdir($targetDirPath, 0755, true)) {
                        logger()->logError("Failed to create directory: {$targetDirPath}");
                        continue;
                    }
                }

                try {
                    $zip->extractFile($entryName, $targetPath);
                    $extracted++;
                } catch (Exception $e) {
                    logger()->logError("Failed to extract file {$entryName}: " . $e->getMessage(), ['exception' => $e]);
                }
            }

            if ($zip) {
                $zip->close();
            }
            if ($uploadedFile && file_exists($uploadedFile)) {
                @unlink($uploadedFile);
            }

            return [
                'success' => true,
                'theme' => $themeSlug,
            ];
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

            logger()->logError('Theme upload error: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * AJAX завантаження теми з ZIP архіву (старый метод для обратной совместимости)
     */
    private function ajaxUploadTheme(): void
    {
        if (! $this->verifyCsrf()) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Помилка безпеки'], 403);
        }

        $request = Request::getInstance();
        $files = $request->files();

        if (! isset($files['theme_file'])) {
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

            // Перевіряємо існування директорії storage/temp/ (повинна бути створена установщиком)
            $projectRoot = dirname(__DIR__, 4);
            $storageParent = $projectRoot . '/storage';
            $storageDir = $storageParent . '/temp/';

            if (! is_dir($storageDir)) {
                throw new Exception('Директорія storage/temp/ не існує. Вона повинна бути створена під час установки системи. Будь ласка, запустіть установщик або створіть директорію вручну.');
            }

            // Перевіряємо права на запис
            if (! is_writable($storageDir)) {
                throw new Exception('Немає прав на запис у директорію: ' . $storageDir);
            }

            $upload->setUploadDir($storageDir);
            $uploadResult = $upload->upload($files['theme_file']);

            if (! $uploadResult['success']) {
                $this->sendJsonResponse(['success' => false, 'error' => $uploadResult['error']], 400);
            }

            $uploadedFile = $uploadResult['file'];

            // Відкриваємо ZIP архів через клас Zip
            $zip = new Zip();
            $zip->open($uploadedFile, ZipArchive::RDONLY);

            // Перевіряємо наявність theme.json
            $entries = $zip->getEntries();
            $hasThemeJson = false;
            $themeJsonPath = null;
            $themeSlug = null;

            foreach ($entries as $entryName) {
                // Нормалізуємо шлях (замінюємо зворотні слеші на прямі для Windows архівів)
                $normalizedPath = str_replace('\\', '/', $entryName);
                $normalizedPath = trim($normalizedPath, '/');

                // Пропускаємо директорії
                if (str_ends_with($normalizedPath, '/')) {
                    continue;
                }

                // Перевіряємо, чи є файл theme.json (в будь-якій папці)
                if (basename($normalizedPath) === 'theme.json') {
                    $hasThemeJson = true;
                    $themeJsonPath = $entryName; // Використовуємо оригінальне ім'я для витягування

                    // Визначаємо slug зі шляху
                    $pathParts = explode('/', $normalizedPath);
                    if (count($pathParts) >= 2) {
                        // Перша частина шляху - це зазвичай назва теми (slug)
                        // Очищаємо від GitHub-суфіксів
                        $themeSlug = $this->cleanGitHubSuffix($pathParts[0]);
                    }

                    break;
                }
            }

            if (! $hasThemeJson) {
                if ($zip) {
                    $zip->close();
                }
                if ($uploadedFile && file_exists($uploadedFile)) {
                    @unlink($uploadedFile);
                }
                $this->sendJsonResponse(['success' => false, 'error' => 'Архів не містить theme.json'], 400);
            }

            // Якщо slug не визначено, спробуємо прочитати theme.json
            if (! $themeSlug) {
                $themeJsonContent = $zip->getEntryContents($themeJsonPath);
                if ($themeJsonContent) {
                    $config = Json::decode($themeJsonContent, true);
                    if ($config && isset($config['slug'])) {
                        $themeSlug = $config['slug'];
                    }
                }
            }

            // Якщо все ще немає slug, використовуємо ім'я файлу без розширення
            if (! $themeSlug) {
                $request = Request::getInstance();
                $files = $request->files();
                $themeSlug = pathinfo($files['theme_file']['name'], PATHINFO_FILENAME);
                // Очищаємо від GitHub-суфіксів
                $themeSlug = $this->cleanGitHubSuffix($themeSlug);
            }

            // Очищаємо slug від небезпечних символів
            $themeSlug = preg_replace('/[^a-z0-9\-_]/i', '', $themeSlug);
            if (empty($themeSlug)) {
                if ($zip) {
                    $zip->close();
                }
                if ($uploadedFile && file_exists($uploadedFile)) {
                    @unlink($uploadedFile);
                }
                $this->sendJsonResponse(['success' => false, 'error' => 'Неможливо визначити slug теми'], 400);
            }

            // Шлях до папки тем
            $themesDir = dirname(__DIR__, 4) . '/themes/';
            $themePath = $themesDir . $themeSlug . '/';

            // Перевіряємо, чи не існує вже тема з таким slug
            if (is_dir($themePath)) {
                if ($zip) {
                    $zip->close();
                }
                if ($uploadedFile && file_exists($uploadedFile)) {
                    @unlink($uploadedFile);
                }
                $this->sendJsonResponse(['success' => false, 'error' => 'Тема з таким slug вже існує: ' . $themeSlug], 400);
            }

            // Створюємо папку для теми
            if (! @mkdir($themePath, 0755, true)) {
                if ($zip) {
                    $zip->close();
                }
                if ($uploadedFile && file_exists($uploadedFile)) {
                    @unlink($uploadedFile);
                }
                $this->sendJsonResponse(['success' => false, 'error' => 'Помилка створення папки теми'], 500);
            }

            // Визначаємо кореневу папку в архіві
            $rootPath = null;
            if ($themeJsonPath) {
                $rootPath = dirname($themeJsonPath);
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

                $targetPath = $themePath . $relativePath;
                $targetDirPath = dirname($targetPath);

                // Створюємо папки якщо потрібно
                if (! is_dir($targetDirPath)) {
                    if (! @mkdir($targetDirPath, 0755, true)) {
                        logger()->logError("Failed to create directory: {$targetDirPath}", ['path' => $targetDirPath]);

                        continue;
                    }
                }

                // Витягуємо файл
                try {
                    $zip->extractFile($entryName, $targetPath);
                    $extracted++;
                } catch (Exception $e) {
                    logger()->logError("Failed to extract file {$entryName}: " . $e->getMessage(), ['exception' => $e, 'entry' => $entryName]);
                    // Продовжуємо з наступним файлом
                }
            }

            if ($zip) {
                $zip->close();
            }
            if ($uploadedFile && file_exists($uploadedFile)) {
                @unlink($uploadedFile);
            }

            // Очищаємо кеш тем
            themeManager()->clearThemeCache();

            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Тему успішно завантажено',
                'theme_slug' => $themeSlug,
                'extracted_files' => $extracted,
            ], 200);
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

            logger()->logError('Theme upload error: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString()]);
            $this->sendJsonResponse(['success' => false, 'error' => 'Помилка: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Перевірка наявності налаштувань у теми
     */
    private function themeHasSettings(string $themeSlug, string $themePath): bool
    {
        // Перевіряємо наявність файлу сторінки налаштувань
        $settingsFiles = [
            $themePath . 'admin/SettingsPage.php',
            $themePath . 'admin/' . ucfirst($themeSlug) . 'SettingsPage.php',
            $themePath . 'SettingsPage.php',
        ];

        foreach ($settingsFiles as $file) {
            if (file_exists($file)) {
                return true;
            }
        }

        // Перевіряємо наявність theme.json з налаштуваннями (використовуємо File клас)
        $themeJsonFile = new File($themePath . 'theme.json');
        if ($themeJsonFile->exists()) {
            try {
                $content = $themeJsonFile->read();
                if ($content) {
                    $config = Json::decode($content, true);
                    if ($config && (isset($config['has_settings']) || isset($config['settings_page']))) {
                        return true;
                    }
                }
            } catch (Exception $e) {
                // Якщо не вдалося прочитати файл, ігноруємо
            }
        }

        return false;
    }

    /**
     * Отримання підтримуваних функцій теми
     *
     * @param array<string, mixed> $theme
     * @param string $themePath
     * @return array<string, mixed>
     */
    private function getThemeFeatures(array $theme, string $themePath): array
    {
        $features = [
            'header' => false,
            'parameters' => false,
            'customization' => false,
            'logo' => false,
            'favicon' => false,
        ];

        // Перевіряємо theme.json
        $themeJsonFile = $themePath . 'theme.json';
        if (file_exists($themeJsonFile)) {
            $content = @file_get_contents($themeJsonFile);
            if ($content) {
                $config = Json::decode($content, true);
                if ($config) {
                    $features['header'] = isset($config['supports_header']) ? (bool)$config['supports_header'] : false;
                    $features['parameters'] = isset($config['supports_parameters']) ? (bool)$config['supports_parameters'] : false;
                    $features['customization'] = isset($config['supports_customization']) ? (bool)$config['supports_customization'] : false;
                    $features['logo'] = isset($config['supports_logo']) ? (bool)$config['supports_logo'] : false;
                    $features['favicon'] = isset($config['supports_favicon']) ? (bool)$config['supports_favicon'] : false;
                }
            }
        }

        // Fallback: перевіряємо наявність файлів
        if (! $features['customization']) {
            $features['customization'] = file_exists($themePath . 'customizer.php');
        }

        if (! $features['parameters']) {
            $features['parameters'] = file_exists($themePath . 'admin/SettingsPage.php') ||
                                      file_exists($themePath . 'SettingsPage.php');
        }

        // Перевіряємо наявність header в index.php
        if (! $features['header']) {
            $indexFile = $themePath . 'index.php';
            if (file_exists($indexFile)) {
                $content = @file_get_contents($indexFile);
                if ($content && (stripos($content, '<header') !== false || stripos($content, 'header') !== false)) {
                    $features['header'] = true;
                }
            }
        }

        // Перевіряємо наявність логотипу та фавікона (зазвичай в assets або корені)
        if (! $features['logo']) {
            $logoFiles = [
                $themePath . 'assets/images/logo.png',
                $themePath . 'assets/images/logo.jpg',
                $themePath . 'assets/images/logo.svg',
                $themePath . 'logo.png',
                $themePath . 'logo.jpg',
                $themePath . 'logo.svg',
            ];
            foreach ($logoFiles as $logoFile) {
                if (file_exists($logoFile)) {
                    $features['logo'] = true;

                    break;
                }
            }
        }

        if (! $features['favicon']) {
            $faviconFiles = [
                $themePath . 'assets/images/favicon.ico',
                $themePath . 'favicon.ico',
                $themePath . 'assets/favicon.ico',
            ];
            foreach ($faviconFiles as $faviconFile) {
                if (file_exists($faviconFile)) {
                    $features['favicon'] = true;

                    break;
                }
            }
        }

        return $features;
    }

    /**
     * Деактивація теми
     */
    private function deactivateTheme()
    {
        if (! $this->verifyCsrf()) {
            $this->setMessage('Помилка безпеки', 'danger');

            return;
        }

        // Перевірка прав доступу
        if (! function_exists('current_user_can') || ! current_user_can('admin.access')) {
            $this->setMessage('У вас немає прав на деактивацію тем', 'danger');

            return;
        }

        $themeSlug = SecurityHelper::sanitizeInput($_POST['theme_slug'] ?? '');

        if (empty($themeSlug)) {
            $this->setMessage('Тему не вибрано', 'danger');

            return;
        }

        // Перевіряємо, чи тема активна
        $activeTheme = themeManager()->getActiveTheme();
        if (! $activeTheme || $activeTheme['slug'] !== $themeSlug) {
            $this->setMessage('Тема не активна', 'danger');

            return;
        }

        // Перевіряємо, чи це стандартна тема
        $session = sessionManager();
        $userId = (int)$session->get('admin_user_id');
        $theme = themeManager()->getTheme($themeSlug);
        $isDefault = $theme && ($theme['is_default'] ?? false);

        if ($isDefault) {
            // Перевіряємо, чи це єдина тема
            $allThemes = themeManager()->getAllThemes();
            $themesCount = count($allThemes);

            // Якщо тема одна і стандартна - тільки розробник може її деактивувати
            if ($themesCount === 1) {
                if ($userId !== 1) {
                    $this->setMessage('Неможливо деактивувати стандартну встановлену тему. Це єдина доступна тема.', 'danger');

                    return;
                }
            } else {
                // Якщо тем більше однієї, стандартну тему не можна деактивувати
                $this->setMessage('Неможливо деактивувати стандартну встановлену тему.', 'danger');

                return;
            }
        }

        // Деактивуємо тему
        $result = themeManager()->deactivateTheme($themeSlug);

        if ($result) {
            // Очищаємо кеш
            themeManager()->clearThemeCache($themeSlug);

            $this->setMessage('Тему успішно деактивовано', 'success');
            $this->redirect('themes');
            exit;
        } else {
            $this->setMessage('Помилка при деактивації теми. Перевірте логи системи.', 'danger');
        }
    }

    /**
     * Видалення теми
     */
    private function deleteTheme()
    {
        if (! $this->verifyCsrf()) {
            $this->setMessage('Помилка безпеки', 'danger');

            return;
        }

        // Перевірка прав доступу
        if (! function_exists('current_user_can') || ! current_user_can('admin.access')) {
            $this->setMessage('У вас немає прав на видалення тем', 'danger');

            return;
        }

        $themeSlug = SecurityHelper::sanitizeInput($_POST['theme_slug'] ?? '');

        if (empty($themeSlug)) {
            $this->setMessage('Тему не вибрано', 'danger');

            return;
        }

        // Перевіряємо, чи це стандартна тема
        $session = sessionManager();
        $userId = (int)$session->get('admin_user_id');
        $theme = themeManager()->getTheme($themeSlug);
        $isDefault = $theme && ($theme['is_default'] ?? false);

        if ($isDefault) {
            // Перевіряємо, чи це єдина тема
            $allThemes = themeManager()->getAllThemes();
            $themesCount = count($allThemes);

            // Якщо тема одна і стандартна - тільки розробник може її видалити (навіть якщо активна)
            if ($themesCount === 1) {
                if ($userId !== 1) {
                    $this->setMessage('Неможливо видалити стандартну встановлену тему. Це єдина доступна тема.', 'danger');

                    return;
                }
                // Розробник може видалити стандартну тему навіть якщо вона активна
            } else {
                // Якщо тем більше однієї, стандартну тему не можна видаляти
                $this->setMessage('Неможливо видалити стандартну встановлену тему.', 'danger');

                return;
            }
        } else {
            // Для нестандартних тем перевіряємо, чи тема активна
            $activeTheme = themeManager()->getActiveTheme();
            if ($activeTheme && $activeTheme['slug'] === $themeSlug) {
                $this->setMessage('Неможливо видалити активну тему. Спочатку активуйте іншу тему.', 'danger');

                return;
            }
        }

        // Отримуємо шлях до теми
        $themePath = themeManager()->getThemePath($themeSlug);

        if (! is_dir($themePath)) {
            $this->setMessage('Тему не знайдено', 'danger');

            return;
        }

        // Видаляємо всі налаштування теми з theme_settings
        try {
            $db = DatabaseHelper::getConnection();
            if ($db) {
                $stmt = $db->prepare('DELETE FROM theme_settings WHERE theme_slug = ?');
                $stmt->execute([$themeSlug]);
            }
        } catch (Exception $e) {
            logger()->logError('Error deleting theme settings: ' . $e->getMessage(), ['exception' => $e]);
        }

        // Видаляємо папку теми
        try {
            $this->deleteDirectory($themePath);

            // Очищаємо кеш тем
            themeManager()->clearThemeCache();

            logger()->logInfo('Тему видалено', ['theme' => $themeSlug]);
            $this->setMessage('Тему успішно видалено', 'success');
            $this->redirect('themes');
            exit;
        } catch (Exception $e) {
            logger()->logError('Theme delete error: ' . $e->getMessage(), ['exception' => $e]);
            $this->setMessage('Помилка при видаленні теми: ' . $e->getMessage(), 'danger');
        }
    }

    /**
     * Рекурсивне видалення директорії
     */
    private function deleteDirectory(string $dir): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }
}
