<?php
/**
 * Компонент модального окна для загрузки файлов (плагинов/тем)
 *
 * @param string $id ID модального окна
 * @param string $title Заголовок модального окна
 * @param string $fileInputName Имя поля файла (plugin_file, theme_file)
 * @param string $action Значение действия для формы (upload_plugin, upload_theme)
 * @param string $accept Тип файлов (по умолчанию .zip)
 * @param string $helpText Подсказка для поля файла
 * @param int $maxSize Максимальный размер файла в MB
 */
if (! isset($id) || empty($id)) {
    return;
}
if (! isset($title)) {
    $title = 'Завантажити файл';
}
if (! isset($fileInputName)) {
    $fileInputName = 'file';
}
if (! isset($action)) {
    $action = 'upload';
}
if (! isset($accept)) {
    $accept = '.zip';
}
if (! isset($helpText)) {
    $helpText = 'Виберіть файл для завантаження';
}
if (! isset($maxSize)) {
    $maxSize = 50;
}

// Генерируем уникальные ID для элементов
$baseName = str_replace(['_file', 'plugin_', 'theme_'], '', $fileInputName);
if (empty($baseName)) {
    $baseName = 'file';
}
$fileInputId = $baseName . 'File';
$formId = 'upload' . ucfirst($baseName) . 'Form';
$uploadBtnId = 'upload' . ucfirst($baseName) . 'Btn';
$progressId = 'uploadProgress';
$resultId = 'uploadResult';

// Для обратной совместимости с существующим JS
if ($fileInputName === 'plugin_file') {
    $fileInputId = 'pluginFile';
    $formId = 'uploadPluginForm';
    $uploadBtnId = 'uploadPluginBtn';
} elseif ($fileInputName === 'theme_file') {
    $fileInputId = 'themeFile';
    $formId = 'uploadThemeForm';
    $uploadBtnId = 'uploadThemeBtn';
}
?>
<div class="modal fade" id="<?= htmlspecialchars($id) ?>" tabindex="-1" aria-labelledby="<?= htmlspecialchars($id) ?>Label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="<?= htmlspecialchars($id) ?>Label">
                    <i class="fas fa-upload me-2"></i><?= htmlspecialchars($title) ?>
                </h5>
                <button type="button" class="btn-close" aria-label="Закрити" data-close-modal="<?= htmlspecialchars($id) ?>"></button>
            </div>
            <form id="<?= htmlspecialchars($formId) ?>" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= SecurityHelper::csrfToken() ?>">
                    <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                    
                    <div class="mb-3">
                        <label for="<?= htmlspecialchars($fileInputId) ?>" class="form-label"><?= htmlspecialchars($helpText) ?></label>
                        <input type="file" class="form-control" id="<?= htmlspecialchars($fileInputId) ?>" name="<?= htmlspecialchars($fileInputName) ?>" accept="<?= htmlspecialchars($accept) ?>" required>
                        <div class="form-text">
                            Максимальний розмір: <?= $maxSize ?> MB
                        </div>
                    </div>
                    
                    <div id="<?= htmlspecialchars($progressId) ?>" class="progress d-none mb-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>
                    
                    <div id="<?= htmlspecialchars($resultId) ?>" class="alert d-none"></div>
                </div>
                <div class="modal-footer">
                    <?php
                    // Кнопка отмены
                    ob_start();
$text = 'Скасувати';
$type = 'secondary';
$icon = '';
$attributes = ['type' => 'button', 'data-close-modal' => $id];
unset($url);
include __DIR__ . '/button.php';
$cancelBtn = ob_get_clean();

// Кнопка загрузки
ob_start();
$text = 'Завантажити';
$type = 'primary';
$icon = 'upload';
$attributes = ['type' => 'submit', 'id' => $uploadBtnId];
unset($url);
include __DIR__ . '/button.php';
$uploadBtn = ob_get_clean();
?>
                    <?= $cancelBtn ?>
                    <?= $uploadBtn ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function closeUploadModal(modalId) {
    const modalElement = document.getElementById(modalId);
    if (!modalElement) {
        return;
    }
    
    // КРИТИЧНО: Убираем фокус СИНХРОННО и НЕМЕДЛЕННО
    // Не используем requestAnimationFrame или setTimeout - нужна синхронность
    const activeElement = document.activeElement;
    if (activeElement && modalElement.contains(activeElement)) {
        // Убираем фокус синхронно
        activeElement.blur();
        
        // Переводим фокус на body синхронно
        // Используем небольшой трюк для гарантии удаления фокуса
        const tempFocus = document.createElement('div');
        tempFocus.style.position = 'fixed';
        tempFocus.style.left = '-9999px';
        tempFocus.style.width = '1px';
        tempFocus.style.height = '1px';
        tempFocus.setAttribute('tabindex', '-1');
        document.body.appendChild(tempFocus);
        tempFocus.focus();
        
        // Немедленно удаляем временный элемент
        setTimeout(function() {
            document.body.removeChild(tempFocus);
        }, 0);
    }
    
    // Закрываем модальное окно СРАЗУ после удаления фокуса
    const bsModal = bootstrap.Modal.getInstance(modalElement);
    if (bsModal) {
        bsModal.hide();
    } else {
        const newModal = new bootstrap.Modal(modalElement);
        newModal.hide();
    }
    
    // Также пробуем через ModalHandler если доступен
    if (window.ModalHandler && typeof window.ModalHandler.hide === 'function') {
        window.ModalHandler.hide(modalId);
    }
}

