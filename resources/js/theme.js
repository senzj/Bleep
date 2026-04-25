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

        black: 'square',
        bumblebee: 'bug',

        business: 'briefcase',
        cmyk: 'palette',

        coffee: 'coffee',
        corporate: 'briefcase',
        cupcake: 'dessert',
        cyberpunk: 'gpu',

        dim: 'sun-dim',
        dracula: 'moon',

        emerald: 'gem',
        fantasy: 'wand',
        forest: 'tree-deciduous',
        garden: 'flower',
        halloween: 'ghost',
        lemonade: 'citrus',
        lofi: 'audio-lines',

        luxury: 'diamond',
        night: 'moon-star',

        nord: 'snowflake',
        pastel: 'paintbrush',

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
            if (menu.dataset.themeMenu !== 'auto') return;
            menu.innerHTML = '';

            const fragment = document.createDocumentFragment();

            themeNames.forEach(theme => {
                const icon  = themeIcons[theme];
                const label = toLabel(theme);

                const li = document.createElement('li');
                li.innerHTML = `
                    <button type="button"
                        data-theme-name="${theme}"
                        class="theme-toggle relative w-full rounded-xl overflow-hidden border-2 border-transparent hover:border-primary transition-all focus:outline-none hover:cursor-pointer">

                        <span data-theme="${theme}" class="flex w-full h-10">
                            <span class="flex-1 bg-primary"></span>
                            <span class="flex-1 bg-secondary"></span>
                            <span class="flex-1 bg-accent"></span>
                            <span class="flex-1 bg-neutral"></span>
                        </span>

                        <span data-theme="${theme}" class="flex items-center gap-1.5 px-2 py-2 bg-base-100">
                            ${icon ? `<i data-lucide="${icon}" class="w-3.5 h-3.5 shrink-0 text-base-content/60"></i>` : ''}
                            <span class="text-xs font-medium text-base-content truncate">${label}</span>
                        </span>

                        <span class="theme-check hidden absolute top-1.5 right-1.5 w-5 h-5 rounded-full bg-primary flex items-center justify-center">
                            <i data-lucide="check" class="w-3 h-3 text-white"></i>
                        </span>
                    </button>
                `;

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
            btn.classList.toggle('border-primary', isActive);
            btn.classList.toggle('border-transparent', !isActive);
            btn.querySelector('.theme-check')?.classList.toggle('hidden', !isActive);
        });
    }

    // Sync the collapsed palette preview's data-theme so DaisyUI colors update
    function updateCollapsedPreview(theme) {
        const effective = resolveEffective(theme);
        document.querySelector('.theme-palette-preview')
            ?.setAttribute('data-theme', effective);
    }

    // Hide/show the collapsed preview based on open/closed state
    const themeCollapseInput = document.getElementById('theme-collapse');
    if (themeCollapseInput) {
        themeCollapseInput.addEventListener('change', () => {
            const preview = document.getElementById('theme-collapsed-preview');
            if (preview) preview.style.opacity = themeCollapseInput.checked ? '0' : '1';
        });
    }

    // init
    buildThemeMenus();
    applyTheme(savedTheme);
    updateThemeButton(savedTheme);
    updateActiveToggle(savedTheme);
    updateCollapsedPreview(savedTheme);
    // Keep theme changes instant (no transition class added)

    // click listener
    themeMenus.forEach(menu => {
        menu.addEventListener('click', (e) => {
            const btn = e.target.closest('.theme-toggle');
            if (!btn) return;
            const theme = btn.dataset.themeName;
            localStorage.setItem('theme', theme);
            applyTheme(theme);
            updateThemeButton(theme);
            updateActiveToggle(theme);
            updateCollapsedPreview(theme);
            window.preferencesManager?.showToast(true, 'Theme saved!');
            window.dispatchEvent(new CustomEvent('theme:change', { detail: { selected: theme, effective: resolveEffective(theme) } }));
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
