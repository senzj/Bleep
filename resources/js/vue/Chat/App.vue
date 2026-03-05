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

onMounted(() => {
    const root = document.getElementById('chat-app');
    const currentUserId = Number(root?.dataset.userId || 0);
    const currentUsername = root?.dataset.username || 'You';

    if (currentUserId) {
        store.init({ currentUserId, currentUsername });
    }
});
</script>

<template>
    <div class="bg-base-100 rounded-lg grid h-full min-h-0 w-full grid-cols-1 overflow-hidden md:grid-cols-[360px_1fr]">
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
