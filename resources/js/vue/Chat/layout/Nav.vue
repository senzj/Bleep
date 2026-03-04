<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import SearchBar from '../components/sidebar/SearchBar.vue';
import { useMessageStore } from '../store/useMessageStore';

const store = useMessageStore();
const query = ref('');
let searchTimer = null;

const filteredConversations = computed(() => {
	const keyword = query.value.trim().toLowerCase();
	if (!keyword) return store.state.conversations;

	return store.state.conversations.filter((conversation) => {
		const title = (conversation.title || '').toLowerCase();
		const participantMatch = (conversation.participants || []).some((participant) => {
			const dname = (participant.dname || '').toLowerCase();
			const username = (participant.username || '').toLowerCase();
			return dname.includes(keyword) || username.includes(keyword);
		});

		return title.includes(keyword) || participantMatch;
	});
});

const filteredUsers = computed(() => {
	const activeDmUserIds = new Set(
		store.state.conversations
			.filter((conversation) => !conversation.is_group)
			.flatMap((conversation) => (conversation.participants || []).map((participant) => Number(participant.id)))
			.filter((id) => id !== Number(store.state.currentUserId)),
	);

	return (store.state.userDirectory || []).filter((user) => !activeDmUserIds.has(Number(user.id)));
});

const mergedList = computed(() => {
	const conversationItems = filteredConversations.value.map((conversation) => ({
		type: 'conversation',
		id: `conversation-${conversation.id}`,
		conversation,
	}));

	const userItems = filteredUsers.value.map((user) => ({
		type: 'user',
		id: `user-${user.id}`,
		user,
	}));

	return [...conversationItems, ...userItems];
});

watch(query, (value) => {
	clearTimeout(searchTimer);
	searchTimer = setTimeout(() => {
		store.fetchUserDirectory(value.trim());
	}, 250);
});

onBeforeUnmount(() => {
	clearTimeout(searchTimer);
});

const getConversationAvatar = (conversation) => {
	const participants = conversation?.participants || [];
	const otherParticipant = participants.find((participant) => Number(participant.id) !== Number(store.state.currentUserId));
	return otherParticipant?.profile_picture_url || '/images/avatar/default.jpg';
};

const getConversationOnlineState = (conversation) => {
	if (!conversation || conversation.is_group) return null;

	const participants = conversation.participants || [];
	const otherParticipant = participants.find((participant) => Number(participant.id) !== Number(store.state.currentUserId));
	if (!otherParticipant) return null;

	return Boolean(otherParticipant.is_online);
};

const getUserOnlineState = (user) => {
	return Boolean(user?.is_online);
};

const formatConversationTime = (isoString) => {
	if (!isoString) return '';

	const date = new Date(isoString);
	if (Number.isNaN(date.getTime())) return '';

	const now = new Date();
	const sameDay = date.toDateString() === now.toDateString();
	if (sameDay) {
		return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
	}

	const diffDays = Math.floor((now.getTime() - date.getTime()) / 86400000);
	if (diffDays < 7) {
		return date.toLocaleDateString([], { weekday: 'short' });
	}

	return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
};


const emit = defineEmits(['open-chat']);

const handleSelectConversation = (conversationId) => {
	store.selectConversation(conversationId);
	emit('open-chat');
};

const startDirectMessage = async (userId) => {
	try {
		await store.createDirectConversation(userId);
		query.value = '';
		emit('open-chat');
	} catch {
		window.alert('Could not create conversation.');
	}
};
</script>

<template>
	<aside class="border-base-300 bg-base-100 flex h-full flex-col border-r p-4">
		<div class="mb-3">
			<h2 class="text-lg font-semibold">Chats</h2>
		</div>

		<SearchBar v-model="query" />

		<div class="max-h-[calc(100dvh-220px)] space-y-2 overflow-y-auto">
			<template v-for="item in mergedList" :key="item.id">
				<button
					v-if="item.type === 'conversation'"
					class="w-full rounded-lg border p-3 text-left cursor-pointer"
					:class="Number(store.state.activeConversationId) === Number(item.conversation.id) ? 'border-primary bg-base-200' : 'border-base-300 bg-base-100'"
					@click="handleSelectConversation(item.conversation.id)"
				>
					<div class="flex items-center gap-2">
						<div class="relative">
							<img
								:src="getConversationAvatar(item.conversation)"
								:alt="`${item.conversation.title} avatar`"
								class="h-9 w-9 rounded-full object-cover"
							>
							<span
								v-if="getConversationOnlineState(item.conversation) !== null"
								class="absolute -right-0.5 -bottom-0.5 h-2.5 w-2.5 rounded-full border border-base-100"
								:class="getConversationOnlineState(item.conversation) ? 'bg-success' : 'bg-base-content/30'"
							/>
						</div>
						<div class="min-w-0 flex-1">
							<div class="flex items-center justify-between gap-2">
								<p class="truncate text-sm font-semibold">{{ item.conversation.title }}</p>
								<p class="text-base-content/60 shrink-0 text-[11px]">
									{{ formatConversationTime(item.conversation.last_message_at) }}
								</p>
							</div>
							<p class="text-base-content/70 truncate text-xs">
								{{ item.conversation.last_message?.body || (item.conversation.last_message ? 'Media' : 'No messages yet') }}
							</p>
						</div>
					</div>
				</button>

				<button
					v-else
					class="border-base-300 hover:bg-base-200 w-full rounded-lg border px-3 py-2 text-left"
					@click="startDirectMessage(item.user.id)"
				>
					<div class="flex items-center justify-between gap-2">
						<div class="flex min-w-0 items-center gap-2">
							<div class="relative">
								<img
									:src="item.user.profile_picture_url"
									:alt="`${item.user.username} avatar`"
									class="h-9 w-9 rounded-full object-cover"
								>
								<span
									class="absolute -right-0.5 -bottom-0.5 h-2.5 w-2.5 rounded-full border border-base-100"
									:class="getUserOnlineState(item.user) ? 'bg-success' : 'bg-base-content/30'"
								/>
							</div>
							<div class="min-w-0">
								<p class="truncate text-sm font-medium">{{ item.user.dname || item.user.username }}</p>
								<p class="text-base-content/70 truncate text-xs">@{{ item.user.username }}</p>
							</div>
						</div>
						<span class="badge badge-outline text-[11px]">{{ item.user.relation_label }}</span>
					</div>
				</button>
			</template>

			<p v-if="store.state.loadingUsers" class="text-base-content/70 p-2 text-xs">Loading...</p>
			<p v-else-if="!mergedList.length" class="text-base-content/70 p-2 text-xs">No chats or users found.</p>
		</div>
	</aside>
</template>
