// Add this to your main JavaScript file
document.addEventListener('DOMContentLoaded', function() {
    const themeToggles = document.querySelectorAll('.theme-toggle');
    const themeIcon = document.querySelector('.theme-icon');

    // map theme -> lucide icon (choose icons available in lucide)
    const themeIcons = {
        'light': 'sun',
        'dark': 'moon',
        'system': 'laptop',
        'lofi': 'star'
    };

    // prefer saved theme, otherwise use current document theme (set in layout) or fallback to 'system'
    const savedTheme = localStorage.getItem('theme') || document.documentElement.getAttribute('data-theme') || 'system';

    // resolve 'system' into an actual theme name used by daisyUI (light/dark)
    function resolveEffective(theme) {
        if (theme === 'system') {
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            return prefersDark ? 'dark' : 'light';
        }
        return theme;
    }

    // apply saved theme to document (if system -> set to resolved light/dark)
    function applyTheme(theme) {
        if (theme === 'system') {
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
        } else {
            document.documentElement.setAttribute('data-theme', theme);
        }
    }

    // update small navbar icon using the effective theme (so system shows sun/moon)
    function updateThemeButton(effectiveTheme) {
        if (!themeIcon) return;
        const icon = themeIcons[effectiveTheme] || themeIcons['light'];
        themeIcon.setAttribute('data-lucide', icon);
        if (window.lucide) window.lucide.createIcons();
    }

    // highlight the saved selection button in the dropdown
    function updateActiveToggle(saved) {
        themeToggles.forEach(btn => {
            if (btn.getAttribute('data-theme') === saved) {
                btn.classList.add('border-primary', 'bg-base-200');
            } else {
                btn.classList.remove('border-primary', 'bg-base-200');
            }
        });
    }

    // initialize
    const effective = resolveEffective(savedTheme);
    applyTheme(savedTheme);
    updateThemeButton(effective);
    updateActiveToggle(savedTheme);

    // click handlers
    themeToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const theme = this.getAttribute('data-theme');
            localStorage.setItem('theme', theme);
            applyTheme(theme);

            const eff = resolveEffective(theme);
            updateThemeButton(eff);
            updateActiveToggle(theme);
        });
    });

    // watch OS preference changes (update effective theme if user selected 'system')
    if (window.matchMedia) {
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => {
            const current = localStorage.getItem('theme') || document.documentElement.getAttribute('data-theme') || 'system';
            if (current === 'system') {
                applyTheme('system');
                updateThemeButton(resolveEffective('system'));
            }
        };
        if (mq.addEventListener) {
            mq.addEventListener('change', onChange);
        } else if (mq.addListener) {
            mq.addListener(onChange);
        }
    }
});
