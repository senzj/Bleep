import { computed, reactive } from 'vue';
import {
    getCachedConversations,
    setCachedConversations,
    getCachedMessages,
    setCachedMessages,
    purgeExpired,
} from './chatCache';

// ── Notification sound helpers ──
const soundConfig = { sendSound: null, receiveSound: null };
const audioCache = {};

const playSound = (path) => {
    if (!path || path === 'none') return;
    try {
        if (!audioCache[path]) {
            audioCache[path] = new Audio(path);
            audioCache[path].load();
        }
        const audio = audioCache[path];
        audio.currentTime = 0;
        audio.play().catch(() => {});
    } catch { /* silently ignore */ }
};

const playSendSound = () => playSound(soundConfig.sendSound);
const playReceiveSound = () => playSound(soundConfig.receiveSound);

// ── Nav badge sync ──
const broadcastUnreadCount = () => {
    const total = state.conversations.reduce((sum, c) => sum + (c.unread_count || 0), 0);
    document.dispatchEvent(new CustomEvent('chat:unread-updated', { detail: { count: total } }));
};

const state = reactive({
    initialized: false,
    loadingConversations: false,
    loadingMessages: false,
    loadingUsers: false,
    currentUserId: null,
    currentUsername: 'You',
    conversations: [],
    userDirectory: [],
    activeConversationId: null,
    messagesByConversation: {},
    messageMetaByConversation: {},
    typingUsersByConversation: {},
    onlineUsersByConversation: {},
    channelRefs: {},
    userChannelRef: null,
    replyToMessage: null,
});

const typingTimeouts = {};
const ACTIVE_CONV_KEY = 'chat_active_conversation_id';
const lastReadMarkerByConversation = {};
let conversationsRefreshTimer = null;

const ensureConversationArrays = (conversationId) => {
    if (!state.messagesByConversation[conversationId]) {
        state.messagesByConversation[conversationId] = [];
    }

    if (!state.typingUsersByConversation[conversationId]) {
        state.typingUsersByConversation[conversationId] = {};
    }

    if (!state.onlineUsersByConversation[conversationId]) {
        state.onlineUsersByConversation[conversationId] = {};
    }

    if (!state.messageMetaByConversation[conversationId]) {
        state.messageMetaByConversation[conversationId] = {
            loaded: false,
            loading: false,
            hasMore: true,
            oldestMessageId: null,
        };
    }
};

// ── Reactions (optimistic updates + offline replay) ──
// Pending reactions are keyed by message id and store the desired end state,
// so replaying them many times (network retries, multiple tabs, reloads) is idempotent.
const PENDING_REACTIONS_KEY = 'bleep_chat_pending_reactions_v1';
const reactionFlights = new Map();
let flushingPendingReactions = false;

