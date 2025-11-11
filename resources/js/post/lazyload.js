(function () {
    const container = document.getElementById('comments-container');
    if (!container) return;

    const sentinel = document.getElementById('comments-sentinel');
    const btn = document.getElementById('load-more-comments'); // optional fallback if present
    const baseUrl = container.dataset.loadMoreUrl;

    let nextPage = parseInt(container.dataset.nextPage || '2', 10);
    let hasMore = container.dataset.hasMore !== '0';
    let loading = false;

    function getExistingDateSet() {
        const set = new Set();
        container.querySelectorAll('.comment-date-header').forEach(h => {
            const d = h.getAttribute('data-date');
            if (d) set.add(d);
        });
        return set;
    }

    function buildFragmentMergingDates(html) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;

        const existingDates = getExistingDateSet();
        wrapper.querySelectorAll('.comment-date-header').forEach(h => {
            const d = h.getAttribute('data-date');
            if (d && existingDates.has(d)) {
                // Remove duplicate headers; keep their comments so they fall under the existing header.
                h.remove();
            }
        });

        return wrapper;
    }

    async function loadMore() {
        if (loading || !hasMore) return;
        loading = true;
        if (btn) { btn.textContent = 'Loading...'; btn.dataset.loading = '1'; }

        try {
            const res = await fetch(baseUrl + '?page=' + nextPage, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            const wrapper = buildFragmentMergingDates(data.html);
            while (wrapper.firstChild) {
                container.appendChild(wrapper.firstChild);
            }

            // Re-initialize Lucide icons for newly appended elements
            if (window.createLucideIcons) {
                window.createLucideIcons();
            } else if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }

            nextPage = data.next_page;
            hasMore = !!data.has_more;
            container.dataset.nextPage = String(nextPage);
            container.dataset.hasMore = hasMore ? '1' : '0';

            if (!hasMore) {
                if (sentinel) sentinel.remove();
                if (btn) btn.remove();
            } else if (btn) {
                btn.textContent = 'Load more';
                btn.dataset.loading = '0';
            }
        } catch (e) {
            console.error(e);
            if (btn) { btn.textContent = 'Error. Retry'; btn.dataset.loading = '0'; }
        } finally {
            loading = false;
        }
    }

    // Auto-fetch on scroll using IntersectionObserver
    if (sentinel && 'IntersectionObserver' in window && hasMore) {
        const io = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) loadMore();
            }
        }, { root: null, rootMargin: '400px 0px', threshold: 0 });

        io.observe(sentinel);
    }

    // Fallback: if button exists, clicking still works
    if (btn) {
        btn.addEventListener('click', loadMore);
    }
})();
