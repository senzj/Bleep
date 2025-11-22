document.addEventListener('DOMContentLoaded', () => {
    const tabButtons = document.querySelectorAll('[data-tab]');
    const feedPanel = document.getElementById('feed-panel'); // entire feed (form + bleeps)
    const mobilePeoplePanel = document.getElementById('mobile-people-panel');

    if (!tabButtons.length || !feedPanel || !mobilePeoplePanel) return;

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
            btn.setAttribute('aria-selected', String(isActive));
        });

        if (tabName === 'people') {
            feedPanel.classList.add('hidden');
            mobilePeoplePanel.classList.remove('hidden');
            mobilePeoplePanel.setAttribute('aria-hidden', 'false');

            // re-run lucide icon rendering if available
            if (window.lucide) {
                if (typeof window.lucide.replace === 'function') {
                    try { window.lucide.replace(); } catch (e) {}
                } else if (typeof window.lucide.createIcons === 'function') {
                    try { window.lucide.createIcons(); } catch (e) {}
                }
            }

            const input = mobilePeoplePanel.querySelector('#user-search-input');
            if (input) input.focus();
        } else {
            mobilePeoplePanel.classList.add('hidden');
            mobilePeoplePanel.setAttribute('aria-hidden', 'true');
            feedPanel.classList.remove('hidden');
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