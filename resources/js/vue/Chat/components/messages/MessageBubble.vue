<script setup>
import { computed } from 'vue';
import MessageMedia from './MessageMedia.vue';

const props = defineProps({
	message: {
		type: Object,
		required: true,
	},
	mine: {
		type: Boolean,
		default: false,
	},
});

const seenByLabel = computed(() => {
	if (!props.mine || !Array.isArray(props.message.seen_by) || !props.message.seen_by.length) return '';

	if (props.message.seen_by.length === 1) {
		return 'Seen';
	}

	const names = props.message.seen_by.map((item) => item.dname || item.username || 'User');
	return `Seen by ${names.join(', ')}`;
});

const statusLabel = computed(() => {
	if (!props.mine) return '';

	switch (props.message.status) {
		case 'sending':
			return 'Sending';
		case 'sent':
			return 'Sent';
		case 'delivered':
			return 'Received';
		case 'seen':
			return 'Seen';
		default:
			return 'Sent';
	}
});

const messageStatusText = computed(() => {
	if (!props.mine) return '';

	const status = statusLabel.value;
	const seenBy = seenByLabel.value;

	if (status && seenBy) {
		return `${status}`;
	}

	return status;
});
</script>

<template>
	<div class="flex flex-col" :class="mine ? 'items-end' : 'items-start mt-3'">
		<!-- Sender name -->
		<p v-if="!mine" class="mb-1 text-xs font-semibold opacity-70 ml-11">
			{{ message.sender?.dname || message.sender?.username || 'User' }}
		</p>

		<!-- Avatar + Bubble -->
		<div class="flex items-end gap-2" :class="mine ? 'flex-row-reverse' : 'flex-row'">
			<img
				:src="message.sender?.profile_picture_url || '/images/avatar/default.jpg'"
				:alt="`${message.sender?.username || 'user'} avatar`"
				class="h-8 w-8 shrink-0 rounded-full object-cover"
			>

			<div class="max-w-[80%] rounded-lg px-3 py-2" :class="mine ? 'bg-primary text-primary-content' : 'bg-base-100 border-base-300 border'">
				<p v-if="message.body" class="whitespace-pre-wrap text-sm">{{ message.body }}</p>
				<MessageMedia :message="message" />

				<div class="mt-1 text-[11px] opacity-75">
					<span>{{ new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
				</div>
			</div>
		</div>

		<!-- Status and seen label -->
		<p v-if="messageStatusText" class="mt-1 text-[11px] opacity-80" :class="mine ? 'text-right' : 'text-left'">
			{{ messageStatusText }}
		</p>
	</div>
</template>
