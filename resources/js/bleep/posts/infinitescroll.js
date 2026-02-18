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

        isLoading = true;
        loadingIndicator.classList.remove('hidden');

        // Hide load more button if it exists
        if (loadMoreBtn) {
            loadMoreBtn.classList.add('hidden');
        }

        try {
            const response = await fetch(`/bleeps/lazy-load?page=${currentPage}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            });

            if (!response.ok) {
                throw new Error('Failed to fetch bleeps');
            }

            const data = await response.json();

            if (data.success && data.html) {
                // Parse new HTML into a detached container
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data.html;

                // Collect element nodes and set up fade-in animation
                const newElements = Array.from(tempDiv.children);
                newElements.forEach(child => {
                    child.style.opacity = '0';
                    child.style.transform = 'translateY(12px)';
                    child.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                });

                // Initialize media/icons ONLY on new elements (scoped to tempDiv)
                // Done BEFORE inserting into visible DOM — prevents reprocessing existing bleeps
                if (window.loadNewMedia) {
                    window.loadNewMedia(tempDiv);
                }
                if (window.initVideoPlayers) {
                    window.initVideoPlayers(tempDiv);
                }
                if (window.lucide) {
                    window.lucide.createIcons({ nodes: tempDiv.querySelectorAll('[data-lucide]') });
                }

                // Lock current scroll position before DOM change
                const scrollBefore = window.scrollY;

                // Move all nodes into a fragment for single DOM insertion
                const fragment = document.createDocumentFragment();
                while (tempDiv.firstChild) {
                    fragment.appendChild(tempDiv.firstChild);
                }
                bleepsContainer.appendChild(fragment);

                // Restore scroll position to prevent any jump
                window.scrollTo(0, scrollBefore);

                // Fade in new elements with stagger
                requestAnimationFrame(() => {
                    newElements.forEach((el, i) => {
                        setTimeout(() => {
                            el.style.opacity = '1';
                            el.style.transform = 'translateY(0)';
                        }, i * 50);
                    });
                });

                hasMore = data.has_more;
                currentPage = data.next_page;
                trigger.dataset.page = currentPage;
                trigger.dataset.hasMore = hasMore;

                if (!hasMore) {
                    endOfContent.classList.remove('hidden');
                    triggerObserver.disconnect();
                    // Remove load more button when reaching end
                    if (loadMoreBtn) {
                        loadMoreBtn.remove();
                        loadMoreBtn = null;
                    }
                }
            }
        } catch (error) {
            console.error('Error loading more bleeps:', error);

            // Show error message
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

            if (window.lucide) {
                window.lucide.createIcons();
            }

            // Show "Load More" button on error as fallback
            if (!loadMoreBtn && hasMore) {
                loadMoreBtn = document.createElement('button');
                loadMoreBtn.id = 'load-more-btn';
                loadMoreBtn.className = 'btn btn-primary btn-block mt-4';
                loadMoreBtn.innerHTML = '<i data-lucide="refresh-cw" class="w-5 h-5 mr-2"></i> Try Again';
                loadMoreBtn.addEventListener('click', () => {
                    loadMoreBleeps();
                });
                trigger.parentElement.insertBefore(loadMoreBtn, trigger);

                if (window.lucide) {
                    window.lucide.createIcons();
                }
            } else if (loadMoreBtn && hasMore) {
                // Show existing button if hidden
                loadMoreBtn.classList.remove('hidden');
            }
        } finally {
            isLoading = false;
            loadingIndicator.classList.add('hidden');
        }
    }
});
