import { createIcons, icons } from 'lucide';

// Expose a safe global initializer that always passes the icons object
window.createLucideIcons = (opts = {}) => createIcons({ icons, ...opts });

// Also expose a minimal lucide-like object so older code referencing window.lucide.createIcons works
window.lucide = {
    createIcons: (...args) => window.createLucideIcons(...args)
};

// Initialize icons after DOM load
document.addEventListener('DOMContentLoaded', () => {
    window.createLucideIcons();
});
