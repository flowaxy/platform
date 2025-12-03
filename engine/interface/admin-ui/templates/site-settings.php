<?php
/**
 * Шаблон страницы настроек сайта
 */

// Перевірка прав доступу на редагування
$session = sessionManager();
$userId = (int)$session->get('admin_user_id');
$hasEditAccess = ($userId === 1) ||
                 (function_exists('user_has_role') && user_has_role($userId, 'developer')) ||
                 (function_exists('current_user_can') && current_user_can('admin.access'));
$readonlyAttr = $hasEditAccess ? '' : 'readonly disabled';
$readonlyClass = $hasEditAccess ? '' : 'bg-light';
?>

<!-- Кастомні уведомлення -->
<?php
// Показуємо кастомне уведомлення замість стандартного alert
if (!empty($message)) {
    $type = $messageType ?? 'info';
    $messageJson = json_encode($message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    $typeJson = json_encode($type, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<script>';
    echo '(function() {';
    echo '    function showCustomNotification() {';
    echo '        if (typeof window.showNotification !== "undefined") {';
    echo '            window.showNotification(' . $messageJson . ', ' . $typeJson . ');';
    echo '        } else if (typeof window.Notifications !== "undefined" && typeof window.Notifications.show === "function") {';
    echo '            window.Notifications.show(' . $messageJson . ', ' . $typeJson . ');';
    echo '        } else {';
    echo '            setTimeout(showCustomNotification, 100);';
    echo '        }';
    echo '    }';
    echo '    if (document.readyState === "loading") {';
    echo '        document.addEventListener("DOMContentLoaded", showCustomNotification);';
    echo '    } else {';
    echo '        setTimeout(showCustomNotification, 100);';
    echo '    }';
    echo '})();';
    echo '</script>';
}
?>

<?php if (! $hasEditAccess): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    У вас є право тільки на перегляд налаштувань. Для зміни налаштувань потрібне право "Зміна налаштувань".
</div>
<?php endif; ?>

<div class="site-settings-page">
<form id="site-settings-form" method="POST" class="settings-form" <?= ! $hasEditAccess ? 'onsubmit="return false;"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= SecurityHelper::csrfToken() ?>">
    <input type="hidden" name="save_settings" value="1">
    
    <div class="row g-3">
        <!-- Загальні налаштування -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6>
                        <i class="fas fa-cog me-2 text-primary"></i>Загальні налаштування
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="siteName" class="form-label fw-medium small">
                                Назва сайту
                                <span class="badge bg-warning text-dark ms-2">В розробці</span>
                            </label>
                            <input type="text" 
                                   class="form-control <?= $readonlyClass ?>" 
                                   id="siteName" 
                                   name="settings[site_name]" 
                                   value="<?= htmlspecialchars($settings['site_name'] ?? 'Flowaxy CMS') ?>"
                                   placeholder="Flowaxy CMS"
                                   <?= $readonlyAttr ?>>
                            <div class="form-text small"><i class="fas fa-heading me-1"></i>Назва вашого сайту</div>
                        </div>
                        <div class="col-md-6">
                            <label for="siteTagline" class="form-label fw-medium small">
                                Ключова фраза
                                <span class="badge bg-warning text-dark ms-2">В розробці</span>
                            </label>
                            <input type="text" 
                                   class="form-control <?= $readonlyClass ?>" 
                                   id="siteTagline" 
                                   name="settings[site_tagline]" 
                                   value="<?= htmlspecialchars($settings['site_tagline'] ?? '') ?>"
                                   placeholder="Короткий опис сайту"
                                   <?= $readonlyAttr ?>>
                            <div class="form-text small"><i class="fas fa-quote-left me-1"></i>Коротка фраза, яка описує ваш сайт</div>
                        </div>
                        <div class="col-md-6">
                            <label for="siteUrl" class="form-label fw-medium small">
                                Адреса сайту (URL)
                                <span class="badge bg-warning text-dark ms-2">В розробці</span>
                            </label>
                            <input type="url" 
                                   class="form-control <?= $readonlyClass ?>" 
                                   id="siteUrl" 
                                   name="settings[site_url]" 
                                   value="<?= htmlspecialchars($settings['site_url'] ?? '') ?>"
                                   placeholder="https://example.com"
                                   <?= $readonlyAttr ?>>
                            <div class="form-text small"><i class="fas fa-link me-1"></i>Повна адреса сайту</div>
                        </div>
                        <div class="col-md-6">
                            <label for="adminEmail" class="form-label fw-medium small">Email адміністратора</label>
                            <input type="email" 
                                   class="form-control <?= $readonlyClass ?>" 
                                   id="adminEmail" 
                                   name="settings[admin_email]" 
                                   value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>"
                                   placeholder="admin@example.com"
                                   <?= $readonlyAttr ?>>
                            <div class="form-text small"><i class="fas fa-envelope me-1"></i>Email адреса для системних повідомлень</div>
                        </div>
                        <div class="col-md-6">
                            <label for="siteProtocol" class="form-label fw-medium small">Протокол сайту</label>
                            <select class="form-select <?= $readonlyClass ?>" id="siteProtocol" name="settings[site_protocol]" <?= $readonlyAttr ?>>
                                <option value="auto" <?= ($settings['site_protocol'] ?? 'auto') === 'auto' ? 'selected' : '' ?>>Автоматично (визначається автоматично)</option>
                                <option value="https" <?= ($settings['site_protocol'] ?? '') === 'https' ? 'selected' : '' ?>>HTTPS (захищений)</option>
                                <option value="http" <?= ($settings['site_protocol'] ?? '') === 'http' ? 'selected' : '' ?>>HTTP (незахищений)</option>
                            </select>
                            <div class="form-text small"><i class="fas fa-shield-alt me-1"></i>Протокол для URL сайту. "Автоматично" визначає протокол на основі запиту</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Локалізація та форматування -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6>
                        <i class="fas fa-language me-2 text-primary"></i>Локалізація та форматування
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="timezone" class="form-label fw-medium small">Часовий пояс</label>
                            <div class="timezone-select-wrapper">
                                <input type="text" 
                                       class="form-control timezone-search-input <?= $readonlyClass ?>" 
                                       id="timezoneSearch" 
                                       placeholder="Пошук часового поясу..." 
                                       autocomplete="off"
                                       style="display: none; position: absolute; top: 0; left: 0; right: 0; z-index: 10;"
                                       <?= $readonlyAttr ?>>
                                <select class="form-select timezone-select <?= $readonlyClass ?>" id="timezone" name="settings[timezone]" size="10" style="display: none;" <?= $readonlyAttr ?>>
                                    <?php
                                    // Завантажуємо значення timezone з БД (без дефолтних значень)
                                    // SiteSettingsPage вже обробило значення з БД
                                    // Використовуємо те, що передано з контролера (може бути порожнім)
                                    // ВАЖЛИВО: Використовуємо array_key_exists замість isset, бо isset поверне false для порожніх рядків
                                    $currentTimezone = '';
                                    if (isset($settings) && is_array($settings) && array_key_exists('timezone', $settings)) {
                                        $currentTimezone = is_string($settings['timezone']) ? trim($settings['timezone']) : '';
                                    }
                                    
                                    // Автоматичне оновлення старого часового поясу на новий
                                    if ($currentTimezone === 'Europe/Kiev') {
                                        $currentTimezone = 'Europe/Kyiv';
                                    }
                                    
                                    // Додаємо опцію "Не вибрано" для порожнього значення
                                    if (empty($currentTimezone)) {
                                        echo '<option value="" selected data-text="Не вибрано">Не вибрано</option>';
                                    } else {
                                        echo '<option value="" data-text="Не вибрано">Не вибрано</option>';
                                    }
                                    
                                    // Завантажуємо всі часові пояси через TimezoneManager
                                    try {
                                        if (function_exists('getTimezoneOptions')) {
                                            $timezoneOptions = getTimezoneOptions($currentTimezone);
                                            
                                            // Додаємо всі часові пояси
                                            foreach ($timezoneOptions as $value => $text) {
                                                $selected = (!empty($currentTimezone) && $currentTimezone === $value) ? 'selected' : '';
                                                echo '<option value="' . htmlspecialchars($value) . '" ' . $selected . ' data-text="' . htmlspecialchars($text) . '">' . htmlspecialchars($text) . '</option>';
                                            }
                                        } else {
                                            // Fallback - додаємо популярні часові пояси
                                            $popularTimezones = [
                                                'UTC' => '(UTC+00:00) UTC',
                                                'Europe/London' => '(UTC+00:00) Лондон',
                                                'Europe/Berlin' => '(UTC+01:00) Берлін',
                                                'Europe/Paris' => '(UTC+01:00) Париж',
                                                'Europe/Kyiv' => '(UTC+02:00) Київ',
                                                'Europe/Moscow' => '(UTC+03:00) Москва',
                                                'Europe/Minsk' => '(UTC+03:00) Мінськ',
                                                'America/New_York' => '(UTC-05:00) Нью-Йорк',
                                                'America/Los_Angeles' => '(UTC-08:00) Лос-Анджелес',
                                                'Asia/Tokyo' => '(UTC+09:00) Токіо',
                                                'Asia/Shanghai' => '(UTC+08:00) Шанхай',
                                            ];
                                            
                                            foreach ($popularTimezones as $value => $text) {
                                                $selected = ($currentTimezone === $value) ? 'selected' : '';
                                                echo '<option value="' . htmlspecialchars($value) . '" ' . $selected . ' data-text="' . htmlspecialchars($text) . '">' . htmlspecialchars($text) . '</option>';
                                            }
                                        }
                                    } catch (Exception $e) {
                                        // При помилці використовуємо мінімальний набір
                                        $fallbackTimezones = [
                                            'UTC' => '(UTC+00:00) UTC',
                                            'Europe/Kyiv' => '(UTC+02:00) Київ',
                                        ];
                                        foreach ($fallbackTimezones as $value => $text) {
                                            $selected = ($currentTimezone === $value) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($value) . '" ' . $selected . ' data-text="' . htmlspecialchars($text) . '">' . htmlspecialchars($text) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <div class="timezone-display-input">
                                    <?php
                                    // Встановлюємо початкове значення для відображення
                                    // ВАЖЛИВО: Використовуємо те ж саме значення, що вже обраховано вище ($currentTimezone)
                                    // Якщо в БД є значення (навіть порожнє), воно має пріоритет
                                    $displayText = '';
                                    
                                    // Використовуємо $currentTimezone, який вже правильно встановлений вище
                                    $timezoneToDisplay = $currentTimezone;
                                    $displayText = '';
                                    
                                    // Отримуємо текст для відображення через TimezoneManager
                                    // ВАЖЛИВО: Використовуємо значення з БД ($timezoneToDisplay = $currentTimezone)
                                    if (!empty($timezoneToDisplay) && $timezoneToDisplay !== '') {
                                        try {
                                            // Спочатку пробуємо отримати через getTimezone (більш точно)
                                            if (function_exists('getTimezone')) {
                                                $tz = getTimezone($timezoneToDisplay);
                                                if ($tz) {
                                                    $displayText = $tz->getText();
                                                }
                                            }
                                            
                                            // Якщо не знайшли через getTimezone, пробуємо через getTimezoneOptions
                                            if (empty($displayText) && function_exists('getTimezoneOptions')) {
                                                $allOptions = getTimezoneOptions();
                                                if (isset($allOptions[$timezoneToDisplay])) {
                                                    $displayText = $allOptions[$timezoneToDisplay];
                                                }
                                            }
                                        } catch (Exception $e) {
                                            // Якщо помилка, використовуємо fallback
                                            logger()->logError('Error getting timezone display: ' . $e->getMessage(), [
                                                'timezone' => $timezoneToDisplay,
                                                'exception' => $e->getMessage(),
                                            ]);
                                        }
                                    }
                                    
                                    // Якщо все ще не знайдено, показуємо порожнє значення або сам ідентифікатор
                                    if (empty($displayText)) {
                                        if (!empty($timezoneToDisplay) && $timezoneToDisplay !== '') {
                                            // Якщо є значення, але не знайдено відображення, показуємо сам ідентифікатор
                                            $displayText = $timezoneToDisplay;
                                        } else {
                                            // Якщо значення порожнє, показуємо "Не вибрано"
                                            $displayText = 'Не вибрано';
                                        }
                                    }
                                    ?>
                                    <input type="text" 
                                           class="form-control timezone-display <?= $readonlyClass ?>" 
                                           id="timezoneDisplay" 
                                           readonly 
                                           value="<?= htmlspecialchars($displayText) ?>"
                                           placeholder="Не вибрано"
                                           <?= $readonlyAttr ?>>
                                    <i class="fas fa-chevron-down timezone-arrow"></i>
                                </div>
                                <div class="timezone-dropdown" id="timezoneDropdown" style="display: none;"></div>
                            </div>
                            <div class="form-text small">
                                <span id="timezone-current-time" data-timezone="<?= htmlspecialchars($currentTimezone ?: (function_exists('getTimezoneFromDatabase') ? getTimezoneFromDatabase() : 'UTC')) ?>">
                                    <i class="fas fa-clock me-2"></i>Поточний час: <span id="current-time-display"><?php
                                    // Показуємо поточну дату та час з урахуванням вибраного часового поясу
                                    $displayTimezone = !empty($currentTimezone) ? $currentTimezone : (function_exists('getTimezoneFromDatabase') ? getTimezoneFromDatabase() : 'UTC');
                                    if (empty($displayTimezone)) {
                                        $displayTimezone = 'UTC';
                                    }
                                    
                                    try {
                                        $timezoneObj = new DateTimeZone($displayTimezone);
                                        $now = new DateTime('now', $timezoneObj);
                                        $currentDateTime = $now->format('d.m.Y H:i:s');
                                        echo htmlspecialchars($currentDateTime);
                                    } catch (Exception $e) {
                                        // Якщо не вдалося створити DateTimeZone, показуємо системний час
                                        $currentDateTime = date('d.m.Y H:i:s');
                                        echo htmlspecialchars($currentDateTime);
                                    }
                                    ?></span>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="siteLanguage" class="form-label fw-medium small">
                                Мова сайту
                                <span class="badge bg-warning text-dark ms-2">В розробці</span>
                            </label>
                            <select class="form-select <?= $readonlyClass ?>" id="siteLanguage" name="settings[site_language]" <?= $readonlyAttr ?>>
                                <option value="uk" <?= ($settings['site_language'] ?? 'uk') === 'uk' ? 'selected' : '' ?>>Українська</option>
                                <option value="en" <?= ($settings['site_language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="ru" <?= ($settings['site_language'] ?? '') === 'ru' ? 'selected' : '' ?>>Русский</option>
                            </select>
                            <div class="form-text small"><i class="fas fa-globe me-1"></i>Основна мова сайту</div>
                        </div>
                        <div class="col-md-6">
                            <label for="dateFormat" class="form-label fw-medium small">
                                Формат дати
                                <span class="badge bg-warning text-dark ms-2">В розробці</span>
                            </label>
                            <select class="form-select <?= $readonlyClass ?>" id="dateFormat" name="settings[date_format]" <?= $readonlyAttr ?>>
                                <option value="d.m.Y" <?= ($settings['date_format'] ?? 'd.m.Y') === 'd.m.Y' ? 'selected' : '' ?>>дд.мм.рррр (01.12.2025)</option>
                                <option value="Y-m-d" <?= ($settings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : '' ?>>рррр-мм-дд (2025-12-01)</option>
                                <option value="d/m/Y" <?= ($settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : '' ?>>дд/мм/рррр (01/12/2025)</option>
                                <option value="m/d/Y" <?= ($settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : '' ?>>мм/дд/рррр (12/01/2025)</option>
                            </select>
                            <div class="form-text small"><i class="fas fa-calendar me-1"></i>Формат відображення дати</div>
                        </div>
                        <div class="col-md-6">
                            <label for="timeFormat" class="form-label fw-medium small">
                                Формат часу
                                <span class="badge bg-warning text-dark ms-2">В розробці</span>
                            </label>
                            <select class="form-select <?= $readonlyClass ?>" id="timeFormat" name="settings[time_format]" <?= $readonlyAttr ?>>
                                <option value="H:i:s" <?= ($settings['time_format'] ?? 'H:i:s') === 'H:i:s' ? 'selected' : '' ?>>24 години (19:30:45)</option>
                                <option value="H:i" <?= ($settings['time_format'] ?? '') === 'H:i' ? 'selected' : '' ?>>24 години без секунд (19:30)</option>
                                <option value="h:i:s A" <?= ($settings['time_format'] ?? '') === 'h:i:s A' ? 'selected' : '' ?>>12 годин (07:30:45 PM)</option>
                                <option value="h:i A" <?= ($settings['time_format'] ?? '') === 'h:i A' ? 'selected' : '' ?>>12 годин без секунд (07:30 PM)</option>
                            </select>
                            <div class="form-text small"><i class="fas fa-clock me-1"></i>Формат відображення часу</div>
                        </div>
                        <div class="col-md-6">
                            <label for="weekStartsOn" class="form-label fw-medium small">
                                Тиждень починається з
                                <span class="badge bg-warning text-dark ms-2">В розробці</span>
                            </label>
                            <select class="form-select <?= $readonlyClass ?>" id="weekStartsOn" name="settings[week_starts_on]" <?= $readonlyAttr ?>>
                                <option value="1" <?= ($settings['week_starts_on'] ?? '1') === '1' ? 'selected' : '' ?>>Понеділок</option>
                                <option value="0" <?= ($settings['week_starts_on'] ?? '') === '0' ? 'selected' : '' ?>>Неділя</option>
                            </select>
                            <div class="form-text small"><i class="fas fa-calendar-week me-1"></i>Перший день тижня</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Користувачі та доступ -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6>
                        <i class="fas fa-users me-2 text-primary"></i>Користувачі та доступ
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium small d-block mb-2">
                                Членство
                                <span class="badge bg-warning text-dark ms-2">В розробці</span>
                            </label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="usersCanRegister" 
                                       name="settings[users_can_register]" 
                                       value="1"
                                       <?= ($settings['users_can_register'] ?? '1') === '1' ? 'checked' : '' ?>
                                       <?= $readonlyAttr ?>>
                                <label class="form-check-label fw-medium" for="usersCanRegister">
                                    Реєструватись може кожен
                                </label>
                            </div>
                            <div class="form-text small mt-1"><i class="fas fa-user-plus me-1"></i>Дозволити реєстрацію нових користувачів</div>
                        </div>
                        <div class="col-md-6">
                            <label for="defaultUserRole" class="form-label fw-medium small">
                                Роль нового користувача за замовчуванням
                                <span class="badge bg-warning text-dark ms-2">В розробці</span>
                            </label>
                            <select class="form-select <?= $readonlyClass ?>" id="defaultUserRole" name="settings[default_user_role]" <?= $readonlyAttr ?>>
                                <?php
                                $selectedRole = $settings['default_user_role'] ?? '2';
                                if (isset($roles) && is_array($roles)) {
                                    foreach ($roles as $role) {
                                        $roleId = (int)($role['id'] ?? 0);
                                        $roleName = htmlspecialchars($role['name'] ?? '');
                                        $selected = ($roleId == $selectedRole) ? 'selected' : '';
                                        echo '<option value="' . $roleId . '" ' . $selected . '>' . $roleName . '</option>';
                                    }
                                } else {
                                    echo '<option value="2" ' . ($selectedRole == '2' ? 'selected' : '') . '>Користувач</option>';
                                }
                                ?>
                            </select>
                            <div class="form-text small"><i class="fas fa-user-tag me-1"></i>Роль, яку отримує новий користувач під час реєстрації</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Безпека та сесії -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6>
                        <i class="fas fa-shield-alt me-2 text-primary"></i>Безпека та сесії
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="sessionLifetime" class="form-label fw-medium small">
                                Час життя сесії (секунди)
                                <span class="badge bg-warning text-dark ms-2">В розробці</span>
                            </label>
                            <input type="number" 
                                   class="form-control <?= $readonlyClass ?>" 
                                   id="sessionLifetime" 
                                   name="settings[session_lifetime]" 
                                   value="<?= htmlspecialchars($settings['session_lifetime'] ?? '7200') ?>"
                                   min="60"
                                   step="60"
                                   placeholder="7200"
                                   <?= $readonlyAttr ?>>
                            <div class="form-text small"><i class="fas fa-hourglass-half me-1"></i>Час життя сесії користувача (за замовчуванням: 7200 сек = 2 години)</div>
                        </div>
                        <div class="col-md-6">
                            <label for="cookieLifetime" class="form-label fw-medium small">
                                Час життя куків (секунди)
                                <span class="badge bg-warning text-dark ms-2">В розробці</span>
                            </label>
                            <input type="number" 
                                   class="form-control <?= $readonlyClass ?>" 
                                   id="cookieLifetime" 
                                   name="settings[cookie_lifetime]" 
                                   value="<?= htmlspecialchars($settings['cookie_lifetime'] ?? '86400') ?>"
                                   min="60"
                                   step="60"
                                   placeholder="86400"
                                   <?= $readonlyAttr ?>>
                            <div class="form-text small"><i class="fas fa-cookie me-1"></i>Час життя куків (за замовчуванням: 86400 сек = 24 години)</div>
                        </div>
                        <div class="col-md-6">
                            <label for="cookiePath" class="form-label fw-medium small">
                                Шлях для куків
                                <span class="badge bg-warning text-dark ms-2">В розробці</span>
                            </label>
                            <input type="text" 
                                   class="form-control <?= $readonlyClass ?>" 
                                   id="cookiePath" 
                                   name="settings[cookie_path]" 
                                   value="<?= htmlspecialchars($settings['cookie_path'] ?? '/') ?>"
                                   placeholder="/"
                                   <?= $readonlyAttr ?>>
                            <div class="form-text small"><i class="fas fa-route me-1"></i>Шлях на сервері, для якого доступні куки</div>
                        </div>
                        <div class="col-md-6">
                            <label for="cookieDomain" class="form-label fw-medium small">
                                Домен для куків
                                <span class="badge bg-warning text-dark ms-2">В розробці</span>
                            </label>
                            <input type="text" 
                                   class="form-control <?= $readonlyClass ?>" 
                                   id="cookieDomain" 
                                   name="settings[cookie_domain]" 
                                   value="<?= htmlspecialchars($settings['cookie_domain'] ?? '') ?>"
                                   placeholder="example.com"
                                   <?= $readonlyAttr ?>>
                            <div class="form-text small"><i class="fas fa-globe me-1"></i>Домен, для якого доступні куки (залиште порожнім для поточного домену)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium small d-block mb-2">
                                Безпечні куки (HTTPS only)
                                <span class="badge bg-warning text-dark ms-2">В розробці</span>
                            </label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="cookieSecure" 
                                       name="settings[cookie_secure]" 
                                       value="1"
                                       <?= ($settings['cookie_secure'] ?? '0') === '1' ? 'checked' : '' ?>
                                       <?= $readonlyAttr ?>>
                                <label class="form-check-label fw-medium" for="cookieSecure">
                                    Безпечні куки (HTTPS only)
                                </label>
                            </div>
                            <div class="form-text small mt-1"><i class="fas fa-lock me-1"></i>Куки будуть передаватись тільки через HTTPS</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium small d-block mb-2">
                                HttpOnly куки
                                <span class="badge bg-warning text-dark ms-2">В розробці</span>
                            </label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="cookieHttpOnly" 
                                       name="settings[cookie_httponly]" 
                                       value="1"
                                       <?= ($settings['cookie_httponly'] ?? '1') === '1' ? 'checked' : '' ?>
                                       <?= $readonlyAttr ?>>
                                <label class="form-check-label fw-medium" for="cookieHttpOnly">
                                    HttpOnly куки
                                </label>
                            </div>
                            <div class="form-text small mt-1"><i class="fas fa-shield-alt me-1"></i>Запобігає доступу до куків через JavaScript</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Продуктивність -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6>
                        <i class="fas fa-database me-2 text-primary"></i>Продуктивність
                    </h6>
                    <div class="form-check form-switch">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="cacheEnabled" 
                               name="settings[cache_enabled]" 
                               value="1"
                               <?= ($settings['cache_enabled'] ?? '1') === '1' ? 'checked' : '' ?>
                               <?= $readonlyAttr ?>
                               title="Увімкнути кешування">
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="cacheDefaultTtl" class="form-label fw-medium small">Час життя кешу (секунди)</label>
                            <input type="number" 
                                   class="form-control <?= $readonlyClass ?>" 
                                   id="cacheDefaultTtl" 
                                   name="settings[cache_default_ttl]" 
                                   value="<?= htmlspecialchars($settings['cache_default_ttl'] ?? '3600') ?>"
                                   min="60"
                                   step="60"
                                   placeholder="3600"
                                   <?= $readonlyAttr ?>>
                            <div class="form-text small"><i class="fas fa-hourglass-half me-1"></i>Стандартний час життя кешу (за замовчуванням: 3600 сек = 1 година)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium small d-block mb-2">Автоматична очистка</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="cacheAutoCleanup" 
                                       name="settings[cache_auto_cleanup]" 
                                       value="1"
                                       <?= ($settings['cache_auto_cleanup'] ?? '1') === '1' ? 'checked' : '' ?>
                                       <?= $readonlyAttr ?>>
                                <label class="form-check-label fw-medium" for="cacheAutoCleanup">
                                    Автоматична очистка застарілого кешу
                                </label>
                            </div>
                            <div class="form-text small mt-1"><i class="fas fa-broom me-1"></i>Автоматично видаляти прострочений кеш</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Логування та діагностика -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6>
                        <i class="fas fa-file-alt me-2 text-primary"></i>Логування та діагностика
                    </h6>
                    <div class="form-check form-switch">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="loggingEnabled" 
                               name="settings[logging_enabled]" 
                               value="1"
                               <?= ($settings['logging_enabled'] ?? '1') === '1' ? 'checked' : '' ?>
                               <?= $readonlyAttr ?>
                               title="Увімкнути логування">
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium small">Рівні логування</label>
                            <?php
                            $selectedLevels = ! empty($settings['logging_levels'])
                                ? (is_array($settings['logging_levels']) ? $settings['logging_levels'] : explode(',', $settings['logging_levels']))
                                : ['INFO', 'WARNING', 'ERROR', 'CRITICAL'];
?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="logLevelDebug" name="settings[logging_levels][]" value="DEBUG" 
                                       <?= in_array('DEBUG', $selectedLevels) ? 'checked' : '' ?>
                                       <?= $readonlyAttr ?>>
                                <label class="form-check-label" for="logLevelDebug">
                                    DEBUG - Всі події
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="logLevelInfo" name="settings[logging_levels][]" value="INFO" 
                                       <?= in_array('INFO', $selectedLevels) ? 'checked' : '' ?>
                                       <?= $readonlyAttr ?>>
                                <label class="form-check-label" for="logLevelInfo">
                                    INFO - Інформаційні події
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="logLevelWarning" name="settings[logging_levels][]" value="WARNING" 
                                       <?= in_array('WARNING', $selectedLevels) ? 'checked' : '' ?>
                                       <?= $readonlyAttr ?>>
                                <label class="form-check-label" for="logLevelWarning">
                                    WARNING - Попередження
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="logLevelError" name="settings[logging_levels][]" value="ERROR" 
                                       <?= in_array('ERROR', $selectedLevels) ? 'checked' : '' ?>
                                       <?= $readonlyAttr ?>>
                                <label class="form-check-label" for="logLevelError">
                                    ERROR - Помилки
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="logLevelCritical" name="settings[logging_levels][]" value="CRITICAL" 
                                       <?= in_array('CRITICAL', $selectedLevels) ? 'checked' : '' ?>
                                       <?= $readonlyAttr ?>>
                                <label class="form-check-label" for="logLevelCritical">
                                    CRITICAL - Критичні помилки
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium small">Типи логування</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="logToFile" name="settings[logging_types][]" value="file" 
                                       <?= in_array('file', $settings['logging_types'] ?? ['file']) ? 'checked' : '' ?>
                                       <?= $readonlyAttr ?>>
                                <label class="form-check-label" for="logToFile">
                                    Логування у файл
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="logToErrorLog" name="settings[logging_types][]" value="error_log"
                                       <?= in_array('error_log', $settings['logging_types'] ?? []) ? 'checked' : '' ?>
                                       <?= $readonlyAttr ?>>
                                <label class="form-check-label" for="logToErrorLog">
                                    Логування в error_log PHP
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="logDbQueries" name="settings[logging_types][]" value="db_queries"
                                       <?= in_array('db_queries', $settings['logging_types'] ?? []) ? 'checked' : '' ?>
                                       <?= $readonlyAttr ?>>
                                <label class="form-check-label" for="logDbQueries">
                                    Логування SQL запитів
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="logDbErrors" name="settings[logging_types][]" value="db_errors"
                                       <?= in_array('db_errors', $settings['logging_types'] ?? ['db_errors']) ? 'checked' : '' ?>
                                       <?= $readonlyAttr ?>>
                                <label class="form-check-label" for="logDbErrors">
                                    Логування помилок БД
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="logSlowQueries" name="settings[logging_types][]" value="slow_queries"
                                       <?= in_array('slow_queries', $settings['logging_types'] ?? ['slow_queries']) ? 'checked' : '' ?>
                                       <?= $readonlyAttr ?>>
                                <label class="form-check-label" for="logSlowQueries">
                                    Логування повільних запитів
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="loggingMaxFileSize" class="form-label fw-medium small">Максимальний розмір файлу (байти)</label>
                            <input type="number" 
                                   class="form-control <?= $readonlyClass ?>" 
                                   id="loggingMaxFileSize" 
                                   name="settings[logging_max_file_size]" 
                                   value="<?= htmlspecialchars($settings['logging_max_file_size'] ?? '10485760') ?>"
                                   min="1048576"
                                   step="1048576"
                                   placeholder="10485760"
                                   <?= $readonlyAttr ?>>
                            <div class="form-text small"><i class="fas fa-file me-1"></i>Максимальний розмір файлу логу (за замовчуванням: 10 MB)</div>
                        </div>
                        <div class="col-md-6">
                            <label for="loggingRetentionDays" class="form-label fw-medium small">Зберігати логи (днів)</label>
                            <input type="number" 
                                   class="form-control <?= $readonlyClass ?>" 
                                   id="loggingRetentionDays" 
                                   name="settings[logging_retention_days]" 
                                   value="<?= htmlspecialchars($settings['logging_retention_days'] ?? '30') ?>"
                                   min="1"
                                   max="365"
                                   placeholder="30"
                                   <?= $readonlyAttr ?>>
                            <div class="form-text small"><i class="fas fa-calendar me-1"></i>Кількість днів зберігання логів (за замовчуванням: 30 днів)</div>
                        </div>
                        <div class="col-md-6">
                            <label for="loggingRotationType" class="form-label fw-medium small">Тип ротації</label>
                            <select class="form-select <?= $readonlyClass ?>" id="loggingRotationType" name="settings[logging_rotation_type]" <?= $readonlyAttr ?>>
                                <option value="size" <?= ($settings['logging_rotation_type'] ?? 'size') === 'size' ? 'selected' : '' ?>>По розміру</option>
                                <option value="time" <?= ($settings['logging_rotation_type'] ?? 'size') === 'time' ? 'selected' : '' ?>>По часу</option>
                                <option value="both" <?= ($settings['logging_rotation_type'] ?? 'size') === 'both' ? 'selected' : '' ?>>По розміру та часу</option>
                            </select>
                            <div class="form-text small"><i class="fas fa-sync-alt me-1"></i>Коли виконувати ротацію логів</div>
                        </div>
                        <div class="col-md-6" id="rotationTimeGroup" style="display: <?= in_array($settings['logging_rotation_type'] ?? 'size', ['time', 'both']) ? 'block' : 'none' ?>;">
                            <label for="loggingRotationTime" class="form-label fw-medium small">Ротація по часу</label>
                            <div class="input-group">
                                <input type="number" 
                                       class="form-control <?= $readonlyClass ?>" 
                                       id="loggingRotationTime" 
                                       name="settings[logging_rotation_time]" 
                                       value="<?= htmlspecialchars($settings['logging_rotation_time'] ?? '24') ?>"
                                       min="1"
                                       placeholder="24"
                                       <?= $readonlyAttr ?>>
                                <select class="form-select <?= $readonlyClass ?>" id="loggingRotationTimeUnit" name="settings[logging_rotation_time_unit]" style="max-width: 120px;" <?= $readonlyAttr ?>>
                                    <option value="hours" <?= ($settings['logging_rotation_time_unit'] ?? 'hours') === 'hours' ? 'selected' : '' ?>>Годин</option>
                                    <option value="days" <?= ($settings['logging_rotation_time_unit'] ?? 'hours') === 'days' ? 'selected' : '' ?>>Днів</option>
                                </select>
                            </div>
                            <div class="form-text small">Період ротації логів по часу</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const rotationType = document.getElementById('loggingRotationType');
            const rotationTimeGroup = document.getElementById('rotationTimeGroup');
            
            if (rotationType && rotationTimeGroup) {
                rotationType.addEventListener('change', function() {
                    if (this.value === 'time' || this.value === 'both') {
                        rotationTimeGroup.style.display = 'block';
                    } else {
                        rotationTimeGroup.style.display = 'none';
                    }
                });
            }

            // Ініціалізація пошуку часових поясів
            const timezoneSelect = document.getElementById('timezone');
            const timezoneSearch = document.getElementById('timezoneSearch');
            const timezoneDisplay = document.getElementById('timezoneDisplay');
            const timezoneDropdown = document.getElementById('timezoneDropdown');
            const timezoneArrow = document.querySelector('.timezone-arrow');
            const timezoneWrapper = document.querySelector('.timezone-select-wrapper');

            if (timezoneSelect && timezoneSearch && timezoneDisplay && timezoneDropdown) {
                // Функція для оновлення відображення вибраного значення
                function updateDisplay() {
                    // Спочатку перевіряємо, чи є вибрана опція
                    const selectedOption = Array.from(timezoneSelect.options).find(function(opt) {
                        return opt.selected === true;
                    });
                    
                    if (selectedOption) {
                        const displayText = selectedOption.getAttribute('data-text') || selectedOption.text;
                        timezoneDisplay.value = displayText;
                    } else {
                        // Якщо немає вибраної опції, шукаємо за значенням select
                        const currentValue = timezoneSelect.value;
                        if (currentValue && currentValue !== '') {
                            // Знаходимо опцію за значенням (може бути будь-який UTC ідентифікатор)
                            const foundOption = Array.from(timezoneSelect.options).find(function(opt) {
                                return opt.value === currentValue;
                            });
                            if (foundOption) {
                                foundOption.selected = true;
                                timezoneDisplay.value = foundOption.getAttribute('data-text') || foundOption.text;
                            } else {
                                // Якщо опції не знайдено, використовуємо значення з PHP (вже правильно встановлене)
                                // Або показуємо сам ідентифікатор
                                if (timezoneDisplay.value === '') {
                                    timezoneDisplay.value = currentValue;
                                }
                            }
                        } else {
                            // Якщо значення порожнє, встановлюємо "Не вибрано"
                            timezoneDisplay.value = 'Не вибрано';
                            timezoneSelect.value = '';
                        }
                    }
                }
                
                // Переконуємося, що поле відображення видиме, а пошукове приховане
                timezoneDisplay.style.display = 'block';
                timezoneSearch.style.display = 'none';
                
                // Оновлюємо відображення при завантаженні сторінки
                // Спочатку знаходимо вибрану опцію (яка була встановлена в PHP)
                const selectedOptionOnLoad = Array.from(timezoneSelect.options).find(function(opt) {
                    return opt.selected === true || opt.hasAttribute('selected');
                });
                
                // Якщо знайдено вибрану опцію, використовуємо її значення
                if (selectedOptionOnLoad) {
                    timezoneSelect.value = selectedOptionOnLoad.value;
                    const displayText = selectedOptionOnLoad.getAttribute('data-text') || selectedOptionOnLoad.text;
                    if (displayText && timezoneDisplay.value !== displayText) {
                        timezoneDisplay.value = displayText;
                    }
                } else {
                    // Якщо немає вибраної опції, використовуємо значення з display field (яке встановлене в PHP)
                    // Або пробуємо знайти опцію за текстом з display field
                    const currentDisplayValue = timezoneDisplay.value;
                    if (currentDisplayValue) {
                        // Шукаємо опцію, яка відповідає відображеному тексту
                        const matchingOption = Array.from(timezoneSelect.options).find(function(opt) {
                            const optionText = opt.getAttribute('data-text') || opt.text;
                            return optionText === currentDisplayValue;
                        });
                        if (matchingOption) {
                            timezoneSelect.value = matchingOption.value;
                            matchingOption.selected = true;
                        }
                    } else {
                        // Якщо немає нічого, викликаємо updateDisplay для встановлення за замовчуванням
                        updateDisplay();
                    }
                }
                
                // Фінальна перевірка: якщо значення не співпадають, оновлюємо
                const finalSelectedOption = Array.from(timezoneSelect.options).find(function(opt) {
                    return opt.value === timezoneSelect.value;
                });
                if (finalSelectedOption) {
                    const expectedText = finalSelectedOption.getAttribute('data-text') || finalSelectedOption.text;
                    if (timezoneDisplay.value !== expectedText) {
                        timezoneDisplay.value = expectedText;
                    }
                } else if (!timezoneSelect.value || timezoneSelect.value === '') {
                    // Якщо значення порожнє, встановлюємо "Не вибрано"
                    if (timezoneDisplay.value === '' || !timezoneDisplay.value) {
                        timezoneDisplay.value = 'Не вибрано';
                    }
                }

                // Функція для фільтрації опцій
                function filterTimezones(searchTerm) {
                    const options = timezoneSelect.options;
                    const filteredOptions = [];
                    
                    for (let i = 0; i < options.length; i++) {
                        const option = options[i];
                        const text = option.getAttribute('data-text') || option.text;
                        const value = option.value.toLowerCase();
                        const searchLower = searchTerm.toLowerCase();
                        
                        if (text.toLowerCase().includes(searchLower) || value.includes(searchLower)) {
                            filteredOptions.push({
                                value: option.value,
                                text: text,
                                selected: option.selected
                            });
                        }
                    }
                    
                    // Сортуємо за алфавітом
                    filteredOptions.sort(function(a, b) {
                        return a.text.localeCompare(b.text);
                    });
                    
                    return filteredOptions;
                }

                // Функція для відображення випадаючого списку
                function showDropdown(options) {
                    if (options.length === 0) {
                        timezoneDropdown.innerHTML = '<div class="timezone-dropdown-item text-muted">Нічого не знайдено</div>';
                    } else {
                        // Відображаємо до 15 результатів для зручності
                        const maxItems = options.length > 15 ? 15 : options.length;
                        timezoneDropdown.innerHTML = options.slice(0, maxItems).map(function(opt) {
                            const activeClass = opt.selected ? 'active' : '';
                            return '<div class="timezone-dropdown-item ' + activeClass + '" data-value="' + 
                                   opt.value + '">' + opt.text + '</div>';
                        }).join('');
                        
                        // Якщо результатів більше, показуємо підказку
                        if (options.length > maxItems) {
                            timezoneDropdown.innerHTML += '<div class="timezone-dropdown-item text-muted" style="font-size: 0.75rem; padding: 0.25rem 0.75rem;">Показано ' + maxItems + ' з ' + options.length + '. Уточніть пошук для більшої кількості результатів.</div>';
                        }
                    }
                    
                    // Додаємо обробники подій для елементів списку
                    timezoneDropdown.querySelectorAll('.timezone-dropdown-item').forEach(function(item) {
                        item.addEventListener('click', function() {
                            const value = this.getAttribute('data-value');
                            const text = this.textContent.trim();
                            
                            // Перевіряємо, чи це не підказка (text-muted елемент)
                            if (this.classList.contains('text-muted')) {
                                return;
                            }
                            
                            // Оновлюємо select (приховане поле форми)
                            timezoneSelect.value = value || '';
                            
                            // Оновлюємо вибрану опцію в select
                            Array.from(timezoneSelect.options).forEach(function(opt) {
                                opt.selected = (opt.value === value);
                            });
                            
                            // Оновлюємо відображення
                            if (value === '' || !value) {
                                // Якщо вибрано "Не вибрано", очищаємо значення
                                timezoneDisplay.value = 'Не вибрано';
                                timezoneSelect.value = '';
                            } else {
                                // Оновлюємо відображення через функцію для узгодженості
                                if (typeof updateDisplay === 'function') {
                                    updateDisplay();
                                } else {
                                    // Fallback: оновлюємо напряму
                                    timezoneDisplay.value = text;
                                }
                            }
                            
                            // Ховаємо dropdown та пошукове поле
                            timezoneDropdown.style.display = 'none';
                            timezoneSearch.value = '';
                            timezoneSearch.style.display = 'none';
                            timezoneDisplay.style.display = 'block';
                            timezoneSearch.blur();
                            timezoneArrow.classList.remove('rotate');
                            
                            // Візуальний фідбек - підсвічуємо поле на мить
                            timezoneDisplay.style.backgroundColor = '#e7f3ff';
                            setTimeout(function() {
                                timezoneDisplay.style.backgroundColor = '';
                            }, 300);
                            
                            // Оновлюємо поточний час при зміні часового поясу
                            if (typeof updateCurrentTime === 'function') {
                                const timezoneContainer = document.getElementById('timezone-current-time');
                                if (timezoneContainer && value) {
                                    timezoneContainer.setAttribute('data-timezone', value);
                                    updateCurrentTime();
                                }
                            }
                        });
                    });
                }

                // Обробка пошуку
                let searchTimeout;
                timezoneSearch.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    const searchTerm = this.value.trim();
                    
                    searchTimeout = setTimeout(function() {
                        if (searchTerm.length === 0) {
                            // Якщо пошук порожній, показуємо перші 15 опцій
                            const options = [];
                            for (let i = 0; i < Math.min(15, timezoneSelect.options.length); i++) {
                                const option = timezoneSelect.options[i];
                                options.push({
                                    value: option.value,
                                    text: option.getAttribute('data-text') || option.text,
                                    selected: option.selected
                                });
                            }
                            if (options.length > 0) {
                                showDropdown(options);
                                timezoneDropdown.style.display = 'block';
                            } else {
                                timezoneDropdown.style.display = 'none';
                            }
                        } else {
                            const filtered = filterTimezones(searchTerm);
                            showDropdown(filtered);
                            timezoneDropdown.style.display = 'block';
                        }
                        timezoneArrow.classList.add('rotate');
                    }, 200);
                });

                // Показ/приховування випадаючого списку при кліку на поле відображення
                timezoneDisplay.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Показуємо пошукове поле поверх поля відображення
                    timezoneSearch.style.display = 'block';
                    timezoneSearch.value = '';
                    timezoneSearch.focus();
                    
                    // Показуємо перші 15 опцій за замовчуванням
                    const options = [];
                    for (let i = 0; i < Math.min(15, timezoneSelect.options.length); i++) {
                        const option = timezoneSelect.options[i];
                        options.push({
                            value: option.value,
                            text: option.getAttribute('data-text') || option.text,
                            selected: option.selected
                        });
                    }
                    showDropdown(options);
                    
                    timezoneDropdown.style.display = 'block';
                    timezoneArrow.classList.add('rotate');
                });

                // Закриття випадаючого списку при кліку поза ним
                document.addEventListener('click', function(e) {
                    if (!timezoneWrapper.contains(e.target)) {
                        timezoneDropdown.style.display = 'none';
                        timezoneSearch.value = '';
                        timezoneSearch.style.display = 'none';
                        timezoneDisplay.style.display = 'block';
                        timezoneArrow.classList.remove('rotate');
                    }
                });

                // Обробка клавіатури для навігації
                timezoneSearch.addEventListener('keydown', function(e) {
                    const items = timezoneDropdown.querySelectorAll('.timezone-dropdown-item');
                    const activeItem = timezoneDropdown.querySelector('.timezone-dropdown-item.active');
                    
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (activeItem) {
                            const next = activeItem.nextElementSibling;
                            if (next) {
                                activeItem.classList.remove('active');
                                next.classList.add('active');
                                next.scrollIntoView({ block: 'nearest' });
                            }
                        } else if (items.length > 0) {
                            items[0].classList.add('active');
                        }
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (activeItem) {
                            const prev = activeItem.previousElementSibling;
                            if (prev) {
                                activeItem.classList.remove('active');
                                prev.classList.add('active');
                                prev.scrollIntoView({ block: 'nearest' });
                            }
                        }
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (activeItem) {
                            activeItem.click();
                        }
                    } else if (e.key === 'Escape') {
                        timezoneDropdown.style.display = 'none';
                        timezoneSearch.value = '';
                        timezoneSearch.style.display = 'none';
                        timezoneDisplay.style.display = 'block';
                        timezoneArrow.classList.remove('rotate');
                    }
                });
            }

            // Функція для оновлення поточного часу
            function updateCurrentTime() {
                const timeDisplay = document.getElementById('current-time-display');
                const timezoneContainer = document.getElementById('timezone-current-time');
                
                if (!timeDisplay || !timezoneContainer) {
                    return;
                }
                
                const timezone = timezoneContainer.getAttribute('data-timezone') || 'UTC';
                
                try {
                    const now = new Date();
                    // Створюємо дату в вибраному часовому поясі
                    const options = {
                        timeZone: timezone,
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: false
                    };
                    
                    const formatter = new Intl.DateTimeFormat('uk-UA', options);
                    const parts = formatter.formatToParts(now);
                    
                    const day = parts.find(p => p.type === 'day').value;
                    const month = parts.find(p => p.type === 'month').value;
                    const year = parts.find(p => p.type === 'year').value;
                    const hour = parts.find(p => p.type === 'hour').value;
                    const minute = parts.find(p => p.type === 'minute').value;
                    const second = parts.find(p => p.type === 'second').value;
                    
                    const formattedTime = day + '.' + month + '.' + year + ' ' + hour + ':' + minute + ':' + second;
                    timeDisplay.textContent = formattedTime;
                } catch (e) {
                    // Fallback - використовуємо системний час
                    const now = new Date();
                    const day = String(now.getDate()).padStart(2, '0');
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    const year = now.getFullYear();
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    const seconds = String(now.getSeconds()).padStart(2, '0');
                    timeDisplay.textContent = day + '.' + month + '.' + year + ' ' + hours + ':' + minutes + ':' + seconds;
                }
            }
            
            // Оновлюємо час кожну секунду
            setInterval(updateCurrentTime, 1000);
            
            // Оновлюємо час при зміні часового поясу через select
            const timezoneSelectForTime = document.getElementById('timezone');
            if (timezoneSelectForTime) {
                timezoneSelectForTime.addEventListener('change', function() {
                    const timezoneContainer = document.getElementById('timezone-current-time');
                    if (timezoneContainer && this.value) {
                        timezoneContainer.setAttribute('data-timezone', this.value);
                        updateCurrentTime();
                    }
                });
            }
        });
        </script>
        
    </div>
</form>
</div>

<style>
/* Стилі в стилі cache-view та plugins */
.site-settings-page {
    background: transparent;
}

/* Компактні відступи між секціями */
.site-settings-page .settings-form > .row {
    margin-bottom: 0;
}

.site-settings-page .settings-form > .row > .col-12 {
    margin-bottom: 0.25rem;
}

.site-settings-page .settings-form > .row > .col-12:last-child {
    margin-bottom: 0;
}

/* Картки налаштувань - плоский стиль як на cache-view */
.site-settings-page .settings-form .card {
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    background: #ffffff;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    margin-bottom: 0.25rem;
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
}

.site-settings-page .settings-form .card:hover {
    box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.08);
    border-color: #d1d5db;
}

/* Заголовки карток - стиль як на cache-view */
.site-settings-page .settings-form .card-header {
    background: linear-gradient(to bottom, #f9fafb, #f3f4f6);
    border-bottom: 1px solid #e5e7eb;
    border-radius: 4px 4px 0 0;
    padding: 10px 14px;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.site-settings-page .settings-form .card-header h6 {
    font-size: 0.8125rem;
    color: #111827;
    margin: 0;
    flex: 1;
    font-weight: 600;
    letter-spacing: -0.01em;
}

/* Стилі для тумблерів в header */
.site-settings-page .settings-form .card-header .form-check {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.site-settings-page .settings-form .card-header .form-check-label {
    margin: 0;
    font-size: 0.875rem;
    white-space: nowrap;
}

.site-settings-page .settings-form .card-body {
    padding: 14px;
}

/* Компактні відступи всередині форм */
.site-settings-page .settings-form .card-body .row.g-3 {
    --bs-gutter-y: 0.5rem;
    --bs-gutter-x: 0.75rem;
    margin-top: calc(var(--bs-gutter-y) * -0.5);
    margin-right: calc(var(--bs-gutter-x) * -0.5);
    margin-left: calc(var(--bs-gutter-x) * -0.5);
}

.site-settings-page .settings-form .card-body .row.g-3 > * {
    padding-right: calc(var(--bs-gutter-x) * 0.5);
    padding-left: calc(var(--bs-gutter-x) * 0.5);
    margin-top: var(--bs-gutter-y);
}

/* Компактні відступи для mb-3 */
.site-settings-page .settings-form .mb-3 {
    margin-bottom: 0.5rem !important;
}

/* На мобільних залишаємо білий фон для карток */
@media (max-width: 767.98px) {
    .site-settings-page .settings-form .card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
    
    .site-settings-page .settings-form .card-body {
        padding: 14px !important;
    }
}


.settings-form .card-header .text-primary {
    color: #0073aa !important;
    font-size: 0.875rem;
}

.settings-form .form-control,
.settings-form .form-select {
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
    transition: border-color 0.15s ease-in-out;
}


.settings-form .form-label {
    color: #374151;
    margin-bottom: 0.375rem;
    font-size: 0.75rem;
    font-weight: 500;
    letter-spacing: -0.01em;
}

.settings-form .form-text {
    color: #6b7280;
    font-size: 0.6875rem;
    margin-top: 0.25rem;
    line-height: 1.4;
}

.settings-form .form-check-input {
    width: 2rem;
    height: 1rem;
    border-radius: 0.5rem;
    cursor: pointer;
}

.settings-form .form-check-input:checked {
    background-color: #0073aa;
    border-color: #0073aa;
}

.settings-form .form-check-input:focus {
    box-shadow: 0 0 0 0.15rem rgba(0, 115, 170, 0.15);
}

.settings-form .form-check-label {
    font-size: 0.875rem;
    color: #23282d;
    margin-left: 0.5rem;
    cursor: pointer;
}

.settings-form .btn {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    border-radius: 4px;
    font-weight: 500;
    border: 1px solid transparent;
    transition: all 0.15s ease-in-out;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    line-height: 1.5;
    vertical-align: middle;
    text-decoration: none;
}

.settings-form .btn-primary {
    background-color: #0073aa;
    border-color: #0073aa;
    color: #ffffff;
}

.settings-form .btn-primary:hover {
    background-color: #005a87;
    border-color: #005a87;
    color: #ffffff;
}

.settings-form .btn-secondary {
    color: #6c757d;
    border-color: #dee2e6;
    background-color: #ffffff;
    min-height: 38px;
}

.settings-form .btn-secondary:hover {
    background-color: #f8f9fa;
    border-color: #adb5bd;
    color: #495057;
}

/* Стилі для вибору часового поясу */
.timezone-select-wrapper {
    position: relative;
}

.timezone-search-input {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    z-index: 10 !important;
    border-bottom: none;
    border-radius: 4px 4px 0 0;
    background: #ffffff !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.timezone-search-input {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    z-index: 2;
    border-bottom: none;
    border-radius: 4px 4px 0 0;
}

.timezone-display-input {
    position: relative;
}

.timezone-display {
    cursor: pointer;
    padding-right: 2.5rem;
}

.timezone-arrow {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    transition: transform 0.2s ease;
    color: #6c757d;
}

.timezone-arrow.rotate {
    transform: translateY(-50%) rotate(180deg);
}

.timezone-select {
    display: none;
}

.timezone-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 4px 4px;
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-top: -1px;
}

.timezone-dropdown-item {
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    transition: background-color 0.15s ease;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.875rem;
}

.timezone-dropdown-item:last-child {
    border-bottom: none;
}

.timezone-dropdown-item:hover,
.timezone-dropdown-item.active {
    background-color: #f8f9fa;
    color: #0073aa;
}

.timezone-dropdown-item.active {
    background-color: #e7f3ff;
    font-weight: 500;
}

.timezone-top-item {
    border-left: 3px solid #0073aa;
    padding-left: calc(0.75rem - 3px);
    font-weight: 500;
}
</style>

