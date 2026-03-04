<script setup>
import ConversationItem from './ConversationItem.vue';

defineProps({
	conversations: {
		type: Array,
		default: () => [],
	},
	activeConversationId: {
		type: Number,
		default: null,
	},
});

const emit = defineEmits(['select']);
</script>

<template>
	<div class="space-y-2">
		<ConversationItem
			v-for="conversation in conversations"
			:key="conversation.id"
			:conversation="conversation"
			:active="Number(activeConversationId) === Number(conversation.id)"
			@click="emit('select', conversation.id)"
		/>

		<p v-if="!conversations.length" class="text-base-content/70 p-2 text-sm">No conversations yet.</p>
	</div>
</template>
