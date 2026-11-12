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

    // A rotation of playful endings. Clicking the line picks another one.
    const END_MESSAGES = [
        'The cake is a lie. But the end is real.',
        'You have reached the edge of the feed. It is cozy here.',
        'No more bleeps. Go make a cup of tea.',
        'That is all, folks. The bleeps have left the building.',
        'You scrolled bravely. The feed salutes you.',
        'End of the line. Mind the gap.',
        'Nothing left to see here. Go outside and touch some grass.',
        'The feed ends here. Your productivity begins now.',
        'All caught up. The internet will make more, unfortunately.',
        'You have unlocked the ancient art of stopping.',
        'Fin. Roll credits.',
        'The bleeps have gone to sleep. Shhh.',
        'That is a wrap. No post-credits scene, we checked.',
        'The end is near. Actually, it is exactly here.',
        'You reached the bottom. The bottom appreciates it.',
        'No bleeps remain. Only vibes.',
        'The feed is empty. The void says hello.',
        'This is the last bleep. Cherish it.',
        'You have seen everything. Everything is tired now.',
        'Achievement unlocked: Touched Grass (Pending).',
        'Feed complete. Please rate your experience in the void.',
        'The scroll ends. The snacks begin.',
        'No more posts. Have you considered a nap?',
        'That was the whole feed. It was a good feed.',
        'End of feed. The plot twist was friendship.',
        'The bleeps are done bleeping.',
        'Nothing to see past this point except this lovely message.',
        'You have reached the final boss: the end of the feed.',
        'The abyss stared back, then went to make dinner.',
        'Feed over. The real bleeps were the friends we made along the way.',
    ];

    const endMessageEl = endOfContent?.querySelector('[data-end-message]');
    let lastEndMessageIndex = -1;

    const pickEndMessage = () => {
        if (!endMessageEl) return;

        let index = lastEndMessageIndex;

        while (END_MESSAGES.length > 1 && index === lastEndMessageIndex) {
            index = Math.floor(Math.random() * END_MESSAGES.length);
        }

        lastEndMessageIndex = index;
        endMessageEl.textContent = END_MESSAGES[index];
    };

    endMessageEl?.addEventListener('click', pickEndMessage);

    const syncEndState = () => {
        if (!endOfContent) return;

        const panel = getActivePanel();
        const atEnd = Boolean(panel) && hasLoadedMore(panel) && !hasMore(panel);
        const wasHidden = endOfContent.classList.contains('hidden');

        endOfContent.classList.toggle('hidden', !atEnd);

        if (atEnd && wasHidden) {
            pickEndMessage();
        }
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

    // Panels for the Following/Friends tabs are rendered on first visit. Until
    // then they only contain a loading placeholder, so the first page has to be
    // fetched with page=1 (infinite scroll normally continues from page 2).
    const loadFeedPanel = async (panel) => {
        if (!panel || panel.dataset.tabLazy !== 'true') {
            return;
        }

        panel.dataset.tabLazy = 'loading';
        panel.dataset.page = '1';
        panel.dataset.hasMore = 'true';

        await loadMoreBleeps(panel, true);

        if (panel.dataset.tabLazy === 'loading') {
            panel.dataset.tabLazy = 'done';
        }

        syncEndState();
    };

    window.loadFeedPanel = loadFeedPanel;

    document.addEventListener('bleep:tab-changed', () => {
        syncEndState();

        const panel = getActivePanel();
        if (!panel) return;

        // A deferred tab needs its first page before infinite scroll applies.
        // The IntersectionObserver below handles loading more when the trigger
        // is actually near the viewport, so nothing is fetched on tab switch
        // unless it becomes visible.
        if (panel.dataset.tabLazy && panel.dataset.tabLazy !== 'done') {
            if (panel.dataset.tabLazy === 'true') {
                loadFeedPanel(panel);
            }
        }
    });

    async function loadMoreBleeps(panelOverride = null, isFirstTabPage = false) {
        const panel = panelOverride || getActivePanel();
        if (!panel) return;

        if (!isFirstTabPage) {
            // Deferred tabs always start at page 1.
            if (panel.dataset.tabLazy === 'true') {
                return loadFeedPanel(panel);
            }
            if (panel.dataset.tabLazy === 'loading') {
                return;
            }
        }

        const tab = getTab(panel);
        if (loadingByTab[tab] || !hasMore(panel)) return;

        loadingByTab[tab] = true;

        // First-page tab loads already show the panel's own hero placeholder.
        // Showing the global "loading more" indicator at the same time made two
        // loaders appear on tab switch.
        if (!isFirstTabPage && !panel.querySelector('[data-tab-loading]')) {
            setLoading(true);
        }

        loadMoreBtn?.classList.add('hidden');

        const finishFirstLoad = (isEmpty) => {
            panel.querySelector('[data-tab-loading]')?.remove();

            const empty = panel.querySelector('[data-tab-empty]');
            if (empty) {
                empty.classList.toggle('hidden', !isEmpty);
            }
        };

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
                finishFirstLoad(true);
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

            finishFirstLoad(false);

            // Fade in immediately — don't wait for icons
            requestAnimationFrame(() => {
                newElements.forEach((el, i) => {
                    setTimeout(() => {
                        el.style.opacity = '1';
                        el.style.transform = 'translateY(0)';
                    }, i * 50);
                });
            });

            // ── Render Lucide icons immediately after insertion ──
            // The shared renderer already batches icons across animation
            // frames. Wrapping it in requestIdleCallback (and only three cards
            // at a time) made newly fetched bleeps show empty icon slots for
            // up to two seconds.
            if (window.renderLucideIcons) {
                window.renderLucideIcons(panel);
            } else if (window.createLucideIcons) {
                window.createLucideIcons(panel);
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

            if (window.createLucideIcons) {
                window.createLucideIcons(panel);
            } else if (window.lucide) {
                window.lucide.createIcons();
            }
        } finally {
            loadingByTab[tab] = false;
            setLoading(Object.values(loadingByTab).some(Boolean));
        }
    }
});