const readPendingReactions = () => {
    try {
        const parsed = JSON.parse(localStorage.getItem(PENDING_REACTIONS_KEY) || '{}');
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch {
        return {};
    }
};

const writePendingReactions = (pending) => {
    try {
        if (Object.keys(pending).length === 0) {
            localStorage.removeItem(PENDING_REACTIONS_KEY);
            return;
        }
        localStorage.setItem(PENDING_REACTIONS_KEY, JSON.stringify(pending));
    } catch { /* storage may be unavailable */ }
};

const queuePendingReaction = (messageId, emoji, active) => {
    const pending = readPendingReactions();
    pending[String(messageId)] = {
        messageId: Number(messageId),
        emoji,
        active: Boolean(active),
        queuedAt: Date.now(),
    };
    writePendingReactions(pending);
};

const clearPendingReaction = (messageId, { emoji = null, active = null } = {}) => {
    const pending = readPendingReactions();
    const key = String(messageId);
    const entry = pending[key];

    if (!entry) return;
    if (emoji !== null && entry.emoji !== emoji) return;
    if (active !== null && Boolean(entry.active) !== Boolean(active)) return;

    delete pending[key];
    writePendingReactions(pending);
};

const persistConversationMessages = (conversationId) => {
    const list = state.messagesByConversation[conversationId];
    if (Array.isArray(list)) {
        setCachedMessages(conversationId, list);
    }
};

const findMessageById = (messageId) => {
    const target = Number(messageId);
    if (!target) return null;

    for (const conversationId of Object.keys(state.messagesByConversation)) {
        const message = (state.messagesByConversation[conversationId] || [])
            .find((item) => Number(item.id) === target);

        if (message) {
            return { conversationId: Number(conversationId), message };
        }
    }

    return null;
};

const applyLocalReaction = (messageId, emoji, active) => {
    const found = findMessageById(messageId);
    if (!found) return;

    const { conversationId, message } = found;
    const currentUserId = Number(state.currentUserId);
    const reactions = Array.isArray(message.reactions) ? [...message.reactions] : [];
    const mineIndex = reactions.findIndex((reaction) => Number(reaction.user_id) === currentUserId);

    if (active) {
        const optimistic = {
            id: mineIndex >= 0 ? reactions[mineIndex].id : `local-${messageId}-${currentUserId}`,
            user_id: currentUserId,
            emoji,
            user: {
                id: currentUserId,
                username: state.currentUsername,
                dname: state.currentUsername,
                profile_picture_url: null,
            },
        };

        if (mineIndex >= 0) {
            reactions[mineIndex] = { ...reactions[mineIndex], ...optimistic };
        } else {
            reactions.push(optimistic);
        }
    } else if (mineIndex >= 0) {
        reactions.splice(mineIndex, 1);
    }

    message.reactions = reactions;
    persistConversationMessages(conversationId);
};

const applyServerReactions = (conversationId, messageId, reactions) => {
    ensureConversationArrays(conversationId);

    const list = state.messagesByConversation[conversationId] || [];
    const message = list.find((item) => Number(item.id) === Number(messageId));
    if (!message) return;

    message.reactions = Array.isArray(reactions) ? reactions : [];
    persistConversationMessages(conversationId);
};

const postReaction = async (messageId, emoji, active) => {
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        queuePendingReaction(messageId, emoji, active);
        return null;
    }

    try {
        const response = await window.axios.post(`/messages/${messageId}/reactions`, {
            emoji,
            active: Boolean(active),
        });

        const data = response.data?.data;
        if (data?.conversation_id && data?.message_id) {
            applyServerReactions(Number(data.conversation_id), Number(data.message_id), data.reactions);
        }

        clearPendingReaction(messageId, { emoji, active });
        return data;
    } catch (error) {
        const status = Number(error?.response?.status || 0);

        // Network failures and server errors are retryable: keep the optimistic
        // state and replay the desired end state when connectivity returns.
        if (!status || status >= 500) {
            queuePendingReaction(messageId, emoji, active);
            return null;
        }

        // The server rejected the desired state (deleted message, lost access).
        clearPendingReaction(messageId, { emoji, active });
        const found = findMessageById(messageId);
        if (found) {
            await fetchMessages(found.conversationId, { force: true, silent: true }).catch(() => {});
        }

        return null;
    }
};

const enqueueReactionRequest = (messageId, emoji, active) => {
    const key = String(messageId);
    const previous = reactionFlights.get(key) || Promise.resolve();

    // Serialise requests per message so a rapid add/remove/save cannot land
    // on the server out of order.
    const request = previous
        .catch(() => {})
        .then(() => postReaction(messageId, emoji, active));

    reactionFlights.set(key, request);
    request
        .finally(() => {
            if (reactionFlights.get(key) === request) {
                reactionFlights.delete(key);
            }
        })
        .catch(() => {});

    return request;
};

