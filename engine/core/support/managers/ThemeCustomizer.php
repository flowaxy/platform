<?php

/**
 * Модуль кастомізації тем
 * Управління налаштуваннями кастомізації для тем
 *
 * @package Engine\Modules
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class ThemeCustomizer extends BaseModule
{
    /**
     * Ініціалізація модуля
     */
    protected function init(): void
    {
        // Модуль ThemeCustomizer не потребує додаткової ініціалізації
    }

    /**
     * Реєстрація хуків модуля
     */
    public function registerHooks(): void
    {
        // Модуль ThemeCustomizer не реєструє хуки
    }

    /**
     * Отримання інформації про модуль
     *
     * @return array<string, string>
     */
    public function getInfo(): array
    {
        return [
            'name' => 'ThemeCustomizer',
            'title' => 'Кастомізатор тем',
            'description' => 'Управління налаштуваннями кастомізації для тем',
            'version' => '1.0.0 Alpha prerelease',
            'author' => 'Flowaxy CMS',
        ];
    }

    /**
     * Отримання API методів модуля
     *
     * @return array<string, string>
     */
    public function getApiMethods(): array
    {
        return [
            'getCustomizerConfig' => 'Отримання конфігурації кастомізатора теми',
            'validateAndSaveSettings' => 'Валідація та збереження налаштувань теми',
            'getCategories' => 'Отримання категорій налаштувань',
            'getDefaultCategories' => 'Отримання категорій за замовчуванням',
        ];
    }

    /**
     * Завантаження конфігурації кастомізатора з теми
     *
     * @param string $themePath Шлях до теми
     * @return array<string, mixed>
     */
    public function getCustomizerConfig(string $themePath): array
    {
        if (empty($themePath) || ! is_dir($themePath)) {
            return [];
        }

        $customizerFile = $themePath . 'customizer.php';

        if (file_exists($customizerFile) && is_readable($customizerFile)) {
            try {
                $config = require $customizerFile;

                return is_array($config) ? $config : [];
            } catch (Exception $e) {
                logger()->logError('ThemeCustomizer: Error loading customizer config: ' . $e->getMessage(), ['exception' => $e]);

                return [];
            }
        }

        return [];
    }

    /**
     * Валідація та збереження налаштувань теми
     *
     * @param array<string, mixed> $settings Масив налаштувань
     * @return array<string, mixed> Результат операції
     */
    public function validateAndSaveSettings(array $settings): array
    {
        if (empty($settings)) {
            return [
                'success' => false,
                'error' => 'Налаштування не передані',
            ];
        }

        try {
            $themeManager = ThemeManager::getInstance();
            $activeTheme = $themeManager->getActiveTheme();

            if (! $activeTheme) {
                return [
                    'success' => false,
                    'error' => 'Тему не активовано',
                ];
            }

            $themeConfig = $themeManager->getThemeConfig($activeTheme['slug']);
            if (! $themeConfig) {
                return [
                    'success' => false,
                    'error' => 'Конфігурацію теми не знайдено',
                ];
            }

            $availableSettings = $themeConfig['available_settings'] ?? [];

            // Збираємо всі доступні ключі налаштувань з конфігурації
            $allowedKeys = [];
            foreach ($availableSettings as $category => $categorySettings) {
                foreach ($categorySettings as $key => $config) {
                    $allowedKeys[] = $key;
                }
            }

            $validatedSettings = [];

            // Валідація та фільтрація налаштувань
            foreach ($settings as $key => $value) {
                // Перевіряємо, що налаштування дозволене в конфігурації теми
                if (! in_array($key, $allowedKeys)) {
                    continue;
                }

                // Отримуємо конфігурацію налаштування
                $settingConfig = null;
                foreach ($availableSettings as $category => $categorySettings) {
                    if (isset($categorySettings[$key])) {
                        $settingConfig = $categorySettings[$key];

                        break;
                    }
                }

                if (! $settingConfig) {
                    continue;
                }

                $validatedValue = $this->validateSetting($key, $value, $settingConfig);
                // Зберігаємо значення, навіть якщо воно порожнє (для текстових полів це нормально)
                $validatedSettings[$key] = $validatedValue;
            }

            // Обробка чекбоксів з усіх категорій (не тільки 'other')
            foreach ($availableSettings as $category => $categorySettings) {
                foreach ($categorySettings as $key => $config) {
                    if ($config['type'] === 'checkbox') {
                        // Якщо налаштування не було передано, встановлюємо '0'
                        if (! isset($settings[$key])) {
                            $validatedSettings[$key] = '0';
                        }
                    }
                }
            }

            // Зберігаємо налаштування
            if (empty($validatedSettings)) {
                return [
                    'success' => false,
                    'error' => 'Немає валідних налаштувань для збереження',
                ];
            }

            $result = $themeManager->setSettings($validatedSettings);

            if ($result) {
                // Очищаємо кеш теми
                $themeManager->clearThemeCache($activeTheme['slug']);

                return [
                    'success' => true,
                    'message' => 'Налаштування успішно збережено',
                    'settings' => $validatedSettings,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Помилка при збереженні налаштувань в базу даних',
                ];
            }
        } catch (Exception $e) {
            logger()->logError('ThemeCustomizer: Error saving settings: ' . $e->getMessage(), ['exception' => $e]);
            return [
                'success' => false,
                'error' => 'Помилка: ' . $e->getMessage(),
            ];
        } catch (Error $e) {
            logger()->logCritical('ThemeCustomizer: Fatal error saving settings: ' . $e->getMessage(), ['exception' => $e]);

            return [
                'success' => false,
                'error' => 'Критична помилка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Валидация одной настройки
     *
     * @param string $key Ключ настройки
     * @param mixed $value Значение настройки
     * @param array $settingConfig Конфигурация настройки
     * @return mixed Валидированное значение
     */
    public function validateSetting(string $key, $value, array $settingConfig)
    {
        $type = $settingConfig['type'] ?? 'text';

        // Нормалізуємо значення
        if ($value === null) {
            $value = '';
        }

        switch ($type) {
            case 'color':
                $value = SecurityHelper::sanitizeInput($value ?? '');

                // Если значение пустое, пытаемся получить значение по умолчанию
                if (empty($value)) {
                    $activeTheme = ThemeManager::getInstance()->getActiveTheme();
                    if ($activeTheme) {
                        $themeConfig = ThemeManager::getInstance()->getThemeConfig($activeTheme['slug']);
                        $defaultSettings = $themeConfig['default_settings'] ?? [];
                        $value = $defaultSettings[$key] ?? '#000000';
                    } else {
                        $value = '#000000';
                    }
                }

                // Валидация формата цвета
                if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value)) {
                    return $value;
                }

                // Якщо значення не валідне, але не порожнє, намагаємося виправити (додаємо # якщо відсутнє)
                if (! empty($value) && $value[0] !== '#') {
                    $value = '#' . $value;
                    if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value)) {
                        return $value;
                    }
                }

                return '#000000'; // Дефолтное значение

            case 'text':
            case 'textarea':
                $value = SecurityHelper::sanitizeInput($value ?? '');
                $maxLength = $settingConfig['max_length'] ?? null;

                if ($maxLength && mb_strlen($value) > $maxLength) {
                    $value = mb_substr($value, 0, $maxLength);
                }

                return $value;

            case 'number':
                $value = filter_var($value, FILTER_VALIDATE_FLOAT);

                if ($value === false) {
                    $value = $settingConfig['default'] ?? 0;
                }

                $min = $settingConfig['min'] ?? null;
                $max = $settingConfig['max'] ?? null;

                if ($min !== null && $value < $min) {
                    $value = $min;
                }

                if ($max !== null && $value > $max) {
                    $value = $max;
                }

                return $value;

            case 'checkbox':
                return ($value === '1' || $value === true || $value === 'on') ? '1' : '0';

            case 'select':
            case 'radio':
                $options = $settingConfig['options'] ?? [];

                if (array_key_exists($value, $options)) {
                    return $value;
                }

                return $settingConfig['default'] ?? '';

            case 'image':
            case 'media':
                $value = SecurityHelper::sanitizeInput($value ?? '');

                return $value;

            case 'css':
                return $value ?? ''; // CSS код не нужно санитизировать полностью

            default:
                return SecurityHelper::sanitizeInput($value ?? '');
        }
    }

    /**
     * Отримання категорій налаштувань
     *
     * @param array $customizerConfig Конфигурация кастомизатора
     * @param array $availableSettings Доступные настройки темы
     * @return array
     */
    /**
     * @param array<string, mixed> $customizerConfig
     * @param array<string, mixed> $availableSettings
     * @return array<string, mixed>
     */
    public function getCategories(array $customizerConfig, array $availableSettings): array
    {
        $defaultCategories = $this->getDefaultCategories();
        $categories = [];

        if (isset($customizerConfig['categories']) && is_array($customizerConfig['categories'])) {
            // Якщо в customizer.php визначені категорії, використовуємо їх
            foreach ($customizerConfig['categories'] as $category => $categoryInfo) {
                if (isset($availableSettings[$category]) && ! empty($availableSettings[$category])) {
                    $categories[$category] = $categoryInfo;
                }
            }
        } else {
            // Інакше використовуємо дефолтні категорії, але тільки ті, що є в available_settings
            foreach ($defaultCategories as $category => $categoryInfo) {
                if (isset($availableSettings[$category]) && ! empty($availableSettings[$category])) {
                    $categories[$category] = $categoryInfo;
                }
            }
        }

        return $categories;
    }

    /**
     * Отримання категорій за замовчуванням
     *
     * @return array
     */
    /**
     * @return array<string, array<string, string>>
     */
    public function getDefaultCategories(): array
    {
        return [
            'identity' => ['icon' => 'fa-id-card', 'label' => 'Ідентичність сайту'],
            'colors' => ['icon' => 'fa-palette', 'label' => 'Кольори'],
            'fonts' => ['icon' => 'fa-font', 'label' => 'Typography'],
            'menu' => ['icon' => 'fa-bars', 'label' => 'Меню'],
            'header' => ['icon' => 'fa-window-maximize', 'label' => 'Header Settings'],
            'footer' => ['icon' => 'fa-window-minimize', 'label' => 'Footer Settings'],
            'css' => ['icon' => 'fa-code', 'label' => 'Додатковий код CSS'],
        ];
    }
}

/**
 * Глобальная функция для получения экземпляра ThemeCustomizer
 *
 * @return ThemeCustomizer
 */
function themeCustomizer(): ThemeCustomizer
{
    return ThemeCustomizer::getInstance();
}
