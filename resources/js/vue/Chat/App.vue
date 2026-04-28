<script setup>
import { onMounted, ref } from 'vue';
import Message from './layout/Message.vue';
import Nav from './layout/Nav.vue';
import CreateGroupChat from './components/modals/CreateGroupChat.vue';
import { useMessageStore } from './store/useMessageStore';

const store = useMessageStore();
const mobileView = ref('nav');
const showCreateGroup = ref(false);

const openChat = () => {
    mobileView.value = 'chat';
};

const goBackToNav = () => {
    mobileView.value = 'nav';
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
    window.addEventListener('popstate', handleUrlNavigation);

    // Render icons after initial mount
    triggerIconRender();
});

// Extract conversation ID from URL path or user_id from query parameter
const handleUrlNavigation = async () => {
    const path = window.location.pathname;
    const searchParams = new URLSearchParams(window.location.search);

    // Check for direct conversation ID in URL path (/chat/123)
    const pathMatch = path.match(/\/chat\/(\d+)$/);
    if (pathMatch && pathMatch[1]) {
        const conversationId = Number(pathMatch[1]);
        await store.selectConversation(conversationId);
        mobileView.value = 'chat';
        return;
    }

    // Check for user_id query parameter (/chat?user_id=123)
    const userId = searchParams.get('user_id');
    if (userId) {
        try {
            await store.createDirectConversation(Number(userId));
            mobileView.value = 'chat';
            // Clean up the query parameter from URL
            window.history.replaceState({}, '', '/chat');
        } catch (error) {
            console.error('Failed to create conversation:', error);
        }
        return;
    }

    // Default: restore from localStorage or select first conversation
    const storedId = Number(localStorage.getItem('chat_active_conversation_id') || 0);
    if (storedId && store.state.conversations.length > 0) {
        const conversationExists = store.state.conversations.some(c => Number(c.id) === storedId);
        if (conversationExists) {
            await store.selectConversation(storedId);
        }
    }
};
</script>

<template>
    <div v-lucide class="bg-base-100 rounded-lg grid h-full min-h-0 w-full grid-cols-1 overflow-hidden md:grid-cols-[360px_1fr]">
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
        />
        <CreateGroupChat
            v-if="showCreateGroup"
            @close="showCreateGroup = false"
            @created="showCreateGroup = false"
        />
    </div>
</template>
