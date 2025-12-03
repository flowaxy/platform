/**
 * Глобальний хелпер для AJAX запитів
 * Спрощує роботу з AJAX запитами в усьому движку
 * 
 * @version 1.0.0 Alpha prerelease
 */

(function() {
    'use strict';
    
    /**
     * Глобальний об'єкт для AJAX запитів
     */
    window.AjaxHelper = {
        /**
         * Виконання AJAX запиту
         * 
         * @param {string} url URL для запиту
         * @param {object} options Опції запиту
         * @returns {Promise}
         */
        request: function(url, options = {}) {
            const defaults = {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            };
            
            // Об'єднуємо опції
            const config = Object.assign({}, defaults, options);
            
            // Додаємо CSRF токен до POST запитів
            if (config.method.toUpperCase() === 'POST' && !(config.body instanceof FormData)) {
                const csrfToken = this.getCsrfToken();
                if (csrfToken) {
                    if (!config.body) {
                        config.body = new URLSearchParams();
                    }
                    if (config.body instanceof URLSearchParams) {
                        config.body.append('csrf_token', csrfToken);
                    } else if (typeof config.body === 'object') {
                        config.body.csrf_token = csrfToken;
                    }
                }
            }
            
            // Обробка FormData
            if (config.body instanceof FormData) {
                const csrfToken = this.getCsrfToken();
                if (csrfToken) {
                    config.body.append('csrf_token', csrfToken);
                }
            } else if (typeof config.body === 'object' && !(config.body instanceof URLSearchParams)) {
                // Преобразуем объект в URLSearchParams для совместимости с сервером
                const params = new URLSearchParams();
                for (const key in config.body) {
                    if (config.body.hasOwnProperty(key)) {
                        params.append(key, config.body[key]);
                    }
                }
                config.body = params;
                config.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
            
            // Виконуємо запит
            return fetch(url, config)
                .then(async response => {
                    // Перевіряємо статус відповіді
                    if (!response.ok) {
                        // Пробуємо получить JSON, но если не получается, возвращаем текст
                        const contentType = response.headers.get('content-type');
                        if (contentType && contentType.includes('application/json')) {
                            try {
                                const data = await response.json();
                                throw new Error(data.error || 'Помилка запиту');
                            } catch (e) {
                                if (e instanceof SyntaxError) {
                                    const text = await response.text();
                                    throw new Error('Сервер повернув некоректну відповідь: ' + text.substring(0, 100));
                                }
                                throw e;
                            }
                        } else {
                            const text = await response.text();
                            throw new Error('HTTP помилка ' + response.status + ': ' + text.substring(0, 100));
                        }
                    }
                    
                    // Перевіряємо тип контенту
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        throw new Error('Сервер повернув не JSON відповідь: ' + text.substring(0, 200));
                    }
                    
                    // Парсимо JSON
                    try {
                        return await response.json();
                    } catch (e) {
                        if (e instanceof SyntaxError) {
                            const text = await response.text();
                            throw new Error('Помилка парсингу JSON: ' + e.message + '. Відповідь: ' + text.substring(0, 200));
                        }
                        throw e;
                    }
                })
                .then(data => {
                    // Перевіряємо успішність операції
                    if (data && typeof data === 'object' && data.success === false) {
                        throw new Error(data.error || 'Помилка обробки запиту');
                    }
                    return data;
                })
                .catch(error => {
                    console.error('AjaxHelper error:', error);
                    // Если это уже Error, просто пробрасываем
                    if (error instanceof Error) {
                        throw error;
                    }
                    // Иначе создаем новый Error
                    throw new Error(error.message || 'Невідома помилка AJAX запиту');
                });
        },
        
        /**
         * GET запит
         * 
         * @param {string} url URL
         * @param {object} params Параметри запиту
         * @param {object} options Додаткові опції
         * @returns {Promise}
         */
        get: function(url, params = {}, options = {}) {
            // Додаємо параметри до URL
            const urlObj = new URL(url, window.location.origin);
            Object.keys(params).forEach(key => {
                if (params[key] !== null && params[key] !== undefined) {
                    urlObj.searchParams.append(key, params[key]);
                }
            });
            
            return this.request(urlObj.toString(), Object.assign({
                method: 'GET'
            }, options));
        },
        
        /**
         * POST запит
         * 
         * @param {string} url URL
         * @param {object} data Дані для відправки
         * @param {object} options Додаткові опції
         * @returns {Promise}
         */
        post: function(url, data = {}, options = {}) {
            return this.request(url, Object.assign({
                method: 'POST',
                body: data
            }, options));
        },
        
        /**
         * Завантаження файлу
         * 
         * @param {string} url URL
         * @param {FormData} formData FormData з файлами
         * @param {function} onProgress Callback для прогресу
         * @returns {Promise}
         */
        upload: function(url, formData, onProgress = null) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                
                // Додаємо CSRF токен
                const csrfToken = this.getCsrfToken();
                if (csrfToken) {
                    formData.append('csrf_token', csrfToken);
                }
                
                // Обробка прогресу
                if (onProgress) {
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percentComplete = (e.loaded / e.total) * 100;
                            onProgress(percentComplete);
                        }
                    });
                }
                
                // Обробка завершення
                xhr.addEventListener('load', function() {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success === false) {
                                reject(new Error(response.error || 'Помилка завантаження'));
                            } else {
                                resolve(response);
                            }
                        } catch (e) {
                            reject(new Error('Помилка парсингу відповіді'));
                        }
                    } else {
                        reject(new Error('Помилка завантаження: ' + xhr.status));
                    }
                });
                
                // Обробка помилок
                xhr.addEventListener('error', function() {
                    reject(new Error('Помилка мережі'));
                });
                
                xhr.open('POST', url);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.send(formData);
            });
        },
        
        /**
         * Отримання CSRF токену
         * 
         * @returns {string}
         */
        getCsrfToken: function() {
            // Шукаємо токен в різних місцях
            const tokenInput = document.querySelector('input[name="csrf_token"]');
            if (tokenInput) {
                return tokenInput.value;
            }
            
            const metaToken = document.querySelector('meta[name="csrf-token"]');
            if (metaToken) {
                return metaToken.getAttribute('content');
            }
            
            // Можна додати збереження в глобальну змінну
            if (window.csrfToken) {
                return window.csrfToken;
            }
            
            return '';
        },
        
        /**
         * Показ помилки
         * 
         * @param {string} message Повідомлення
         * @param {string} type Тип помилки
         */
        showError: function(message, type = 'danger') {
            if (typeof showNotification !== 'undefined') {
                showNotification(message, type);
            } else {
                alert(message);
            }
        },
        
        /**
         * Показ успіху
         * 
         * @param {string} message Повідомлення
         */
        showSuccess: function(message) {
            if (typeof showNotification !== 'undefined') {
                showNotification(message, 'success');
            } else {
                alert(message);
            }
        }
    };
})();