const flushPendingReactions = async () => {
    if (flushingPendingReactions) return;
    if (typeof navigator !== 'undefined' && navigator.onLine === false) return;

    const pending = readPendingReactions();
    const entries = Object.values(pending);
    if (entries.length === 0) return;

    flushingPendingReactions = true;
    const conversationsToRefresh = new Set();

    try {
        for (const entry of entries) {
            const messageId = Number(entry?.messageId);
            if (!messageId || !entry?.emoji) continue;

            if (reactionFlights.has(String(messageId))) continue;

            try {
                const response = await window.axios.post(`/messages/${messageId}/reactions`, {
                    emoji: entry.emoji,
                    active: Boolean(entry.active),
                });

                const data = response.data?.data;
                if (data?.conversation_id && data?.message_id) {
                    applyServerReactions(Number(data.conversation_id), Number(data.message_id), data.reactions);
                    conversationsToRefresh.add(Number(data.conversation_id));
                }

                clearPendingReaction(messageId, { emoji: entry.emoji, active: entry.active });
            } catch (error) {
                const status = Number(error?.response?.status || 0);

                if (status && status < 500) {
                    // Permanently rejected (message deleted, no access): drop it.
                    clearPendingReaction(messageId, { emoji: entry.emoji, active: entry.active });
                    const found = findMessageById(messageId);
                    if (found) {
                        conversationsToRefresh.add(found.conversationId);
                    }
                    continue;
                }

                // Still offline or the server is down: stop and retry later.
                break;
            }
        }
    } finally {
        flushingPendingReactions = false;
    }

    // Reconcile against the server so other people's reactions are exact too.
    for (const conversationId of conversationsToRefresh) {
        if (!state.messageMetaByConversation[conversationId]?.loaded) continue;
        await fetchMessages(conversationId, { force: true, silent: true }).catch(() => {});
    }
};

const handleReactionOnline = () => {
    flushPendingReactions().catch(() => {});
};

const handleReactionVisibility = () => {
    if (document.visibilityState === 'visible') {
        handleReactionOnline();
    }
};


const sortConversations = () => {
    state.conversations.sort((a, b) => {
        const aPinned = Boolean(a?.is_pinned);
        const bPinned = Boolean(b?.is_pinned);
        if (aPinned !== bPinned) return Number(bPinned) - Number(aPinned);

        const aTime = a?.last_message_at ? new Date(a.last_message_at).getTime() : 0;
        const bTime = b?.last_message_at ? new Date(b.last_message_at).getTime() : 0;
        return bTime - aTime;
    });
};

const upsertMessage = (conversationId, message) => {
    ensureConversationArrays(conversationId);

    const list = state.messagesByConversation[conversationId];
    const byIdIndex = list.findIndex((item) => item.id === message.id);

    if (byIdIndex >= 0) {
        list[byIdIndex] = { ...list[byIdIndex], ...message };
    } else {
        const byClientUuidIndex = message.client_uuid
            ? list.findIndex((item) => item.client_uuid && item.client_uuid === message.client_uuid)
            : -1;

        if (byClientUuidIndex >= 0) {
            list[byClientUuidIndex] = { ...list[byClientUuidIndex], ...message };
        } else {
            list.push(message);
        }
    }

    list.sort((a, b) => Number(a.id || 0) - Number(b.id || 0));

    const conversation = state.conversations.find((item) => item.id === conversationId);
    if (conversation) {
        conversation.last_message = message;
        conversation.last_message_at = message.created_at;
        sortConversations();
    }
};

const applyReadReceipt = (conversationId, messageId, reader, readAt) => {
    ensureConversationArrays(conversationId);

    const list = state.messagesByConversation[conversationId];
    list.forEach((message) => {
        if (message.sender_id !== state.currentUserId) return;
        if (Number(message.id) > Number(messageId)) return;

        const seenBy = Array.isArray(message.seen_by) ? [...message.seen_by] : [];
        const existingIndex = seenBy.findIndex((item) => Number(item.id) === Number(reader.id));
        const payload = {
            id: reader.id,
            username: reader.username,
            dname: reader.dname,
            read_at: readAt,
            profile_picture_url: reader.profile_picture_url ?? null,
        };

        if (existingIndex >= 0) {
            seenBy[existingIndex] = payload;
        } else {
            seenBy.push(payload);
        }

        message.seen_by = seenBy;
        message.read_count = seenBy.length;
        message.status = 'seen';
    });

    // Update participant's last_read_at in the conversation
    const conversation = state.conversations.find((c) => Number(c.id) === Number(conversationId));
    if (conversation && conversation.participants) {
        const participant = conversation.participants.find((p) => Number(p.id) === Number(reader.id));
        if (participant) {
            participant.last_read_at = readAt;
        }
    }
};

