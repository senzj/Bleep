import { createIcons, icons as lucideIcons } from 'lucide';
// import * as labIcons from '@lucide/lab';

const allIcons = {
    ...lucideIcons,
    // ...labIcons,
};

window.lucide = {
    createIcons: (opts = {}) =>
        createIcons({
            icons: allIcons,
            ...opts,
        }),
    icons: allIcons,
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
