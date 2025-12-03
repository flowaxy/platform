<?php
/**
 * Центральний обробник модальних вікон
 * Працює для сайту та адмінки
 *
 * @package Engine\Classes\UI
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class ModalHandler
{
    private static ?self $instance = null;
    private array $modals = [];
    private array $handlers = [];
    private string $context = 'admin'; // 'admin' або 'frontend'

    /**
     * Отримати екземпляр (Singleton)
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Встановити контекст (admin або frontend)
     */
    public function setContext(string $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Отримати контекст
     */
    public function getContext(): string
    {
        return $this->context;
    }

    /**
     * Реєстрація модального вікна
     *
     * @param string $id ID модального вікна
     * @param array $config Конфігурація модального вікна
     * @return self
     */
    public function register(string $id, array $config = []): self
    {
        $this->modals[$id] = array_merge([
            'id' => $id,
            'title' => '',
            'type' => 'default', // default, upload, confirm, form, component
            'component' => '', // upload, confirm, form (шлях до компонента в modals/)
            'size' => '', // sm, lg, xl
            'centered' => false,
            'backdrop' => true,
            'keyboard' => true,
            'action' => '',
            'url' => '',
            'method' => 'POST',
            'enctype' => 'application/x-www-form-urlencoded',
            'onSuccess' => null,
            'onError' => null,
            'onClose' => null,
            'fields' => [],
            'buttons' => [],
            'content' => '',
            'footer' => '',
        ], $config);

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
    public function registerHandler(string $modalId, string $action, callable $handler): self
    {
        $key = $modalId . ':' . $action;
        $this->handlers[$key] = $handler;

        return $this;
    }

    /**
     * Обробка AJAX запиту від модального вікна
     *
     * @param string $modalId ID модального вікна
     * @param string $action Дія
     * @param array $data Дані запиту
     * @param array $files Файли
     * @return array Результат обробки
     */
    public function handle(string $modalId, string $action, array $data = [], array $files = []): array
    {
        $key = $modalId . ':' . $action;

        if (! isset($this->handlers[$key])) {
            return [
                'success' => false,
                'error' => 'Обработчик не найден для действия: ' . $action,
            ];
        }

        try {
            $handler = $this->handlers[$key];
            $result = call_user_func($handler, $data, $files);

            // Если результат не массив, оборачиваем его
            if (! is_array($result)) {
                $result = ['success' => true, 'data' => $result];
            }

            // Добавляем success если отсутствует
            if (! isset($result['success'])) {
                $result['success'] = true;
            }

            return $result;
        } catch (Exception $e) {
            error_log("ModalHandler error for {$modalId}:{$action}: " . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Ошибка обработки: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Получить конфигурацию модального окна
     */
    public function getModal(string $id): ?array
    {
        return $this->modals[$id] ?? null;
    }

    /**
     * Получить все зарегистрированные модальные окна
     */
    public function getAllModals(): array
    {
        return $this->modals;
    }

    /**
     * Генерация HTML модального окна
     *
     * @param string $id ID модального окна
     * @param array $options Дополнительные опции
     * @return string HTML
     */
    public function render(string $id, array $options = []): string
    {
        $modal = $this->getModal($id);
        if (! $modal) {
            return '';
        }

        // Объединяем конфигурацию с опциями
        $config = array_merge($modal, $options);
        
        // Якщо вказано компонент - рендеримо його
        if (!empty($config['component'])) {
            return $this->renderComponent($config['component'], $config);
        }

        // Генерируем уникальные ID для элементов
        $formId = $id . 'Form';
        $bodyId = $id . 'Body';
        $footerId = $id . 'Footer';
        $resultId = $id . 'Result';
        $progressId = $id . 'Progress';

        // Размер модального окна
        $dialogClass = 'modal-dialog';
        if (! empty($config['size'])) {
            $dialogClass .= ' modal-' . $config['size'];
        }
        if ($config['centered']) {
            $dialogClass .= ' modal-dialog-centered';
        }

        // Backdrop и keyboard
        $backdrop = $config['backdrop'] ? 'true' : 'false';
        $keyboard = $config['keyboard'] ? 'true' : 'false';

        ob_start();
        ?>
        <div class="modal fade" 
             id="<?= htmlspecialchars($id) ?>" 
             tabindex="-1" 
             aria-labelledby="<?= htmlspecialchars($id) ?>Label" 
             aria-hidden="true"
             data-bs-backdrop="<?= $backdrop ?>"
             data-bs-keyboard="<?= $keyboard ?>"
             data-modal-id="<?= htmlspecialchars($id) ?>"
             data-modal-action="<?= htmlspecialchars($config['action']) ?>">
            <div class="<?= $dialogClass ?>">
                <div class="modal-content">
                    <?php if (! empty($config['title'])): ?>
                    <div class="modal-header">
                        <h5 class="modal-title" id="<?= htmlspecialchars($id) ?>Label">
                            <?= htmlspecialchars($config['title']) ?>
                        </h5>
                        <button type="button" class="btn-close" data-close-modal="<?= htmlspecialchars($id) ?>" aria-label="Закрити"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form id="<?= htmlspecialchars($formId) ?>" 
                          method="<?= htmlspecialchars($config['method']) ?>"
                          enctype="<?= htmlspecialchars($config['enctype']) ?>"
                          data-modal-id="<?= htmlspecialchars($id) ?>">
                        <?php if (! empty($config['url'])): ?>
                        <input type="hidden" name="url" value="<?= htmlspecialchars($config['url']) ?>">
                        <?php endif; ?>
                        <input type="hidden" name="modal_id" value="<?= htmlspecialchars($id) ?>">
                        <input type="hidden" name="action" value="<?= htmlspecialchars($config['action']) ?>">
                        <input type="hidden" name="csrf_token" value="<?= SecurityHelper::csrfToken() ?>">
                        
                        <div class="modal-body" id="<?= htmlspecialchars($bodyId) ?>">
                            <?php if (! empty($config['content'])): ?>
                                <?= $config['content'] ?>
                            <?php else: ?>
                                <?php $this->renderFields($config['fields']); ?>
                            <?php endif; ?>
                            
                            <div id="<?= htmlspecialchars($progressId) ?>" class="progress d-none mb-3">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                            </div>
                            
                            <div id="<?= htmlspecialchars($resultId) ?>" class="alert d-none"></div>
                        </div>
                        
                        <?php if (! empty($config['footer']) || ! empty($config['buttons'])): ?>
                        <div class="modal-footer gap-2" id="<?= htmlspecialchars($footerId) ?>">
                            <?php if (! empty($config['footer'])): ?>
                                <?= $config['footer'] ?>
                            <?php else: ?>
                                <?php $this->renderButtons($config['buttons'], $id); ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Рендеринг модального компонента
     * 
     * @param string $componentName Ім'я компонента (upload, confirm, form)
     * @param array $config Конфігурація
     * @return string
     */
    private function renderComponent(string $componentName, array $config): string
    {
        $componentsPath = dirname(__DIR__) . '/admin-ui/components/modals/';
        $componentFile = $componentsPath . $componentName . '.php';
        
        if (!file_exists($componentFile)) {
            return "<!-- Modal component '{$componentName}' not found at {$componentFile} -->";
        }
        
        // Підключаємо стилі компонентів (один раз)
        static $stylesLoaded = false;
        $styles = '';
        
        if (!$stylesLoaded) {
            $cssFile = $componentsPath . 'modals.css';
            if (file_exists($cssFile)) {
                $styles = "<style>\n" . file_get_contents($cssFile) . "\n</style>\n";
            }
            $stylesLoaded = true;
        }
        
        ob_start();
        include $componentFile;
        $html = ob_get_clean();
        
        return $styles . $html;
    }

    /**
     * Рендеринг полей формы
     */
    private function renderFields(array $fields): void
    {
        foreach ($fields as $field) {
            $type = $field['type'] ?? 'text';
            $name = $field['name'] ?? '';
            $label = $field['label'] ?? '';
            $value = $field['value'] ?? '';
            $required = $field['required'] ?? false;
            $placeholder = $field['placeholder'] ?? '';
            $help = $field['help'] ?? '';
            $options = $field['options'] ?? [];
            $attributes = $field['attributes'] ?? [];

            if (empty($name)) {
                continue;
            }

            $attrString = '';
            foreach ($attributes as $key => $val) {
                $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
            }

            ?>
            <div class="mb-3">
                <?php if (! empty($label)): ?>
                <label for="<?= htmlspecialchars($name) ?>" class="form-label">
                    <?= htmlspecialchars($label) ?>
                    <?php if ($required): ?><span class="text-danger">*</span><?php endif; ?>
                </label>
                <?php endif; ?>
                
                <?php if ($type === 'textarea'): ?>
                    <textarea class="form-control" 
                              id="<?= htmlspecialchars($name) ?>" 
                              name="<?= htmlspecialchars($name) ?>" 
                              placeholder="<?= htmlspecialchars($placeholder) ?>"
                              <?= $required ? 'required' : '' ?>
                              <?= $attrString ?>><?= htmlspecialchars($value) ?></textarea>
                <?php elseif ($type === 'select'): ?>
                    <select class="form-select" 
                            id="<?= htmlspecialchars($name) ?>" 
                            name="<?= htmlspecialchars($name) ?>"
                            <?= $required ? 'required' : '' ?>
                            <?= $attrString ?>>
                        <?php foreach ($options as $optValue => $optLabel): ?>
                            <option value="<?= htmlspecialchars($optValue) ?>" <?= $optValue == $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($optLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($type === 'file'): ?>
                    <?php 
                    $multiple = $field['multiple'] ?? false;
                    $inputName = $multiple ? htmlspecialchars($name) . '[]' : htmlspecialchars($name);
                    $dropzoneId = 'dropzone_' . htmlspecialchars($name);
                    ?>
                    <div class="file-dropzone" id="<?= $dropzoneId ?>" data-input="<?= htmlspecialchars($name) ?>">
                        <input type="file" 
                               class="file-dropzone-input" 
                               id="<?= htmlspecialchars($name) ?>" 
                               name="<?= $inputName ?>"
                               <?= $multiple ? 'multiple' : '' ?>
                               <?= $required ? 'required' : '' ?>
                               <?= $attrString ?>>
                        <div class="file-dropzone-content">
                            <div class="file-dropzone-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="file-dropzone-text">
                                <span class="file-dropzone-primary">Перетягніть файли сюди</span>
                                <span class="file-dropzone-secondary">або <span class="file-dropzone-link">виберіть файли</span></span>
                            </div>
                            <div class="file-dropzone-hint">
                                <?= $multiple ? 'Можна вибрати декілька ZIP-архівів' : 'ZIP-архів до 50 MB' ?>
                            </div>
                        </div>
                        <div class="file-dropzone-files" id="<?= $dropzoneId ?>_files"></div>
                    </div>
                <?php else: ?>
                    <input type="<?= htmlspecialchars($type) ?>" 
                           class="form-control" 
                           id="<?= htmlspecialchars($name) ?>" 
                           name="<?= htmlspecialchars($name) ?>"
                           value="<?= htmlspecialchars($value) ?>"
                           placeholder="<?= htmlspecialchars($placeholder) ?>"
                           <?= $required ? 'required' : '' ?>
                           <?= $attrString ?>>
                <?php endif; ?>
                
                <?php if (! empty($help)): ?>
                <div class="form-text"><?= htmlspecialchars($help) ?></div>
                <?php endif; ?>
            </div>
            <?php
        }
    }

    /**
     * Рендеринг кнопок
     */
    private function renderButtons(array $buttons, string $modalId): void
    {
        if (empty($buttons)) {
            // Кнопки по умолчанию
            ?>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
            <button type="submit" class="btn btn-primary" data-modal-submit="<?= htmlspecialchars($modalId) ?>">Зберегти</button>
            <?php
            return;
        }

        foreach ($buttons as $button) {
            $text = $button['text'] ?? '';
            $type = $button['type'] ?? 'secondary';
            $icon = $button['icon'] ?? '';
            $action = $button['action'] ?? '';
            $attributes = $button['attributes'] ?? [];

            $attrString = '';
            foreach ($attributes as $key => $val) {
                $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
            }

            if ($action === 'close' || $action === 'cancel') {
                $attrString .= ' type="button" data-close-modal="' . htmlspecialchars($modalId) . '"';
            } elseif ($action === 'submit') {
                $attrString .= ' type="submit" data-modal-submit="' . htmlspecialchars($modalId) . '"';
            } else {
                $attrString .= ' type="button"';
            }

            echo '<button class="btn btn-' . htmlspecialchars($type) . '"' . $attrString . '>';
            if (! empty($icon)) {
                echo '<i class="fas fa-' . htmlspecialchars($icon) . ' me-1"></i>';
            }
            echo htmlspecialchars($text);
            echo '</button>' . "\n";
        }
    }

    /**
     * Генерация JavaScript для работы с модальными окнами
     */
    public function renderScripts(): string
    {
        $baseUrl = $this->context === 'admin'
            ? UrlHelper::admin('')
            : '';

        ob_start();
        ?>
        <script>
        (function() {
            'use strict';
            
            /**
             * Центральный обработчик модальных окон
             * Работает для сайта и админки
             */
            class ModalHandler {
                constructor(baseUrl) {
                    this.baseUrl = baseUrl;
                    this.activeModals = new Map();
                    this.init();
                }
                
                init() {
                    // Обработка отправки форм в модальных окнах
                    document.addEventListener('submit', (e) => {
                        const form = e.target;
                        if (!form.hasAttribute('data-modal-id')) {
                            return;
                        }
                        
                        e.preventDefault();
                        this.handleSubmit(form);
                    });
                    
                    // Очистка при закрытии модального окна
                    document.addEventListener('hidden.bs.modal', (e) => {
                        const modal = e.target;
                        const modalId = modal.getAttribute('data-modal-id');
                        if (modalId) {
                            this.resetModal(modalId);
                        }
                    });
                    
                    // Обработка закрытия модальных окон с правильным управлением фокусом
                    this.initModalAccessibility();
                }
                
                /**
                 * Обработка отправки формы
                 */
                async handleSubmit(form) {
                    const modalId = form.getAttribute('data-modal-id');
                    const action = form.querySelector('input[name="action"]')?.value || '';
                    const modal = document.getElementById(modalId);
                    
                    if (!modal) {
                        return;
                    }
                    
                    const formData = new FormData(form);
                    const submitBtn = form.querySelector('[data-modal-submit]');
                    const progressBar = modal.querySelector('.progress');
                    const resultAlert = modal.querySelector('.alert');
                    
                    // Сохраняем оригинальный текст кнопки в переменной, доступной во всех блоках
                    let originalText = '';
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Завантаження...';
                    }
                    
                    if (progressBar) {
                        progressBar.classList.remove('d-none');
                        const bar = progressBar.querySelector('.progress-bar');
                        if (bar) {
                            let progress = 0;
                            const interval = setInterval(() => {
                                progress += 10;
                                if (progress <= 90) {
                                    bar.style.width = progress + '%';
                                }
                            }, 200);
                            
                            // Сохраняем interval для очистки
                            this.activeModals.set(modalId, { interval });
                        }
                    }
                    
                    if (resultAlert) {
                        resultAlert.classList.add('d-none');
                    }
                    
                    try {
                        // Получаем URL для запроса
                        // Форма рендерится без action, поэтому используем текущий URL
                        let url = '';
                        try {
                            // Пытаемся получить action из формы
                            if (form.hasAttribute('action') && form.getAttribute('action')) {
                                url = form.getAttribute('action');
                            } else if (form.action && typeof form.action === 'string' && form.action.trim() !== '') {
                                url = form.action;
                            } else {
                                // Используем текущий URL страницы (пустая строка для текущего URL)
                                url = '';
                            }
                        } catch (e) {
                            // Если не удалось получить action, используем пустую строку (текущий URL)
                            url = '';
                        }
                        
                        // Если URL пустой, используем пустую строку для запроса на текущий URL
                        if (!url || url.trim() === '') {
                            url = '';
                        }
                        
                        // Используем более тихий подход для валидационных ошибок
                        const response = await fetch(url, {
                            method: form.method || 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }).catch(networkError => {
                            // Сетевые ошибки логируем
                            console.error('ModalHandler network error:', networkError);
                            throw networkError;
                        });
                        
                        // Проверяем статус ответа
                        const isValidationError = response.status >= 400 && response.status < 500;
                        
                        if (!response.ok) {
                            const text = await response.text().catch(() => '');
                            let errorMessage = '';
                            
                            // Пытаемся извлечь ошибку из HTML ответа
                            if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                                errorMessage = `Помилка сервера (${response.status}): Сервер повернув HTML замість JSON. Можливо, маршрут не знайдено.`;
                            } else {
                                // Пытаемся распарсить JSON и извлечь поле error
                                try {
                                    const jsonData = JSON.parse(text);
                                    if (jsonData && typeof jsonData === 'object' && jsonData.error) {
                                        errorMessage = jsonData.error;
                                    } else {
                                        errorMessage = text.substring(0, 200);
                                    }
                                } catch (e) {
                                    // Не JSON, показываем текст
                                    errorMessage = text.substring(0, 200);
                                }
                            }
                            
                            // Для валидационных ошибок обрабатываем тихо, без throw
                            if (isValidationError) {
                                // Останавливаем прогресс
                                if (this.activeModals.has(modalId)) {
                                    const { interval } = this.activeModals.get(modalId);
                                    if (interval) {
                                        clearInterval(interval);
                                    }
                                }
                                
                                if (progressBar) {
                                    progressBar.classList.add('d-none');
                                    const bar = progressBar.querySelector('.progress-bar');
                                    if (bar) {
                                        bar.style.width = '0%';
                                    }
                                }
                                
                                // Показываем ошибку пользователю без префикса
                                this.showResult(modalId, errorMessage, 'danger');
                                
                                if (submitBtn) {
                                    submitBtn.disabled = false;
                                    submitBtn.innerHTML = originalText || 'Зберегти';
                                }
                                
                                // Валидационные ошибки обрабатываем тихо - не логируем в консоль
                                return;
                            }
                            
                            // Для серверных ошибок (5xx) выбрасываем исключение
                            const error = new Error(errorMessage);
                            error.status = response.status;
                            error.isValidationError = isValidationError;
                            throw error;
                        }
                        
                        // Проверяем, что ответ JSON
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            const text = await response.text();
                            let errorMessage = 'Сервер повернув не JSON відповідь. ';
                            
                            if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                                errorMessage += 'Повернуто HTML замість JSON. Можливо, маршрут не знайдено або сталася помилка.';
                            } else {
                                errorMessage += 'Тип контенту: ' + contentType + '. Перші 100 символів: ' + text.substring(0, 100);
                            }
                            
                            throw new Error(errorMessage);
                        }
                        
                        const data = await response.json();
                        
                        // Останавливаем прогресс
                        if (this.activeModals.has(modalId)) {
                            const { interval } = this.activeModals.get(modalId);
                            if (interval) {
                                clearInterval(interval);
                            }
                        }
                        
                        if (progressBar) {
                            const bar = progressBar.querySelector('.progress-bar');
                            if (bar) {
                                bar.style.width = '100%';
                            }
                        }
                        
                        setTimeout(() => {
                            if (data.success) {
                                this.showResult(modalId, data.message || 'Операція успішна', 'success');
                                
                                // Вызываем callback onSuccess если есть
                                const modalElement = document.getElementById(modalId);
                                if (modalElement && modalElement.dataset.onSuccess) {
                                    const callback = window[modalElement.dataset.onSuccess];
                                    if (typeof callback === 'function') {
                                        callback(data);
                                    }
                                }
                                
                                // Закрываем модальное окно через 2 секунды или сразу
                                if (data.closeModal !== false) {
                                    setTimeout(() => {
                                        this.hide(modalId);
                                    }, data.closeDelay || 2000);
                                }
                                
                                // Перезагрузка страницы если нужно
                                if (data.reload) {
                                    setTimeout(() => {
                                        window.location.reload();
                                    }, data.reloadDelay || 2000);
                                }
                            } else {
                                this.showResult(modalId, data.error || 'Помилка виконання операції', 'danger');
                                
                                // Вызываем callback onError если есть
                                const modalElement = document.getElementById(modalId);
                                if (modalElement && modalElement.dataset.onError) {
                                    const callback = window[modalElement.dataset.onError];
                                    if (typeof callback === 'function') {
                                        callback(data);
                                    }
                                }
                            }
                            
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText || 'Зберегти';
                            }
                            
                            if (progressBar) {
                                progressBar.classList.add('d-none');
                                const bar = progressBar.querySelector('.progress-bar');
                                if (bar) {
                                    bar.style.width = '0%';
                                }
                            }
                        }, 500);
                    } catch (error) {
                        // Логируем только реальные ошибки (не валидационные 4xx)
                        const isValidationError = error.isValidationError === true || (error.status >= 400 && error.status < 500);
                        
                        if (!isValidationError) {
                            // Логируем только серверные ошибки (5xx) и сетевые ошибки
                            console.error('ModalHandler error:', error);
                        }
                        
                        let errorMessage = error.message || 'Помилка підключення до сервера';
                        
                        // Если в сообщении об ошибке есть JSON, пытаемся извлечь поле error
                        try {
                            // Ищем JSON объект в сообщении (учитываем вложенные объекты и строки)
                            const jsonStart = error.message.indexOf('{');
                            if (jsonStart !== -1) {
                                // Пытаемся найти конец JSON объекта
                                let braceCount = 0;
                                let jsonEnd = jsonStart;
                                for (let i = jsonStart; i < error.message.length; i++) {
                                    if (error.message[i] === '{') braceCount++;
                                    if (error.message[i] === '}') {
                                        braceCount--;
                                        if (braceCount === 0) {
                                            jsonEnd = i + 1;
                                            break;
                                        }
                                    }
                                }
                                
                                if (jsonEnd > jsonStart) {
                                    const jsonStr = error.message.substring(jsonStart, jsonEnd);
                                    const jsonData = JSON.parse(jsonStr);
                                    if (jsonData && typeof jsonData === 'object' && jsonData.error) {
                                        errorMessage = jsonData.error;
                                    }
                                }
                            }
                        } catch (e) {
                            // Если не удалось распарсить, используем исходное сообщение
                        }
                        
                        // Если ошибка уже извлечена из JSON (не содержит JSON структуру), показываем её без префикса
                        const isExtractedError = errorMessage && !errorMessage.includes('{') && !errorMessage.includes('success');
                        // Для валидационных ошибок не добавляем префикс
                        const finalMessage = (isExtractedError || isValidationError) 
                            ? errorMessage 
                            : 'Помилка підключення до сервера: ' + errorMessage;
                        
                        this.showResult(modalId, finalMessage, 'danger');
                        
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText || 'Зберегти';
                        }
                        
                        if (progressBar) {
                            progressBar.classList.add('d-none');
                            const bar = progressBar.querySelector('.progress-bar');
                            if (bar) {
                                bar.style.width = '0%';
                            }
                        }
                    }
                }
                
                /**
                 * Показать результат
                 */
                showResult(modalId, message, type) {
                    const modal = document.getElementById(modalId);
                    if (!modal) {
                        return;
                    }
                    
                    const resultAlert = modal.querySelector('.alert');
                    if (resultAlert) {
                        resultAlert.textContent = message;
                        resultAlert.className = 'alert alert-' + type;
                        resultAlert.classList.remove('d-none');
                    }
                }
                
                /**
                 * Инициализация доступности для всех модальных окон
                 */
                initModalAccessibility() {
                    document.querySelectorAll('[data-modal-id]:not([data-accessibility-initialized])').forEach((modalElement) => {
                        this.initModalAccessibilityForElement(modalElement);
                    });
                }
                
                /**
                 * Инициализация доступности для конкретного модального окна
                 */
                initModalAccessibilityForElement(modalElement) {
                    const modalId = modalElement.getAttribute('data-modal-id');
                    if (!modalId || modalElement.hasAttribute('data-accessibility-initialized')) {
                        return;
                    }
                    
                    // Помечаем, что обработчики установлены
                    modalElement.setAttribute('data-accessibility-initialized', 'true');
                    
                    // Функция для правильного закрытия модального окна
                    const closeModal = (modalIdToClose) => {
                            const modal = document.getElementById(modalIdToClose);
                            if (!modal) {
                                return;
                            }
                            
                            // КРИТИЧНО: Убираем фокус СИНХРОННО и НЕМЕДЛЕННО
                            const activeElement = document.activeElement;
                            if (activeElement && modal.contains(activeElement)) {
                                activeElement.blur();
                                const tempFocus = document.createElement('div');
                                tempFocus.style.position = 'fixed';
                                tempFocus.style.left = '-9999px';
                                tempFocus.style.width = '1px';
                                tempFocus.style.height = '1px';
                                tempFocus.setAttribute('tabindex', '-1');
                                document.body.appendChild(tempFocus);
                                tempFocus.focus();
                                
                                setTimeout(() => {
                                    if (tempFocus.parentNode) {
                                        document.body.removeChild(tempFocus);
                                    }
                                }, 0);
                            }
                            
                            const bsModal = bootstrap.Modal.getInstance(modal);
                            if (bsModal) {
                                bsModal.hide();
                            } else {
                                const newModal = new bootstrap.Modal(modal);
                                newModal.hide();
                            }
                            
                            // Убеждаемся, что backdrop удален и body восстановлен
                            setTimeout(() => {
                                // Удаляем все backdrop элементы, если они остались
                                const backdrops = document.querySelectorAll('.modal-backdrop');
                                backdrops.forEach(backdrop => {
                                    backdrop.remove();
                                });
                                
                                // Удаляем класс modal-open с body
                                document.body.classList.remove('modal-open');
                                
                                // Восстанавливаем padding-right и overflow
                                document.body.style.paddingRight = '';
                                document.body.style.overflow = '';
                                
                                // Удаляем inline стили с body, если они были добавлены Bootstrap
                                if (!document.body.hasAttribute('style') || document.body.style.length === 0) {
                                    document.body.removeAttribute('style');
                                }
                            }, 150); // Небольшая задержка для завершения анимации Bootstrap
                        };
                        
                        // Обработка события ПЕРЕД показом модального окна
                        modalElement.addEventListener('show.bs.modal', (e) => {
                            // Убираем aria-hidden ДО того, как Bootstrap начнет показывать окно
                            if (modalElement.getAttribute('aria-hidden') === 'true') {
                                modalElement.removeAttribute('aria-hidden');
                                modalElement.setAttribute('aria-modal', 'true');
                            }
                        }, true); // Capture phase
                        
                        // Обработка события ПОСЛЕ показа модального окна
                        modalElement.addEventListener('shown.bs.modal', () => {
                            // Убеждаемся что aria-hidden убран и aria-modal установлен
                            if (modalElement.getAttribute('aria-hidden') === 'true') {
                                modalElement.removeAttribute('aria-hidden');
                            }
                            modalElement.setAttribute('aria-modal', 'true');
                        });
                        
                        // Обработка события ПЕРЕД закрытием модального окна
                        modalElement.addEventListener('hide.bs.modal', (e) => {
                            const activeElement = document.activeElement;
                            if (activeElement && modalElement.contains(activeElement)) {
                                activeElement.blur();
                                const tempFocus = document.createElement('div');
                                tempFocus.style.position = 'fixed';
                                tempFocus.style.left = '-9999px';
                                tempFocus.style.width = '1px';
                                tempFocus.style.height = '1px';
                                tempFocus.setAttribute('tabindex', '-1');
                                document.body.appendChild(tempFocus);
                                tempFocus.focus();
                                
                                setTimeout(() => {
                                    if (tempFocus.parentNode) {
                                        document.body.removeChild(tempFocus);
                                    }
                                }, 100);
                            }
                        }, true); // Capture phase
                        
                        // Обработка события ПОСЛЕ закрытия модального окна
                        modalElement.addEventListener('hidden.bs.modal', () => {
                            // Удаляем backdrop если он остался
                            const backdrops = document.querySelectorAll('.modal-backdrop');
                            backdrops.forEach(backdrop => {
                                backdrop.remove();
                            });
                            
                            // Восстанавливаем состояние body
                            document.body.classList.remove('modal-open');
                            document.body.style.paddingRight = '';
                            document.body.style.overflow = '';
                            
                            // Удаляем inline стили если они пусты
                            if (!document.body.hasAttribute('style') || document.body.style.length === 0) {
                                document.body.removeAttribute('style');
                            }
                            
                            document.body.removeAttribute('tabindex');
                            
                            // Возвращаем фокус на триггер
                            const triggerElement = document.querySelector('[data-bs-target="#' + modalId + '"]');
                            if (triggerElement) {
                                try {
                                    triggerElement.focus();
                                } catch (e) {}
                            }
                        });
                        
                        // Обработка закрытия через ESC - перехватываем раньше Bootstrap
                        document.addEventListener('keydown', (e) => {
                            if (e.key === 'Escape' && modalElement.classList.contains('show')) {
                                const bsModal = bootstrap.Modal.getInstance(modalElement);
                                if (bsModal) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    e.stopImmediatePropagation();
                                    closeModal(modalId);
                                }
                            }
                        }, true); // Capture phase - перехватываем ДО Bootstrap
                        
                        // Обработка закрытия при клике на backdrop
                        modalElement.addEventListener('click', (e) => {
                            if (e.target === modalElement) {
                                closeModal(modalId);
                            }
                        });
                        
                        // Обработка кнопок закрытия - используем делегирование событий
                        // Обработка на уровне модального окна, чтобы перехватывать все кнопки
                        modalElement.addEventListener('click', (e) => {
                            const btn = e.target.closest('[data-close-modal], .btn-close');
                            if (!btn || !modalElement.contains(btn)) {
                                return;
                            }
                            
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            
                            // КРИТИЧНО: Убираем фокус СИНХРОННО и НЕМЕДЛЕННО
                            const activeElement = document.activeElement;
                            if (activeElement && modalElement.contains(activeElement)) {
                                activeElement.blur();
                                const tempFocus = document.createElement('div');
                                tempFocus.style.position = 'fixed';
                                tempFocus.style.left = '-9999px';
                                tempFocus.style.width = '1px';
                                tempFocus.style.height = '1px';
                                tempFocus.setAttribute('tabindex', '-1');
                                document.body.appendChild(tempFocus);
                                tempFocus.focus();
                                
                                setTimeout(() => {
                                    if (tempFocus.parentNode) {
                                        document.body.removeChild(tempFocus);
                                    }
                                }, 0);
                            }
                            
                            const modalIdToClose = btn.getAttribute('data-close-modal') || modalId;
                            closeModal(modalIdToClose);
                            
                            return false;
                        }, true); // Capture phase - перехватываем ДО Bootstrap
                        
                        // Дополнительная обработка mousedown для кнопок закрытия
                        modalElement.addEventListener('mousedown', (e) => {
                            const btn = e.target.closest('[data-close-modal], .btn-close');
                            if (!btn || !modalElement.contains(btn)) {
                                return;
                            }
                            
                            // Убираем фокус сразу при нажатии мыши
                            if (document.activeElement === btn) {
                                btn.blur();
                                document.body.setAttribute('tabindex', '-1');
                                document.body.focus();
                                setTimeout(() => {
                                    document.body.removeAttribute('tabindex');
                                }, 50);
                            }
                        }, true); // Capture phase
                        
                        // MutationObserver для отслеживания изменений aria-hidden
                        const observer = new MutationObserver((mutations) => {
                            mutations.forEach((mutation) => {
                                if (mutation.type === 'attributes' && mutation.attributeName === 'aria-hidden') {
                                    const target = mutation.target;
                                    const activeElement = document.activeElement;
                                    
                                    // Если модальное окно ПОКАЗАНО, но aria-hidden установлен в true - это ошибка
                                    if (target.classList.contains('show') && 
                                        target.getAttribute('aria-hidden') === 'true') {
                                        // Исправляем: убираем aria-hidden если окно показано
                                        target.removeAttribute('aria-hidden');
                                        target.setAttribute('aria-modal', 'true');
                                    }
                                    
                                    // Если aria-hidden установлен в true, но внутри есть элемент с фокусом
                                    if (target.getAttribute('aria-hidden') === 'true' && 
                                        activeElement && 
                                        target.contains(activeElement)) {
                                        
                                        // Убираем фокус немедленно
                                        activeElement.blur();
                                        document.body.setAttribute('tabindex', '-1');
                                        document.body.focus();
                                        setTimeout(() => {
                                            document.body.removeAttribute('tabindex');
                                        }, 50);
                                    }
                                }
                            });
                        });
                        
                        observer.observe(modalElement, {
                            attributes: true,
                            attributeFilter: ['aria-hidden']
                        });
                        
                        // Также наблюдаем за изменениями класса 'show'
                        const classObserver = new MutationObserver((mutations) => {
                            mutations.forEach((mutation) => {
                                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                                    const target = mutation.target;
                                    // Если класс 'show' добавлен, но aria-hidden все еще true - исправляем
                                    if (target.classList.contains('show') && 
                                        target.getAttribute('aria-hidden') === 'true') {
                                        target.removeAttribute('aria-hidden');
                                        target.setAttribute('aria-modal', 'true');
                                    }
                                }
                            });
                        });
                        
                        classObserver.observe(modalElement, {
                            attributes: true,
                            attributeFilter: ['class']
                        });
                }
                
                /**
                 * Сброс модального окна
                 */
                resetModal(modalId) {
                    const modal = document.getElementById(modalId);
                    if (!modal) {
                        return;
                    }
                    
                    const form = modal.querySelector('form');
                    if (form) {
                        form.reset();
                    }
                    
                    const resultAlert = modal.querySelector('.alert');
                    if (resultAlert) {
                        resultAlert.classList.add('d-none');
                        resultAlert.textContent = '';
                    }
                    
                    const progressBar = modal.querySelector('.progress');
                    if (progressBar) {
                        progressBar.classList.add('d-none');
                        const bar = progressBar.querySelector('.progress-bar');
                        if (bar) {
                            bar.style.width = '0%';
                        }
                    }
                    
                    const submitBtn = modal.querySelector('[data-modal-submit]');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    
                    // Очищаем intervals
                    if (this.activeModals.has(modalId)) {
                        const { interval } = this.activeModals.get(modalId);
                        if (interval) {
                            clearInterval(interval);
                        }
                        this.activeModals.delete(modalId);
                    }
                }
                
                /**
                 * Открыть модальное окно
                 */
                show(modalId, options = {}) {
                    const modalElement = document.getElementById(modalId);
                    if (!modalElement) {
                        console.error('Modal not found: ' + modalId);
                        return;
                    }
                    
                    // Убеждаемся, что обработчики доступности установлены для этого модального окна
                    if (!modalElement.hasAttribute('data-accessibility-initialized')) {
                        this.initModalAccessibilityForElement(modalElement);
                    }
                    
                    // Применяем опции
                    if (options.data) {
                        this.fillModalData(modalId, options.data);
                    }
                    
                    const bsModal = new bootstrap.Modal(modalElement);
                    bsModal.show();
                }
                
                /**
                 * Закрыть модальное окно
                 */
                hide(modalId) {
                    const modalElement = document.getElementById(modalId);
                    if (!modalElement) {
                        return;
                    }
                    
                    // Убираем фокус перед закрытием
                    const activeElement = document.activeElement;
                    if (activeElement && modalElement.contains(activeElement)) {
                        activeElement.blur();
                    }
                    
                    const bsModal = bootstrap.Modal.getInstance(modalElement);
                    if (bsModal) {
                        bsModal.hide();
                    }
                    
                    // Убеждаемся, что backdrop удален и body восстановлен
                    setTimeout(() => {
                        // Удаляем все backdrop элементы
                        const backdrops = document.querySelectorAll('.modal-backdrop');
                        backdrops.forEach(backdrop => {
                            backdrop.remove();
                        });
                        
                        // Восстанавливаем состояние body
                        document.body.classList.remove('modal-open');
                        document.body.style.paddingRight = '';
                        document.body.style.overflow = '';
                        
                        // Удаляем inline стили если они пусты
                        if (!document.body.hasAttribute('style') || document.body.style.length === 0) {
                            document.body.removeAttribute('style');
                        }
                    }, 150);
                }
                
                /**
                 * Заполнить данные в модальном окне
                 */
                fillModalData(modalId, data) {
                    const modal = document.getElementById(modalId);
                    if (!modal) {
                        return;
                    }
                    
                    const form = modal.querySelector('form');
                    if (!form) {
                        return;
                    }
                    
                    for (const [key, value] of Object.entries(data)) {
                        const input = form.querySelector(`[name="${key}"]`);
                        if (input) {
                            if (input.type === 'checkbox') {
                                input.checked = !!value;
                            } else {
                                input.value = value;
                            }
                        }
                    }
                }
            }
            
            // Инициализация глобального обработчика
            window.ModalHandler = new ModalHandler('<?= $baseUrl ?>');
            
            // Инициализация Drag & Drop для файловых полей
            function initFileDropzones() {
                document.querySelectorAll('.file-dropzone').forEach(dropzone => {
                    if (dropzone.hasAttribute('data-initialized')) return;
                    dropzone.setAttribute('data-initialized', 'true');
                    
                    const input = dropzone.querySelector('.file-dropzone-input');
                    const filesContainer = dropzone.querySelector('.file-dropzone-files');
                    
                    if (!input) return;
                    
                    // Drag events
                    ['dragenter', 'dragover'].forEach(eventName => {
                        dropzone.addEventListener(eventName, (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            dropzone.classList.add('dragover');
                        });
                    });
                    
                    ['dragleave', 'drop'].forEach(eventName => {
                        dropzone.addEventListener(eventName, (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            dropzone.classList.remove('dragover');
                        });
                    });
                    
                    // Drop handler
                    dropzone.addEventListener('drop', (e) => {
                        const files = e.dataTransfer.files;
                        if (files.length > 0) {
                            input.files = files;
                            updateFilesList(filesContainer, files, input);
                        }
                    });
                    
                    // Input change handler
                    input.addEventListener('change', () => {
                        updateFilesList(filesContainer, input.files, input);
                    });
                });
            }
            
            function updateFilesList(container, files, input) {
                if (!container) return;
                container.innerHTML = '';
                
                if (files.length === 0) return;
                
                Array.from(files).forEach((file, index) => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';
                    
                    const size = formatFileSize(file.size);
                    
                    fileItem.innerHTML = `
                        <div class="file-item-icon">
                            <i class="fas fa-file-archive"></i>
                        </div>
                        <div class="file-item-info">
                            <div class="file-item-name">${escapeHtml(file.name)}</div>
                            <div class="file-item-size">${size}</div>
                        </div>
                        <button type="button" class="file-item-remove" data-index="${index}" title="Видалити">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    
                    container.appendChild(fileItem);
                });
                
                // Remove file handler
                container.querySelectorAll('.file-item-remove').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        const dt = new DataTransfer();
                        const index = parseInt(btn.dataset.index);
                        Array.from(input.files).forEach((file, i) => {
                            if (i !== index) dt.items.add(file);
                        });
                        input.files = dt.files;
                        updateFilesList(container, input.files, input);
                    });
                });
            }
            
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            // Инициализация при загрузке и при открытии модальных окон
            document.addEventListener('DOMContentLoaded', initFileDropzones);
            document.addEventListener('shown.bs.modal', initFileDropzones);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
