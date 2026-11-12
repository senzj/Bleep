/**
 * Unified share modal.
 *
 * One surface handles every way a bleep can be shared:
 *  - copy the generated share link
 *  - send it to one or more people / group chats (multi-select)
 *  - open an external platform share intent
 *
 * Guests only get the link and the external platforms, because the chat list
 * requires an authenticated session.
 */

const SHARE_PLATFORM_URLS = {
    facebook: (url) => `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`,
    x: (url, text) => `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`,
    whatsapp: (url, text) => `https://wa.me/?text=${encodeURIComponent(`${text} ${url}`)}`,
    telegram: (url, text) => `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`,
    linkedin: (url) => `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}`,
    email: (url, text) => `mailto:?subject=${encodeURIComponent(text)}&body=${encodeURIComponent(url)}`,
};

const shareIconMarkup = (target) => (target.isGroup
    ? '<i data-lucide="users" class="h-4 w-4"></i>'
    : '<i data-lucide="user" class="h-4 w-4"></i>');

const renderShareIcons = (container) => {
    if (container && typeof window.createLucideIcons === 'function') {
        window.createLucideIcons(container);
    }
};

const openShareModal = (bleepId) => {
    const modal = document.getElementById('share-modal');
    const overlay = document.getElementById('share-modal-overlay');
    const input = document.getElementById('share-url-input');
    const urlDisplay = document.getElementById('share-url-display');
    const linkCard = document.getElementById('share-link-card');
    const copyBtn = document.getElementById('share-copy-btn');
    const cancelBtn = document.getElementById('share-cancel-btn');
    const closeBtn = document.getElementById('share-close-btn');
    const nativeBtn = document.getElementById('share-native-btn');
    const submitBtn = document.getElementById('share-submit-btn');
    const selectedCount = document.getElementById('share-selected-count');
    const chatSearch = document.getElementById('share-chat-search');
    const chatList = document.getElementById('share-chat-list');

    if (!modal || !overlay || !input || !urlDisplay || !linkCard || !copyBtn || !cancelBtn) {
        return;
    }

    // If the modal is already open for another bleep, tear the old handlers down
    // before wiring the new ones so we never POST twice per click.
    if (window.__shareModalCleanup) {
        window.__shareModalCleanup();
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const canUseChat = Boolean(chatList && submitBtn);
    const canNativeShare = typeof navigator.share === 'function';
    const cleanups = [];
    const previouslyFocused = document.activeElement;

    let actionInFlight = false;
    let shareUrl = null;
    let shareUrlPromise = null;
    let chatTargets = [];
    let chatTargetsRequested = false;
    const selectedKeys = new Set();

    const attach = (element, type, handler) => {
        if (!element) return;
        element.addEventListener(type, handler);
        cleanups.push(() => element.removeEventListener(type, handler));
    };

    const cleanup = () => {
        while (cleanups.length) {
            const dispose = cleanups.pop();
            dispose();
        }
    };

    const restoreFocus = () => {
        if (previouslyFocused && typeof previouslyFocused.focus === 'function' && document.contains(previouslyFocused)) {
            previouslyFocused.focus({ preventScroll: true });
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
        }, 1800);
    };

    const close = () => {
        cleanup();
        actionInFlight = false;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        restoreFocus();
        window.__shareModalCleanup = null;
    };

    window.__shareModalCleanup = close;

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

    const requestJson = async (url, { method = 'GET', body = null } = {}) => {
        const res = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf } : {}),
            },
            ...(body ? { body: JSON.stringify(body) } : {}),
        });

        if (res.status === 401) {
            window.location.href = '/login';
            throw new Error('unauthenticated');
        }

        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            throw new Error(data.message || 'Something went wrong.');
        }

        return data;
    };

    const updateShareCounts = (id, shares) => {
        document.querySelectorAll(`.share-count[data-bleep-id="${id}"]`).forEach((el) => {
            el.textContent = shares ?? 0;
        });
        document.querySelectorAll(`.share-text[data-bleep-id="${id}"]`).forEach((el) => {
            const s = shares ?? 0;
            el.textContent = `${s} ${s === 1 ? 'Share' : 'Shares'}`;
        });
    };

    const setUrl = (url) => {
        input.value = url;
        urlDisplay.textContent = url;
        urlDisplay.setAttribute('title', url);
    };

    // Creates (or reuses) the link row so copy, chat sends and external shares
    // all count as a single share.
    const ensureShareUrl = async () => {
        if (shareUrl) return shareUrl;
        if (shareUrlPromise) return shareUrlPromise;

        shareUrlPromise = requestJson(`/bleeps/${bleepId}/share`, { method: 'POST', body: {} })
            .then((data) => {
                shareUrl = data.share_url || `${window.location.origin}/bleeps/${bleepId}`;

                if (data.shares_count !== undefined) {
                    updateShareCounts(bleepId, data.shares_count);
                }

                setUrl(shareUrl);

                return shareUrl;
            })
            .finally(() => {
                shareUrlPromise = null;
            });

        return shareUrlPromise;
    };

    const setBusy = (busy, label) => {
        if (!submitBtn) return;

        submitBtn.disabled = busy || selectedKeys.size === 0;
        submitBtn.classList.toggle('btn-disabled', submitBtn.disabled);
        submitBtn.setAttribute('aria-disabled', String(submitBtn.disabled));

        if (busy && label) {
            submitBtn.dataset.label = submitBtn.querySelector('span')?.textContent || '';
            const span = submitBtn.querySelector('span');
            if (span) span.textContent = label;
        } else if (submitBtn.dataset.label) {
            const span = submitBtn.querySelector('span');
            if (span) span.textContent = submitBtn.dataset.label;
        }
    };

    const syncSelectionUI = () => {
        const count = selectedKeys.size;

        if (selectedCount) {
            selectedCount.textContent = `${count} selected`;
            selectedCount.classList.toggle('text-primary', count > 0);
        }

        chatList?.querySelectorAll('[data-target-key]').forEach((row) => {
            const active = selectedKeys.has(row.dataset.targetKey);
            row.setAttribute('aria-selected', String(active));
            row.classList.toggle('bg-primary/30', active);
            row.classList.toggle('border-primary/40', active);

            const check = row.querySelector('[data-target-check]');
            if (check) {
                check.classList.toggle('hidden', !active);
                check.innerHTML = active
                    ? '<i data-lucide="check" class="h-3 w-3"></i>'
                    : '';
                renderShareIcons(check);
            }
        });

        setBusy(actionInFlight, null);
    };

    const toggleTarget = (key) => {
        if (selectedKeys.has(key)) {
            selectedKeys.delete(key);
        } else {
            selectedKeys.add(key);
        }

        syncSelectionUI();
    };

    const copyHandler = async () => {
        try {
            const url = await ensureShareUrl();
            await copyToClipboard(url);
            showToast('Link copied to clipboard');
        } catch (err) {
            if (err?.message === 'unauthenticated') return;
            console.error('Share copy failed', err);
            showToast(err?.message || 'Copy failed', 'error');
        }
    };

    const nativeHandler = async () => {
        if (actionInFlight) return;
        actionInFlight = true;

        try {
            const url = await ensureShareUrl();
            await navigator.share({ title: 'Bleep', text: 'Check out this bleep', url });
            close();
        } catch (err) {
            // Cancelling the OS share sheet is not an error.
            if (err?.name === 'AbortError' || err?.message === 'unauthenticated') return;
            showToast(err?.message || 'Share failed', 'error');
        } finally {
            actionInFlight = false;
        }
    };

    const platformHandler = async (key) => {
        if (actionInFlight) return;
        actionInFlight = true;

        // Open the tab synchronously so popup blockers do not cancel it while
        // the share URL request is still in flight. Keep a handle (no
        // `noopener` in the window features) so the blank tab can be reused or
        // closed instead of being left behind on about:blank.
        const isEmail = key === 'email';
        const popup = isEmail ? null : window.open('about:blank', '_blank');

        if (popup) {
            try {
                popup.opener = null;
            } catch {
                /* cross-origin during navigation */
            }
        }

        try {
            const url = await ensureShareUrl();
            const text = 'Check out this bleep on Bleep';

            if (key === 'messenger') {
                // Messenger has no stable web share endpoint without a
                // registered app id, so copy the link and open Messenger in the
                // same tab we already reserved.
                await copyToClipboard(url);

                if (popup) {
                    popup.location.href = 'https://www.messenger.com/';
                } else {
                    window.open('https://www.messenger.com/', '_blank', 'noopener,noreferrer');
                }

                showToast('Link copied — paste it in Messenger');
                close();
                return;
            }

            const buildUrl = SHARE_PLATFORM_URLS[key];
            if (!buildUrl) {
                popup?.close();
                return;
            }

            const target = buildUrl(url, text);

            if (isEmail) {
                window.location.href = target;
                close();
                return;
            }

            if (popup) {
                popup.location.href = target;
            } else {
                window.open(target, '_blank', 'noopener,noreferrer');
            }

            close();
        } catch (err) {
            popup?.close();
            if (err?.message !== 'unauthenticated') {
                showToast(err?.message || 'Could not open that platform.', 'error');
            }
        } finally {
            actionInFlight = false;
        }
    };

    const targetMeta = (target) => (target.isGroup ? 'Group chat' : 'Direct message');

    const renderChatTargets = () => {
        if (!chatList) return;

        const term = (chatSearch?.value || '').trim().toLowerCase();
        const visible = chatTargets.filter((target) => {
            if (!term) return true;
            return target.title.toLowerCase().includes(term);
        });

        chatList.replaceChildren();

        if (!visible.length) {
            const empty = document.createElement('p');
            empty.className = 'px-2 py-3 text-xs text-base-content/50';
            empty.textContent = chatTargets.length
                ? 'No chats match that search.'
                : 'Follow people or start a chat to share bleeps here.';
            chatList.appendChild(empty);
            return;
        }

        visible.forEach((target) => {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'relative flex shrink-0 items-center gap-2 rounded-full border border-base-300 bg-base-100 py-1 pl-1 pr-3 text-left text-sm transition hover:bg-base-200';
            row.dataset.targetKey = target.key;
            row.setAttribute('role', 'option');
            row.setAttribute('aria-selected', String(selectedKeys.has(target.key)));
            row.title = `${target.title} — ${targetMeta(target)}`;

            const avatar = document.createElement('span');
            avatar.className = 'flex size-7 shrink-0 items-center justify-center overflow-hidden rounded-full bg-base-200 text-base-content/60';

            if (target.avatar) {
                const img = document.createElement('img');
                img.src = target.avatar;
                img.alt = '';
                img.className = 'size-7 shrink-0 rounded-full object-cover';
                avatar.appendChild(img);
            } else {
                avatar.innerHTML = shareIconMarkup(target);
            }

            row.appendChild(avatar);

            const name = document.createElement('span');
            name.className = 'max-w-32 truncate text-sm font-medium';
            name.textContent = target.title;
            row.appendChild(name);

            const check = document.createElement('span');
            check.dataset.targetCheck = '1';
            check.className = 'pointer-events-none absolute -right-1 -top-1 flex size-4 items-center justify-center rounded-full bg-primary text-primary-content shadow';
            row.appendChild(check);

            row.addEventListener('click', () => toggleTarget(target.key));

            chatList.appendChild(row);
        });

        renderShareIcons(chatList);
        syncSelectionUI();
    };

    const mergeChatTargets = (conversations, users) => {
        const conversationTargets = [];
        const userTargets = [];
        const usersWithDm = new Set();

        conversations.forEach((conversation) => {
            if (conversation.has_left) return;

            const participants = Array.isArray(conversation.participants) ? conversation.participants : [];
            const lastMessageAt = Date.parse(conversation.last_message_at || '') || 0;

            conversationTargets.push({
                key: `conversation-${conversation.id}`,
                conversationId: Number(conversation.id),
                title: conversation.title || (conversation.is_group ? 'Group chat' : 'Direct message'),
                isGroup: Boolean(conversation.is_group),
                avatar: conversation.avatar || null,
                sortScore: lastMessageAt,
            });

            if (!conversation.is_group) {
                participants.forEach((participant) => {
                    usersWithDm.add(Number(participant.id));
                });
            }
        });

        users.forEach((user) => {
            if (usersWithDm.has(Number(user.id))) return;

            userTargets.push({
                key: `user-${user.id}`,
                userId: Number(user.id),
                title: user.dname || user.username || 'User',
                isGroup: false,
                avatar: user.profile_picture_url || null,
                sortScore: Number(user.interaction_score || 0),
            });
        });

        // Chats you use most recently come first, then friends you interact
        // with most through likes and comments.
        conversationTargets.sort((a, b) => b.sortScore - a.sortScore);
        userTargets.sort((a, b) => b.sortScore - a.sortScore || a.title.localeCompare(b.title));

        return [...conversationTargets, ...userTargets];
    };

    const loadChatTargets = async () => {
        if (chatTargetsRequested || !chatList) return;
        chatTargetsRequested = true;

        chatList.replaceChildren();
        const loading = document.createElement('p');
        loading.className = 'px-2 py-3 text-xs text-base-content/50';
        loading.textContent = 'Loading…';
        chatList.appendChild(loading);

        try {
            const [conversations, users] = await Promise.all([
                requestJson('/chat/conversations'),
                requestJson('/chat/users'),
            ]);

            chatTargets = mergeChatTargets(conversations.data || [], users.data || []);
            renderChatTargets();
        } catch (err) {
            if (err?.message === 'unauthenticated') return;
            chatTargetsRequested = false;
            chatList.replaceChildren();
            const failed = document.createElement('p');
            failed.className = 'px-2 py-3 text-xs text-error';
            failed.textContent = 'Could not load your chats. Try again.';
            chatList.appendChild(failed);
        }
    };

    const openConversation = async (target) => {
        if (target.conversationId) return target.conversationId;

        const direct = await requestJson('/chat/conversations/direct', {
            method: 'POST',
            body: { user_id: target.userId },
        });

        const conversationId = Number(direct?.data?.id) || null;

        if (!conversationId) {
            throw new Error('Could not open that chat.');
        }

        target.conversationId = conversationId;

        return conversationId;
    };

    const sendToSelected = async () => {
        if (!canUseChat || actionInFlight || selectedKeys.size === 0) return;

        const targets = chatTargets.filter((target) => selectedKeys.has(target.key));
        if (!targets.length) return;

        actionInFlight = true;
        setBusy(true, 'Sending…');

        try {
            // Make sure the share exists even if the viewer only sends to chats.
            const url = await ensureShareUrl();

            let sent = 0;
            const failed = [];

            for (const target of targets) {
                try {
                    const conversationId = await openConversation(target);

                    await requestJson('/messages', {
                        method: 'POST',
                        body: {
                            conversation_id: conversationId,
                            body: url,
                            shared_bleep_id: Number(bleepId),
                        },
                    });

                    sent += 1;
                } catch (err) {
                    if (err?.message === 'unauthenticated') return;
                    failed.push(target.title);
                }
            }

            if (sent > 0) {
                showToast(
                    sent === 1
                        ? `Sent to ${targets[0].title}`
                        : `Sent to ${sent} chats`
                );
            }

            if (failed.length) {
                showToast(`Could not send to ${failed.join(', ')}`, 'error');
            }

            if (!failed.length) {
                close();
            }
        } catch (err) {
            if (err?.message !== 'unauthenticated') {
                showToast(err?.message || 'Could not send.', 'error');
            }
        } finally {
            actionInFlight = false;
            setBusy(false, null);
            syncSelectionUI();
        }
    };

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

    attach(nativeBtn, 'click', nativeHandler);
    attach(submitBtn, 'click', sendToSelected);
    attach(cancelBtn, 'click', close);
    attach(closeBtn, 'click', close);
    attach(overlay, 'click', close);
    attach(chatSearch, 'input', renderChatTargets);

    modal.querySelectorAll('[data-share-platform]').forEach((button) => {
        attach(button, 'click', () => platformHandler(button.dataset.sharePlatform));
    });

    // Escape closes; Tab stays inside the dialog.
    attach(document, 'keydown', (event) => {
        if (event.key === 'Escape') {
            close();
            return;
        }

        if (event.key !== 'Tab') return;

        const focusable = Array.from(
            modal.querySelectorAll('button, [href], input, [tabindex]:not([tabindex="-1"])')
        ).filter((el) => !el.disabled && el.offsetParent !== null);

        if (!focusable.length) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    // Reset per-open state.
    setUrl(`${window.location.origin}/bleeps/${bleepId}`);
    selectedKeys.clear();

    if (chatSearch) {
        chatSearch.value = '';
    }

    // Only offer the OS share sheet on devices that support it.
    if (nativeBtn) {
        nativeBtn.classList.toggle('hidden', !canNativeShare);
    }

    syncSelectionUI();

    if (canUseChat) {
        loadChatTargets();
    }

    // Show modal
    modal.removeAttribute('x-cloak');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';

    requestAnimationFrame(() => {
        linkCard.focus({ preventScroll: true });
        renderShareIcons(modal);
    });
};

window.addEventListener('open-share-modal', (event) => {
    const bleepId = String(event.detail?.bleepId || '');
    if (!bleepId) return;
    openShareModal(bleepId);
});
