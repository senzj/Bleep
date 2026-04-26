<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import MessageBubble from './MessageBubble.vue';
import { LucideMessageCircle, LucideMessageCircleMore } from 'lucide-vue-next';

const props = defineProps({
	messages: {
		type: Array,
		default: () => [],
	},
	currentUserId: {
		type: Number,
		default: 0,
	},
	conversationId: {
		type: Number,
		default: 0,
	},
	loading: {
		type: Boolean,
		default: false,
	},
	loaded: {
		type: Boolean,
		default: false,
	},
	hasMore: {
		type: Boolean,
		default: false,
	},
	loadingOlder: {
		type: Boolean,
		default: false,
	},
	participants: {
		type: Array,
		default: () => [],
	},
});

const emit = defineEmits(['load-older', 'edit-message', 'delete-message', 'reply-message']);

const onEditMessage = (payload) => {
	emit('edit-message', payload);
};

const onDeleteMessage = (messageId) => {
	emit('delete-message', messageId);
};

const onReplyMessage = (message) => {
	emit('reply-message', message);
};

const highlightedMessageId = ref(null);
let highlightTimeout = null;

const scrollToMessage = (messageId) => {
	const el = listRef.value;
	if (!el) return;
	const target = el.querySelector(`[data-message-id="${messageId}"]`);
	if (target) {
		target.scrollIntoView({ behavior: 'smooth', block: 'center' });
		highlightedMessageId.value = messageId;
		clearTimeout(highlightTimeout);
		highlightTimeout = setTimeout(() => { highlightedMessageId.value = null; }, 2000);
	}
};

const listRef = ref(null);
const contentRef = ref(null);
const initialized = ref(false);
const pendingPrependAdjust = ref(null);
const isAtBottom = ref(true);
let resizeObserver = null;

const formatDateHeader = (isoString) => {
	const date = new Date(isoString || Date.now());
	if (Number.isNaN(date.getTime())) return 'Unknown date';

	return date.toLocaleDateString([], {
		month: 'long',
		day: 'numeric',
		year: 'numeric',
	});
};

const dateKeyFromMessage = (message) => {
	const source = message?.created_at || message?.updated_at || null;
	const date = source ? new Date(source) : new Date();
	if (Number.isNaN(date.getTime())) return 'unknown-date';
	return `${date.getFullYear()}-${date.getMonth() + 1}-${date.getDate()}`;
};

// For each participant, find the latest message they have read.
// Uses two data sources for reliability:
//   1. participant.last_read_at — find the latest message where created_at <= last_read_at
//   2. message.seen_by arrays — find the highest message ID where the participant appears
// Whichever yields a later message wins (handles race conditions with stale data).
const seenAvatarsByMessageId = computed(() => {
	const result = new Map(); // msgId -> person[]

	const participants = props.participants || [];

	const messageList = props.messages || [];

	participants.forEach((participant) => {
		const uid = Number(participant.id);
		let latestReadMessage = null;
		let latestReadAt = participant.last_read_at || null;

		// Source 1: last_read_at timestamp on the participant
		if (participant.last_read_at) {
			const readTimestamp = new Date(participant.last_read_at).getTime();
			if (!Number.isNaN(readTimestamp)) {
				for (let i = messageList.length - 1; i >= 0; i--) {
					const msg = messageList[i];
					const msgTime = new Date(msg.created_at).getTime();
					if (!Number.isNaN(msgTime) && msgTime <= readTimestamp) {
						latestReadMessage = msg;
						break; // messages are sorted ascending, so first match from end is latest
					}
				}
			}
		}

		// Source 2: seen_by arrays on individual messages (fallback / supplement)
		for (let i = messageList.length - 1; i >= 0; i--) {
			const msg = messageList[i];
			if (!Array.isArray(msg.seen_by)) continue;
			const seenEntry = msg.seen_by.find((s) => Number(s.id) === uid);
			if (seenEntry) {
				// If this message is later than what last_read_at found, use it instead
				if (!latestReadMessage || Number(msg.id) > Number(latestReadMessage.id)) {
					latestReadMessage = msg;
					latestReadAt = seenEntry.read_at || seenEntry.last_read_at || latestReadAt;
				}
				break; // seen_by is populated on messages up to the read point; highest ID wins
			}
		}

		if (latestReadMessage) {
			// A sender should not appear in seen receipts for their own message.
			if (Number(latestReadMessage.sender_id) === uid) {
				return;
			}

			if (!result.has(latestReadMessage.id)) {
				result.set(latestReadMessage.id, []);
			}
			result.get(latestReadMessage.id).push({
				id: participant.id,
				profile_picture_url: participant.profile_picture_url,
				dname: participant.dname,
				username: participant.username,
				last_read_at: latestReadAt
			});
		}
	});

	return result;
});

