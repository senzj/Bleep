document.addEventListener('DOMContentLoaded', () => {
    const bleepsList = document.getElementById('bleeps-list');
    const repostsList = document.getElementById('reposts-list');
    const bleepsBtn = document.getElementById('bleeps-load-more');
    const repostsBtn = document.getElementById('reposts-load-more');
    const bleepsSentinel = document.getElementById('bleeps-sentinel');
    const repostsSentinel = document.getElementById('reposts-sentinel');

    if (!bleepsList || !repostsList) return;

    // Determine active tab from Alpine.js
    const getActiveTab = () => {
        const tabPanel = document.querySelector('[role="tabpanel"]');
        if (!tabPanel) return null;
        const ariaLabel = tabPanel.getAttribute('aria-labelledby');
        if (ariaLabel && ariaLabel.includes('bleeps')) return 'bleeps';
        if (ariaLabel && ariaLabel.includes('reposts')) return 'reposts';
        return null;
    };

    async function loadMore(listEl, type) {
        const next = listEl.dataset.nextUrl;
        if (!next || listEl.dataset.loading === '1') return;
        listEl.dataset.loading = '1';

        try {
            const res = await fetch(next, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            if (!res.ok) throw new Error('Bad response');
            const data = await res.json();

            const tmp = document.createElement('div');
            tmp.innerHTML = data.html;
            while (tmp.firstChild) listEl.appendChild(tmp.firstChild);

            listEl.dataset.nextUrl = data.next_page_url || '';
            const btn = type === 'bleeps' ? bleepsBtn : repostsBtn;
            btn && btn.classList.toggle('hidden', !listEl.dataset.nextUrl);

            if (window.lucide) window.lucide.createIcons();
            window.dispatchEvent(new Event('content-appended'));
        } catch (e) {
            console.warn('Load more error:', e);
        } finally {
            listEl.dataset.loading = '0';
        }
    }

    const bleepsObserver = new IntersectionObserver((entries) => {
        const activeTab = getActiveTab();
        if (entries.some(e => e.isIntersecting) && activeTab === 'bleeps') {
            loadMore(bleepsList, 'bleeps');
        }
    }, { rootMargin: '600px' });

    const repostsObserver = new IntersectionObserver((entries) => {
        const activeTab = getActiveTab();
        if (entries.some(e => e.isIntersecting) && activeTab === 'reposts') {
            loadMore(repostsList, 'reposts');
        }
    }, { rootMargin: '600px' });

    bleepsSentinel && bleepsObserver.observe(bleepsSentinel);
    repostsSentinel && repostsObserver.observe(repostsSentinel);

    bleepsBtn?.addEventListener('click', () => loadMore(bleepsList, 'bleeps'));
    repostsBtn?.addEventListener('click', () => loadMore(repostsList, 'reposts'));

    // On tab change: reinitialize icons, update button visibility
    const onTabChanged = () => {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
        bleepsBtn?.classList.toggle('hidden', !bleepsList.dataset.nextUrl);
        repostsBtn?.classList.toggle('hidden', !repostsList.dataset.nextUrl);
    };

    window.addEventListener('tab-changed', onTabChanged);
    onTabChanged();
});
