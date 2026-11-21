<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import SearchBar from '../components/sidebar/SearchBar.vue';
import { useMessageStore } from '../store/useMessageStore';
import { useChatPreferences } from '../composables/useChatPreferences';
import LucideIcons from '../../LucideIcons.vue';

const store = useMessageStore();
const { displayName } = useChatPreferences();
const homeUrl = document.getElementById('chat-app')?.dataset.homeUrl || '/';
const query = ref('');
let searchTimer = null;
let presenceTimer = null;

const isOnline = (person, conversation = null) => {
	if (!person) return false;

	const presenceMap = conversation
		? (store.state.onlineUsersByConversation[conversation.id] || {})
		: {};

	return Boolean(presenceMap[person.id]) || Boolean(person.is_online);
};

const toPerson = (participant, conversation = null) => ({
	id: Number(participant.id),
	username: participant.username,
	dname: participant.dname,
	profile_picture_url: participant.profile_picture_url,
	is_friend: Boolean(participant.is_friend),
	is_online: true,
	conversationId: conversation ? Number(conversation.id) : null,
});

/**
 * Everyone who is online right now and is either a mutual friend, someone the
 * viewer has chatted with, or a member of an existing direct conversation.
 * Conversations are merged with the friend directory so neither source alone
 * hides a familiar face.
 */
const onlinePeople = computed(() => {
	const people = new Map();

	(store.state.conversations || []).forEach((conversation) => {
		if (conversation.is_group) return;

		(conversation.participants || []).forEach((participant) => {
			if (Number(participant.id) === Number(store.state.currentUserId)) return;
			if (!isOnline(participant, conversation)) return;

			const id = Number(participant.id);
			const existing = people.get(id);

			// Prefer the conversation entry so tapping opens the thread.
			if (!existing || !existing.conversationId) {
				people.set(id, toPerson(participant, conversation));
			}
		});
	});

	(store.state.userDirectory || []).forEach((user) => {
		if (!isOnline(user)) return;

		const id = Number(user.id);
		if (!people.has(id)) {
			people.set(id, toPerson(user));
		}
	});

	return [...people.values()];
});

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

onMounted(() => {
	// Presence is a server snapshot; refresh it quietly while the viewer is on
	// the chat list so the "online now" row does not stay stale.
	presenceTimer = window.setInterval(() => {
		if (query.value.trim()) return;
		store.fetchUserDirectory().catch(() => {});
	}, 60000);
});