const groupedMessages = computed(() => {
	const groups = [];
	const indexByKey = new Map();

	(props.messages || []).forEach((message) => {
		const key = dateKeyFromMessage(message);

		if (!indexByKey.has(key)) {
			indexByKey.set(key, groups.length);
			groups.push({
				key,
				label: formatDateHeader(message?.created_at || message?.updated_at),
				messages: [],
			});
		}

		groups[indexByKey.get(key)].messages.push(message);
	});

	return groups;
});

const scrollToBottom = () => {
	const el = listRef.value;
	if (!el) return;
	el.scrollTop = el.scrollHeight;
};

const requestOlderMessages = () => {
	if (!props.hasMore || props.loadingOlder) return;

	const el = listRef.value;
	if (el) {
		pendingPrependAdjust.value = {
			height: el.scrollHeight,
			top: el.scrollTop,
		};
	}

	emit('load-older');
};

const onScroll = () => {
	const el = listRef.value;
	if (!el) return;

	isAtBottom.value = el.scrollHeight - el.scrollTop - el.clientHeight < 60;

	if (el.scrollTop <= 60) {
		requestOlderMessages();
	}
};

watch(() => props.messages.length, async () => {
	await nextTick();

	const el = listRef.value;
	if (!el) return;

	if (!initialized.value) {
		scrollToBottom();
		initialized.value = true;
		pendingPrependAdjust.value = null;
		return;
	}

	if (pendingPrependAdjust.value) {
		const { height, top } = pendingPrependAdjust.value;
		el.scrollTop = Math.max(0, el.scrollHeight - height + top);
		pendingPrependAdjust.value = null;
		return;
	}

	scrollToBottom();
});

watch(() => props.conversationId, () => {
	initialized.value = false;
	pendingPrependAdjust.value = null;
});

onMounted(async () => {
	await nextTick();
	scrollToBottom();

	if (contentRef.value) {
		resizeObserver = new ResizeObserver(() => {
			if (isAtBottom.value) {
				scrollToBottom();
			}
		});
		resizeObserver.observe(contentRef.value);
	}
});

onUnmounted(() => {
	resizeObserver?.disconnect();
	resizeObserver = null;
});

const showInitialLoader = computed(() => !props.loaded || props.loading);
const showEmptyState = computed(() => props.loaded && !props.loading && !props.messages.length);
</script>

<template>
	<div ref="listRef" class="flex flex-col flex-1 min-h-0 overflow-y-auto px-4 py-4" @scroll="onScroll">

		<!-- Loaders sit outside contentRef so they can flex-fill the scroll container -->
		<div v-if="showInitialLoader" class="flex flex-1 items-center justify-center gap-2 text-sm text-base-content/70">
			<span class="loading loading-infinity loading-md loading-primary"></span>
			<span>Fetching messages</span>
		</div>

		<div v-else-if="showEmptyState" class="flex flex-1 flex-col items-center justify-center gap-2 text-sm text-base-content/70">
			<LucideMessageCircleMore class="h-10 w-10" />
			<span>Start a conversation by sending a message!</span>
		</div>

		<div v-else ref="contentRef">
			<p v-if="loadingOlder" class="text-base-content/60 text-center text-xs mb-2">Loading older messages…</p>

			<template v-for="group in groupedMessages" :key="group.key">
				<div class="sticky top-0 z-1 flex justify-center py-1">
					<span class="bg-base-300/80 rounded-full px-3 py-1 text-[11px] font-medium">
						{{ group.label }}
					</span>
				</div>

				<MessageBubble
					v-for="(message, index) in group.messages"
					:key="message.id"
					:data-message-id="message.id"
					:message="message"
					:mine="Number(message.sender_id) === Number(currentUserId)"
					:show-avatar="index === group.messages.length - 1 || Number(group.messages[index + 1]?.sender_id) !== Number(message.sender_id)"
					:seen-avatars="seenAvatarsByMessageId.get(message.id) || []"
					:highlighted="highlightedMessageId === message.id"
					@edit-message="onEditMessage"
					@delete-message="onDeleteMessage"
					@reply="onReplyMessage"
					@scroll-to-message="scrollToMessage"
				/>
			</template>
		</div>

	</div>
</template>
