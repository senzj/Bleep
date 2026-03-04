<script setup>
import { computed } from 'vue';

const props = defineProps({
	conversation: {
		type: Object,
		required: true,
	},
	active: {
		type: Boolean,
		default: false,
	},
});

const preview = computed(() => {
	const last = props.conversation.last_message;
	if (!last) return 'No messages yet';
	if (last.media_kind === 'voice') return '🎤 Voice message';
	if (last.media_kind === 'audio') return '🎵 Audio file';
	if (last.media_kind === 'media' && !last.body) return '📎 Media';
	return last.body || 'Media';
});
</script>

<template>
	<button
		class="w-full rounded-lg border p-3 text-left"
		:class="active ? 'border-primary bg-base-200' : 'border-base-300 bg-base-100'"
	>
		<p class="truncate text-sm font-semibold">{{ conversation.title }}</p>
		<p class="text-base-content/70 truncate text-xs">{{ preview }}</p>
	</button>
</template>
