document.addEventListener('DOMContentLoaded', () => {
    const prefs = {
        showNsfw: document.body?.dataset?.showNsfw === '1',
        blurNsfw: document.body?.dataset?.blurNsfw !== '0',
        autoplayVideos: document.body?.dataset?.autoplayVideos !== '0',
    };

    function setSrcForDeferred(container) {
        // Handle both data-media-src and data-src attributes
        container.querySelectorAll('[data-media-src], [data-src]').forEach(el => {
            const tag = el.tagName.toLowerCase();
            const src = el.getAttribute('data-media-src') || el.getAttribute('data-src');
            if (!src) return;
            if (tag === 'img') el.setAttribute('src', src);
            if (tag === 'source') {
                const parentVideo = el.closest('video');

                // Only set src if not already set (to avoid refetching)
                if (!el.src || el.src !== src) {
                    el.setAttribute('src', src);
                    const mime = el.getAttribute('data-media-mime') || el.getAttribute('type');
                    if (mime) el.setAttribute('type', mime);

                    if (parentVideo) {
                        try {
                            parentVideo.load();
                            // Autoplay video after it's loaded (muted to respect browser policies)
                            if (prefs.autoplayVideos) {
                                parentVideo.addEventListener('loadedmetadata', () => {
                                    if (parentVideo.muted) {
                                        parentVideo.play().catch(() => {});
                                    }
                                }, { once: true });
                            }
                        } catch (e) {}
                    }
                } else if (parentVideo && parentVideo.muted && prefs.autoplayVideos) {
                    // Source already loaded — just autoplay if needed
                    parentVideo.play().catch(() => {});
                }
            }
            if (tag === 'video') {
                // Only set src if not already set
                if (!el.src || el.src !== src) {
                    el.setAttribute('src', src);
                    try {
                        el.load();
                        // Autoplay after loaded
                        if (prefs.autoplayVideos) {
                            el.addEventListener('loadedmetadata', () => {
                                if (el.muted) {
                                    el.play().catch(() => {});
                                }
                            }, { once: true });
                        }
                    } catch (e) {}
                } else if (el.muted && prefs.autoplayVideos) {
                    // Video already loaded — just autoplay if needed
                    el.play().catch(() => {});
                }
            }
        });
    }

    function clearDeferred(container) {
        // Pause all videos in the container (but keep sources cached for reuse)
        container.querySelectorAll('video').forEach(video => {
            try {
                video.pause();
                video.currentTime = 0;
            } catch(e) {}
            // Keep src attributes so video stays in browser cache — don't refetch on re-reveal
            // Remove initialization marker so video can be re-initialized when revealed again
            video.removeAttribute('data-volume-initialized');
        });

        // Clear image sources (images don't cache the same way as videos)
        container.querySelectorAll('img[data-media-src], img[data-src]').forEach(el => {
            el.removeAttribute('src');
        });
    }

    function revealNsfw(bleepId) {
        if (!bleepId) return;
        if (!prefs.showNsfw) return;
        const wrapper = document.querySelector(`.nsfw-wrapper[data-bleep-id="${bleepId}"], .bleep-nsfw-wrapper[data-bleep-id="${bleepId}"]`);
        if (!wrapper) {
            console.error('NSFW wrapper not found for bleepId:', bleepId);
            return;
        }

        // console.log('Revealing NSFW content for bleep:', bleepId);

        try { sessionStorage.setItem(`nsfw_viewed_${bleepId}`, '1'); } catch (e) {}

        // Find elements within the wrapper (not by data-bleep-id)
        const content = wrapper.querySelector('.nsfw-content');
        const placeholder = wrapper.querySelector('.nsfw-placeholder');
        const normalContent = wrapper.querySelector('.normal-bleep-content');

        if (content) {
            const txt = content.getAttribute('data-bleep-message') || '';
            const msg = content.querySelector('.nsfw-message');
            if (msg) msg.textContent = txt;
            content.classList.remove('hidden');
            setSrcForDeferred(content);

            // Re-initialize video players for videos in revealed content
            // This ensures they get added back to the intersection observer for autoplay
            if (window.initVideoPlayers) {
                window.initVideoPlayers(content);
            }

            // Add red border to indicate NSFW content
            content.classList.add('border-2', 'border-red-500/20', 'rounded-lg', 'p-1');
        } else {
            console.error('NSFW content container not found');
        }

        // Hide normal content and placeholder when revealing NSFW
        if (normalContent) normalContent.classList.add('hidden');
        if (placeholder) placeholder.classList.add('hidden');

        wrapper.dataset.revealed = '1';

        // Re-render Lucide icons if they exist in the revealed content
        if (window.createLucideIcons) window.createLucideIcons(content);
    }

    function hideNsfw(bleepId) {
        if (!bleepId) return;
        const wrapper = document.querySelector(`.nsfw-wrapper[data-bleep-id="${bleepId}"], .bleep-nsfw-wrapper[data-bleep-id="${bleepId}"]`);
        if (!wrapper) return;

        try { sessionStorage.removeItem(`nsfw_viewed_${bleepId}`); } catch (e) {}

        // Find elements within the wrapper
        const content = wrapper.querySelector('.nsfw-content');
        const placeholder = wrapper.querySelector('.nsfw-placeholder');
        const normalContent = wrapper.querySelector('.normal-bleep-content');

        if (content) {
            const msg = content.querySelector('.nsfw-message');
            if (msg) msg.textContent = '';
            content.classList.add('hidden');

            // Remove red border
            content.classList.remove('border-2', 'border-red-500/20', 'rounded-lg', 'p-1');

            clearDeferred(content);
        }

        // Don't show normal content when hiding NSFW - only show placeholder
        if (normalContent) normalContent.classList.add('hidden');
        if (placeholder) placeholder.classList.remove('hidden');

        delete wrapper.dataset.revealed;
    }

    // expose helper so edit.js can call it to apply live update changes without duplicating logic
    window.applyLiveBleepUpdate = function (b) {
        const wrapper = document.querySelector(`.nsfw-wrapper[data-bleep-id="${b.id}"], .bleep-nsfw-wrapper[data-bleep-id="${b.id}"]`);
        if (!wrapper) return;

        const isNsfw = !!b.is_nsfw;
        wrapper.setAttribute('data-is-nsfw', isNsfw ? '1' : '0');

        const placeholder = wrapper.querySelector('.nsfw-placeholder');
        const content = wrapper.querySelector('.nsfw-content');
        const normalContent = wrapper.querySelector('.normal-bleep-content');
        const plainMsg = normalContent?.querySelector('.bleep-message');
        const gallery = normalContent?.querySelector('.bleep-media-gallery, [data-bleep-media]');

        if (isNsfw) {
            if (placeholder) placeholder.classList.remove('hidden');
            if (content) content.classList.add('hidden');
            if (normalContent) normalContent.classList.add('hidden');
            if (content) clearDeferred(content);
        } else {
            if (placeholder) placeholder.classList.add('hidden');
            if (content) {
                const msgN = content.querySelector('.nsfw-message');
                if (msgN) msgN.textContent = '';
                content.classList.add('hidden');
                clearDeferred(content);
            }
            if (normalContent) normalContent.classList.remove('hidden');
            if (plainMsg) plainMsg.textContent = b.message;
            if (gallery) {
                gallery.querySelectorAll('[data-media-src]').forEach(el => {
                    const src = el.getAttribute('data-media-src');
                    if (!src) return;
                    const tag = el.tagName.toLowerCase();
                    if (tag === 'img' && !el.getAttribute('src')) el.setAttribute('src', src);
                    if (tag === 'source' && !el.getAttribute('src')) el.setAttribute('src', src);
                });
            }
        }
    };

    // click handlers
    document.addEventListener('click', (e) => {
        const reveal = e.target.closest('.nsfw-reveal-btn');
        if (reveal) {
            if (!prefs.showNsfw) return;
            revealNsfw(reveal.getAttribute('data-bleep-id'));
            return;
        }
        const hideBtn = e.target.closest('.hide-nsfw-btn');
        if (hideBtn) { hideNsfw(hideBtn.getAttribute('data-bleep-id')); return; }
    });

    // apply initial preference state
    document.querySelectorAll('.nsfw-wrapper[data-bleep-id], .bleep-nsfw-wrapper[data-bleep-id]').forEach(w => {
        const id = w.getAttribute('data-bleep-id');
        const isNsfw = w.getAttribute('data-is-nsfw') === '1';
        if (!isNsfw) return;

        if (!prefs.showNsfw) {
            hideNsfw(id);
            const revealBtn = w.querySelector('.nsfw-reveal-btn');
            if (revealBtn) {
                revealBtn.setAttribute('disabled', 'true');
                revealBtn.classList.add('opacity-60', 'cursor-not-allowed');
            }
            return;
        }

        if (!prefs.blurNsfw) {
            revealNsfw(id);
            return;
        }

        // When blur is enabled, hide by default unless revealed in this session
        try {
            if (id && sessionStorage.getItem(`nsfw_viewed_${id}`) === '1') {
                revealNsfw(id);
            } else {
                hideNsfw(id);
            }
        } catch (e) {
            hideNsfw(id);
        }
    });
});
