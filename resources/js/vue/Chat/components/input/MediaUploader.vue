<script setup>
import LucideIcons from '../../../LucideIcons.vue';

defineProps({
	disabled: {
		type: Boolean,
		default: false,
	},
});

const emit = defineEmits(['file-selected']);

const pickFile = () => {
	const input = document.createElement('input');
	input.type = 'file';
	input.accept = 'image/*,video/*,audio/*,.pdf';

	input.onchange = () => {
		const file = input.files?.[0];
		if (!file) return;

		const mediaKind = file.type.startsWith('audio/') ? 'audio' : 'media';
		emit('file-selected', { file, mediaKind });
	};

	input.click();
};
</script>

<template>
	<button class="btn btn-circle btn-outline" type="button" :disabled="disabled" @click="pickFile">
		<LucideIcons name="paperclip" class="h-5 w-5" />
	</button>
</template>