// Добавляем обработчик на все кнопки закрытия
document.addEventListener('DOMContentLoaded', function() {
    const modalId = '<?= htmlspecialchars($id) ?>';
    const modalElement = document.getElementById(modalId);
    if (!modalElement) {
        return;
    }
    
    // Обработка события ПЕРЕД закрытием модального окна (hide.bs.modal)
    // Это происходит ДО того, как Bootstrap установит aria-hidden
    modalElement.addEventListener('hide.bs.modal', function(e) {
        // Убираем фокус ПЕРЕД тем, как Bootstrap установит aria-hidden
        const activeElement = document.activeElement;
        if (activeElement && modalElement.contains(activeElement)) {
            // Сохраняем элемент для возврата фокуса после закрытия
            modalElement._previousActiveElement = activeElement;
            
            // Убираем фокус немедленно и синхронно
            activeElement.blur();
            
            // Переводим фокус на временный невидимый элемент
            const tempFocus = document.createElement('div');
            tempFocus.style.position = 'fixed';
            tempFocus.style.left = '-9999px';
            tempFocus.style.width = '1px';
            tempFocus.style.height = '1px';
            tempFocus.setAttribute('tabindex', '-1');
            document.body.appendChild(tempFocus);
            tempFocus.focus();
            
            // Удаляем временный элемент после небольшой задержки
            setTimeout(function() {
                if (tempFocus.parentNode) {
                    document.body.removeChild(tempFocus);
                }
            }, 100);
        }
    }, true); // Используем capture phase для раннего перехвата
    
    // Обработка события ПОСЛЕ закрытия модального окна (hidden.bs.modal)
    modalElement.addEventListener('hidden.bs.modal', function() {
        // Убираем tabindex с body
        document.body.removeAttribute('tabindex');
        
        // Возвращаем фокус на элемент, который открыл модальное окно
        const triggerElement = document.querySelector('[data-bs-target="#' + modalId + '"]');
        if (triggerElement) {
            try {
                triggerElement.focus();
            } catch (e) {
                // Если не удалось установить фокус, игнорируем ошибку
            }
        }
    });
    
    // Обработка события показа модального окна (show.bs.modal - ПЕРЕД показом)
    modalElement.addEventListener('show.bs.modal', function(e) {
        // КРИТИЧНО: Убираем aria-hidden ДО того, как Bootstrap начнет показывать окно
        if (modalElement.getAttribute('aria-hidden') === 'true') {
            modalElement.removeAttribute('aria-hidden');
            modalElement.setAttribute('aria-modal', 'true');
        }
    }, true); // Используем capture phase
    
    // Обработка события ПОСЛЕ показа модального окна (shown.bs.modal)
    modalElement.addEventListener('shown.bs.modal', function() {
        // Убеждаемся что aria-hidden убран и aria-modal установлен
        if (modalElement.getAttribute('aria-hidden') === 'true') {
            modalElement.removeAttribute('aria-hidden');
        }
        modalElement.setAttribute('aria-modal', 'true');
        
        // Также убираем aria-hidden через MutationObserver на всякий случай
        const checkAriaHidden = setInterval(function() {
            if (modalElement.classList.contains('show') && 
                modalElement.getAttribute('aria-hidden') === 'true') {
                modalElement.removeAttribute('aria-hidden');
                modalElement.setAttribute('aria-modal', 'true');
            } else if (!modalElement.classList.contains('show')) {
                clearInterval(checkAriaHidden);
            }
        }, 100);
        
        // Останавливаем проверку через 2 секунды
        setTimeout(function() {
            clearInterval(checkAriaHidden);
        }, 2000);
    });
    
    // Обработка закрытия через ESC
    modalElement.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeUploadModal(modalId);
        }
    });
    
    // Обработка клика на backdrop
    modalElement.addEventListener('click', function(e) {
        if (e.target === modalElement) {
            closeUploadModal(modalId);
        }
    });
    
    // Обработка клика на кнопки закрытия - убираем фокус ДО клика
    const closeButtons = modalElement.querySelectorAll('[data-close-modal], .btn-close');
    closeButtons.forEach(function(btn) {
        // Убираем фокус при mousedown (ДО click события)
        btn.addEventListener('mousedown', function(e) {
            // КРИТИЧНО: Убираем фокус ДО того, как произойдет click
            if (document.activeElement === this) {
                this.blur();
                // Переводим фокус на body синхронно
                document.body.setAttribute('tabindex', '-1');
                document.body.focus();
                // Убираем tabindex после небольшой задержки
                setTimeout(function() {
                    document.body.removeAttribute('tabindex');
                }, 50);
            }
        }, true); // Используем capture phase
        
        // Полностью перехватываем клик
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            // Еще раз убеждаемся, что фокус убран
            if (document.activeElement === this || modalElement.contains(document.activeElement)) {
                this.blur();
                document.body.setAttribute('tabindex', '-1');
                document.body.focus();
                setTimeout(function() {
                    document.body.removeAttribute('tabindex');
                }, 50);
            }
            
            const modalIdToClose = this.getAttribute('data-close-modal') || modalId;
            closeUploadModal(modalIdToClose);
            
            return false;
        }, true); // Используем capture phase для раннего перехвата
    });
    
    // Используем MutationObserver для отслеживания изменений aria-hidden
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
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
                    setTimeout(function() {
                        document.body.removeAttribute('tabindex');
                    }, 50);
                }
            }
        });
    });
    
    // Наблюдаем за изменениями aria-hidden
    observer.observe(modalElement, {
        attributes: true,
        attributeFilter: ['aria-hidden']
    });
    
    // Также наблюдаем за изменениями класса 'show'
    const classObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
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
});
</script>
