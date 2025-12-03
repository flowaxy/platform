/**
 * Flowaxy CMS - Storage Manager (JavaScript)
 * Управление клиентским хранилищем (LocalStorage/SessionStorage)
 * 
 * @package Engine\Assets\Js
 * @version 1.0.0 Alpha prerelease
 */

(function(window) {
    'use strict';
    
    /**
     * Менеджер хранилища
     */
    class StorageManager {
        constructor(type = 'localStorage', prefix = '') {
            this.type = type; // 'localStorage' или 'sessionStorage'
            this.prefix = prefix;
            this.storage = window[type];
            
            if (!this.storage) {
                console.warn(`Storage type "${type}" is not supported in this browser`);
                // Fallback на объект в памяти
                this.storage = {
                    _data: {},
                    getItem: (key) => this.storage._data[key] || null,
                    setItem: (key, value) => { this.storage._data[key] = value; },
                    removeItem: (key) => { delete this.storage._data[key]; },
                    clear: () => { this.storage._data = {}; },
                    key: (index) => Object.keys(this.storage._data)[index] || null,
                    get length() { return Object.keys(this.storage._data).length; }
                };
            }
        }
        
        /**
         * Формирование полного ключа с префиксом
         */
        getFullKey(key) {
            return this.prefix ? `${this.prefix}.${key}` : key;
        }
        
        /**
         * Отримання значення зі сховища
         */
        get(key, defaultValue = null) {
            const fullKey = this.getFullKey(key);
            const value = this.storage.getItem(fullKey);
            
            if (value === null) {
                return defaultValue;
            }
            
            // Спроба декодувати JSON
            try {
                return JSON.parse(value);
            } catch (e) {
                return value;
            }
        }
        
        /**
         * Встановлення значення в сховище
         */
        set(key, value) {
            const fullKey = this.getFullKey(key);
            
            // Кодуємо значення в JSON, якщо це не рядок
            const jsonValue = typeof value === 'string' ? value : JSON.stringify(value);
            
            try {
                this.storage.setItem(fullKey, jsonValue);
                return true;
            } catch (e) {
                console.error('Error setting storage value:', e);
                return false;
            }
        }
        
        /**
         * Перевірка наявності ключа в сховищі
         */
        has(key) {
            const fullKey = this.getFullKey(key);
            return this.storage.getItem(fullKey) !== null;
        }
        
        /**
         * Видалення значення зі сховища
         */
        remove(key) {
            const fullKey = this.getFullKey(key);
            try {
                this.storage.removeItem(fullKey);
                return true;
            } catch (e) {
                console.error('Error removing storage value:', e);
                return false;
            }
        }
        
        /**
         * Отримання всіх даних зі сховища
         */
        all() {
            const result = {};
            const prefixLen = this.prefix ? this.prefix.length + 1 : 0;
            
            for (let i = 0; i < this.storage.length; i++) {
                const key = this.storage.key(i);
                
                if (!this.prefix || key.startsWith(this.prefix + '.')) {
                    const resultKey = prefixLen > 0 ? key.substring(prefixLen) : key;
                    result[resultKey] = this.get(resultKey);
                }
            }
            
            return result;
        }
        
        /**
         * Очищення всіх даних зі сховища
         */
        clear(onlyPrefix = true) {
            if (!onlyPrefix || !this.prefix) {
                try {
                    this.storage.clear();
                    return true;
                } catch (e) {
                    console.error('Error clearing storage:', e);
                    return false;
                }
            }
            
            // Очищаємо тільки ключі з префіксом
            const keysToRemove = [];
            for (let i = 0; i < this.storage.length; i++) {
                const key = this.storage.key(i);
                if (key.startsWith(this.prefix + '.')) {
                    keysToRemove.push(key);
                }
            }
            
            keysToRemove.forEach(key => this.storage.removeItem(key));
            return true;
        }
        
        /**
         * Отримання кількох значень за ключами
         */
        getMultiple(keys) {
            const result = {};
            keys.forEach(key => {
                result[key] = this.get(key);
            });
            return result;
        }
        
        /**
         * Встановлення кількох значень
         */
        setMultiple(values) {
            let result = true;
            Object.keys(values).forEach(key => {
                if (!this.set(key, values[key])) {
                    result = false;
                }
            });
            return result;
        }
        
        /**
         * Видалення кількох значень
         */
        removeMultiple(keys) {
            let result = true;
            keys.forEach(key => {
                if (!this.remove(key)) {
                    result = false;
                }
            });
            return result;
        }
        
        /**
         * Отримання значення як JSON
         */
        getJson(key, defaultValue = null) {
            return this.get(key, defaultValue);
        }
        
        /**
         * Встановлення значення як JSON
         */
        setJson(key, value) {
            return this.set(key, value);
        }
        
        /**
         * Збільшення числового значення
         */
        increment(key, increment = 1) {
            const current = parseInt(this.get(key, 0)) || 0;
            const newValue = current + increment;
            this.set(key, newValue);
            return newValue;
        }
        
        /**
         * Зменшення числового значення
         */
        decrement(key, decrement = 1) {
            return this.increment(key, -decrement);
        }
    }
    
    /**
     * Фабрика для створення менеджерів сховища
     */
    class StorageFactory {
        static localStorage(prefix = '') {
            return new StorageManager('localStorage', prefix);
        }
        
        static sessionStorage(prefix = '') {
            return new StorageManager('sessionStorage', prefix);
        }
        
        static get(type, prefix = '') {
            if (type === 'localStorage') {
                return this.localStorage(prefix);
            } else if (type === 'sessionStorage') {
                return this.sessionStorage(prefix);
            }
            return null;
        }
    }
    
    // Експорт в глобальну область видимості
    window.FlowaxyStorage = {
        StorageManager: StorageManager,
        StorageFactory: StorageFactory,
        localStorage: (prefix) => StorageFactory.localStorage(prefix),
        sessionStorage: (prefix) => StorageFactory.sessionStorage(prefix)
    };
    
    // Створюємо зручні глобальні екземпляри
    window.Storage = StorageFactory.localStorage('flowaxy');
    window.SessionStorage = StorageFactory.sessionStorage('flowaxy');
    
})(window);

