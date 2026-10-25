// Infinite scroll for the home feed, scoped to the active tab panel.
document.addEventListener('DOMContentLoaded', function () {
    const feedPanel = document.getElementById('feed-panel');
    const loadingIndicator = document.getElementById('loading-indicator');
    const endOfContent = document.getElementById('end-of-content');
    const trigger = document.getElementById('infinite-scroll-trigger');
    const panels = Array.from(document.querySelectorAll('[data-tab-panel]'));

    if (!trigger || !panels.length) return;

    // Per-tab loading flags so a slow tab cannot block another one.
    const loadingByTab = {};
    let loadMoreBtn = null;

    const getActivePanel = () => {
        if (feedPanel && feedPanel.classList.contains('hidden')) return null;

        return panels.find((panel) => !panel.classList.contains('hidden')) || null;
    };

    const getTab = (panel) => panel.dataset.tabPanel || 'bleep';
    const getPage = (panel) => parseInt(panel.dataset.page || '2', 10) || 2;
    const hasMore = (panel) => panel.dataset.hasMore === 'true';

    // Only announce the end once the user has actually paged through the feed
    // (showing it on first paint would duplicate the "no posts" empty states).
    const hasLoadedMore = (panel) => panel.dataset.loaded === 'true';

    const syncEndState = () => {
        if (!endOfContent) return;

        const panel = getActivePanel();
        const atEnd = Boolean(panel) && hasLoadedMore(panel) && !hasMore(panel);
        endOfContent.classList.toggle('hidden', !atEnd);
    };

    const setLoading = (isLoading) => {
        loadingIndicator?.classList.toggle('hidden', !isLoading);
    };

    const triggerObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                loadMoreBleeps();
            }
        });
    }, {
        // Start loading when the trigger is within 1000px of the viewport
        rootMargin: '0px 0px 1000px 0px',
        threshold: 0,
    });

    triggerObserver.observe(trigger);

    syncEndState();

    document.addEventListener('bleep:tab-changed', () => {
        syncEndState();

        // The newly shown tab may already be near the viewport bottom.
        requestAnimationFrame(() => {
            const panel = getActivePanel();
            if (panel && hasMore(panel) && !loadingByTab[getTab(panel)]) {
                loadMoreBleeps();
            }
        });
    });

    async function loadMoreBleeps() {
        const panel = getActivePanel();
        if (!panel) return;

        const tab = getTab(panel);
        if (loadingByTab[tab] || !hasMore(panel)) return;

        loadingByTab[tab] = true;
        setLoading(true);
        loadMoreBtn?.classList.add('hidden');

        try {
            const response = await fetch(
                `/bleeps/lazy-load?page=${getPage(panel)}&tab=${encodeURIComponent(tab)}`,
                {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                }
            );

            if (!response.ok) throw new Error('Failed to fetch bleeps');

            const data = await response.json();

            if (!data.success) return;

            // An empty page still advances the paging state so we don't keep
            // requesting the same out-of-range page.
            if (!data.html) {
                panel.dataset.hasMore = data.has_more ? 'true' : 'false';
                panel.dataset.page = String(data.next_page || (getPage(panel) + 1));
                panel.dataset.loaded = 'true';
                syncEndState();

                return;
            }

            // Parse HTML off the critical path
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = data.html;

            const newElements = Array.from(tempDiv.children);

            // Set initial hidden state before insert
            newElements.forEach((child) => {
                child.style.opacity = '0';
                child.style.transform = 'translateY(12px)';
                child.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            });

            // Init media/nsfw BEFORE inserting
            if (window.loadNewMedia) window.loadNewMedia(tempDiv);
            if (window.initVideoPlayers) window.initVideoPlayers(tempDiv);
            if (window.initializeNsfwWrappers) window.initializeNsfwWrappers(tempDiv);

            // Single DOM insertion
            const scrollBefore = window.scrollY;
            const fragment = document.createDocumentFragment();
            while (tempDiv.firstChild) fragment.appendChild(tempDiv.firstChild);
            panel.appendChild(fragment);
            window.scrollTo(0, scrollBefore);

            // Fade in immediately — don't wait for icons
            requestAnimationFrame(() => {
                newElements.forEach((el, i) => {
                    setTimeout(() => {
                        el.style.opacity = '1';
                        el.style.transform = 'translateY(0)';
                    }, i * 50);
                });
            });

            // ── Render Lucide icons in small chunks during idle time ──
            // Prevents blocking the main thread for 300ms+ all at once
            if (window.createLucideIcons) {
                const chunkSize = 3; // process 3 cards at a time
                let index = 0;

                function processNextChunk(deadline) {
                    while (index < newElements.length) {
                        // Stop if browser needs the main thread back
                        if (deadline && deadline.timeRemaining() < 5) {
                            requestIdleCallback(processNextChunk);
                            return;
                        }
                        const end = Math.min(index + chunkSize, newElements.length);
                        for (let i = index; i < end; i++) {
                            window.createLucideIcons(newElements[i]);
                        }
                        index = end;
                    }
                }

                if ('requestIdleCallback' in window) {
                    requestIdleCallback(processNextChunk, { timeout: 2000 });
                } else {
                    // Safari fallback — small setTimeout yields to browser
                    setTimeout(() => {
                        newElements.forEach((el) => window.createLucideIcons(el));
                    }, 100);
                }
            }

            panel.dataset.hasMore = data.has_more ? 'true' : 'false';
            panel.dataset.page = String(data.next_page || (getPage(panel) + 1));
            panel.dataset.loaded = 'true';

            syncEndState();

            if (!data.has_more) {
                loadMoreBtn?.remove();
                loadMoreBtn = null;
            }
        } catch (error) {
            console.error('Error loading more bleeps:', error);

            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error shadow-lg my-4';
            errorDiv.innerHTML = `
                <div>
                    <i data-lucide="alert-circle" class="w-6 h-6"></i>
                    <span>Failed to load more bleeps. Please try again.</span>
                </div>
                <button class="btn btn-sm" onclick="this.parentElement.remove()">Dismiss</button>
            `;
            panel.appendChild(errorDiv);

            if (!loadMoreBtn) {
                loadMoreBtn = document.createElement('button');
                loadMoreBtn.id = 'load-more-btn';
                loadMoreBtn.className = 'btn btn-primary btn-block mt-4';
                loadMoreBtn.innerHTML = '<i data-lucide="refresh-cw" class="w-5 h-5 mr-2"></i> Try Again';
                loadMoreBtn.addEventListener('click', () => loadMoreBleeps());
                trigger.parentElement.insertBefore(loadMoreBtn, trigger);
            } else {
                loadMoreBtn.classList.remove('hidden');
            }

            if (window.lucide) window.lucide.createIcons();
        } finally {
            loadingByTab[tab] = false;
            setLoading(Object.values(loadingByTab).some(Boolean));
        }
    }
});
