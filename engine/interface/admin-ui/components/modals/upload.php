<?php
/**
 * Компонент модального вікна завантаження файлів
 * 
 * @param array $config Конфігурація модального вікна
 *   - id: string - ID модального вікна
 *   - title: string - Заголовок
 *   - action: string - Дія форми
 *   - fileInputName: string - Ім'я поля файлу
 *   - accept: string - Типи файлів (.zip)
 *   - multiple: bool - Дозволити декілька файлів
 *   - maxSize: int - Максимальний розмір в MB
 *   - hint: string - Підказка
 */

if (!isset($config) || !is_array($config)) {
    return;
}

$id = $config['id'] ?? 'uploadModal';
$title = $config['title'] ?? 'Завантажити файл';
$action = $config['action'] ?? 'upload';
$fileInputName = $config['fileInputName'] ?? 'file';
$accept = $config['accept'] ?? '.zip';
$multiple = $config['multiple'] ?? false;
$maxSize = $config['maxSize'] ?? 50;
$hint = $config['hint'] ?? ($multiple ? 'Можна вибрати декілька файлів' : 'Один файл');
$label = $config['label'] ?? 'Виберіть файл';

$inputName = $multiple ? $fileInputName . '[]' : $fileInputName;
$dropzoneId = 'dropzone_' . $id;
$formId = $id . 'Form';
$filesContainerId = $dropzoneId . '_files';
$inputId = 'input_' . $id;
?>