const subscribeConversation = (conversationId) => {
    if (!window.Echo || state.channelRefs[conversationId]) return;

    const privateChannel = window.Echo.private(`conversation.${conversationId}`);
    privateChannel.listen('.message.sent', (event) => {
        upsertMessage(conversationId, event.message);
        playReceiveSound();

        if (Number(state.activeConversationId) === Number(conversationId)) {
            markConversationRead(conversationId);
        }
    });

    privateChannel.listen('.message.read', (event) => {
        applyReadReceipt(conversationId, event.message_id, event.reader, event.read_at);
    });

    privateChannel.listen('.message.updated', (event) => {
        if (!event?.message) return;
        upsertMessage(conversationId, event.message);
    });

    privateChannel.listen('.message.deleted', (event) => {
        if (!event?.message) return;
        upsertMessage(conversationId, event.message);
    });

    privateChannel.listen('.message.reacted', (event) => {
        if (!event?.message_id) return;
        applyServerReactions(conversationId, event.message_id, event.reactions);
    });

    const presenceChannel = window.Echo.join(`conversation-online.${conversationId}`)
        .here((users) => {
            ensureConversationArrays(conversationId);
            const map = {};
            users.forEach((user) => {
                map[user.id] = user;
            });
            state.onlineUsersByConversation[conversationId] = map;
        })
        .joining((user) => {
            ensureConversationArrays(conversationId);
            state.onlineUsersByConversation[conversationId][user.id] = user;
        })
        .leaving((user) => {
            ensureConversationArrays(conversationId);
            delete state.onlineUsersByConversation[conversationId][user.id];
        })
        .listenForWhisper('typing', (event) => {
            if (!event?.user_id || Number(event.user_id) === Number(state.currentUserId)) return;

            ensureConversationArrays(conversationId);

            state.typingUsersByConversation[conversationId][event.user_id] = event.name || 'Someone';

            const timeoutKey = `${conversationId}:${event.user_id}`;
            clearTimeout(typingTimeouts[timeoutKey]);
            typingTimeouts[timeoutKey] = setTimeout(() => {
                delete state.typingUsersByConversation[conversationId][event.user_id];
            }, 2500);
        });

    state.channelRefs[conversationId] = {
        privateChannel,
        presenceChannel,
    };
};

const scheduleConversationRefresh = (delay = 200) => {
    if (conversationsRefreshTimer) {
        clearTimeout(conversationsRefreshTimer);
    }

    conversationsRefreshTimer = setTimeout(() => {
        conversationsRefreshTimer = null;
        fetchConversations({ silent: true }).catch(() => {});
    }, delay);
};

const fetchConversations = async ({ silent = false } = {}) => {
    if (!silent) {
        state.loadingConversations = true;
    }

    const storedId = Number(localStorage.getItem(ACTIVE_CONV_KEY) || 0);
    const previousActiveId = Number(state.activeConversationId || storedId || 0);

    try {
        const response = await window.axios.get('/chat/conversations');

        // Preserve locally-known last_read_at values that may be newer
        // than the server snapshot (WebSocket read events can arrive before
        // the HTTP response that was already in-flight).
        const localReadPositions = new Map();
        state.conversations.forEach((conv) => {
            (conv.participants || []).forEach((p) => {
                if (p.last_read_at) {
                    localReadPositions.set(`${conv.id}:${p.id}`, p.last_read_at);
                }
            });
        });

        state.conversations = response.data?.data || [];

        // Restore any local read positions that are newer than what the server returned
        state.conversations.forEach((conv) => {
            (conv.participants || []).forEach((p) => {
                const key = `${conv.id}:${p.id}`;
                const local = localReadPositions.get(key);
                if (local) {
                    const localTime = new Date(local).getTime();
                    const serverTime = p.last_read_at ? new Date(p.last_read_at).getTime() : 0;
                    if (localTime > serverTime) {
                        p.last_read_at = local;
                    }
                }
            });
        });

        state.conversations.forEach((conversation) => {
            ensureConversationArrays(conversation.id);
            subscribeConversation(conversation.id);
        });

        sortConversations();

        const activeStillExists = state.conversations.some((conversation) => Number(conversation.id) === previousActiveId);

        if (activeStillExists) {
            await selectConversation(previousActiveId, { historyMode: 'none' });
        } else if (state.conversations.length > 0) {
            await selectConversation(state.conversations[0].id, { historyMode: 'none' });
        }

        // Persist to cache for instant load next time
        setCachedConversations(state.conversations);
    } finally {
        if (!silent) {
            state.loadingConversations = false;
        }
        broadcastUnreadCount();
    }
};

