// infinite scroll for bleeps cards on home page
document.addEventListener('DOMContentLoaded', function() {
    const bleepsContainer = document.getElementById('bleeps-container');
    const loadingIndicator = document.getElementById('loading-indicator');
    const endOfContent = document.getElementById('end-of-content');
    const trigger = document.getElementById('infinite-scroll-trigger');

    if (!bleepsContainer || !trigger) return;

    let isLoading = false;
    let currentPage = parseInt(trigger.dataset.page) || 2;
    let hasMore = trigger.dataset.hasMore === 'true';
    let loadMoreBtn = null; // Track button reference

    // Use IntersectionObserver on the trigger element instead of scroll percentage
    // This fires when the trigger element is about to enter the viewport
    const triggerObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !isLoading && hasMore) {
                loadMoreBleeps();
            }
        });
    }, {
        // Start loading when the trigger is within 600px of the viewport
        rootMargin: '0px 0px 1000px 0px',
        threshold: 0
    });

    triggerObserver.observe(trigger);

    async function loadMoreBleeps() {
        if (isLoading || !hasMore) return;
        if (bleepsContainer.classList.contains('hidden')) return;

        isLoading = true;
        loadingIndicator.classList.remove('hidden');
        if (loadMoreBtn) loadMoreBtn.classList.add('hidden');

        try {
            const response = await fetch(`/bleeps/lazy-load?page=${currentPage}&tab=bleep`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            });

            if (!response.ok) throw new Error('Failed to fetch bleeps');

            const data = await response.json();

            if (data.success && data.html) {
                // Parse HTML off the critical path
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data.html;

                const newElements = Array.from(tempDiv.children);

                // Set initial hidden state before insert
                newElements.forEach(child => {
                    child.style.opacity = '0';
                    child.style.transform = 'translateY(12px)';
                    child.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                });

                // Init media/nsfw BEFORE inserting (same as before)
                if (window.loadNewMedia) window.loadNewMedia(tempDiv);
                if (window.initVideoPlayers) window.initVideoPlayers(tempDiv);
                if (window.initializeNsfwWrappers) window.initializeNsfwWrappers(tempDiv);

                // Single DOM insertion
                const scrollBefore = window.scrollY;
                const fragment = document.createDocumentFragment();
                while (tempDiv.firstChild) fragment.appendChild(tempDiv.firstChild);
                bleepsContainer.appendChild(fragment);
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
                            newElements.forEach(el => window.createLucideIcons(el));
                        }, 100);
                    }
                }

                hasMore = data.has_more;
                currentPage = data.next_page;
                trigger.dataset.page = currentPage;
                trigger.dataset.hasMore = hasMore;

                if (!hasMore) {
                    endOfContent.classList.remove('hidden');
                    triggerObserver.disconnect();
                    if (loadMoreBtn) { loadMoreBtn.remove(); loadMoreBtn = null; }
                }
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
            bleepsContainer.appendChild(errorDiv);

            if (!loadMoreBtn && hasMore) {
                loadMoreBtn = document.createElement('button');
                loadMoreBtn.id = 'load-more-btn';
                loadMoreBtn.className = 'btn btn-primary btn-block mt-4';
                loadMoreBtn.innerHTML = '<i data-lucide="refresh-cw" class="w-5 h-5 mr-2"></i> Try Again';
                loadMoreBtn.addEventListener('click', () => loadMoreBleeps());
                trigger.parentElement.insertBefore(loadMoreBtn, trigger);
            } else if (loadMoreBtn && hasMore) {
                loadMoreBtn.classList.remove('hidden');
            }

            if (window.lucide) window.lucide.createIcons();
        } finally {
            isLoading = false;
            loadingIndicator.classList.add('hidden');
        }
    }
});
