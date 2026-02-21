import { createIcons } from 'lucide';
import { VanillaIcons } from './icons/vanilla/index.js';

window.lucide = {
    createIcons: (opts = {}) =>
        createIcons({
            icons: VanillaIcons,
            ...opts,
        }),
    icons: VanillaIcons,
};

// Helper to render icons only within a specific container element
window.createLucideIcons = function(container) {
    if (!container) {
        window.lucide.createIcons();
        return;
    }
    const nodes = container.querySelectorAll('[data-lucide]');
    if (nodes.length) {
        window.lucide.createIcons({ nodes });
    }
};

if (typeof window.lucide.replace === 'undefined') {
    window.lucide.replace = function (opts = {}) {
        try {
            window.lucide.createIcons(opts);
        } catch (e) {
            console.error('Error replacing icons:', e);
        }
    };
}

document.addEventListener('DOMContentLoaded', () => {
    window.lucide.createIcons();
});
