import { createIcons, icons } from 'lucide';

// Expose both createIcons and icons on window.lucide
window.lucide = {
    createIcons: (opts = {}) => createIcons({ icons, ...opts }),
    icons
};

// Optional helper
window.createLucideIcons = (opts = {}) => window.lucide.createIcons(opts);

// Initialize icons after DOM load
document.addEventListener('DOMContentLoaded', () => {
    window.lucide.createIcons();
});
