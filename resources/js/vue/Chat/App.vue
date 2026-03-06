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

onMounted(() => {
    const root = document.getElementById('chat-app');
    const currentUserId = Number(root?.dataset.userId || 0);
    const currentUsername = root?.dataset.username || 'You';
    const sendSound = root?.dataset.sendSound || null;
    const receiveSound = root?.dataset.receiveSound || null;

    if (currentUserId) {
        store.init({ currentUserId, currentUsername, sendSound, receiveSound });
    }

    // Render icons after initial mount
    triggerIconRender();
});
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