const fetchUserDirectory = async (search = '') => {
    state.loadingUsers = true;
    try {
        const response = await window.axios.get('/chat/users', {
            params: search ? { q: search } : {},
        });
        state.userDirectory = response.data?.data || [];
    } finally {
        state.loadingUsers = false;
    }
};

const mergeMessageLists = (incoming, existing) => {
    const map = new Map();

    [...incoming, ...existing].forEach((message) => {
        const key = message?.id || message?.client_uuid;
        if (!key) return;

        const prev = map.get(key) || {};
        map.set(key, { ...prev, ...message });
    });

    return [...map.values()].sort((a, b) => Number(a.id || 0) - Number(b.id || 0));
};

const fetchMessages = async (conversationId, { beforeId = null, limit = 40, force = false, silent = false } = {}) => {
    ensureConversationArrays(conversationId);

    const meta = state.messageMetaByConversation[conversationId];
    const isPaginatingOlder = Boolean(beforeId);

    if (meta.loading) return;
    if (!force && !isPaginatingOlder && meta.loaded) return;

    meta.loading = true;
    if (!silent) {
        state.loadingMessages = !isPaginatingOlder && Number(state.activeConversationId) === Number(conversationId);
    }

    try {
        const response = await window.axios.get(`/chat/conversations/${conversationId}/messages`, {
            params: {
                limit,
                ...(isPaginatingOlder ? { before_id: beforeId } : {}),
            },
        });

        const incoming = response.data?.data || [];
        const serverMeta = response.data?.meta || {};
        const existing = state.messagesByConversation[conversationId] || [];

        if (isPaginatingOlder) {
            state.messagesByConversation[conversationId] = mergeMessageLists(incoming, existing);
        } else {
            state.messagesByConversation[conversationId] = incoming;
        }

        const list = state.messagesByConversation[conversationId] || [];
        const firstMessage = list[0] || null;
        meta.oldestMessageId = firstMessage?.id || null;
        meta.hasMore = Boolean(serverMeta.has_more);
        meta.loaded = true;

        // Persist to cache for instant load next time
        setCachedMessages(conversationId, state.messagesByConversation[conversationId]);
    } finally {
        meta.loading = false;
        state.loadingMessages = false;
    }
};

const fetchOlderMessages = async (conversationId = state.activeConversationId) => {
    const targetId = Number(conversationId || 0);
    if (!targetId) return;

    ensureConversationArrays(targetId);
    const meta = state.messageMetaByConversation[targetId];

    if (!meta.loaded) {
        await fetchMessages(targetId, { force: true });
        return;
    }

    if (!meta.hasMore || meta.loading || !meta.oldestMessageId) return;

    await fetchMessages(targetId, {
        beforeId: meta.oldestMessageId,
        limit: 40,
    });
};

const markConversationRead = async (conversationId) => {
    ensureConversationArrays(conversationId);
    const list = state.messagesByConversation[conversationId] || [];
    // Find the last message with a real server ID (skip optimistic tmp-... UUIDs)
    const lastRealMessage = [...list].reverse().find((m) => m?.id && Number.isInteger(Number(m.id)) && !String(m.id).startsWith('tmp-'));
    if (!lastRealMessage) return;

    // Skip repeat POSTs for a conversation we have already marked up to this message.
    const marker = String(lastRealMessage.id);
    if (lastReadMarkerByConversation[conversationId] === marker) return;
    lastReadMarkerByConversation[conversationId] = marker;

    try {
        await window.axios.post(`/chat/conversations/${conversationId}/read`, {
            last_message_id: lastRealMessage.id,
        });

        // Update current user's last_read_at in the conversation participants
        const conversation = state.conversations.find((c) => Number(c.id) === Number(conversationId));
        if (conversation && conversation.participants) {
            const currentParticipant = conversation.participants.find((p) => Number(p.id) === Number(state.currentUserId));
            if (currentParticipant) {
                currentParticipant.last_read_at = new Date().toISOString();
            }
        }
    } catch {
        // Allow a retry next time the conversation is opened.
        delete lastReadMarkerByConversation[conversationId];
    }
};

