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

    const linkTab = document.getElementById('share-tab-link');
    const chatTab = document.getElementById('share-tab-chat');
    const linkPanel = document.getElementById('share-panel-link');
    const chatPanel = document.getElementById('share-panel-chat');
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
    const canUseChat = Boolean(linkTab && chatTab && chatPanel);
    const canNativeShare = typeof navigator.share === 'function';

    const cleanups = [];
    const previouslyFocused = document.activeElement;

    let actionInFlight = false;
    let sendingInChat = false;
    let shareUrl = null;
    let chatTargets = [];
    let chatTargetsRequested = false;

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
        }, 1500);
    };

    const close = () => {
        cleanup();
        actionInFlight = false;
        sendingInChat = false;
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

    // Creates (or reuses) the link row so copy and native share share one POST.
    const ensureShareUrl = async () => {
        if (shareUrl) return shareUrl;

        const data = await requestJson(`/bleeps/${bleepId}/share`, { method: 'POST', body: {} });

        shareUrl = data.share_url || `${window.location.origin}/bleeps/${bleepId}`;

        if (data.shares_count !== undefined) {
            updateShareCounts(bleepId, data.shares_count);
        }

        setUrl(shareUrl);

        return shareUrl;
    };

    const copyHandler = async () => {
        if (actionInFlight) return;
        actionInFlight = true;

        try {
            const url = await ensureShareUrl();
            await copyToClipboard(url);
            showToast('Link copied to clipboard');
            close();
        } catch (err) {
            if (err?.message === 'unauthenticated') return;
            console.error('Share copy failed', err);
            showToast(err?.message || 'Copy failed', 'error');
        } finally {
            actionInFlight = false;
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
            if (err?.name !== 'AbortError') {
                showToast(err?.message || 'Share failed', 'error');
            }
        } finally {
            actionInFlight = false;
        }
    };

    const setTab = (tab) => {
        if (!canUseChat) return;

        const isChat = tab === 'chat';

        linkPanel.classList.toggle('hidden', isChat);
        chatPanel.classList.toggle('hidden', !isChat);

        linkTab.classList.toggle('btn-active', !isChat);
        chatTab.classList.toggle('btn-active', isChat);
        linkTab.setAttribute('aria-selected', String(!isChat));
        chatTab.setAttribute('aria-selected', String(isChat));

        if (isChat) {
            loadChatTargets();
            requestAnimationFrame(() => chatSearch?.focus({ preventScroll: true }));
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
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left transition hover:bg-base-200';
            button.dataset.targetKey = target.key;

            const icon = document.createElement('span');
            icon.className = 'flex size-9 shrink-0 items-center justify-center rounded-full bg-base-200 text-base-content/60';
            icon.innerHTML = target.isGroup
                ? '<i data-lucide="users" class="w-4 h-4"></i>'
                : '<i data-lucide="user" class="w-4 h-4"></i>';

            if (!target.isGroup && target.avatar) {
                const img = document.createElement('img');
                img.src = target.avatar;
                img.alt = '';
                img.className = 'size-9 shrink-0 rounded-full object-cover';
                button.appendChild(img);
            } else {
                button.appendChild(icon);
            }

            const text = document.createElement('span');
            text.className = 'min-w-0 flex-1';

            const name = document.createElement('span');
            name.className = 'block truncate text-sm font-medium';
            name.textContent = target.title;
            text.appendChild(name);

            const meta = document.createElement('span');
            meta.className = 'block truncate text-[11px] text-base-content/50';
            meta.dataset.chatMeta = '1';
            meta.textContent = targetMeta(target);
            text.appendChild(meta);

            button.appendChild(text);

            button.addEventListener('click', () => sendToChat(target, button));

            chatList.appendChild(button);
        });

        if (window.lucide?.replace) {
            window.lucide.replace({ nodes: chatList.querySelectorAll('[data-lucide]') });
        }
    };

    const mergeChatTargets = (conversations, users) => {
        const targets = [];
        const usersWithDm = new Set();

        conversations.forEach((conversation) => {
            const participants = Array.isArray(conversation.participants) ? conversation.participants : [];

            targets.push({
                key: `conversation-${conversation.id}`,
                conversationId: Number(conversation.id),
                title: conversation.title || (conversation.is_group ? 'Group chat' : 'Direct message'),
                isGroup: Boolean(conversation.is_group),
                avatar: null,
            });

            if (!conversation.is_group) {
                participants.forEach((participant) => {
                    usersWithDm.add(Number(participant.id));
                });
            }
        });

        users.forEach((user) => {
            if (usersWithDm.has(Number(user.id))) return;

            targets.push({
                key: `user-${user.id}`,
                userId: Number(user.id),
                title: user.dname || user.username || 'User',
                isGroup: false,
                avatar: user.profile_picture_url || null,
            });
        });

        return targets;
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

    const sendToChat = async (target, button) => {
        if (sendingInChat) return;
        sendingInChat = true;
        button.disabled = true;

        const meta = button.querySelector('[data-chat-meta]');
        const previousMeta = meta?.textContent || '';
        if (meta) meta.textContent = 'Sending…';

        try {
            let conversationId = target.conversationId;

            if (!conversationId) {
                const direct = await requestJson('/chat/conversations/direct', {
                    method: 'POST',
                    body: { user_id: target.userId },
                });
                conversationId = Number(direct?.data?.id) || null;
            }

            if (!conversationId) {
                throw new Error('Could not open that chat.');
            }

            await requestJson('/messages', {
                method: 'POST',
                body: {
                    conversation_id: conversationId,
                    shared_bleep_id: Number(bleepId),
                },
            });

            showToast(`Sent to ${target.title}`);
            close();
        } catch (err) {
            if (err?.message !== 'unauthenticated') {
                showToast(err?.message || 'Could not send.', 'error');
            }
            button.disabled = false;
            if (meta) meta.textContent = previousMeta;
            sendingInChat = false;
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
    attach(cancelBtn, 'click', close);
    attach(closeBtn, 'click', close);
    attach(overlay, 'click', close);

    attach(linkTab, 'click', () => setTab('link'));
    attach(chatTab, 'click', () => setTab('chat'));
    attach(chatSearch, 'input', renderChatTargets);

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

    // Reset per-open state
    setUrl(`${window.location.origin}/bleeps/${bleepId}`);
    setTab('link');

    if (chatSearch) {
        chatSearch.value = '';
    }

    // Only offer the OS share sheet on devices that support it.
    if (nativeBtn) {
        nativeBtn.classList.toggle('hidden', !canNativeShare);
    }

    // Show modal
    modal.removeAttribute('x-cloak');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';

    requestAnimationFrame(() => {
        linkCard.focus({ preventScroll: true });
    });
};

window.addEventListener('open-share-modal', (event) => {
    const bleepId = String(event.detail?.bleepId || '');
    if (!bleepId) return;
    openShareModal(bleepId);
});
