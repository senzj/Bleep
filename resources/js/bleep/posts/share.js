document.addEventListener('click', (e) => {
    const trigger = e.target.closest('.share-btn');
    if (!trigger) return;

    const bleepId = trigger.dataset.bleepId;
    if (!bleepId) return;

    const modal = document.getElementById('share-modal');
    const overlay = document.getElementById('share-modal-overlay');
    const input = document.getElementById('share-url-input');
    const urlDisplay = document.getElementById('share-url-display');
    const linkCard = document.getElementById('share-link-card');
    const copyBtn = document.getElementById('share-copy-btn');
    const cancelBtn = document.getElementById('share-cancel-btn');

    if (!modal || !overlay || !input || !urlDisplay || !linkCard || !copyBtn || !cancelBtn) {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const cleanups = [];
    let actionInFlight = false;

    const attach = (element, type, handler) => {
        element.addEventListener(type, handler);
        cleanups.push(() => element.removeEventListener(type, handler));
    };

    const cleanup = () => {
        while (cleanups.length) {
            const dispose = cleanups.pop();
            dispose();
        }
    };

    const showToast = (text, type = 'success') => {
        const t = document.createElement('div');
        t.textContent = text;
        const bgColor = type === 'error' ? '#DC2626' : '#111827';
        Object.assign(t.style, {
            position: 'fixed',
            right: '16px',
            bottom: '16px',
            padding: '8px 12px',
            background: bgColor,
            color: '#fff',
            borderRadius: '8px',
            zIndex: 9999,
            fontSize: '13px',
            boxShadow: '0 10px 30px rgba(17,24,39,0.20)',
            transition: 'opacity 0.25s ease',
        });
        document.body.appendChild(t);
        setTimeout(() => {
            t.style.opacity = '0';
            setTimeout(() => t.remove(), 250);
        }, 1500);
    };

    const close = () => {
        cleanup();
        actionInFlight = false;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    const copyToClipboard = async (text) => {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            return;
        }
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    };

    const copyHandler = async () => {
        if (actionInFlight) return;
        actionInFlight = true;

        try {
            const res = await fetch(`/bleeps/${bleepId}/share`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({}),
            });

            if (res.status === 401) {
                window.location.href = '/login';
                return;
            }

            if (!res.ok) {
                const message = await res.text();
                throw new Error(message);
            }

            const data = await res.json();

            if (data.share_url) {
                input.value = data.share_url;
                urlDisplay.textContent = data.share_url;
                urlDisplay.setAttribute('title', data.share_url);
            } else {
                const fallback = `${window.location.origin}/bleeps/${bleepId}`;
                input.value = fallback;
                urlDisplay.textContent = fallback;
            }

            // Update share counts only
            if (data.shares_count !== undefined) {
                updateShareCounts(bleepId, data.shares_count);
            }

            await copyToClipboard(input.value);
            showToast('Link copied to clipboard');
            close();
        } catch (err) {
            console.error('Share copy failed', err);
            showToast('Copy failed', 'error');
        } finally {
            actionInFlight = false;
        }
    };

    const updateShareCounts = (bleepId, shares) => {
        document.querySelectorAll(`.share-count[data-bleep-id="${bleepId}"]`).forEach((el) => {
            el.textContent = shares ?? 0;
        });
        document.querySelectorAll(`.share-text[data-bleep-id="${bleepId}"]`).forEach((el) => {
            const s = shares ?? 0;
            el.textContent = `${s} ${s === 1 ? 'Share' : 'Shares'}`;
        });
    };

    // Attach event listeners
    attach(copyBtn, 'click', (event) => {
        event.stopPropagation();
        copyHandler();
    });

    attach(linkCard, 'click', copyHandler);

    attach(linkCard, 'keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            copyHandler();
        }
    });

    attach(cancelBtn, 'click', close);
    attach(overlay, 'click', close);

    // Escape key to close
    attach(document, 'keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });

    // Set initial URL
    const fallbackUrl = `${window.location.origin}/bleeps/${bleepId}`;
    input.value = fallbackUrl;
    urlDisplay.textContent = fallbackUrl;
    urlDisplay.setAttribute('title', fallbackUrl);

    // Show modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    requestAnimationFrame(() => {
        linkCard.focus({ preventScroll: true });
    });
});
