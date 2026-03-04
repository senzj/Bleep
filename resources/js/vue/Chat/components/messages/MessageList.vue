<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import MessageBubble from './MessageBubble.vue';

const props = defineProps({
	messages: {
		type: Array,
		default: () => [],
	},
	currentUserId: {
		type: Number,
		required: true,
	},
	conversationId: {
		type: Number,
		default: 0,
	},
	loading: {
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
});

const emit = defineEmits(['load-older']);

const listRef = ref(null);
const initialized = ref(false);
const pendingPrependAdjust = ref(null);

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
});
</script>

<template>
	<div ref="listRef" class="flex-1 min-h-0 space-y-2 overflow-y-auto px-4 py-4" @scroll="onScroll">
		<p v-if="loadingOlder" class="text-base-content/60 text-center text-xs">Loading older messages…</p>
		<p v-if="loading" class="text-base-content/70 text-sm">Loading messages...</p>
		<p v-else-if="!messages.length" class="text-base-content/70 text-sm">No messages yet.</p>

		<template v-for="group in groupedMessages" :key="group.key">
			<div class="sticky top-0 z-1 flex justify-center py-1">
				<span class="bg-base-300/80 rounded-full px-3 py-1 text-[11px] font-medium">
					{{ group.label }}
				</span>
			</div>

			<MessageBubble
				v-for="message in group.messages"
				:key="message.id"
				:message="message"
				:mine="Number(message.sender_id) === Number(currentUserId)"
			/>
		</template>
	</div>
</template>