const selectConversation = async (conversationId, { historyMode = 'push' } = {}) => {
    const targetId = Number(conversationId);

    state.activeConversationId = targetId;
    localStorage.setItem(ACTIVE_CONV_KEY, String(targetId));

    // Keep the browser URL in sync without stacking duplicate history entries.
    // `fromChatList` marks entries we pushed from the chat list so the mobile
    // back button can step back to it instead of leaving the page.
    if (historyMode === 'push') {
        window.history.pushState(
            { conversationId: targetId, fromChatList: true },
            '',
            `/chat/${targetId}`
        );
    } else if (historyMode === 'replace') {
        window.history.replaceState({ conversationId: targetId }, '', `/chat/${targetId}`);
    }

    ensureConversationArrays(targetId);
    const meta = state.messageMetaByConversation[targetId];

    // Reset unread count immediately in local state
    const conv = state.conversations.find((c) => Number(c.id) === targetId);
    if (conv) {
        conv.unread_count = 0;
    }
    broadcastUnreadCount();

    subscribeConversation(targetId);

    if (!meta.loaded) {
        // Try cache first for instant render
        const cached = await getCachedMessages(targetId);
        if (cached && cached.length > 0) {
            state.messagesByConversation[targetId] = cached;
            meta.loaded = true;
            meta.oldestMessageId = cached[0]?.id || null;
        }

        // Always fetch fresh data from server
        await fetchMessages(targetId, { force: true });
    }

    await markConversationRead(targetId);
};