onBeforeUnmount(() => {
	clearTimeout(searchTimer);
	window.clearInterval(presenceTimer);
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

const mediaSummary = (last) => {
	const items = Array.isArray(last.media_items) && last.media_items.length
		? last.media_items
		: (last.media_path || last.media_url ? [{ media_kind: last.media_kind, media_type: last.media_type }] : []);

	if (!items.length) return null;

	const kinds = items.map((item) => {
		const type = item.media_type || '';
		const kind = item.media_kind || '';
		if (kind === 'voice' || type.startsWith('audio/webm')) return 'voice';
		if (type.startsWith('image/')) return 'image';
		if (type.startsWith('video/')) return 'video';
		if (kind === 'audio' || type.startsWith('audio/')) return 'audio';
		return 'file';
	});

	const count = kinds.length;
	const label = (kind) => {
		const plural = count > 1 ? 's' : '';
		if (kind === 'voice') return `Voice message${plural}`;
		if (kind === 'image') return `Photo${plural}`;
		if (kind === 'video') return `Video${plural}`;
		if (kind === 'audio') return `Audio file${plural}`;
		return `File${plural}`;
	};

	return count > 1 ? `${count} attachments` : label(kinds[0]);
};

// Mirrors the label a person would read in a notification, not the raw body.
const conversationTitle = (conversation) => {
	if (!conversation) return 'Conversation';
	if (conversation.is_group) return conversation.title || 'Group Chat';

	const other = getConversationOtherParticipant(conversation);
	return displayName(other, conversation.title) || 'Conversation';
};

const conversationPreview = (conversation) => {
	const last = conversation?.last_message;
	if (!last) return 'No messages yet';
	if (last.is_deleted) return 'Message deleted';

	const mine = Number(last.sender_id) === Number(store.state.currentUserId);
	const senderName = displayName(last.sender, last.sender?.username || 'Someone');
	const isGroup = Boolean(conversation?.is_group);

	let text;

	if (last.shared_bleep) {
		text = 'Sent a bleep post';
	} else if (last.media_kind === 'voice') {
		text = 'Voice message';
	} else if (last.media_kind === 'audio') {
		text = 'Audio file';
	} else if (last.body) {
		const username = store.state.currentUsername;
		const mentionsMe = !mine && username
			&& new RegExp(`@${username}\\b`, 'i').test(last.body);

		text = mentionsMe ? 'Mentioned you' : last.body;
	} else {
		text = mediaSummary(last) || 'Media';
	}

	if (mine) return text;
	return isGroup ? `${senderName}: ${text}` : text;
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

const openOnlinePerson = (person) => {
	if (person.conversationId) {
		handleSelectConversation(person.conversationId);

		return;
	}

	startDirectMessage(person.id);
};
</script>

<template>
	<aside class="border-base-300 bg-base-100 flex h-full min-h-0 flex-col border-r">
		<header class="border-base-200 shrink-0 space-y-3 border-b p-4">
			<div class="flex items-center justify-between gap-2">
				<div class="flex min-w-0 items-center gap-2">
					<!-- Phones have no app-level bottom nav on chat; this is the way home. -->
					<a
						:href="homeUrl"
						class="btn btn-ghost btn-sm btn-circle md:hidden"
						aria-label="Back to bleeps"
						title="Back to bleeps"
					>
						<i data-lucide="arrow-left" class="lucide lucide-sm"></i>
					</a>
					<h2 class="truncate text-lg font-semibold">Chats</h2>
				</div>

				<!-- Create Group Button -->
				<button class="btn btn-primary btn-sm gap-1.5 rounded-lg" @click="emit('create-group')">
					<i data-lucide="users-round" class="h-4 w-4"></i>
					<span class="hidden sm:inline">New group</span>
				</button>
			</div>

			<SearchBar v-model="query" />
		</header>

		<div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-3">
			<section v-if="onlinePeople.length && !query.trim()" class="mb-4" aria-labelledby="chat-online-heading">
				<div class="mb-2 flex items-center justify-between">
					<h3 id="chat-online-heading" class="text-base-content/50 text-[11px] font-semibold uppercase tracking-wider">
						Online now
					</h3>
					<span class="text-success inline-flex items-center gap-1 text-[11px] font-semibold">
						<span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
						{{ onlinePeople.length }}
					</span>
				</div>

				<div class="-mx-1 flex gap-3 overflow-x-auto px-1 pb-1">
					<button
						v-for="person in onlinePeople"
						:key="`online-${person.id}`"
						class="hover:bg-base-200 flex w-16 shrink-0 flex-col items-center gap-1.5 rounded-xl p-1 text-center transition-colors"
						:title="`Message ${displayName(person, person.username)}`"
						@click="openOnlinePerson(person)"
					>
						<span class="relative block h-12 w-12">
							<img
								:src="person.profile_picture_url || '/images/avatar/default.jpg'"
								:alt="`${person.username} avatar`"
								class="h-12 w-12 rounded-full object-cover ring-2 ring-green-500/30"
							>
							<span class="border-base-100 absolute -right-0.5 -bottom-0.5 h-3.5 w-3.5 rounded-full border-2 bg-green-500"></span>
						</span>
						<span class="text-base-content/80 w-full truncate text-[11px] font-medium">
							{{ displayName(person, person.username) }}
						</span>
					</button>
				</div>
			</section>

			<section>
				<div class="mb-2 flex items-center justify-between px-0.5">
					<h3 class="text-base-content/50 text-[11px] font-semibold uppercase tracking-wider">Messages</h3>
					<span v-if="store.state.conversations.length" class="text-base-content/40 text-[11px]">
						{{ store.state.conversations.length }}
					</span>
				</div>

				<div class="space-y-2">
			<template v-for="item in mergedList" :key="item.id">
				<button
					v-if="item.type === 'conversation'"
					class="group flex w-full cursor-pointer items-center gap-3 rounded-xl border p-3 text-left transition-colors"
					:class="Number(store.state.activeConversationId) === Number(item.conversation.id) ? 'border-primary bg-primary/5' : 'border-base-300 bg-base-100 hover:border-base-content/20 hover:bg-base-200/60'"
					@click="handleSelectConversation(item.conversation.id)"
				>
					<div class="flex min-w-0 flex-1 items-center gap-3">
						<!-- Avatar: group photo grid vs user photo -->
						<div class="relative shrink-0">
							<div
								v-if="item.conversation.is_group"
								class="bg-base-300 h-11 w-11 overflow-hidden rounded-full"
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
									class="h-11 w-11 rounded-full object-cover"
								>
								<span
									v-if="getConversationOnlineState(item.conversation) !== null"
									class="border-base-100 absolute -right-0.5 -bottom-0.5 h-3.5 w-3.5 rounded-full border-2"
									:class="getConversationOnlineState(item.conversation) ? 'bg-green-500' : 'bg-gray-400'"
								/>
							</template>
						</div>

						<div class="min-w-0 flex-1">
							<div class="flex items-baseline justify-between gap-2">
								<div class="flex min-w-0 items-baseline gap-1.5">
									<p class="truncate text-sm font-semibold">
										{{ conversationTitle(item.conversation) }}
									</p>
									<p
										v-if="!item.conversation.is_group && getConversationOtherParticipant(item.conversation)"
										class="text-base-content/40 hidden truncate text-[11px] sm:inline"
									>
										@{{ getConversationOtherParticipant(item.conversation).username }}
									</p>
								</div>

								<span class="text-base-content/50 shrink-0 text-[11px]">
									{{ formatConversationTime(item.conversation.last_message_at) }}
								</span>
							</div>

							<div class="mt-0.5 flex items-center justify-between gap-2">
								<p class="text-base-content/60 truncate text-xs">
									{{ conversationPreview(item.conversation) }}
								</p>

								<span
									v-if="item.conversation.unread_count > 0"
									class="badge badge-primary badge-sm shrink-0 font-semibold"
								>
									{{ item.conversation.unread_count > 99 ? '99+' : item.conversation.unread_count }}
								</span>
							</div>
						</div>
					</div>
				</button>

				<button
					v-else
					class="group border-base-300 hover:border-base-content/20 hover:bg-base-200/60 flex w-full items-center justify-between gap-3 rounded-xl border border-dashed px-3 py-2.5 text-left transition-colors"
					@click="startDirectMessage(item.user.id)"
				>
					<div class="flex min-w-0 items-center gap-3">
						<div class="relative shrink-0">
							<img
								:src="item.user.profile_picture_url || '/images/avatar/default.jpg'"
								:alt="`${item.user.username} avatar`"
								class="h-10 w-10 rounded-full object-cover"
							>
							<span
								class="border-base-100 absolute -right-0.5 -bottom-0.5 h-3.5 w-3.5 rounded-full border-2"
								:class="getUserOnlineState(item.user) ? 'bg-green-500' : 'bg-gray-400'"
							/>
						</div>
						<div class="min-w-0">
							<p class="truncate text-sm font-medium">{{ displayName(item.user, item.user.username) }}</p>
							<p class="text-base-content/60 truncate text-xs">@{{ item.user.username }}</p>
						</div>
					</div>
					<span class="badge badge-ghost badge-sm shrink-0">
						{{ item.user.relation_label || 'Start chat' }}
					</span>
				</button>
			</template>

			<div
				v-if="store.state.loadingUsers && !mergedList.length"
				class="text-base-content/60 flex items-center justify-center gap-2 py-10 text-xs"
			>
				<span class="loading loading-spinner loading-sm loading-primary"></span>
				<p>Fetching people…</p>
			</div>

			<div
				v-else-if="!mergedList.length"
				class="border-base-300 bg-base-200/40 mt-2 flex flex-col items-center gap-2 rounded-xl border border-dashed px-4 py-10 text-center"
			>
				<LucideIcons name="users" class="text-base-content/30 h-7 w-7" />
				<p class="text-sm font-medium">No chats yet</p>
				<p class="text-base-content/60 text-xs">
					Start a conversation from the people above or search for a friend.
				</p>
			</div>
				</div>
			</section>
		</div>
	</aside>
</template>
