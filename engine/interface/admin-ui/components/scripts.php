<?php
/**
 * Компонент JavaScript скриптов
 */
?>
<!-- Bootstrap Bundle JS (включає Popper) -->
<script src="<?= UrlHelper::admin('assets/styles/bootstrap/js/bootstrap.bundle.min.js') ?>?v=5.1.3"></script>

<!-- Ajax Helper (глобальний хелпер для AJAX запитів) -->
<script src="<?= UrlHelper::adminAsset('scripts/ajax-helper.js') ?>"></script>

<!-- Storage Manager (управління клієнтським сховищем) -->
<script src="<?= UrlHelper::adminAsset('scripts/storage.js') ?>"></script>

<?php
// Підключаємо центральний обробник модальних вікон
if (class_exists('ModalHandler')) {
    $modalHandler = ModalHandler::getInstance();
    $modalHandler->setContext('admin');
    echo $modalHandler->renderScripts();
}
?>

<script>
// WordPress-подібна функціональність sidebar
function toggleSidebar() {
    const sidebar = document.getElementById('sidebarMenu');
    const main = document.querySelector('main.col-md-9.ms-sm-auto.col-lg-10');
    
    sidebar.classList.toggle('collapsed');
    
    // Оновлюємо відступ основного контенту
    if (sidebar.classList.contains('collapsed')) {
        main.style.marginLeft = '36px';
        main.style.marginTop = '40px';
    } else {
        main.style.marginLeft = '160px';
        main.style.marginTop = '40px';
    }
}

function toggleSubmenu(element, event) {
    // Запобігаємо сплиттю події та переходу за посиланням
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const parentItem = element.closest('.has-submenu');
    const submenu = parentItem.querySelector('.submenu');
    const arrow = element.querySelector('.submenu-arrow');
    
    // Визначаємо контекст (десктоп або мобільний)
    const isMobile = element.closest('.mobile-sidebar') !== null;
    const context = isMobile ? '.mobile-sidebar' : '.sidebar';
    
    // НЕ закриваємо інші підменю - дозволяємо відкривати кілька одночасно
    // Видалено логіку закриття інших підменю для підтримки одночасного відкриття
    
    // Перемикаємо поточне підменю
    parentItem.classList.toggle('open');
    
    // Запобігаємо переходу за посиланням
    return false;
}

// Функція для мобільного меню
function toggleMobileSidebar() {
    const mobileSidebar = document.querySelector('.mobile-sidebar');
    const overlay = document.querySelector('.mobile-sidebar-overlay');
    
    if (mobileSidebar && overlay) {
        mobileSidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        
        // Блокуємо прокрутку body коли меню відкрите
        if (mobileSidebar.classList.contains('show')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
}

// Закриття мобільного меню при кліку на посилання
document.addEventListener('DOMContentLoaded', function() {
    // Закриваємо меню тільки при кліку на звичайні посилання
    const mobileLinks = document.querySelectorAll('.mobile-sidebar .nav-link:not(.submenu-toggle), .mobile-sidebar .submenu-link');
    mobileLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const mobileSidebar = document.querySelector('.mobile-sidebar');
            const overlay = document.querySelector('.mobile-sidebar-overlay');
            
            if (mobileSidebar && overlay) {
                mobileSidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    });
    
    // Предотвращаем закрытие меню при клике на submenu-toggle
    const submenuToggles = document.querySelectorAll('.mobile-sidebar .submenu-toggle');
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    
    // Закрытие меню при изменении размера экрана
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            const mobileSidebar = document.querySelector('.mobile-sidebar');
            const overlay = document.querySelector('.mobile-sidebar-overlay');
            
            if (mobileSidebar && overlay) {
                mobileSidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
    });
    
    // Автоматическое открытие активного субменю
    const activeSubmenu = document.querySelector('.sidebar .has-submenu .active');
    if (activeSubmenu) {
        const parentSubmenu = activeSubmenu.closest('.has-submenu');
        if (parentSubmenu) {
            parentSubmenu.classList.add('open');
        }
    }
    
    // Автоматическое открытие активного субменю на мобильных
    const activeMobileSubmenu = document.querySelector('.mobile-sidebar .has-submenu .active');
    if (activeMobileSubmenu) {
        const parentMobileSubmenu = activeMobileSubmenu.closest('.has-submenu');
        if (parentMobileSubmenu) {
            parentMobileSubmenu.classList.add('open');
        }
    }
});

// Ініціалізація tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});

