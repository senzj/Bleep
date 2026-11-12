<script setup>
import { computed, ref } from 'vue';
import MessageInput from '../components/input/MessageInput.vue';
import MessageList from '../components/messages/MessageList.vue';
import { useMessageStore } from '../store/useMessageStore';

const emit = defineEmits(['go-back']);

const store = useMessageStore();

const leavingGroup = ref(false);
const leaveError = ref('');

const isGroupConversation = computed(() => Boolean(store.activeConversation.value?.is_group));

const hasLeftGroup = computed(() => {
	const conversation = store.activeConversation.value;
	return Boolean(conversation?.is_group && conversation?.has_left);
});

const handleLeaveGroup = async () => {
	const conversation = store.activeConversation.value;
	if (!conversation || leavingGroup.value) return;

	const confirmed = window.confirm(
		`Leave "${conversation.title || 'this group chat'}"? You can still read the history, but you will not be able to send messages.`
	);

	if (!confirmed) return;

	leavingGroup.value = true;
	leaveError.value = '';

	try {
		await store.leaveConversation(conversation.id);
	} catch (error) {
		leaveError.value = error?.response?.data?.message || 'Could not leave this group chat.';
	} finally {
		leavingGroup.value = false;
	}
};

const getOtherParticipant = (conversation) => {
	if (!conversation) return null;
	const participants = conversation.participants || [];
	return participants.find((participant) => Number(participant.id) !== Number(store.state.currentUserId)) || null;
};

const typingText = computed(() => {
	const names = store.typingUsers.value;
	if (!names.length) return '';

	if (names.length === 1) return `${names[0]} is typing...`;
	if (names.length === 2) return `${names[0]} and ${names[1]} are typing...`;
	return `${names.length} people are typing...`;
});

const headerAvatarUrl = computed(() => {
	const conversation = store.activeConversation.value;
	if (!conversation) return '/images/avatar/default.jpg';

	const otherParticipant = getOtherParticipant(conversation);
	return otherParticipant?.profile_picture_url || '/images/avatar/default.jpg';
});

const formatActiveAgo = (isoString) => {
	if (!isoString) return 'Active recently';

	const lastActive = new Date(isoString).getTime();
	if (Number.isNaN(lastActive)) return 'Active recently';

	const diffSeconds = Math.max(0, Math.floor((Date.now() - lastActive) / 1000));
	if (diffSeconds < 60) return 'Active just now';

	const diffMinutes = Math.floor(diffSeconds / 60);
	if (diffMinutes < 60) return `Active ${diffMinutes}m ago`;

	const diffHours = Math.floor(diffMinutes / 60);
	if (diffHours < 24) return `Active ${diffHours}h ago`;

	const diffDays = Math.floor(diffHours / 24);
	if (diffDays < 7) return `Active ${diffDays}d ago`;

	const diffWeeks = Math.floor(diffDays / 7);
	if (diffWeeks < 4) return `Active ${diffWeeks}w ago`;

	const diffMonths = Math.floor(diffDays / 30);
	if (diffMonths < 12) return `Active ${diffMonths}mo ago`;

	const diffYears = Math.floor(diffDays / 365);
	return `Active ${diffYears}y ago`;
};

const headerStatusText = computed(() => {
	const conversation = store.activeConversation.value;
	if (!conversation) return '0 online';

	if (conversation.is_group) {
		return `${store.onlineUsers.value.length} online`;
	}

	const otherParticipant = getOtherParticipant(conversation);
	if (!otherParticipant) return 'Active recently';

	const isPresenceOnline = store.onlineUsers.value.some((user) => Number(user.id) === Number(otherParticipant.id));
	const isSessionOnline = Boolean(otherParticipant.is_online);
	const isOnlineNow = isPresenceOnline || isSessionOnline;
	if (isOnlineNow) return 'Online';

	return formatActiveAgo(otherParticipant.last_seen_at || otherParticipant.last_read_at);
});

const showOnlineIndicator = computed(() => {
	const conversation = store.activeConversation.value;
	if (!conversation || conversation.is_group) return false;

	const otherParticipant = getOtherParticipant(conversation);
	if (!otherParticipant) return false;

	const isPresenceOnline = store.onlineUsers.value.some((user) => Number(user.id) === Number(otherParticipant.id));
	return isPresenceOnline || Boolean(otherParticipant.is_online);
});

