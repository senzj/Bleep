import { createIcons } from 'lucide';
import * as navigation from './icons/navigation';
import * as user from './icons/user';
import * as actions from './icons/actions';
import * as media from './icons/media';
import * as status from './icons/status';
import * as communication from './icons/communication';
import * as security from './icons/security';
import * as misc from './icons/misc';
import * as devices from './icons/devices';
import * as theme from './icons/theme';

const allIcons = {
    ...navigation,
    ...user,
    ...actions,
    ...media,
    ...status,
    ...communication,
    ...security,
    ...misc,
    ...devices,
    ...theme,
};

window.lucide = {
    createIcons: (opts = {}) =>
        createIcons({
            icons: allIcons,
            ...opts,
        }),
    icons: allIcons,
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
