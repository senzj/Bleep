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

// Create icons immediately when DOM is ready, and observe for new additions
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.lucide.createIcons();
    });
} else {
    // DOM already loaded, create icons now
    window.lucide.createIcons();
}

// Watch for dynamically added icons
const observer = new MutationObserver((mutations) => {
    let hasNewIcons = false;
    for (const mutation of mutations) {
        if (mutation.type === 'childList' || mutation.type === 'attributes') {
            hasNewIcons = true;
            break;
        }
    }
    if (hasNewIcons) {
        window.lucide.createIcons();
    }
});

observer.observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['data-lucide'],
});