const activeConversationMeta = computed(() => {
	const conversationId = Number(store.state.activeConversationId || 0);
	if (!conversationId) {
		return {
			loaded: false,
			hasMore: false,
			loading: false,
			oldestMessageId: null,
		};
	}

	return store.state.messageMetaByConversation[conversationId] || {
		loaded: false,
		hasMore: false,
		loading: false,
		oldestMessageId: null,
	};
});

const handleLoadOlderMessages = async () => {
	const conversationId = Number(store.state.activeConversationId || 0);
	if (!conversationId) return;

	await store.fetchOlderMessages(conversationId);
};

const handleEditMessage = async (payload) => {
	await store.editMessage(payload);
};

const handleDeleteMessage = async (messageId) => {
	await store.deleteMessage(messageId);
};

const handleReplyMessage = (message) => {
	store.setReplyTo(message);
};
</script>

<template>
	<section class="bg-base-200 flex h-full min-h-0 flex-1 flex-col overflow-hidden">
		<header class="border-base-300 bg-base-100 sticky top-0 z-10 shrink-0 flex items-center justify-between border-b px-4 py-3">
			<div class="flex items-center gap-3">
				<button class="btn btn-ghost btn-sm btn-circle md:hidden" @click="emit('go-back')">
					<i data-lucide="arrow-left" class="lucide lucide-sm"></i>
				</button>

                <div class="relative">
					<img
						:src="headerAvatarUrl"
						:alt="`${store.activeConversation.value?.title || 'Conversation'} avatar`"
						class="h-10 w-10 rounded-full object-cover"
					>
                    <span
                        v-if="showOnlineIndicator"
                        class="absolute bottom-0 right-0 h-3.5 w-3.5 rounded-full border-2 border-base-100"
                        :class="showOnlineIndicator ? 'bg-green-500' : 'bg-gray-500'"
                    ></span>
				</div>

				<div>
					<h3 class="font-semibold">
                        {{ store.activeConversation.value?.title || 'Select a conversation' }}
					</h3>
					<p class="text-base-content/70 text-xs">
						{{ headerStatusText }}
					</p>
				</div>
			</div>

			<button
				v-if="isGroupConversation && !hasLeftGroup"
				type="button"
				class="btn btn-ghost btn-sm text-error"
				:disabled="leavingGroup"
				@click="handleLeaveGroup"
			>
				<span v-if="leavingGroup" class="loading loading-spinner loading-xs"></span>
				<i v-else data-lucide="log-out" class="lucide lucide-sm"></i>
				<span class="hidden sm:inline">Leave</span>
			</button>
		</header>

		<MessageList
			class="min-h-0 flex-1"
			:messages="store.activeMessages.value"
			:current-user-id="store.state.currentUserId"
			:conversation-id="Number(store.state.activeConversationId || 0)"
			:loading="store.state.loadingMessages"
			:loaded="activeConversationMeta.loaded"
			:has-more="activeConversationMeta.hasMore"
			:loading-older="activeConversationMeta.loading && !!activeConversationMeta.oldestMessageId"
            :participants="store.activeConversation.value?.participants || []"
            @load-older="handleLoadOlderMessages"
            @edit-message="handleEditMessage"
            @delete-message="handleDeleteMessage"
            @reply-message="handleReplyMessage"
	    />

        <div class="bg-base-100 shrink-0">
            <p v-if="leaveError" class="border-base-300 border-t px-4 py-2 text-xs text-error">
                {{ leaveError }}
            </p>

            <p v-if="typingText && !hasLeftGroup" class="border-base-300 border-t px-4 py-1.5 text-xs italic opacity-70">
                <span class="loading loading-dots loading-md mr-1"></span>
                {{ typingText }}
            </p>

            <div v-if="hasLeftGroup" class="border-base-300 border-t px-4 py-4 text-center">
                <p class="text-sm font-medium">You have left this Group chat</p>
                <p class="text-base-content/70 mt-1 text-xs">
                    You can still read the history. Rejoin is not available yet.
                </p>
            </div>

            <MessageInput v-else />
        </div>
    </section>
</template>
