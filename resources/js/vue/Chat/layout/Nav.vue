<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import SearchBar from '../components/sidebar/SearchBar.vue';
import { useMessageStore } from '../store/useMessageStore';
import LucideIcons from '../../LucideIcons.vue';

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

const getConversationOtherParticipant = (conversation) => {
	const participants = conversation?.participants || [];
	return participants.find((p) => Number(p.id) !== Number(store.state.currentUserId)) || null;
};

// Returns up to 4 participants (excluding self) for the group avatar grid.
const getGroupAvatarParticipants = (conversation) => {
	return (conversation?.participants || [])
		.filter((p) => Number(p.id) !== Number(store.state.currentUserId))
		.slice(0, 4);
};

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

	const presenceMap = store.state.onlineUsersByConversation[conversation.id] || {};
	const isPresenceOnline = Boolean(presenceMap[otherParticipant.id]);

	return isPresenceOnline || Boolean(otherParticipant.is_online);
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


const emit = defineEmits(['open-chat', 'create-group']);

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
		<div class="mb-3 flex items-center justify-between">
			<h2 class="text-lg font-semibold">Chats</h2>

            <!-- Create Group Button -->
            <button class="btn btn-primary btn-sm rounded-md" @click="emit('create-group')">
                <i data-lucide="users" class="w-4 h-4"></i>
                <span class="hidden sm:inline">Create Group</span>
            </button>
		</div>

		<SearchBar v-model="query" />

		<div class="max-h-full space-y-2 overflow-y-auto">
			<template v-for="item in mergedList" :key="item.id">
				<button
					v-if="item.type === 'conversation'"
					class="w-full rounded-lg border p-3 text-left cursor-pointer"
					:class="Number(store.state.activeConversationId) === Number(item.conversation.id) ? 'border-primary bg-base-200' : 'border-base-300 bg-base-100'"
					@click="handleSelectConversation(item.conversation.id)"
				>
					<div class="flex items-center gap-2">
						<!-- Avatar: group photo grid vs user photo -->
						<div class="relative shrink-0">
							<div
								v-if="item.conversation.is_group"
								class="h-10 w-10 rounded-full overflow-hidden bg-base-300"
							>
								<!-- 1 photo: full circle -->
								<template v-if="getGroupAvatarParticipants(item.conversation).length === 1">
									<img
										:src="getGroupAvatarParticipants(item.conversation)[0].profile_picture_url || '/images/avatar/default.jpg'"
										class="h-full w-full object-cover"
									/>
								</template>
								<!-- 2 photos: side by side -->
								<template v-else-if="getGroupAvatarParticipants(item.conversation).length === 2">
									<div class="flex h-full w-full">
										<img
											v-for="p in getGroupAvatarParticipants(item.conversation)"
											:key="p.id"
											:src="p.profile_picture_url || '/images/avatar/default.jpg'"
											class="h-full w-1/2 object-cover"
										/>
									</div>
								</template>
								<!-- 3 photos: left half + right column of 2 -->
								<template v-else-if="getGroupAvatarParticipants(item.conversation).length === 3">
									<div class="flex h-full w-full">
										<img
											:src="getGroupAvatarParticipants(item.conversation)[0].profile_picture_url || '/images/avatar/default.jpg'"
											class="h-full w-1/2 object-cover"
										/>
										<div class="flex h-full w-1/2 flex-col">
											<img
												:src="getGroupAvatarParticipants(item.conversation)[1].profile_picture_url || '/images/avatar/default.jpg'"
												class="h-1/2 w-full object-cover"
											/>
											<img
												:src="getGroupAvatarParticipants(item.conversation)[2].profile_picture_url || '/images/avatar/default.jpg'"
												class="h-1/2 w-full object-cover border-t border-base-100/40"
											/>
										</div>
									</div>
								</template>
								<!-- 4 photos: 2×2 grid -->
								<template v-else-if="getGroupAvatarParticipants(item.conversation).length >= 4">
									<div class="grid h-full w-full grid-cols-2 grid-rows-2">
										<img
											v-for="p in getGroupAvatarParticipants(item.conversation)"
											:key="p.id"
											:src="p.profile_picture_url || '/images/avatar/default.jpg'"
											class="h-full w-full object-cover"
										/>
									</div>
								</template>
								<!-- 0 photos fallback: icon -->
								<template v-else>
									<div class="text-base-content/60 flex h-full w-full items-center justify-center">
										<i data-lucide="users" class="lucide h-5 w-5"></i>
									</div>
								</template>
							</div>
							<template v-else>
								<img
									:src="getConversationAvatar(item.conversation)"
									:alt="`${item.conversation.title}'s avatar`"
									class="h-10 w-10 rounded-full object-cover"
								>
								<span
									v-if="getConversationOnlineState(item.conversation) !== null"
									class="absolute -right-0.5 -bottom-0.5 h-3.5 w-3.5 rounded-full border-2 border-base-100"
									:class="getConversationOnlineState(item.conversation) ? 'bg-green-500' : 'bg-gray-500'"
								/>
							</template>
						</div>

						<div class="min-w-0 flex-1">
							<div class="flex items-start justify-between gap-2">
								<div class="min-w-0 flex items-center gap-2">
                                    <p class="truncate text-sm font-semibold">
                                        {{ item.conversation.title }}

                                    </p>
                                    <p
                                        v-if="!item.conversation.is_group && getConversationOtherParticipant(item.conversation)"
                                        class="text-base-content/40 truncate text-xs"
                                    >
                                        @{{ getConversationOtherParticipant(item.conversation).username }}
                                    </p>
								</div>

								<div class="flex shrink-0 flex-col items-end">
									<span
										v-if="item.conversation.unread_count > 0"
										class="badge badge-error badge-xs min-w-5 text-white font-semibold"
									>
										{{ item.conversation.unread_count > 99 ? '99+' : item.conversation.unread_count }}
									</span>
								</div>
							</div>

							<div class="flex items-center justify-between gap-2">
                                <p class="text-base-content/70 truncate text-xs ml-1">
                                    {{ item.conversation.last_message?.body || (item.conversation.last_message ? 'Media' : 'No messages yet') }}
                                </p>

                                <p class="text-base-content/60 text-[11px]">
                                    {{ formatConversationTime(item.conversation.last_message_at) }}
                                </p>
                            </div>
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
									class="h-10 w-10 rounded-full object-cover"
								>
								<span
									class="absolute -right-0.5 -bottom-0.5 h-4 w-4 rounded-full border-2 border-base-100"
									:class="getUserOnlineState(item.user) ? 'bg-green-500' : 'bg-gray-500'"
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

			<span v-if="store.state.loadingUsers" class="text-base-content/70 p-2 text-xs justify-center flex items-center gap-2">
                <span class="loading loading-spinner loading-sm loading-primary"></span>
                <p>Fetching users</p>
            </span>

			<span v-else-if="!mergedList.length" class="text-base-content/70 p-2 text-xs justify-center flex items-center gap-2">
                <LucideIcons name="users" class="h-5 w-5" />
                <p>No chats or users found.</p>
            </span>
		</div>
	</aside>
</template>
