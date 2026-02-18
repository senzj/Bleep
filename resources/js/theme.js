// Add this to your main JavaScript file
document.addEventListener('DOMContentLoaded', function() {
    // only set default if nothing saved
    const DEFAULT_THEME = 'lofi';
    let savedTheme = localStorage.getItem('theme');
    if (!savedTheme) {
        savedTheme = DEFAULT_THEME;
        localStorage.setItem('theme', savedTheme);
    }

    const themeMenus = document.querySelectorAll('.theme-menu');
    const getThemeToggles = () => document.querySelectorAll('.theme-toggle');
    const themeButtons = document.querySelectorAll('.theme-button');

    const themeNames = [
        'light', 'dark', 'system', 'acid', 'aqua', 'autumn', 'black', 'bumblebee',
        'business', 'cmyk', 'coffee', 'corporate', 'cupcake', 'cyberpunk', 'dim',
        'dracula', 'emerald', 'fantasy', 'forest', 'garden', 'halloween', 'lemonade',
        'lofi', 'luxury', 'night', 'nord', 'pastel', 'retro', 'sunset', 'synthwave',
        'valentine', 'wireframe', 'winter'
    ];

    const themeIcons = {
        light: 'sun',
        dark: 'moon',
        system: 'laptop',
        acid: 'flask-round',
        aqua: 'droplet',
        autumn: 'leaf',
        black: '',
        bumblebee: '',
        business: 'briefcase',
        cmyk: '',
        coffee: 'coffee',
        corporate: 'briefcase',
        cupcake: 'dessert',
        cyberpunk: 'gpu',
        dim: '',
        dracula: '',
        emerald: 'gem',
        fantasy: 'wand',
        forest: 'tree-deciduous',
        garden: 'flower',
        halloween: 'ghost',
        lemonade: 'citrus',
        lofi: 'audio-lines',
        luxury: 'diamond',
        night: 'moon-star',
        nord: '',
        pastel: '',
        retro: 'audio-waveform',
        sunset: 'sunset',
        synthwave: 'cpu',
        valentine: 'heart',
        wireframe: 'frame',
        winter: 'snowflake'
    };

    const themeLabels = {
        light: 'Light',
        dark: 'Dark',
        system: 'System',
        acid: 'Acid',
        aqua: 'Aqua',
        autumn: 'Autumn',
        black: 'Black',
        bumblebee: 'Bumblebee',
        business: 'Business',
        cmyk: 'CMYK',
        coffee: 'Coffee',
        corporate: 'Corporate',
        cupcake: 'Cupcake',
        cyberpunk: 'Cyberpunk',
        dim: 'Dim',
        dracula: 'Dracula',
        emerald: 'Emerald',
        fantasy: 'Fantasy',
        forest: 'Forest',
        garden: 'Garden',
        halloween: 'Halloween',
        lemonade: 'Lemonade',
        lofi: 'Lofi',
        luxury: 'Luxury',
        night: 'Night',
        nord: 'Nord',
        pastel: 'Pastel',
        retro: 'Retro',
        sunset: 'Sunset',
        synthwave: 'Synthwave',
        valentine: 'Valentine',
        wireframe: 'Wireframe',
        winter: 'Winter'
    };

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

    function updateCurrentThemeIcons(iconName) {
        document.querySelectorAll('.theme-current-icon').forEach(iconEl => {
            const classes = iconEl.getAttribute('class') || 'w-4 h-4 theme-current-icon';
            const finalClasses = classes.includes('theme-current-icon')
                ? classes
                : `${classes} theme-current-icon`;
            const placeholder = document.createElement('i');
            placeholder.setAttribute('data-lucide', iconName);
            placeholder.setAttribute('class', finalClasses);
            iconEl.replaceWith(placeholder);
        });
        window.lucide?.createIcons?.();
    }

    function toLabel(theme) {
        if (themeLabels[theme]) return themeLabels[theme];
        return theme.replace(/(^|[-_])([a-z])/g, (_, __, chr) => ` ${chr.toUpperCase()}`).trim();
    }

    function buildThemeMenus() {
        themeMenus.forEach(menu => {
            if (menu.dataset.themeMenu !== 'auto' || menu.children.length > 0) return;
            const fragment = document.createDocumentFragment();

            themeNames.forEach(theme => {
                const li = document.createElement('li');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.dataset.themeName = theme;
                btn.className = 'theme-toggle cursor-pointer flex items-center gap-3 w-full px-3 py-2 text-sm text-base-content rounded-md hover:bg-base-200 transition';

                const iconName = themeIcons[theme] || 'palette';
                btn.innerHTML = `<i data-lucide="${iconName}" class="w-5 h-5"></i><span>${toLabel(theme)}</span>`;
                li.appendChild(btn);
                fragment.appendChild(li);
            });

            menu.appendChild(fragment);
            window.lucide?.createIcons?.();
        });
    }

    function updateThemeButton(selectedTheme) {
        const effective = resolveEffective(selectedTheme);
        const iconKey = (selectedTheme === 'system' && SHOW_EFFECTIVE_ICON_FOR_SYSTEM) ? effective : selectedTheme;
        const label = toLabel(selectedTheme);
        const effLabel = selectedTheme === 'system' ? `${label} (${effective === 'dark' ? 'Dark' : 'Light'})` : label;

        themeButtons.forEach(button => {
            const themeIcon = button.querySelector('.theme-icon');
            if (themeIcon) {
                setLucideIcon(themeIcon, themeIcons[iconKey] || 'sun');
            }
            button.setAttribute('aria-label', `Current theme: ${effLabel}. Click to change.`);
            button.setAttribute('title', `Theme: ${effLabel}`);
        });

        document.querySelectorAll('.theme-current-label').forEach(labelEl => {
            labelEl.textContent = label;
        });

        updateCurrentThemeIcons(themeIcons[iconKey] || 'palette');
    }

    function updateActiveToggle(selected) {
        getThemeToggles().forEach(btn => {
            const isActive = btn.dataset.themeName === selected;
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            btn.classList.toggle('border', isActive);
            btn.classList.toggle('border-primary', isActive);
            btn.classList.toggle('bg-base-200', isActive);
        });
    }

    // init
    buildThemeMenus();
    applyTheme(savedTheme);
    updateThemeButton(savedTheme);
    updateActiveToggle(savedTheme);
    // Keep theme changes instant (no transition class added)

    // clicks
    themeMenus.forEach(menu => {
        menu.addEventListener('click', (e) => {
            const btn = e.target.closest('.theme-toggle');
            if (!btn) return;
            const theme = btn.dataset.themeName;
            localStorage.setItem('theme', theme);
            applyTheme(theme);
            updateThemeButton(theme);
            updateActiveToggle(theme);
            window.dispatchEvent(new CustomEvent('theme:change', { detail: { selected: theme, effective: resolveEffective(theme) } }));

            // Save to database if user is logged in
            saveThemeToServer(theme);
        });
    });

    /**
     * Save theme preference to server
     */
    async function saveThemeToServer(theme) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrfToken) return; // Not on a page with CSRF token (probably not logged in)

        try {
            const response = await fetch('/api/preferences/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ key: 'theme', value: theme }),
            });

            if (!response.ok) {
                console.warn('[Theme] Failed to save theme to server');
            }
        } catch (error) {
            // Silently fail - localStorage still works as fallback
            console.warn('[Theme] Could not save theme to server:', error.message);
        }
    }

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
