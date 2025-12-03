<?php
/**
 * Компонент системы уведомлений
 */
?>
<!-- Контейнер для сповіщень -->
<div id="notifications-container" class="notifications-container"></div>

<!-- Стилі для сповіщень -->
<style>
.notifications-container {
    position: fixed;
    top: calc(var(--header-height, 40px) + 3px + 10px);
    right: 20px;
    left: auto;
    transform: none;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 380px;
    pointer-events: none;
}

.notification {
    background: #ffffff;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 320px;
    max-width: 380px;
    pointer-events: auto;
    position: relative;
    overflow: hidden;
    border-left: 3px solid;
    animation: slideInRight 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    transform-origin: right center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.notification.fade-out {
    animation: slideOutRight 0.3s ease-in forwards;
    pointer-events: none;
}

.notification::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    height: 2px;
    background: currentColor;
    animation: progressBar 5s linear forwards;
    opacity: 0.6;
    width: 100%;
}

/* Іконки для типів сповіщень */
.notification-icon {
    font-size: 18px;
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-content {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.notification-message {
    flex: 1;
    font-size: 13px;
    line-height: 1.4;
    color: #23282d;
    margin: 0;
    font-weight: 400;
    letter-spacing: -0.01em;
}

.notification-close {
    background: rgba(0, 0, 0, 0.04);
    border: none;
    padding: 0;
    cursor: pointer;
    color: #666;
    font-size: 12px;
    line-height: 1;
    transition: all 0.15s ease;
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.notification-close:hover {
    background: rgba(0, 0, 0, 0.08);
    color: #23282d;
}

/* Типы уведомлений - строгий flat дизайн */
.notification-success {
    border-left-color: #28a745;
    border-color: #e8f5e9;
    background: #ffffff;
}

.notification-success .notification-icon {
    color: #28a745;
}

.notification-info {
    border-left-color: #17a2b8;
    border-color: #e3f2fd;
    background: #ffffff;
}

.notification-info .notification-icon {
    color: #17a2b8;
}

.notification-warning {
    border-left-color: #ffc107;
    border-color: #fff8e1;
    background: #ffffff;
}

.notification-warning .notification-icon {
    color: #ffc107;
}

.notification-danger {
    border-left-color: #dc3545;
    border-color: #ffebee;
    background: #ffffff;
}

.notification-danger .notification-icon {
    color: #dc3545;
}

/* Анимации - плавные и строгие */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

@keyframes progressBar {
    from {
        transform: scaleX(1);
        transform-origin: left;
    }
    to {
        transform: scaleX(0);
        transform-origin: left;
    }
}

/* Адаптивность */
@media (max-width: 768px) {
    .notifications-container {
        top: calc(var(--header-height-mobile, 48px) + 3px + 10px);
        left: 10px;
        right: 10px;
        transform: none;
        max-width: none;
    }
    
    .notification {
        min-width: auto;
        max-width: none;
        padding: 12px 14px;
    }
    
    .notification-message {
        font-size: 12px;
    }
}
</style>

<!-- JavaScript для уведомлений -->
<script>
/**
 * Система уведомлений для админ-панели
 * Использование: showNotification('Сообщение', 'success|info|warning|danger')
 */
(function() {
    'use strict';
    
    // Иконки для разных типов уведомлений
    const icons = {
        success: '<i class="fas fa-check-circle"></i>',
        info: '<i class="fas fa-info-circle"></i>',
        warning: '<i class="fas fa-exclamation-triangle"></i>',
        danger: '<i class="fas fa-times-circle"></i>'
    };
    
    /**
     * Показать уведомление
     * @param {string} message - Текст сообщения
     * @param {string} type - Тип уведомления (success, info, warning, danger)
     * @param {number} duration - Длительность показа в миллисекундах (по умолчанию 5000)
     */
    function showNotification(message, type = 'info', duration = 5000) {
        const container = document.getElementById('notifications-container');
        if (!container) {
            console.error('Notifications container not found');
            return;
        }
        
        // Створюємо елемент сповіщення
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const icon = icons[type] || icons.info;
        
        notification.innerHTML = `
            <div class="notification-icon">${icon}</div>
            <div class="notification-content">
                <p class="notification-message">${escapeHtml(message)}</p>
                <button type="button" class="notification-close" aria-label="Закрити">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        // Добавляем в контейнер
        container.appendChild(notification);
        
        // Функция удаления уведомления
        const removeNotification = () => {
            notification.classList.add('fade-out');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        };
        
        // Обробник закриття по кнопці
        const closeBtn = notification.querySelector('.notification-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', removeNotification);
        }
        
        // Автоматическое закрытие через указанное время
        if (duration > 0) {
            setTimeout(removeNotification, duration);
        }
        
        // Возвращаем функцию для ручного закрытия
        return removeNotification;
    };
    
    /**
     * Экранирование HTML для безопасности
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Делаем функцию доступной глобально (если еще не определена)
    if (typeof window.showNotification === 'undefined') {
        window.showNotification = showNotification;
    }
    
    // Также делаем доступной через глобальный объект для совместимости
    window.Notifications = {
        show: showNotification,
        success: (message, duration) => showNotification(message, 'success', duration),
        info: (message, duration) => showNotification(message, 'info', duration),
        warning: (message, duration) => showNotification(message, 'warning', duration),
        error: (message, duration) => showNotification(message, 'danger', duration)
    };
})();
</script>

