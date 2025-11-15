document.addEventListener('DOMContentLoaded', () => {
    function setSrcForDeferred(container) {
        container.querySelectorAll('[data-media-src]').forEach(el => {
            const tag = el.tagName.toLowerCase();
            const src = el.getAttribute('data-media-src');
            if (!src) return;
            if (tag === 'img') el.setAttribute('src', src);
            if (tag === 'source') {
                el.setAttribute('src', src);
                const parentVideo = el.closest('video');
                const mime = el.getAttribute('data-media-mime') || el.getAttribute('type');
                if (mime) el.setAttribute('type', mime);
                try { parentVideo?.load(); } catch (e) {}
            }
            if (tag === 'video') {
                el.setAttribute('src', src);
                try { el.load(); } catch (e) {}
            }
        });
    }

    function clearDeferred(container) {
        container.querySelectorAll('[data-media-src], .nsfw-media').forEach(el => {
            const tag = el.tagName.toLowerCase();
            if (tag === 'img') el.removeAttribute('src');
            if (tag === 'source') el.removeAttribute('src');
            if (tag === 'video') {
                try { el.pause(); } catch(e) {}
                el.removeAttribute('src');
                const source = el.querySelector('source');
                if (source) source.removeAttribute('src');
            }
        });
    }

    function revealNsfw(bleepId) {
        if (!bleepId) return;
        const wrapper = document.querySelector(`.nsfw-wrapper[data-bleep-id="${bleepId}"], .bleep-nsfw-wrapper[data-bleep-id="${bleepId}"]`);
        if (!wrapper) {
            console.error('NSFW wrapper not found for bleepId:', bleepId);
            return;
        }

        console.log('Revealing NSFW content for bleep:', bleepId);

        try { localStorage.setItem(`nsfw_viewed_${bleepId}`, '1'); } catch (e) {}

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
        if (window.createLucideIcons) window.createLucideIcons();
    }

    function hideNsfw(bleepId) {
        if (!bleepId) return;
        const wrapper = document.querySelector(`.nsfw-wrapper[data-bleep-id="${bleepId}"], .bleep-nsfw-wrapper[data-bleep-id="${bleepId}"]`);
        if (!wrapper) return;

        try { localStorage.removeItem(`nsfw_viewed_${bleepId}`); } catch (e) {}

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
        if (reveal) { revealNsfw(reveal.getAttribute('data-bleep-id')); return; }
        const hideBtn = e.target.closest('.hide-nsfw-btn');
        if (hideBtn) { hideNsfw(hideBtn.getAttribute('data-bleep-id')); return; }
    });

    // auto-reveal from localStorage
    document.querySelectorAll('.nsfw-wrapper[data-bleep-id], .bleep-nsfw-wrapper[data-bleep-id]').forEach(w => {
        const id = w.getAttribute('data-bleep-id');
        try {
            if (id && localStorage.getItem(`nsfw_viewed_${id}`) === '1') revealNsfw(id);
        } catch (e) {}
    });
});
