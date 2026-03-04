import { computed, reactive } from 'vue';

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
});

const typingTimeouts = {};
let conversationsSyncInterval = null;

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
};

const subscribeConversation = (conversationId) => {
    if (!window.Echo || state.channelRefs[conversationId]) return;

    const privateChannel = window.Echo.private(`conversation.${conversationId}`);
    privateChannel.listen('.message.sent', (event) => {
        upsertMessage(conversationId, event.message);

        if (Number(state.activeConversationId) === Number(conversationId)) {
            markConversationRead(conversationId);
        }
    });

    privateChannel.listen('.message.read', (event) => {
        applyReadReceipt(conversationId, event.message_id, event.reader, event.read_at);
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

const fetchConversations = async ({ silent = false } = {}) => {
    if (!silent) {
        state.loadingConversations = true;
    }

    const previousActiveId = Number(state.activeConversationId || 0);

    try {
        const response = await window.axios.get('/chat/conversations');
        state.conversations = response.data?.data || [];

        state.conversations.forEach((conversation) => {
            ensureConversationArrays(conversation.id);
            subscribeConversation(conversation.id);
        });

        sortConversations();

        const activeStillExists = state.conversations.some((conversation) => Number(conversation.id) === previousActiveId);

        if (activeStillExists) {
            state.activeConversationId = previousActiveId;
        } else if (state.conversations.length > 0) {
            await selectConversation(state.conversations[0].id);
        }
    } finally {
        if (!silent) {
            state.loadingConversations = false;
        }
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

const fetchMessages = async (conversationId, { beforeId = null, limit = 40, force = false } = {}) => {
    ensureConversationArrays(conversationId);

    const meta = state.messageMetaByConversation[conversationId];
    const isPaginatingOlder = Boolean(beforeId);

    if (meta.loading) return;
    if (!force && !isPaginatingOlder && meta.loaded) return;

    meta.loading = true;
    state.loadingMessages = !isPaginatingOlder && Number(state.activeConversationId) === Number(conversationId);

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
    const lastMessage = list[list.length - 1];
    if (!lastMessage?.id) return;

    await window.axios.post(`/chat/conversations/${conversationId}/read`, {
        last_message_id: lastMessage.id,
    });
};

const selectConversation = async (conversationId) => {
    state.activeConversationId = Number(conversationId);
    ensureConversationArrays(conversationId);
    const meta = state.messageMetaByConversation[conversationId];

    subscribeConversation(conversationId);

    if (!meta.loaded) {
        await fetchMessages(conversationId, { force: true });
    }

    await markConversationRead(conversationId);
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
            client_uuid: clientUuid,
        });

        upsertMessage(conversationId, response.data.data);
        await markConversationRead(conversationId);
        await fetchUserDirectory();
        return response.data.data;
    } catch (error) {
        state.messagesByConversation[conversationId] = (state.messagesByConversation[conversationId] || [])
            .filter((message) => message.client_uuid !== clientUuid && message.id !== clientUuid);

        throw error;
    }
};

const uploadMedia = async (file, mediaKind = 'media') => {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('media_kind', mediaKind);

    const response = await window.axios.post('/chat/media', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return response.data.data;
};

const sendVoiceMessage = async (blob) => {
    const voiceFile = new File([blob], `voice-${Date.now()}.webm`, {
        type: blob.type || 'audio/webm',
    });

    const uploaded = await uploadMedia(voiceFile, 'voice');
    return sendMessage({
        media_path: uploaded.media_path,
        media_url: uploaded.media_url,
        media_type: uploaded.media_type,
        media_kind: 'voice',
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

const init = async ({ currentUserId, currentUsername }) => {
    if (state.initialized) return;

    state.currentUserId = Number(currentUserId);
    state.currentUsername = currentUsername || 'You';
    state.initialized = true;

    if (window.Echo && !state.userChannelRef) {
        state.userChannelRef = window.Echo.private(`App.Models.User.${state.currentUserId}`);
        state.userChannelRef.listen('.chat.conversation.updated', async (event) => {
            const updatedConversationId = Number(event?.conversation_id || 0);
            await fetchConversations({ silent: true });

            if (!updatedConversationId) return;

            ensureConversationArrays(updatedConversationId);
            if (Number(state.activeConversationId) === updatedConversationId) {
                await fetchMessages(updatedConversationId, { force: true });
                await markConversationRead(updatedConversationId);
            }
        });

        state.userChannelRef.listen('.chat.message.delivered', async (event) => {
            const conversationId = Number(event?.conversation_id || 0);
            const message = event?.message || null;

            if (!conversationId || !message) return;

            await fetchConversations({ silent: true });

            ensureConversationArrays(conversationId);
            subscribeConversation(conversationId);
            upsertMessage(conversationId, message);

            if (Number(state.activeConversationId) === conversationId) {
                await markConversationRead(conversationId);
            }
        });
    }

    if (!conversationsSyncInterval) {
        conversationsSyncInterval = setInterval(() => {
            fetchConversations({ silent: true });
        }, 8000);
    }

    await Promise.all([
        fetchConversations(),
        fetchUserDirectory(),
    ]);
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
        uploadMedia,
        sendVoiceMessage,
        createDirectConversation,
        sendTyping,
        markConversationRead,
    };
};
