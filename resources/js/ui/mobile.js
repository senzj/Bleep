document.addEventListener('DOMContentLoaded', () => {
    const tabButtons = document.querySelectorAll('[data-tab]');
    const feedPanel = document.getElementById('feed-panel'); // entire feed (form + bleeps)
    const mobilePanel = document.getElementById('mobile-panel');
    const tabPanels = document.querySelectorAll('[data-tab-panel]');

    if (!tabButtons.length || !feedPanel) return;

    function normalizeTab(name) {
        // accept synonyms (feed <-> bleep) to avoid mismatches
        if (!name) return 'bleep';
        return name === 'feed' ? 'bleep' : name;
    }

    function setActiveTab(name) {
        const tabName = normalizeTab(name);

        tabButtons.forEach(btn => {
            const isActive = normalizeTab(btn.dataset.tab) === tabName;
            btn.classList.toggle('data-tab-active', isActive);
            btn.classList.toggle('btn-active', isActive);
            btn.classList.toggle('bg-primary', isActive);
            btn.classList.toggle('text-primary-content', isActive);
            btn.setAttribute('aria-selected', String(isActive));
        });

        if (tabName === 'people') {
            feedPanel.classList.add('hidden');
            if (mobilePanel) {
                mobilePanel.classList.remove('hidden');
                mobilePanel.setAttribute('aria-hidden', 'false');
            }

            // re-run lucide icon rendering if available
            if (window.lucide) {
                if (typeof window.lucide.replace === 'function') {
                    try { window.lucide.replace(); } catch (e) {}
                } else if (typeof window.lucide.createIcons === 'function') {
                    try { window.lucide.createIcons(); } catch (e) {}
                }
            }

            const input = mobilePanel?.querySelector('#user-search-input');
            if (input) input.focus();
        } else {
            if (mobilePanel) {
                mobilePanel.classList.add('hidden');
                mobilePanel.setAttribute('aria-hidden', 'true');
            }
            feedPanel.classList.remove('hidden');

            tabPanels.forEach(panel => {
                const isActive = normalizeTab(panel.dataset.tabPanel) === tabName;
                panel.classList.toggle('hidden', !isActive);
                panel.setAttribute('aria-hidden', String(!isActive));
            });

            // Re-initialize NSFW handlers when switching tabs
            if (window.initializeNsfwWrappers) {
                // Find the currently active panel and initialize its NSFW content
                document.querySelectorAll('[data-tab-panel]:not(.hidden)').forEach(panel => {
                    window.initializeNsfwWrappers(panel);
                });
            }
        }
    }

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            setActiveTab(btn.dataset.tab);
        });
    });

    // default state: show the feed/bleep tab
    setActiveTab('bleep');
});
