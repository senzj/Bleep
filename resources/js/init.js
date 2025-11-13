(() => {
    const DEFAULT = 'lofi';
    let t = localStorage.getItem('theme') || DEFAULT;
    if (t === 'system') {
        try {
            t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        } catch (e) {}
    }
    document.documentElement.setAttribute('data-theme', t);
})();

/**
 * Automatically detect and send user timezone
 * Run immediately, don't wait for DOMContentLoaded
 */
(() => {
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

    // Store timezone for later use
    window.__userTimezone = timezone;

    // Set timezone header for axios when it loads
    if (window.axios) {
        window.axios.defaults.headers.common['X-Timezone'] = timezone;
    }

    // For regular fetch requests - override immediately
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        if (args[1]) {
            args[1].headers = {
                ...args[1].headers,
                'X-Timezone': timezone
            };
        } else {
            args[1] = {
                headers: {
                    'X-Timezone': timezone
                }
            };
        }
        return originalFetch.apply(this, args);
    };
})();

// Ensure axios gets the timezone when it's ready
document.addEventListener('DOMContentLoaded', () => {
    if (window.axios && window.__userTimezone) {
        window.axios.defaults.headers.common['X-Timezone'] = window.__userTimezone;
    }
});
