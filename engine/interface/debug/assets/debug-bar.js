/**
 * JavaScript для Debug Bar
 */

(function() {
    'use strict';

    const initDebugBar = () => {
        const debugBar = document.getElementById('flowaxy-debug-bar');
        if (!debugBar) {
            return;
        }

        // Перемикання табів
        const tabs = debugBar.querySelectorAll('.debug-tab');
        const contents = debugBar.querySelectorAll('.debug-tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetId = tab.getAttribute('data-tab');
                
                // Видаляємо активний клас
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));
                
                // Додаємо активний клас
                tab.classList.add('active');
                const targetContent = document.getElementById('debug-tab-' + targetId);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            });
        });

        // Активація першого табу
        if (tabs.length > 0) {
            tabs[0].click();
        }

        // Перемикання згортання/розгортання
        const toggle = debugBar.querySelector('.debug-bar-toggle');
        if (toggle) {
            toggle.addEventListener('click', () => {
                debugBar.classList.toggle('collapsed');
                toggle.textContent = debugBar.classList.contains('collapsed') ? '+' : '×';
            });
        }
    };

    // Ініціалізація при завантаженні
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDebugBar);
    } else {
        initDebugBar();
    }
})();

