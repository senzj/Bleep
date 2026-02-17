import { createIcons, icons } from 'lucide';

// Expose both createIcons and icons on window.lucide
window.lucide = {
    createIcons: (opts = {}) => createIcons({ icons, ...opts }),
    icons
};

// Optional helper
if (typeof window.lucide.replace === 'undefined') {
    window.lucide.replace = function(opts = {}) {
        if (typeof window.lucide.createIcons === 'function') {
            try {
                window.lucide.createIcons(opts);
            } catch (e) {
                console.error('Error replacing icons:', e);
            }
        }
    };
}

// Initialize icons after DOM load
document.addEventListener('DOMContentLoaded', () => {
    window.lucide.createIcons();
});