const sendMessage = async (payload) => {
    const conversationId = Number(payload.conversation_id || state.activeConversationId);
    if (!conversationId) throw new Error('No active conversation selected.');

    const clientUuid = payload.client_uuid || `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
    const optimisticMessage = {
        id: clientUuid,
        client_uuid: clientUuid,
        conversation_id: conversationId,
        sender_id: state.currentUserId,
        sender: {
            id: state.currentUserId,
            username: state.currentUsername,
            dname: state.currentUsername,
            profile_picture_url: null,
        },
        body: payload.body || null,
        media_path: payload.media_path || null,
        media_url: payload.media_url || null,
        media_type: payload.media_type || null,
        media_kind: payload.media_kind || 'none',
        media_duration: payload.media_duration || null,
        media_items: Array.isArray(payload.media_items) ? payload.media_items : [],
        is_edited: false,
        edited_at: null,
        is_deleted: false,
        deleted_at: null,
        status: 'sending',
        seen_by: [],
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
    };

    upsertMessage(conversationId, optimisticMessage);

    try {
        const response = await window.axios.post('/messages', {
            conversation_id: conversationId,
            body: payload.body || null,
            media_path: payload.media_path || null,
            media_type: payload.media_type || null,
            media_kind: payload.media_kind || 'none',
            media_duration: payload.media_duration || null,
            media_items: Array.isArray(payload.media_items) ? payload.media_items : [],
            reply_to_id: payload.reply_to_id || null,
            client_uuid: clientUuid,
        });

        upsertMessage(conversationId, response.data.data);
        markConversationRead(conversationId); // fire-and-forget, never throws
        playSendSound();
        return response.data.data;
    } catch (error) {
        // Only roll back on actual send failure, not read-receipt errors
        state.messagesByConversation[conversationId] = (state.messagesByConversation[conversationId] || [])
            .filter((message) => message.client_uuid !== clientUuid && message.id !== clientUuid);

        throw error;
    }
};

const editMessage = async (payloadOrMessageId, maybeBody = null) => {
    const isObjectPayload = payloadOrMessageId && typeof payloadOrMessageId === 'object';
    const messageId = Number(isObjectPayload ? payloadOrMessageId.messageId : payloadOrMessageId);

    if (!messageId) {
        throw new Error('Invalid message id.');
    }

    const rawBody = isObjectPayload ? payloadOrMessageId.body : maybeBody;
    const trimmedBody = rawBody === null || rawBody === undefined
        ? null
        : String(rawBody).trim();

    const retainedMediaIds = isObjectPayload && Array.isArray(payloadOrMessageId.retainedMediaIds)
        ? payloadOrMessageId.retainedMediaIds
            .map((id) => Number(id))
            .filter((id) => Number.isFinite(id) && id > 0)
        : null;

    const mediaItems = isObjectPayload && Array.isArray(payloadOrMessageId.newMediaItems)
        ? payloadOrMessageId.newMediaItems
        : [];

    const response = await window.axios.patch(`/messages/${messageId}`, {
        body: trimmedBody,
        retained_media_ids: retainedMediaIds,
        media_items: mediaItems,
    });

    const updated = response.data?.data;
    if (updated?.conversation_id) {
        upsertMessage(Number(updated.conversation_id), updated);
    }

    return updated;
};

const deleteMessage = async (messageId) => {
    const response = await window.axios.delete(`/messages/${messageId}`);

    const deleted = response.data?.data;
    if (deleted?.conversation_id) {
        upsertMessage(Number(deleted.conversation_id), deleted);
    }

    return deleted;
};

const toggleReaction = async (messageId, emoji) => {
    const found = findMessageById(messageId);
    const mine = found
        ? (found.message.reactions || []).find((reaction) => Number(reaction.user_id) === Number(state.currentUserId))
        : null;

    // Clicking the emoji you already reacted with removes it; any other emoji
    // switches your reaction. The server receives the desired end state, which
    // makes retries and offline replays safe.
    const active = !(mine && mine.emoji === emoji);

    applyLocalReaction(messageId, emoji, active);

    return enqueueReactionRequest(messageId, emoji, active);
};

const setReplyTo = (message) => {
    state.replyToMessage = message || null;
};

const clearReplyTo = () => {
    state.replyToMessage = null;
};

const uploadMedia = async (file, mediaKind = 'media', onProgress = null) => {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('media_kind', mediaKind);

    const response = await window.axios.post('/chat/media', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (event) => {
            if (onProgress && event.total) {
                onProgress(Math.round((event.loaded * 100) / event.total));
            }
        },
    });

    return response.data.data;
};

const sendVoiceMessage = async (blob, durationSeconds = 0, onProgress = null) => {
    const voiceFile = new File([blob], `voice-${Date.now()}.webm`, {
        type: blob.type || 'audio/webm',
    });

    const uploaded = await uploadMedia(voiceFile, 'voice', onProgress);
    return sendMessage({
        media_path: uploaded.media_path,
        media_url: uploaded.media_url,
        media_type: uploaded.media_type,
        media_kind: 'voice',
        media_duration: durationSeconds,
    });
};

const createDirectConversation = async (userId) => {
    const response = await window.axios.post('/chat/conversations/direct', {
        user_id: Number(userId),
    });

    await fetchConversations();
    await fetchUserDirectory();
    const id = response.data?.data?.id;
    if (id) {
        await selectConversation(id);
    }

    return id;
};

const createGroupConversation = async ({ name, userIds }) => {
    const response = await window.axios.post('/chat/conversations/group', {
        name,
        user_ids: userIds,
    });

    await fetchConversations();
    const id = response.data?.data?.id;
    if (id) {
        await selectConversation(id);
    }
    return id;
};

const sendTyping = () => {
    const conversationId = state.activeConversationId;
    if (!conversationId) return;

    const channel = state.channelRefs[conversationId]?.presenceChannel;
    if (!channel?.whisper) return;

    channel.whisper('typing', {
        user_id: state.currentUserId,
        name: state.currentUsername,
        at: Date.now(),
    });
};

const init = async ({ currentUserId, currentUsername, sendSound, receiveSound }) => {
    if (state.initialized) return;

    state.currentUserId = Number(currentUserId);
    state.currentUsername = currentUsername || 'You';
    state.initialized = true;

    window.addEventListener('online', handleReactionOnline);
    window.addEventListener('focus', handleReactionOnline);
    document.addEventListener('visibilitychange', handleReactionVisibility);

    soundConfig.sendSound = sendSound || null;
    soundConfig.receiveSound = receiveSound || null;

    if (window.Echo && !state.userChannelRef) {
        state.userChannelRef = window.Echo.private(`App.Models.User.${state.currentUserId}`);
        state.userChannelRef.listen('.chat.conversation.updated', (event) => {
            const updatedConversationId = Number(event?.conversation_id || 0);

            // Coalesce bursts of conversation updates into a single refresh.
            scheduleConversationRefresh();

            if (!updatedConversationId) return;

            // Subscribe to the new/updated conversation so we start receiving its messages.
            // Do NOT force-fetch messages here — .chat.message.delivered already upserts
            // the message directly, so a full re-fetch would cause a spurious
            // "loading older messages" / "fetching messages" flash.
            ensureConversationArrays(updatedConversationId);
            subscribeConversation(updatedConversationId);
        });

        state.userChannelRef.listen('.chat.message.delivered', (event) => {
            const conversationId = Number(event?.conversation_id || 0);
            const message = event?.message || null;

            if (!conversationId || !message) return;

            ensureConversationArrays(conversationId);
            subscribeConversation(conversationId);

            // Show the message immediately; refresh sidebar metadata in the background.
            upsertMessage(conversationId, message);
            scheduleConversationRefresh();

            if (Number(state.activeConversationId) === conversationId) {
                markConversationRead(conversationId);
            }
        });
    }

    // Purge expired cache entries, then hydrate from cache for instant render
    purgeExpired();

    const cachedConversations = await getCachedConversations();
    if (cachedConversations && cachedConversations.length > 0) {
        state.conversations = cachedConversations;
        sortConversations();
        broadcastUnreadCount();

        // Restore the previously active conversation from cache
        const storedId = Number(localStorage.getItem(ACTIVE_CONV_KEY) || 0);
        const activeId = storedId || state.conversations[0]?.id;
        if (activeId) {
            state.activeConversationId = Number(activeId);
            ensureConversationArrays(activeId);

            // Load cached messages for the active conversation
            const cachedMessages = await getCachedMessages(activeId);
            if (cachedMessages && cachedMessages.length > 0) {
                state.messagesByConversation[activeId] = cachedMessages;
                const meta = state.messageMetaByConversation[activeId];
                meta.loaded = true;
                meta.oldestMessageId = cachedMessages[0]?.id || null;
            }

            // Subscribe channels for all cached conversations
            state.conversations.forEach((conversation) => {
                ensureConversationArrays(conversation.id);
                subscribeConversation(conversation.id);
            });
        }

        // Mark loading as done so UI renders the cached data immediately
        state.loadingConversations = false;
    }

    // Fetch fresh data from server in background, replacing cache. Failures are
    // expected while offline; cached data stays visible and reactions queue up.
    await Promise.all([
        fetchConversations({ silent: cachedConversations?.length > 0 }).catch(() => {}),
        fetchUserDirectory().catch(() => {}),
    ]);

    // Replay reactions queued while offline, then reconcile those conversations.
    flushPendingReactions().catch(() => {});
};

export const useMessageStore = () => {
    const activeConversation = computed(() => {
        return state.conversations.find((item) => Number(item.id) === Number(state.activeConversationId)) || null;
    });

    const activeMessages = computed(() => {
        if (!state.activeConversationId) return [];
        return state.messagesByConversation[state.activeConversationId] || [];
    });

    const typingUsers = computed(() => {
        if (!state.activeConversationId) return [];
        const map = state.typingUsersByConversation[state.activeConversationId] || {};
        return Object.values(map);
    });

    const onlineUsers = computed(() => {
        if (!state.activeConversationId) return [];
        const map = state.onlineUsersByConversation[state.activeConversationId] || {};
        return Object.values(map);
    });

    return {
        state,
        activeConversation,
        activeMessages,
        typingUsers,
        onlineUsers,
        init,
        fetchConversations,
        fetchUserDirectory,
        selectConversation,
        fetchOlderMessages,
        sendMessage,
        editMessage,
        deleteMessage,
        uploadMedia,
        sendVoiceMessage,
        createDirectConversation,
        createGroupConversation,
        sendTyping,
        markConversationRead,
        toggleReaction,
        setReplyTo,
        clearReplyTo,
    };
};