<div class="modal fade" id="<?= htmlspecialchars($id) ?>" tabindex="-1" aria-labelledby="<?= htmlspecialchars($id) ?>Label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="<?= htmlspecialchars($id) ?>Label"><?= htmlspecialchars($title) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрити"></button>
            </div>
            
            <form id="<?= htmlspecialchars($formId) ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="modal_id" value="<?= htmlspecialchars($id) ?>">
                <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                <input type="hidden" name="csrf_token" value="<?= SecurityHelper::csrfToken() ?>">
                
                <div class="modal-body">
                    <div class="upload-field">
                        <label class="upload-label"><?= htmlspecialchars($label) ?> <span class="required">*</span></label>
                        
                        <!-- Dropzone для drag & drop -->
                        <div class="upload-dropzone" id="<?= htmlspecialchars($dropzoneId) ?>">
                            <input type="file" 
                                   class="upload-input" 
                                   id="<?= htmlspecialchars($inputId) ?>" 
                                   name="<?= htmlspecialchars($inputName) ?>"
                                   accept="<?= htmlspecialchars($accept) ?>"
                                   <?= $multiple ? 'multiple' : '' ?>>
                            
                            <div class="upload-content">
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="upload-text">
                                    <span class="upload-primary">Перетягніть файли сюди</span>
                                    <span class="upload-secondary">або <span class="upload-link">виберіть файли</span></span>
                                </div>
                                <span class="upload-hint"><?= htmlspecialchars($hint) ?></span>
                            </div>
                        </div>
                        
                        <!-- Список файлів ПОЗА dropzone -->
                        <div class="upload-files" id="<?= htmlspecialchars($filesContainerId) ?>"></div>
                        
                        <div class="upload-info">Максимальний розмір: <?= $maxSize ?> MB на файл</div>
                        
                        <div class="upload-options">
                            <label class="upload-checkbox">
                                <input type="checkbox" name="overwrite" value="1">
                                <span>Перезаписати існуючі (оновлення)</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="upload-progress d-none" id="<?= htmlspecialchars($id) ?>_progress">
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <div class="upload-result d-none" id="<?= htmlspecialchars($id) ?>_result"></div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i>
                        Завантажити
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const modalId = '<?= htmlspecialchars($id) ?>';
    const modal = document.getElementById(modalId);
    const dropzone = document.getElementById('<?= htmlspecialchars($dropzoneId) ?>');
    const input = document.getElementById('<?= htmlspecialchars($inputId) ?>');
    const filesContainer = document.getElementById('<?= htmlspecialchars($filesContainerId) ?>');
    const form = document.getElementById('<?= htmlspecialchars($formId) ?>');
    const progressEl = document.getElementById(modalId + '_progress');
    const resultEl = document.getElementById(modalId + '_result');
    
    if (!dropzone || !modal || dropzone.hasAttribute('data-initialized')) return;
    dropzone.setAttribute('data-initialized', 'true');
    
    // Зберігаємо файли окремо (бо input.files не можна напряму модифікувати)
    let selectedFiles = [];
    let isSubmitting = false; // Захист від повторної відправки
    
    // Drag & Drop на dropzone
    ['dragenter', 'dragover'].forEach(event => {
        dropzone.addEventListener(event, e => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dragover');
        });
    });
    
    ['dragleave', 'drop'].forEach(event => {
        dropzone.addEventListener(event, e => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dragover');
        });
    });
    
    dropzone.addEventListener('drop', e => {
        if (e.dataTransfer.files.length > 0) {
            addFiles(e.dataTransfer.files);
        }
    });
    
    // Вибір файлів через input
    input.addEventListener('change', () => {
        if (input.files.length > 0) {
            addFiles(input.files);
        }
    });
    
    // Додати файли до списку
    function addFiles(files) {
        <?php if ($multiple): ?>
        // Множинний вибір - додаємо до існуючих
        Array.from(files).forEach(f => {
            if (!selectedFiles.find(sf => sf.name === f.name && sf.size === f.size)) {
                selectedFiles.push(f);
            }
        });
        <?php else: ?>
        // Один файл - замінюємо
        selectedFiles = [files[0]];
        <?php endif; ?>
        updateFilesList();
        syncInputFiles();
    }
    
    // Видалити файл зі списку
    function removeFile(index) {
        selectedFiles.splice(index, 1);
        updateFilesList();
        syncInputFiles();
    }
    
    // Синхронізувати input.files з selectedFiles
    function syncInputFiles() {
        const dt = new DataTransfer();
        selectedFiles.forEach(f => dt.items.add(f));
        input.files = dt.files;
    }
    
    // Оновити відображення списку файлів
    function updateFilesList() {
        filesContainer.innerHTML = '';
        
        if (selectedFiles.length === 0) {
            return;
        }
        
        selectedFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'upload-file-item';
            item.innerHTML = `
                <div class="upload-file-icon"><i class="fas fa-file-archive"></i></div>
                <div class="upload-file-info">
                    <div class="upload-file-name">${escapeHtml(file.name)}</div>
                    <div class="upload-file-size">${formatSize(file.size)}</div>
                </div>
                <button type="button" class="upload-file-remove" data-index="${index}" title="Видалити">
                    <i class="fas fa-times"></i>
                </button>
            `;
            filesContainer.appendChild(item);
        });
        
        // Обробники видалення
        filesContainer.querySelectorAll('.upload-file-remove').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const idx = parseInt(this.dataset.index);
                removeFile(idx);
            });
        });
    }
    
    // Обробка відправки форми
    const submitBtn = form.querySelector('button[type="submit"]');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        // Захист від повторної відправки
        if (isSubmitting) {
            return false;
        }
        
        if (selectedFiles.length === 0) {
            showResult('Виберіть хоча б один файл', 'error');
            return;
        }
        
        // Блокуємо повторну відправку
        isSubmitting = true;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Завантаження...';
        
        const formData = new FormData(form);
        
        // Показуємо прогрес
        progressEl.classList.remove('d-none');
        resultEl.classList.add('d-none');
        
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressEl.querySelector('.progress-bar').style.width = percent + '%';
            }
        });
        
        xhr.addEventListener('load', function() {
            progressEl.classList.add('d-none');
            
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    showResult(response.message || 'Успішно завантажено!', 'success');
                    // Закриваємо модал та перезавантажуємо
                    setTimeout(() => {
                        closeModal();
                        window.location.reload();
                    }, 1500);
                } else {
                    showResult(response.error || 'Помилка завантаження', 'error');
                    // Розблоковуємо кнопку при помилці
                    resetSubmitButton();
                }
            } catch (err) {
                showResult('Помилка обробки відповіді', 'error');
                resetSubmitButton();
            }
        });
        
        xhr.addEventListener('error', function() {
            progressEl.classList.add('d-none');
            showResult('Помилка мережі', 'error');
            resetSubmitButton();
        });
        
        xhr.open('POST', window.location.href);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(formData);
    });
    
    function resetSubmitButton() {
        isSubmitting = false;
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-upload"></i> Завантажити';
    }
    
    function showResult(message, type) {
        resultEl.textContent = message;
        resultEl.className = 'upload-result ' + type;
        resultEl.classList.remove('d-none');
    }
    
    function formatSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Функція закриття модалу
    function closeModal() {
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) {
            bsModal.hide();
        } else {
            // Fallback - закриваємо вручну
            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            modal.removeAttribute('aria-modal');
        }
        
        // Видаляємо backdrop якщо залишився
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }
    
    // Очистка стану
    function resetState() {
        selectedFiles = [];
        updateFilesList();
        syncInputFiles();
        progressEl.classList.add('d-none');
        resultEl.classList.add('d-none');
        progressEl.querySelector('.progress-bar').style.width = '0%';
        resetSubmitButton(); // Скидаємо стан кнопки
    }
    
    // Очистка при закритті модалу
    modal.addEventListener('hidden.bs.modal', resetState);
    
    // Обробка кнопок закриття
    modal.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', function() {
            closeModal();
        });
    });
})();
</script>