// Виправлення проблем з доступністю (accessibility) для модальних вікон Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    const allModals = document.querySelectorAll('.modal');
    
    allModals.forEach(function(modalElement) {
        // При відкритті модального вікна
        modalElement.addEventListener('show.bs.modal', function(e) {
            // Прибираємо aria-hidden ДО того, як Bootstrap покаже вікно
            if (modalElement.getAttribute('aria-hidden') === 'true') {
                modalElement.removeAttribute('aria-hidden');
            }
            // Встановлюємо aria-modal для правильної роботи скрін-рідерів
            modalElement.setAttribute('aria-modal', 'true');
        });
        
        // Після повного відкриття модального вікна
        modalElement.addEventListener('shown.bs.modal', function(e) {
            // Переконуємося, що aria-hidden прибрано
            if (modalElement.getAttribute('aria-hidden') === 'true') {
                modalElement.removeAttribute('aria-hidden');
            }
            
            // Встановлюємо фокус на перший інтерактивний елемент або на модальне вікно
            const firstFocusable = modalElement.querySelector(
                'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"]):not([disabled])'
            );
            
            if (firstFocusable) {
                // Не встановлюємо фокус на кнопку закриття, якщо є інші елементи
                if (!firstFocusable.classList.contains('btn-close')) {
                    firstFocusable.focus();
                } else {
                    // Якщо перший елемент - кнопка закриття, шукаємо наступний
                    const secondFocusable = modalElement.querySelectorAll(
                        'button:not([disabled]):not(.btn-close), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"]):not([disabled])'
                    )[1];
                    if (secondFocusable) {
                        secondFocusable.focus();
                    }
                }
            }
        });
        
        // При закритті модального вікна
        modalElement.addEventListener('hide.bs.modal', function(e) {
            // Прибираємо фокус з елементів всередині перед закриттям
            const activeElement = document.activeElement;
            if (modalElement.contains(activeElement)) {
                activeElement.blur();
            }
        });
        
        // Після повного закриття модального вікна
        modalElement.addEventListener('hidden.bs.modal', function(e) {
            // Встановлюємо aria-hidden після закриття
            modalElement.setAttribute('aria-hidden', 'true');
            modalElement.removeAttribute('aria-modal');
        });
        
        // MutationObserver для відстеження змін aria-hidden Bootstrap
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'aria-hidden') {
                    // Якщо модальне вікно показано (має клас 'show'), але aria-hidden = true - виправляємо
                    if (modalElement.classList.contains('show') && 
                        modalElement.getAttribute('aria-hidden') === 'true') {
                        modalElement.removeAttribute('aria-hidden');
                        modalElement.setAttribute('aria-modal', 'true');
                    }
                }
            });
        });
        
        // Спостерігаємо за змінами атрибутів
        observer.observe(modalElement, {
            attributes: true,
            attributeFilter: ['aria-hidden', 'class']
        });
    });
});

// Ініціалізація dropdown для меню Інтеграції
(function() {
    let isDropdownOpen = false;
    let integrationsDropdown = null;
    let integrationsDropdownToggle = null;
    let dropdownMenu = null;
    
    function initIntegrationsDropdown() {
        integrationsDropdown = document.getElementById('integrations-dropdown');
        integrationsDropdownToggle = document.getElementById('integrations-dropdown-toggle');
        
        // Якщо елементів немає на сторінці - просто виходимо без повідомлень
        if (!integrationsDropdown || !integrationsDropdownToggle) {
            return;
        }
        
        dropdownMenu = integrationsDropdown.querySelector('.integrations-dropdown-menu');
        if (!dropdownMenu) {
            dropdownMenu = integrationsDropdown.querySelector('.dropdown-menu');
        }
        
        // Якщо меню не знайдено - виходимо без повідомлень
        if (!dropdownMenu) {
            return;
        }
        
        // Обробник кліку на кнопку (працює і на touch пристроях)
        integrationsDropdownToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            toggleDropdown();
            return false;
        });
        
        // Обробник для touch пристроїв
        integrationsDropdownToggle.addEventListener('touchend', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            toggleDropdown();
            return false;
        });
        
        // Закриваємо dropdown при кліку поза ним
        document.addEventListener('click', function(e) {
            if (isDropdownOpen && integrationsDropdown && !integrationsDropdown.contains(e.target)) {
                closeDropdown();
            }
        }, true);
        
        // Закриваємо dropdown при кліку на пункт меню
        const dropdownItems = dropdownMenu.querySelectorAll('.dropdown-item');
        dropdownItems.forEach(item => {
            item.addEventListener('click', function() {
                closeDropdown();
            });
        });
        
        // Закрываем dropdown при нажатии Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isDropdownOpen) {
                closeDropdown();
            }
        });
    }
    
    function toggleDropdown() {
        if (isDropdownOpen) {
            closeDropdown();
        } else {
            openDropdown();
        }
    }
    
    function openDropdown() {
        if (!integrationsDropdown || !dropdownMenu) {
            return;
        }
        
        isDropdownOpen = true;
        integrationsDropdown.classList.add('show');
        dropdownMenu.classList.add('show');
        integrationsDropdownToggle.setAttribute('aria-expanded', 'true');
    }
    
    function closeDropdown() {
        if (!integrationsDropdown || !dropdownMenu) return;
        
        isDropdownOpen = false;
        integrationsDropdown.classList.remove('show');
        dropdownMenu.classList.remove('show');
        integrationsDropdownToggle.setAttribute('aria-expanded', 'false');
    }
    
    // Ініціалізація при завантаженні DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initIntegrationsDropdown);
    } else {
        // DOM вже завантажено, ініціалізуємо відразу
        setTimeout(initIntegrationsDropdown, 100);
    }
})();

