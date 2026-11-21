<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Message from './layout/Message.vue';
import Nav from './layout/Nav.vue';
import CreateGroupChat from './components/modals/CreateGroupChat.vue';
import ChatSettings from './components/settings/ChatSettings.vue';
import { useMessageStore } from './store/useMessageStore';

const store = useMessageStore();
const mobileView = ref('nav');
const showCreateGroup = ref(false);
const settingsOpen = ref(false);

const gridClass = computed(() => {
    if (settingsOpen.value) {
        return 'grid-cols-1 md:grid-cols-[340px_1fr] lg:grid-cols-[300px_1fr_320px] xl:grid-cols-[340px_1fr_340px]';
    }

    return 'grid-cols-1 md:grid-cols-[360px_1fr]';
});

const activeConversation = computed(() => store.activeConversation.value);

const openChat = () => {
    mobileView.value = 'chat';
};

const goBackToNav = () => {
    // Only step back when this entry was pushed from the chat list; otherwise
    // browser history would leave the chat page entirely (direct link load).
    if (window.history.state?.fromChatList) {
        window.history.back();
        return;
    }

    mobileView.value = 'nav';
    window.history.replaceState({}, '', '/chat');
};

// Trigger icon rendering after a slight delay to ensure DOM is ready
const triggerIconRender = () => {
    if (window.lucide && window.lucide.replace) {
        // Use nextTick via setTimeout to ensure DOM is updated
        setTimeout(() => {
            window.lucide.replace();
        }, 0);
    }
};

const onPopState = () => {
    handleUrlNavigation(true);
};

onMounted(async () => {
    const root = document.getElementById('chat-app');
    const currentUserId = Number(root?.dataset.userId || 0);
    const currentUsername = root?.dataset.username || 'You';
    const sendSound = root?.dataset.sendSound || null;
    const receiveSound = root?.dataset.receiveSound || null;

    if (currentUserId) {
        await store.init({ currentUserId, currentUsername, sendSound, receiveSound });
    }

    // Handle URL-based conversation selection
    await handleUrlNavigation();

    // Listen for browser back/forward navigation
    window.addEventListener('popstate', onPopState);

    // Render icons after initial mount
    triggerIconRender();
});

onBeforeUnmount(() => {
    window.removeEventListener('popstate', onPopState);
});

// Extract conversation ID from URL path or user_id from query parameter
const handleUrlNavigation = async (isPopState = false) => {
    const path = window.location.pathname;
    const searchParams = new URLSearchParams(window.location.search);

    // Check for direct conversation ID in URL path (/chat/123)
    const pathMatch = path.match(/\/chat\/(\d+)$/);
    if (pathMatch && pathMatch[1]) {
        const conversationId = Number(pathMatch[1]);
        // The URL already points here, so replace the entry instead of pushing a copy.
        await store.selectConversation(conversationId, { historyMode: 'replace' });
        mobileView.value = 'chat';
        return;
    }

    // Check for user_id query parameter (/chat?user_id=123)
    const userId = searchParams.get('user_id');
    if (userId) {
        try {
            const conversationId = await store.createDirectConversation(Number(userId));
            mobileView.value = 'chat';
            // Drop the one-off query parameter but keep the conversation URL.
            if (conversationId) {
                window.history.replaceState(
                    { conversationId: Number(conversationId) },
                    '',
                    `/chat/${conversationId}`
                );
            } else {
                window.history.replaceState({}, '', '/chat');
            }
        } catch (error) {
            console.error('Failed to create conversation:', error);
        }
        return;
    }

    // Back/forward navigation landed on the plain chat list: show the nav pane
    // and leave the previously selected conversation untouched on desktop.
    if (isPopState) {
        mobileView.value = 'nav';
        return;
    }

    // Default: restore from localStorage or select first conversation
    const storedId = Number(localStorage.getItem('chat_active_conversation_id') || 0);
    if (storedId && store.state.conversations.length > 0) {
        const conversationExists = store.state.conversations.some(c => Number(c.id) === storedId);
        if (conversationExists) {
            // Push so the browser Back button returns to the chat list.
            await store.selectConversation(storedId);
        }
    }
};
</script>

<template>
    <div
        v-lucide
        class="bg-base-100 rounded-lg grid h-full min-h-0 w-full min-w-0 grid-rows-1"
        :class="gridClass"
    >
        <Nav
            class="min-h-0"
            :class="mobileView === 'nav' ? 'flex' : 'hidden md:flex'"
            @open-chat="openChat"
            @create-group="showCreateGroup = true"
        />
        <Message
            class="min-h-0"
            :class="mobileView === 'chat' ? 'flex' : 'hidden md:flex'"
            @go-back="goBackToNav"
            @open-settings="settingsOpen = true"
        />

        <div
            v-if="settingsOpen"
            class="fixed inset-0 z-40 bg-black/40 lg:hidden"
            aria-hidden="true"
            @click="settingsOpen = false"
        ></div>

        <div
            v-if="settingsOpen && activeConversation"
            class="chat-settings-panel fixed inset-y-0 right-0 z-50 w-[92vw] max-w-sm shadow-2xl lg:static lg:z-auto lg:w-auto lg:max-w-none lg:shadow-none"
        >
            <ChatSettings :conversation="activeConversation" @close="settingsOpen = false" />
        </div>

        <CreateGroupChat
            v-if="showCreateGroup"
            @close="showCreateGroup = false"
            @created="showCreateGroup = false"
        />
    </div>
</template>
