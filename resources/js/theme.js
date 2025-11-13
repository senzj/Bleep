// Add this to your main JavaScript file
document.addEventListener('DOMContentLoaded', function() {
    // only set default if nothing saved
    const DEFAULT_THEME = 'lofi';
    let savedTheme = localStorage.getItem('theme');
    if (!savedTheme) {
        savedTheme = DEFAULT_THEME;
        localStorage.setItem('theme', savedTheme);
    }

    const themeMenu = document.getElementById('theme-menu');
    const themeToggles = themeMenu ? themeMenu.querySelectorAll('.theme-toggle') : [];
    const themeButton = document.querySelector('.theme-button');

    const themeIcons = { light: 'sun', dark: 'moon', system: 'laptop', lofi: 'star' };
    const themeLabels = { light: 'Light', dark: 'Dark', system: 'System', lofi: 'Lofi' };
    const SHOW_EFFECTIVE_ICON_FOR_SYSTEM = false;

    function resolveEffective(theme) {
        if (theme === 'system') {
            return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
        }
        return theme;
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', resolveEffective(theme));
    }

    function setLucideIcon(el, name) {
        if (!el) return;
        el.setAttribute('data-lucide', name);
        el.classList.add('w-5', 'h-5', 'theme-icon');
        const lib = window.lucide?.icons;
        if (lib?.[name]?.toSvg) {
            el.innerHTML = lib[name].toSvg({ class: 'w-5 h-5 theme-icon' });
        } else {
            el.innerHTML = '';
            window.lucide?.createIcons?.();
        }
    }

    function updateThemeButton(selectedTheme) {
        const themeIcon = document.querySelector('.theme-button .theme-icon') || document.querySelector('.theme-icon');
        if (!themeIcon) return;
        const effective = resolveEffective(selectedTheme);
        const iconKey = (selectedTheme === 'system' && SHOW_EFFECTIVE_ICON_FOR_SYSTEM) ? effective : selectedTheme;
        setLucideIcon(themeIcon, themeIcons[iconKey] || 'sun');

        const label = themeLabels[selectedTheme] || selectedTheme;
        const effLabel = selectedTheme === 'system' ? `${label} (${effective === 'dark' ? 'Dark' : 'Light'})` : label;
        themeButton?.setAttribute('aria-label', `Current theme: ${effLabel}. Click to change.`);
        themeButton?.setAttribute('title', `Theme: ${effLabel}`);
    }

    function updateActiveToggle(selected) {
        themeToggles.forEach(btn => {
            const isActive = btn.dataset.themeName === selected;
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            btn.classList.toggle('border', isActive);
            btn.classList.toggle('border-primary', isActive);
            btn.classList.toggle('bg-base-200', isActive);
        });
    }

    // init
    applyTheme(savedTheme);
    updateThemeButton(savedTheme);
    updateActiveToggle(savedTheme);
    requestAnimationFrame(() => document.documentElement.classList.add('theme-transition'));

    // clicks
    themeMenu?.addEventListener('click', (e) => {
        const btn = e.target.closest('.theme-toggle');
        if (!btn) return;
        const theme = btn.dataset.themeName;
        localStorage.setItem('theme', theme);
        applyTheme(theme);
        updateThemeButton(theme);
        updateActiveToggle(theme);
        window.dispatchEvent(new CustomEvent('theme:change', { detail: { selected: theme, effective: resolveEffective(theme) } }));
    });

    // OS changes while on system
    if (window.matchMedia) {
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => {
            const current = localStorage.getItem('theme') || 'system';
            if (current === 'system') {
                applyTheme('system');
                updateThemeButton('system');
                updateActiveToggle('system');
            }
        };
        mq.addEventListener?.('change', onChange) ?? mq.addListener?.(onChange);
    }
});