// Подтверждение удаления
document.querySelectorAll('[data-confirm-delete]').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        const message = this.dataset.confirmDelete || 'Вы уверены, что хотите удалить этот элемент?';
        
        if (confirm(message)) {
            if (this.href) {
                window.location.href = this.href;
            } else if (this.form) {
                this.form.submit();
            }
        }
    });
});

// Обробка очищення кешу через AJAX
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.cache-clear-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const action = this.dataset.action;
            const actionText = action === 'clear_all' 
                ? 'Ви впевнені, що хочете очистити весь кеш?' 
                : 'Ви впевнені, що хочете очистити прострочений кеш?';
            
            if (!confirm(actionText)) {
                return;
            }
            
            // Отримуємо CSRF токен з meta тега або прихованого поля
            let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (!csrfToken) {
                // Ищем в формах на странице
                const csrfInput = document.querySelector('input[name="csrf_token"]');
                if (csrfInput) {
                    csrfToken = csrfInput.value;
                }
            }
            
            if (!csrfToken) {
                showNotification('Помилка: CSRF токен не знайдено', 'danger');
                return;
            }
            
            // Отправляем AJAX запрос
            const formData = new FormData();
            formData.append('cache_action', action);
            formData.append('csrf_token', csrfToken);
            formData.append('ajax', '1');
            
            // Показываем индикатор загрузки
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Обробка...';
            this.disabled = true;
            
            fetch('<?= UrlHelper::admin('cache-clear') ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                // Перевіряємо Content-Type заголовок
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        throw new Error('Очікувався JSON, але отримано: ' + text.substring(0, 100));
                    });
                }
                
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                // Восстанавливаем кнопку
                this.innerHTML = originalText;
                this.disabled = false;
                
                // Показываем сообщение
                if (data.success) {
                    if (typeof showNotification !== 'undefined') {
                        showNotification(data.message || 'Кеш успішно очищено', 'success');
                    } else {
                        alert(data.message || 'Кеш успішно очищено');
                    }
                } else {
                    if (typeof showNotification !== 'undefined') {
                        showNotification(data.message || 'Помилка при очищенні кешу', 'danger');
                    } else {
                        alert(data.message || 'Помилка при очищенні кешу');
                    }
                }
                
                // Закрываем dropdown
                const dropdownElement = this.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]');
                if (dropdownElement) {
                    const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                    if (dropdown) {
                        dropdown.hide();
                    }
                }
            })
            .catch(error => {
                // Восстанавливаем кнопку
                this.innerHTML = originalText;
                this.disabled = false;
                
                let errorMessage = 'Помилка при очищенні кешу';
                if (error.message) {
                    // Якщо помилка містить "Unexpected token" або "JSON" - це проблема з форматом відповіді
                    if (error.message.includes('Unexpected token') || 
                        error.message.includes('JSON') || 
                        error.message.includes('Очікувався JSON')) {
                        errorMessage = 'Помилка при очищенні кешу: отримано некоректну відповідь від сервера. Спробуйте оновити сторінку.';
                    } else {
                        errorMessage = 'Помилка при очищенні кешу: ' + error.message;
                    }
                }
                
                if (typeof showNotification !== 'undefined') {
                    showNotification(errorMessage, 'danger');
                } else {
                    alert(errorMessage);
                }
                console.error('Cache clear error:', error);
            });
        });
    });
});

// Функция showNotification теперь определена в notifications.php
</script>

<?php
// Выводим JavaScript код из StorageManager (если есть)
if (class_exists('StorageManager')) {
    $storageManager = StorageManager::getInstance();
    $js = $storageManager->getJavaScript();
    if (! empty($js)) {
        echo "\n<!-- StorageManager JavaScript -->\n";
        echo $js;
        echo "\n";
    }
}

// Вызываем хук admin_footer для дополнительных скриптов
hook_dispatch('admin_footer');
?>

