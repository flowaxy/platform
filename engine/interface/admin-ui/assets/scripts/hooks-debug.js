/**
 * JavaScript для сторінки відлагодження хуків
 */

(function() {
    'use strict';

    // Ініціалізація табів
    const initTabs = () => {
        const tabs = document.querySelectorAll('.hooks-tab');
        const contents = document.querySelectorAll('.hooks-tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetId = tab.getAttribute('data-tab');
                
                // Видаляємо активний клас з усіх табів та контентів
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));
                
                // Додаємо активний клас до вибраного табу та контенту
                tab.classList.add('active');
                const targetContent = document.getElementById(targetId);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            });
        });
    };

    // Фільтрація таблиці хуків
    const initFiltering = () => {
        const searchInput = document.getElementById('hook-search');
        const typeFilter = document.getElementById('hook-type-filter');
        const timeFilter = document.getElementById('hook-time-filter');

        if (!searchInput) return;

        const filterTable = () => {
            const searchTerm = searchInput.value.toLowerCase();
            const typeValue = typeFilter ? typeFilter.value : '';
            const timeValue = timeFilter ? timeFilter.value : '';
            const rows = document.querySelectorAll('.hooks-table tbody tr');

            rows.forEach(row => {
                const hookName = row.querySelector('.hook-name')?.textContent.toLowerCase() || '';
                const hookType = row.querySelector('.hook-type-badge')?.textContent.toLowerCase() || '';
                const timeValueEl = row.querySelector('.time-badge')?.textContent || '';
                const timeNum = parseFloat(timeValueEl.replace(/[^\d.]/g, '')) || 0;

                let show = true;

                // Пошук за назвою
                if (searchTerm && !hookName.includes(searchTerm)) {
                    show = false;
                }

                // Фільтр за типом
                if (typeValue && hookType !== typeValue.toLowerCase()) {
                    show = false;
                }

                // Фільтр за часом виконання
                if (timeValue) {
                    if (timeValue === 'fast' && timeNum >= 0.01) {
                        show = false;
                    } else if (timeValue === 'medium' && (timeNum < 0.01 || timeNum >= 0.1)) {
                        show = false;
                    } else if (timeValue === 'slow' && timeNum < 0.1) {
                        show = false;
                    }
                }

                row.style.display = show ? '' : 'none';
            });
        };

        searchInput.addEventListener('input', filterTable);
        if (typeFilter) typeFilter.addEventListener('change', filterTable);
        if (timeFilter) timeFilter.addEventListener('change', filterTable);
    };

    // Сортування таблиці
    const initSorting = () => {
        const headers = document.querySelectorAll('.hooks-table th[data-sort]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                const sortKey = header.getAttribute('data-sort');
                const tbody = header.closest('table').querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));

                const isAsc = header.classList.contains('sort-asc');
                
                // Видаляємо сортування з інших заголовків
                headers.forEach(h => {
                    h.classList.remove('sort-asc', 'sort-desc');
                });

                // Додаємо сортування до поточного заголовка
                header.classList.add(isAsc ? 'sort-desc' : 'sort-asc');

                // Сортуємо рядки
                rows.sort((a, b) => {
                    const aValue = getSortValue(a, sortKey);
                    const bValue = getSortValue(b, sortKey);
                    
                    if (typeof aValue === 'number') {
                        return isAsc ? aValue - bValue : bValue - aValue;
                    }
                    
                    const comparison = String(aValue).localeCompare(String(bValue));
                    return isAsc ? comparison : -comparison;
                });

                // Переставляємо рядки
                rows.forEach(row => tbody.appendChild(row));
            });
        });
    };

    const getSortValue = (row, key) => {
        const cell = row.querySelector(`[data-${key}]`);
        if (!cell) return '';
        
        const value = cell.getAttribute(`data-${key}`);
        const numValue = parseFloat(value);
        return isNaN(numValue) ? value : numValue;
    };

    // Ініціалізація при завантаженні сторінки
    document.addEventListener('DOMContentLoaded', () => {
        initTabs();
        initFiltering();
        initSorting();
    });
})();

